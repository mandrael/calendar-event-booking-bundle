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

namespace Markocupic\CalendarEventBookingBundle\EventBooking\Config;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Input;
use Contao\Model\Collection;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\CalendarEventBookingBundle\Checkout\Step\SubscriptionStep;
use Markocupic\CalendarEventBookingBundle\EventBooking\Booking\BookingState;
use Markocupic\CalendarEventBookingBundle\EventBooking\Validator\BookingValidator;
use Markocupic\CalendarEventBookingBundle\Exception\CalendarNotFoundException;
use Markocupic\CalendarEventBookingBundle\Model\CebbRegistrationModel;

class EventConfig
{
    private Adapter $configAdapter;

    private Adapter $dateAdapter;

    public function __construct(
        private readonly BookingValidator $bookingValidator,
        private readonly CalendarEventsModel $event,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
    ) {
        $this->configAdapter = $this->framework->getAdapter(Config::class);
        $this->dateAdapter = $this->framework->getAdapter(Date::class);
    }

    public static function getEventFromRequest(): CalendarEventsModel|null
    {
        if (Input::get('events')) {
            $eventIdentifier = Input::get('events');
        } elseif (Input::get('event')) {
            $eventIdentifier = Input::get('events');
        } elseif (Input::get('auto_item')) {
            $eventIdentifier = Input::get('auto_item');
            Input::setGet('events', $eventIdentifier);
            Input::setGet('event', $eventIdentifier);
        }

        // Return null if the event can not be determined from request
        if (!empty($eventIdentifier)) {
            if (null !== ($objEvent = CalendarEventsModel::findByIdOrAlias($eventIdentifier))) {
                return $objEvent;
            }
        }

        return null;
    }

    /**
     * @return mixed|null
     */
    public function get(string $propertyName): mixed
    {
        return $this->getModel()->{$propertyName};
    }

    public function getModel(): CalendarEventsModel
    {
        return $this->event;
    }

    public function hasWaitingList(): bool
    {
        return (bool) $this->get('activateWaitingList');
    }

