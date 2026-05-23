<?php

declare(strict_types=1);

/*
 * This file is part of the Calendar Event Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/calendar-event-booking-bundle
 */

namespace Markocupic\CalendarEventBookingBundle\Controller\FrontendModule;

use Codefog\HasteBundle\UrlParser;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Markocupic\CalendarEventBookingBundle\Event\CancelBookingEvent;
use Markocupic\CalendarEventBookingBundle\Exception\EventBookingUnsubscribeException;
use Markocupic\CalendarEventBookingBundle\Exception\SeverityLevel;
use Markocupic\CalendarEventBookingBundle\Helper\NotificationManager;
use Markocupic\CalendarEventBookingBundle\Model\CalendarEventsMemberModel;
use Markocupic\CalendarEventBookingBundle\Util\FigureUtil;
use Markocupic\ContaoFlashMessage\FlashMessage\MessageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;

#[AsFrontendModule(EventBookingUnsubscribeController::TYPE, category: 'events')]
class EventBookingUnsubscribeController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_booking_unsubscribe';

    public const ACTION = 'unsubscribe';

    private const FORM_ID = 'tl_unsubscribe_from_event';

    private const QUERY_PARAM_ACTION = 'action';

    private const QUERY_PARAM_BOOKING_TOKEN = 'bookingToken';

    private const QUERY_PARAM_UNSUBSCRIBED = 'hasUnsubscribed';

    private const TRANS_DOMAIN = 'mc_calendar_event_booking';

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FigureUtil $figureUtil,
        private readonly LockFactory $lockFactory,
        #[Autowire(service: 'markocupic_calendar_event_booking.flash_message.unsubscribe')]
        private readonly MessageInterface $message,
        private readonly NotificationCenter $notificationCenter,
        private readonly NotificationManager $notificationManager,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly TranslatorInterface $translator,
        private readonly UrlParser $urlParser,
        private readonly LoggerInterface|null $contaoErrorLogger,
        private readonly LoggerInterface|null $contaoGeneralLogger,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return parent::__invoke($request, $model, $section, $classes);
        }

        if (null !== $page) {
            $page->noSearch = 1;
        }

        if (self::ACTION !== $request->query->get(self::QUERY_PARAM_ACTION)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $bookingToken = $request->query->get(self::QUERY_PARAM_BOOKING_TOKEN, '');

        if (empty($bookingToken)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $bookingToken = $request->query->get(self::QUERY_PARAM_BOOKING_TOKEN, '');
        $booking = CalendarEventsMemberModel::findOneByBookingToken($bookingToken);
        $calEvent = null;
        $isValid = false;

        try {
            $calEvent = $this->validateBooking($booking, $request, $template);
            $isValid = true;
            $template->set('hasForm', true);
            $template->set('formId', self::FORM_ID);
            $template->set('requestToken', $this->csrfTokenManager->getDefaultTokenValue());
        } catch (EventBookingUnsubscribeException $e) {
            $this->message->add($e->getTranslatableText(), $e->getSeverityLevel());
        }

        if ($isValid && self::FORM_ID === $request->request->get('FORM_SUBMIT')) {
            $response = $this->handleFormSubmission($booking, $calEvent, $request, $bookingToken);

            if ($response instanceof Response) {
                return $response;
            }
        }

        $this->applyEventImage($model, $calEvent, $template);

        $template->set('messagesUnwrapped', $this->message->renderUnwrapped(peek: true));
        $template->set('messages', $this->message->hasMessages() ? $this->message->getAll() : null);

        return $template->getResponse();
    }

    /**
     * Validates the booking and populates template data.
     * Throws EventBookingUnsubscribeException on any validation failure.
     */
    private function validateBooking(CalendarEventsMemberModel|null $booking, Request $request, FragmentTemplate $template): CalendarEventsModel
    {
        if (null === $booking) {
            $this->addCssClassToTemplate('error booking-not-found', $template);

            throw new EventBookingUnsubscribeException('Booking not found.', $this->translator->trans('mod_unsubscribe.error.invalid_uuid', [], self::TRANS_DOMAIN), SeverityLevel::ERROR);
        }

        $template->set('booking', $booking);

        $calEvent = $booking->getRelated('pid');

        if (!$calEvent instanceof CalendarEventsModel) {
            $this->addCssClassToTemplate('error event-not-found', $template);

            throw new EventBookingUnsubscribeException('Event not found.', $this->translator->trans('mod_unsubscribe.error.event_not_found', [], self::TRANS_DOMAIN), SeverityLevel::ERROR);
        }

        $template->set('event', $calEvent);
        $template->set('calendar', $calEvent->getRelated('pid'));

        if ($booking->canceled) {
            $template->set('hasUnsubscribed', true);
            $this->addCssClassToTemplate('info booking-already-canceled', $template);

            $transKey = 'true' === $request->query->get(self::QUERY_PARAM_UNSUBSCRIBED)
                ? 'mod_unsubscribe.info.unsubscribe_success'
                : 'mod_unsubscribe.info.already_unsubscribed';

            throw new EventBookingUnsubscribeException('You have unsubscribed.', $this->translator->trans($transKey, ['%title%' => $calEvent->title], self::TRANS_DOMAIN), SeverityLevel::INFO);
        }

        if (!$calEvent->enableDeregistration) {
            $this->addCssClassToTemplate('error unsubscription-not-allowed', $template);

            throw new EventBookingUnsubscribeException('Unsubscription not allowed.', $this->translator->trans('mod_unsubscribe.error.unsubscription_not_allowed', ['%title%' => $calEvent->title], self::TRANS_DOMAIN), SeverityLevel::ERROR);
        }

        if ($this->isUnsubscriptionLimitExpired($calEvent)) {
            $this->addCssClassToTemplate('error unsubscription-limit-expired', $template);

            throw new EventBookingUnsubscribeException('Unsubscription limit has expired.', $this->translator->trans('mod_unsubscribe.error.unsubscription_limit_expired', ['%title%' => $calEvent->title], self::TRANS_DOMAIN), SeverityLevel::ERROR);
        }

        return $calEvent;
    }

    private function handleFormSubmission(CalendarEventsMemberModel $booking, CalendarEventsModel $calEvent, Request $request, string $bookingToken): Response|null
    {
        $this->connection->beginTransaction();
        $lock = $this->lockFactory->createLock(base64_encode(self::class.$bookingToken));
        $lock->acquire(true);

        try {
            $booking->canceled = true;
            $booking->temporaryReserved = false;
            $booking->save();
            $this->connection->commit();

            $request->attributes->set('_calendar_event_booking_token', $booking->bookingToken);

            $this->contaoGeneralLogger?->info(\sprintf('Booking for event "%s" ID %d has been unsubscribed by link.', $calEvent->title, $booking->id));

            // Send notifications
            $this->notify($booking, $calEvent);

            $event = new CancelBookingEvent($booking, self::class, $request);
            $this->eventDispatcher->dispatch($event);

            if ($event->getResponse() instanceof Response) {
                return $event->getResponse();
            }

            return new RedirectResponse($this->urlParser->addQueryString(self::QUERY_PARAM_UNSUBSCRIBED.'=true'));
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $this->message->addError($this->translator->trans('mod_unsubscribe.error.unexpected_error', [], self::TRANS_DOMAIN));
            $this->contaoErrorLogger?->error($e->getMessage());

            return null;
        } finally {
            $lock->release();
        }
    }

    private function applyEventImage(ModuleModel $model, CalendarEventsModel|null $calEvent, FragmentTemplate $template): void
    {
        if (!$model->ceb_addImage || null === $calEvent || !$calEvent->addImage) {
            return;
        }

        $figure = $this->figureUtil->buildFigure($calEvent->row());

        if (null !== $figure) {
            $template->set('addImage', true);
            $template->set('figure', $figure);
        }
    }

    /**
     * @throws \Exception
     */
    private function notify(CalendarEventsMemberModel $booking, CalendarEventsModel $calEvent): void
    {
        $calendar = $calEvent->getRelated('pid');

        if (!$calendar?->unsubscribeNotification) {
            return;
        }

        $tokens = $this->notificationManager->getNotificationTokens($booking);
        $this->notificationCenter->sendNotification($calendar->unsubscribeNotification, $tokens);
    }

    private function isUnsubscriptionLimitExpired(CalendarEventsModel $calEvent): bool
    {
        if (!empty($calEvent->unsubscribeLimitTstamp)) {
            return time() > $calEvent->unsubscribeLimitTstamp;
        }

        $limitDays = $calEvent->unsubscribeLimit > 0 ? $calEvent->unsubscribeLimit : 0;
        $limitTimestamp = $limitDays * 3600 * 24;

        if ($calEvent->addTime && $calEvent->startTime > $calEvent->startDate) {
            return time() + $limitTimestamp > $calEvent->startTime;
        }

        return strtotime('today 00:00') + $limitTimestamp > $calEvent->startDate;
    }

    private function addCssClassToTemplate(string $cssClass, FragmentTemplate $template): void
    {
        $classes = (string) $template->get('element_css_classes').' '.$cssClass;
        $template->set('element_css_classes', implode(' ', array_filter(explode(' ', $classes))));
    }
}
