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

namespace Markocupic\CalendarEventBookingBundle\EventBooking\EventRegistration;

use Markocupic\CalendarEventBookingBundle\Event\BookingStateChangeEvent;
use Markocupic\CalendarEventBookingBundle\EventBooking\Booking\BookingState;
use Markocupic\CalendarEventBookingBundle\Model\CebbRegistrationModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class EventRegistration
{
    public const TABLE = 'tl_cebb_registration';

    private CebbRegistrationModel|null $model = null;

    private array $moduleData = [];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function hasModel(): bool
    {
        return null !== $this->model;
    }

    /**
     * @throws \Exception
     */
    public function getModel(): CebbRegistrationModel|null
    {
        if (!$this->hasModel()) {
            throw new \Exception('Model not found. Please use the EventRegistration::create() method first.');
        }

        return $this->model;
    }

    public function create(CebbRegistrationModel|null $model = null): void
    {
        if (null === $model) {
            $model = new CebbRegistrationModel();
        }

        $this->model = $model;
    }

    /**
     * @throws \Exception
     */
    public function changeBookingState(string $bookingStateNew): void
    {
        if (!\in_array($bookingStateNew, BookingState::ALL, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid booking state "%s" transmitted.', $bookingStateNew));
        }

        $model = $this->getModel();

        $bookingStateOld = $model->bookingState ?? '';

        if ($bookingStateOld === $bookingStateNew) {
            return;
        }

        if (BookingState::STATE_CONFIRMED === $bookingStateNew) {
            $model->confirmedOn = time();
        }

        $model->bookingState = $bookingStateNew;

        $model->save();

        $event = new BookingStateChangeEvent($model, $bookingStateOld, $bookingStateNew);

        $this->eventDispatcher->dispatch($event);
    }

    /**
     * @throws \Exception
     */
    public function unsubscribe(): void
    {
        $this->changeBookingState(BookingState::STATE_UNSUBSCRIBED);

        $registration = $this->getModel();
        $registration->unsubscribedOn = time();
        $registration->save();
    }

    public function getModuleData(): array
    {
        return $this->moduleData;
    }

    public function setModuleData(array $arrData): void
    {
        $this->moduleData = $arrData;
    }

    public function getTable(): string
    {
        return self::TABLE;
    }
}
