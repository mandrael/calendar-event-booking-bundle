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

namespace Markocupic\CalendarEventBookingBundle\Twig\Extension;

use Contao\CalendarEventsModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventFactory;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigCalendarEventExtension extends AbstractExtension
{
    public function __construct(
        private readonly EventFactory $eventFactory,
        private readonly Connection $connection,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cebb_event_get', [$this, 'get']),
            new TwigFunction('cebb_event_get_event', [$this, 'getEvent']),
            new TwigFunction('cebb_event_get_registrations', [$this, 'getRegistrations']),
            new TwigFunction('cebb_event_get_number_free_seats', [$this, 'getNumberFreeSeats']),
            new TwigFunction('cebb_event_get_number_free_seats_waiting_list', [$this, 'getNumberFreeSeatsWaitingList']),
            new TwigFunction('cebb_event_get_confirmed_booking_count', [$this, 'getConfirmedBookingCount']),
            new TwigFunction('cebb_event_is_fully_booked', [$this, 'isFullyBooked']),
        ];
    }

    public function get(int $eventId, string $key): mixed
    {
        $eventConfig = $this->getEventConfig($eventId);

        return $eventConfig->get($key);
    }

    public function getEvent(int|null $eventId = null): CalendarEventsModel|null
    {
        if (null !== $eventId) {
            $event = CalendarEventsModel::findById($eventId);
            if (null !== $event) {
                return $event;
            }
        }

        // Try to retrieve the event from request
        return EventConfig::getEventFromRequest();
    }

    public function getRegistrations(int $eventId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_cebb_registration WHERE pid = :eventId',
            [
                'eventId' => $eventId,
            ],
            [
                'eventId' => Types::INTEGER,
            ],
        );
    }

    public function getNumberFreeSeats(int $eventId, $ignoreRegWithUncompletedCheckout = true): int
    {
        $eventConfig = $this->getEventConfig($eventId);

        return $eventConfig->getNumberOfFreeSeats($ignoreRegWithUncompletedCheckout);
    }

    public function getNumberFreeSeatsWaitingList(int $eventId, $ignoreRegWithUncompletedCheckout = true): int
    {
        $eventConfig = $this->getEventConfig($eventId);

        return $eventConfig->getNumberOfFreeSeatsWaitingList($ignoreRegWithUncompletedCheckout);
    }

    public function isFullyBooked(int $eventId, $ignoreRegWithUncompletedCheckout = true): bool
    {
        $eventConfig = $this->getEventConfig($eventId);

        return $eventConfig->isFullyBooked($ignoreRegWithUncompletedCheckout);
    }

    public function getConfirmedBookingCount(int $eventId, $ignoreRegWithUncompletedCheckout = true): int
    {
        $eventConfig = $this->getEventConfig($eventId);

        return $eventConfig->getConfirmedBookingsCount($ignoreRegWithUncompletedCheckout);
    }

    protected function getEventConfig(int $eventId): EventConfig|null
    {
        $event = CalendarEventsModel::findById($eventId);

        if (null === $event) {
            throw new \Exception('Event not found.');
        }

        return $this->eventFactory->create($event);
    }
}
