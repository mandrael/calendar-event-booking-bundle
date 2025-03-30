<?php

declare(strict_types=1);

/*
 * This file is part of Calendar Event Booking Bundle.
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
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Markocupic\CalendarEventBookingBundle\EventBooking\Booking\BookingState;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventFactory;
use Markocupic\CalendarEventBookingBundle\EventBooking\EventRegistration\EventRegistration;
use Markocupic\CalendarEventBookingBundle\EventBooking\Notification\Notification;
use Markocupic\CalendarEventBookingBundle\EventBooking\Template\AddTemplateData;
use Markocupic\CalendarEventBookingBundle\EventListener\ContaoHooks\AbstractHook;
use Markocupic\CalendarEventBookingBundle\Model\CebbRegistrationModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFrontendModule(EventUnsubscribeController::TYPE, category: 'events', template: 'mod_event_unsubscribe')]
class EventUnsubscribeController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_unsubscribe';

    protected CalendarEventsModel|null $objEvent = null;

    protected bool $blnUnsubscribed = false;

    protected array $errorMsg = [];

    public function __construct(
        private EventRegistration $eventRegistration,
        private readonly AddTemplateData $addTemplateData,
        private readonly EventFactory $eventFactory,
        private readonly Notification $notification,
        private readonly UriSigner $uriSigner,
        private readonly UrlParser $urlParser,
        public readonly Connection $connection,
        public readonly ContaoCsrfTokenManager $csrfTokenManager,
        public readonly ContaoFramework $framework,
        public readonly ScopeMatcher $scopeMatcher,
        public readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        // Is frontend
        if ($page instanceof PageModel && $this->scopeMatcher->isFrontendRequest($request)) {
            $page->noSearch = 1;

            if ('true' !== $request->query->get('unsubscribed')) {
                $translator = $this->translator;

                $regUuid = $request->query->get('regUuid', false);

                if (!$this->hasError()) {
                    if (empty($regUuid)) {
                        $this->addError($translator->trans('ERR.invalid_registration_uuid', [], 'contao_default'));
                    }
                }

                if (!$this->hasError()) {
                    $registration = $this->framework->getAdapter(CebbRegistrationModel::class)->findOneByUuid($regUuid);
                    if (null === $registration) {
                        $this->addError($translator->trans('ERR.invalid_registration_uuid', [], 'contao_default'));
                    }
                }

                // First check if deletion is possible
                if (!empty($registration) && !$this->hasError()) {
                    if (null === ($this->objEvent = $registration->getRelated('pid'))) {
                        $this->addError($translator->trans('ERR.event_not_found', [], 'contao_default'));
                    } else {
                        $eventConfig = $this->eventFactory->create($this->objEvent);
                    }

                    if (!$this->hasError()) {
                        if (BookingState::STATE_UNSUBSCRIBED === $registration->bookingState) {
                            $this->addError($translator->trans('ERR.already_unsubscribed.', [$this->objEvent->title], 'contao_default'));
                        }
                    }

                    if (!$this->hasError()) {
                        if ((isset($eventConfig) && !$eventConfig->get('enableUnsubscription')) || (!empty($registration->bookingState) && BookingState::STATE_CONFIRMED !== $registration->bookingState)) {
                            $this->addError($translator->trans('ERR.event_unsubscription_not_allowed', [$this->objEvent->title], 'contao_default'));
                        }
                    }

                    if (!$this->hasError()) {
                        $blnLimitExpired = false;

                        // User has set a specific unsubscription limit timestamp, this has precedence
                        if (!empty($this->objEvent->unsubscribeLimitTstamp)) {
                            if (time() > $this->objEvent->unsubscribeLimitTstamp) {
                                $blnLimitExpired = true;
                            }
                        } else {
                            // We only have an unsubscription limit expressed in days before event start date
                            $limit = !$this->objEvent->unsubscribeLimit > 0 ? 0 : $this->objEvent->unsubscribeLimit;

                            if (time() + $limit * 3600 * 24 > $this->objEvent->startDate) {
                                $blnLimitExpired = true;
                            }
                        }

                        if ($blnLimitExpired) {
                            $this->addError($translator->trans('ERR.unsubscription_limit_expired', [$this->objEvent->title], 'contao_default'));
                        }
                    }
                }

                // Unsubscription is possible
                if (!$this->hasError()) {
                    // Unsubscribe, notify and redirect
                    if ('tl_unsubscribe_from_event' === $request->request->get('FORM_SUBMIT')) {
                        $regUuid = $request->query->get('regUuid', false);
                        $registration = $this->framework->getAdapter(CebbRegistrationModel::class)->findOneByUuid($regUuid);
                        $eventConfig = $this->eventFactory->create($this->objEvent);

                        // Unsubscribe member
                        $this->eventRegistration->create($registration);
                        $this->eventRegistration->unsubscribe();

                        // Trigger the unsubscribe from event hook
                        if (isset($GLOBALS['TL_HOOKS'][AbstractHook::HOOK_UNSUBSCRIBE_FROM_EVENT]) && \is_array($GLOBALS['TL_HOOKS'][AbstractHook::HOOK_UNSUBSCRIBE_FROM_EVENT])) {
                            foreach ($GLOBALS['TL_HOOKS'][AbstractHook::HOOK_UNSUBSCRIBE_FROM_EVENT] as $callback) {
                                $this->framework->getAdapter(System::class)->importStatic($callback[0])->{$callback[1]}($eventConfig, $this->eventRegistration);
                            }
                        }

                        // Send notification
                        $this->sendNotifications($registration, $this->objEvent, $model);

                        $href = $this->urlParser->addQueryString(
                            sprintf(
                                'unsubscribed=true&eid=%s&regUuid=%s',
                                $this->objEvent->id,
                                $regUuid,
                            ),
                            $page->getAbsoluteUrl(),
                        );

                        $href = $this->uriSigner->sign($href);

                        $this->framework->getAdapter(Controller::class)->redirect($href);
                    }
                }
            }

            if ('true' === $request->query->get('unsubscribed') && $this->uriSigner->checkRequest($request)) {
                $this->blnUnsubscribed = true;
            }
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function hasError(): bool
    {
        return !empty($this->errorMsg);
    }

    protected function addError(string $strMsg): void
    {
        $this->errorMsg[] = $strMsg;
    }

    /**
     * @throws \Exception
     */
    protected function sendNotifications(CebbRegistrationModel $registration, CalendarEventsModel $objEvent, ModuleModel $model): void
    {
        $eventConfig = $this->eventFactory->create($objEvent);

        if (!$eventConfig->get('enableUnsubscribeNotification')) {
            return;
        }

        // Multiple notifications possible
        $arrNotificationIds = StringUtil::deserialize($eventConfig->get('eventUnsubscribeNotification'), true);
        $arrNotificationIds = array_map('intval', $arrNotificationIds);

        if (!empty($arrNotificationIds)) {
            // Get notification tokens
            $eventConfig = $this->eventFactory->create($objEvent);

            $this->notification->setTokens($eventConfig, $registration, (int) $eventConfig->get('eventUnsubscribeNotificationSender'));
            $this->notification->notify($arrNotificationIds);
        }
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        if ($this->blnUnsubscribed) {
            $template->set('blnUnsubscribed', true);

            if (null !== ($objEvent = $this->framework->getAdapter(CalendarEventsModel::class)->findById($request->query->get('eid')))) {
                $eventConfig = $this->eventFactory->create($objEvent);
                $template->set('event', $objEvent);
                $template->set('eventConfig', $eventConfig);

                // Augment template with more data
                $template->setData(array_merge($template->getData(), $this->addTemplateData->getTemplateData($eventConfig)));
            }
        } else {
            $template->set('blnUnsubscribed', false);

            if (!$this->hasError()) {
                $regUuid = $request->query->get('regUuid', false);
                $eventConfig = $this->eventFactory->create($this->objEvent);

                $template->set('eventConfig', $eventConfig);
                $template->set('formId', 'tl_unsubscribe_from_event');
                $template->set('event', $this->objEvent);
                $template->set('member', CebbRegistrationModel::findOneByUuid($regUuid));
                $template->set('request_token', $this->csrfTokenManager->getDefaultTokenValue());

                // Augment template with more data
                $template->setData(array_merge($template->getData(), $this->addTemplateData->getTemplateData($eventConfig)));
            }
        }

        $template->set('hasError', $this->hasError());
        $template->set('errorMsg', $this->errorMsg);

        return $template->getResponse();
    }
}
