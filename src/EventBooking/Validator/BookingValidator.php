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

namespace Markocupic\CalendarEventBookingBundle\EventBooking\Validator;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Doctrine\DBAL\Exception;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Markocupic\CalendarEventBookingBundle\Exception\CalendarNotFoundException;
use Markocupic\CalendarEventBookingBundle\Model\CebbCartModel;

class BookingValidator
{
    public const FLASH_KEY = '_event_registration';

    private Adapter $stringUtilAdapter;

    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
    }

    /**
     * @throws Exception
     */
    public function validateBookingMax(EventConfig $eventConfig, int $numSeats = 1): bool
    {
        if (!$eventConfig->isBookable()) {
            return false;
        }

        $calendar = $eventConfig->getModel()->getRelated('pid');

        if (null === $calendar) {
            throw new CalendarNotFoundException('Can not find a matching calendar for event with ID '.$eventConfig->get('id').'.');
        }

        $seatsAvailable = $eventConfig->getBookingMax();

        // Value is not set, unlimited number of subscriptions
        if (!$seatsAvailable) {
            return true;
        }

        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $bookingStates = $stringUtilAdapter->deserialize($calendar->calculateTotalFrom, true);
        $total = $eventConfig->countByEventAndBookingState($bookingStates, false);

        return !($total + $numSeats > $seatsAvailable);
    }

    /**
     * @throws Exception
     */
    public function validateBookingMaxWaitingList(EventConfig $eventConfig, int $numSeats = 1): bool
    {
        if (!$eventConfig->isBookable()) {
            return false;
        }

        if (!$eventConfig->hasWaitingList()) {
            return false;
        }

        // Value is not set, unlimited number of subscriptions
        $seatsAvailable = $eventConfig->getWaitingListLimit();

        if (!$seatsAvailable) {
            return true;
        }

        $total = $eventConfig->getWaitingListCount(false);

        return !($total + $numSeats > $seatsAvailable);
    }

    public function validateBookingStartDate(EventConfig $eventConfig): bool
    {
        if (!$eventConfig->isBookable() || $eventConfig->getModel()->bookingStartDate > time()) {
            return false;
        }

        return true;
    }

    public function validateBookingEndDate(EventConfig $eventConfig): bool
    {
        if (!$eventConfig->isBookable() || !is_numeric($eventConfig->getModel()->bookingEndDate) || $eventConfig->getModel()->bookingEndDate < time()) {
            return false;
        }

        return true;
    }

    public function validateMaxCartItems(EventConfig $eventConfig, CebbCartModel|null $cart = null): bool
    {
        $intAllowed = $eventConfig->get('maxItemsPerCart');

        if (null === $cart) {
            return true;
        }

        // 0 means infinite items allowed
        if (0 === $intAllowed) {
            return true;
        }

        $arrRegistrations = $this->stringUtilAdapter->deserialize($cart->registrations, true);
        $intAvailable = \count($arrRegistrations);

        if ($intAvailable < $intAllowed) {
            return true;
        }

        return false;
    }

    /**
     * Validate if:
     * - Cart has reached max allowed items
     *   and
     * - Event is bookable
     *   and
     * - Event is not fully booked
     *   or
     * - Event is fully booked, but subscribing to the waiting list is still possible.
     *
     * @throws Exception
     */
    public function validateCanRegister(EventConfig $eventConfig, CebbCartModel|null $cart = null, int $numSeats = 1): bool
    {
        if (!$this->validateMaxCartItems($eventConfig, $cart)) {
            return false;
        }

        if (!$eventConfig->isBookable()) {
            return false;
        }

        if (!$this->validateBookingStartDate($eventConfig)) {
            return false;
        }

        if (!$this->validateBookingEndDate($eventConfig)) {
            return false;
        }

        if ($this->validateBookingMax($eventConfig, $numSeats)) {
            return true;
        }

        if ($this->validateBookingMaxWaitingList($eventConfig, $numSeats)) {
            return true;
        }

        return false;
    }
}