    /**
     * @throws Exception
     */
    public function isWaitingListFull(bool $ignoreRegWithUncompletedCheckout = true): bool
    {
        if ($this->get('activateWaitingList')) {
            if (empty($this->get('waitingListLimit'))) {
                return true;
            }

            if ($this->getWaitingListCount($ignoreRegWithUncompletedCheckout) < (int) $this->get('waitingListLimit')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function getWaitingListCount(bool $ignoreRegWithUncompletedCheckout = true): int
    {
        if (!$this->get('activateWaitingList') || empty($this->get('waitingListLimit'))) {
            return 0;
        }

        return $this->countByEventAndBookingState(BookingState::STATE_WAITING_LIST, $ignoreRegWithUncompletedCheckout);
    }

    /**
     * Is fully booked means:
     * - Event has no free seats
     *   and
     * - The waiting list is ignored.
     *
     * @throws Exception
     */
    public function isFullyBooked(bool $ignoreRegWithUncompletedCheckout = true): bool
    {
        $calendar = $this->getCalendar();

        if (null === $calendar) {
            throw new CalendarNotFoundException('Can not find a matching calendar for event with ID '.$this->getModel()->id.'.');
        }

        $bookingStates = StringUtil::deserialize($calendar->calculateTotalFrom, true);

        $regCount = $this->countByEventAndBookingState($bookingStates, $ignoreRegWithUncompletedCheckout);

        $bookingMax = $this->getBookingMax();

        if ($bookingMax > 0 && $regCount >= $bookingMax) {
            return true;
        }

        return false;
    }

    public function getCalendar(): CalendarModel|null
    {
        return CalendarModel::findById($this->getModel()->pid);
    }

    public function getBookingMax(): int
    {
        return (int) $this->get('maxMembers');
    }

    /**
     * @throws Exception
     */
    public function getNotConfirmedCount(bool $ignoreRegWithUncompletedCheckout = true): int
    {
        return $this->countByEventAndBookingState(BookingState::STATE_NOT_CONFIRMED, $ignoreRegWithUncompletedCheckout);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function getNumberOfFreeSeats(bool $ignoreRegWithUncompletedCheckout = true): int
    {
        $total = $this->getRegistrationTotalCount($ignoreRegWithUncompletedCheckout);
        $seatsAvailable = $this->getBookingMax() - $total;

        return max($seatsAvailable, 0);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function getNumberOfFreeSeatsWaitingList(bool $ignoreRegWithUncompletedCheckout = true): int
    {
        $total = $this->getWaitingListCount($ignoreRegWithUncompletedCheckout);
        $seatsAvailable = $this->getWaitingListLimit() - $total;

        return max($seatsAvailable, 0);
    }

    public function getRegistrationTotalCount(bool $ignoreRegWithUncompletedCheckout = true): int
    {
        $calendar = $this->getCalendar();

        if (null === $calendar) {
            throw new CalendarNotFoundException('Can not find a matching calendar for event with ID '.$this->getModel()->id.'.');
        }

        $bookingStates = StringUtil::deserialize($calendar->calculateTotalFrom, true);

        return $this->countByEventAndBookingState($bookingStates, $ignoreRegWithUncompletedCheckout);
    }

    public function isBookable(): bool
    {
        return (bool) $this->get('enableBookingForm');
    }

    /**
     * @throws Exception
     */
    public function getConfirmedBookingsCount(bool $ignoreRegWithUncompletedCheckout = true): int
    {
        return $this->countByEventAndBookingState(BookingState::STATE_CONFIRMED, $ignoreRegWithUncompletedCheckout);
    }

    public function getWaitingListLimit(): int
    {
        if (!$this->get('activateWaitingList')) {
            return 0;
        }

        return (int) $this->get('waitingListLimit');
    }

    public function isNotificationActivated(): bool
    {
        return (bool) $this->get('enableBookingNotification');
    }

    public function getBookingStartDate(string $format = 'timestamp'): string
    {
        $tstamp = empty($this->event->bookingStartDate) ? 0 : (int) $this->event->bookingStartDate;

        if ('timestamp' === $format) {
            $varValue = (string) $tstamp;
        } elseif ('date' === $format) {
            $varValue = $this->dateAdapter->parse($this->configAdapter->get('dateFormat'), $tstamp);
        } elseif ('datim' === $format) {
            $varValue = $this->dateAdapter->parse($this->configAdapter->get('datimFormat'), $tstamp);
        } else {
            $varValue = (string) $tstamp;
        }

        return $varValue;
    }

    public function getBookingEndDate(string $format = 'timestamp'): string
    {
        $tstamp = empty($this->event->bookingEndDate) ? 0 : (int) $this->event->bookingEndDate;

        if ('timestamp' === $format) {
            $varValue = (string) $tstamp;
        } elseif ('date' === $format) {
            $varValue = $this->dateAdapter->parse($this->configAdapter->get('dateFormat'), $tstamp);
        } elseif ('datim' === $format) {
            $varValue = $this->dateAdapter->parse($this->configAdapter->get('datimFormat'), $tstamp);
        } else {
            $varValue = (string) $tstamp;
        }

        return $varValue;
    }

    public function getBookingMin(): int
    {
        return (int) $this->get('minMembers');
    }

    public function getRegistrationsAsArray(array $arrBookingStateFilter = [], bool $ignoreRegWithUncompletedCheckout = true, array $arrOptions = []): array
    {
        $arrReg = [];

        if (null !== ($collection = $this->getRegistrations($arrBookingStateFilter, $ignoreRegWithUncompletedCheckout, $arrOptions))) {
            while ($collection->next()) {
                $arrReg[] = $collection->row();
            }
        }

        return $arrReg;
    }

    public function getRegistrations(array $arrBookingStateFilter = [], bool $ignoreRegWithUncompletedCheckout = true, array $arrOptions = []): Collection|null
    {
        $registrationAdapter = $this->framework->getAdapter(CebbRegistrationModel::class);

        if (empty($arrBookingStateFilter)) {
            return $registrationAdapter->findByPid($this->getModel()->id);
        }

        $t = $registrationAdapter->getTable();

        $arrColumns = [
            $t.'.pid = ?',
            $t.'.bookingState IN('.implode(',', array_fill(0, \count($arrBookingStateFilter), '?')).')',
        ];

        $arrValues = [
            $this->getModel()->id,
            ...$arrBookingStateFilter,
        ];

        if (true === $ignoreRegWithUncompletedCheckout) {
            $arrColumns[] = $t.'.checkoutCompleted = ?';
            $arrValues[] = true;
        }

        return $registrationAdapter->findBy($arrColumns, $arrValues, $arrOptions);
    }

    public function getEventStatus(int $numSeats = 1): string
    {
        if (!$this->isBookable()) {
            $status = SubscriptionStep::CASE_EVENT_NOT_BOOKABLE;
        } elseif (!$this->bookingValidator->validateBookingStartDate($this)) {
            $status = SubscriptionStep::CASE_BOOKING_NOT_YET_POSSIBLE;
        } elseif (!$this->bookingValidator->validateBookingEndDate($this)) {
            $status = SubscriptionStep::CASE_BOOKING_NO_LONGER_POSSIBLE;
        } elseif ($this->bookingValidator->validateBookingMax($this, $numSeats)) {
            $status = SubscriptionStep::CASE_BOOKING_POSSIBLE;
        } elseif ($this->bookingValidator->validateBookingMaxWaitingList($this, $numSeats)) {
            $status = SubscriptionStep::CASE_WAITING_LIST_POSSIBLE;
        } else {
            $status = SubscriptionStep::CASE_EVENT_FULLY_BOOKED;
        }

        return $status;
    }

    /**
     * @throws Exception
     */
    public function countByEventAndBookingState(array|string $bookingStates, $ignoreRegWithUncompletedCheckout = true): int
    {
        $bookingStates = \is_array($bookingStates) ? $bookingStates : [$bookingStates];
        if ($ignoreRegWithUncompletedCheckout) {
            $sumBookingTotal = $this->connection->fetchOne(
                'SELECT SUM(quantity) FROM tl_cebb_registration WHERE checkoutCompleted = :checkoutCompleted AND pid = :eventId && bookingState IN("'.implode('","', $bookingStates).'")',
                [
                    'checkoutCompleted' => true,
                    'eventId' => $this->getModel()->id,
                ],
                [
                    'checkoutCompleted' => Types::BOOLEAN,
                    'eventId' => Types::INTEGER,
                ],
            );
        } else {
            $sumBookingTotal = $this->connection->fetchOne(
                'SELECT SUM(quantity) FROM tl_cebb_registration WHERE pid = :eventId && bookingState IN("'.implode('","', $bookingStates).'")',
                [
                    'eventId' => $this->getModel()->id,
                ],
                [
                    'eventId' => Types::INTEGER,
                ],
            );
        }

        return is_numeric($sumBookingTotal) ? (int) $sumBookingTotal : 0;
    }
}
