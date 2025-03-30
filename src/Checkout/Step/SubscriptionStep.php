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

namespace Markocupic\CalendarEventBookingBundle\Checkout\Step;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\FormModel;
use Contao\Message;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\CalendarEventBookingBundle\Event\SetBookingAvailabilityEvent;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Markocupic\CalendarEventBookingBundle\EventBooking\EventRegistration\EventRegistration;
use Markocupic\CalendarEventBookingBundle\EventBooking\Validator\BookingValidator;
use Markocupic\CalendarEventBookingBundle\Util\CartUtil;
use Markocupic\CalendarEventBookingBundle\Util\CheckoutUtil;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriptionStep implements CheckoutStepInterface, ValidationCheckoutStepInterface, RedirectCheckoutStepInterface
{
    public const CASE_BOOKING_NOT_YET_POSSIBLE = 'bookingNotYetPossible';

    public const CASE_BOOKING_NO_LONGER_POSSIBLE = 'bookingNoLongerPossible';

    public const CASE_BOOKING_POSSIBLE = 'bookingPossible';

    public const CASE_EVENT_FULLY_BOOKED = 'eventFullyBooked';

    public const CASE_EVENT_NOT_BOOKABLE = 'eventNotBookable';

    public const CASE_WAITING_LIST_POSSIBLE = 'waitingListPossible';

    private const STEP_IDENTIFIER = 'subscription';

    private string $templatePath = '';

    public function __construct(
        private readonly BookingValidator $bookingValidator,
        private readonly CartUtil $cartUtil,
        private readonly CheckoutUtil $checkoutUtil,
        private readonly Connection $connection,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EventRegistration $eventRegistration,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function initialize(EventConfig $eventConfig, Request $request): void
    {
        // Empty
    }

    public function getIdentifier(): string
    {
        return self::STEP_IDENTIFIER;
    }

    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    public function setTemplatePath(string $templatePath = ''): void
    {
        $this->templatePath = $templatePath;
    }

    public function doAutoForward(EventConfig $eventConfig, Request $request): bool
    {
        return false;
    }

    public function validate(EventConfig $eventConfig, Request $request): bool
    {
        if (!$eventConfig->isBookable()) {
            return false;
        }

        $cart = $this->cartUtil->hasCart($request) ? $this->cartUtil->getCart($request) : null;

        if (null === $cart) {
            return false;
        }

        if ($cart->checkoutCompleted) {
            return false;
        }

        if (empty($this->cartUtil->getRegistrations($request))) {
            return false;
        }

        return true;
    }

    public function commitStep(EventConfig $eventConfig, Request $request): bool
    {
        if ('cebb_remove_participant' === $request->request->get('FORM_SUBMIT')) {
            if ($request->request->has('cebb::delete_participant_btn')) {
                $uuid = $request->request->get('cebb::delete_participant_btn');
                $this->deleteRegistration($uuid, $request);

                return true;
            }
        }

        // !!! Return false here, and do nothing else, because Contao/Form is
        // redirecting already

        return false;
    }

    public function getResponse(EventConfig $eventConfig, Request $request): RedirectResponse
    {
        return new RedirectResponse($request->getUri());
    }

    public function prepareStep(EventConfig $eventConfig, Request $request): array
    {
        $template = [];
        $this->framework->getAdapter(System::class)->loadLanguageFile($this->eventRegistration->getTable());
        $moduleModel = $this->checkoutUtil->getModuleModel($request);
        $form = $this->framework->getAdapter(FormModel::class)->findById($moduleModel->form);
        $cart = $this->cartUtil->hasCart($request) ? $this->cartUtil->getCart($request) : null;

        if (null === $form) {
            throw new \RuntimeException('No event booking form assigned to the frontend module. Please check if a booking form has been selected in frontend module with ID: '.$moduleModel->id);
        }

        $intendedSeats = 1;
        $bookingAvailability = $eventConfig->getEventStatus($intendedSeats);
        $bookingAvailabilityExplain = $this->getBookingAvailabilityExplain($eventConfig, $bookingAvailability);

        $event = new SetBookingAvailabilityEvent($request, $eventConfig, $bookingAvailability, $bookingAvailabilityExplain);
        $this->eventDispatcher->dispatch($event);

        if ($event->isStopped()) {
            $bookingAvailability = $event->getBookingAvailability();
            $bookingAvailabilityExplain = $event->getBookingAvailabilityExplain();
        }

        $template['bookingAvailability'] = $bookingAvailability;
        $template['bookingAvailabilityExplain'] = $bookingAvailabilityExplain;

        /*
         * Display the form only, if:
         * a. regular subscription is possible or
         * b. subscription to the waiting list is possible
         */
        if ($this->bookingValidator->validateCanRegister($eventConfig, $cart, $intendedSeats)) {
            $this->eventRegistration->setModuleData($moduleModel->row());
            $template['form'] = $this->framework->getAdapter(Controller::class)->getForm($moduleModel->form);
        }

        if ($this->cartUtil->countRegistrations($request) >= $eventConfig->get('maxItemsPerCart')) {
            $this->framework->getAdapter(Message::class)->addInfo('Es können keine weitere Registrierungen gemacht werden.');
        }

        // Code below here will not be processed if the registration form has been
        // submitted and a redirecting has been set on the form.
        $template['model'] = $moduleModel;
        $template['registrations'] = $this->cartUtil->getRegistrations($request);
        $template['csrf_token'] = $this->csrfTokenManager->getDefaultTokenValue();

        return $template;
    }

    protected function deleteRegistration(string $uuid, Request $request): void
    {
        // Delete from tl_cebb_registration
        $this->connection->delete('tl_cebb_registration', ['uuid' => $uuid], ['uuid' => Types::STRING]);

        $arrRegistrations = $this->cartUtil->getRegistrations($request);

        // Delete from tl_cebb_cart
        foreach ($arrRegistrations as $index => $registration) {
            if ($uuid === $registration['uuid']) {
                unset($arrRegistrations[$index]);
            }
        }

        // Update registrations in the allocated cart record
        $cart = $this->cartUtil->getCart($request);
        $cart->registrations = serialize($arrRegistrations);
        $cart->tstamp = time();
        $cart->save();
    }

    protected function getBookingAvailabilityExplain(EventConfig $eventConfig, string $bookingAvailability): string
    {
        $text = '';

        // Display booking availability
        switch ($bookingAvailability) {
            case self::CASE_BOOKING_NOT_YET_POSSIBLE:
                $text = $this->translator->trans(
                    'MSC.'.$bookingAvailability,
                    [$this->framework->getAdapter(Date::class)->parse($this->framework->getAdapter(Config::class)->get('dateFormat'), $eventConfig->get('bookingStartDate'))],
                    'contao_default',
                );
                break;
            case self::CASE_BOOKING_NO_LONGER_POSSIBLE:
            case self::CASE_EVENT_FULLY_BOOKED:
            case self::CASE_WAITING_LIST_POSSIBLE:
            case self::CASE_BOOKING_POSSIBLE:
                $text = $this->translator->trans(
                    'MSC.'.$bookingAvailability,
                    [],
                    'contao_default',
                );
                break;
        }

        return $text;
    }
}
