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

namespace Markocupic\CalendarEventBookingBundle\EventBooking\Template;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Doctrine\DBAL\Exception;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Markocupic\CalendarEventBookingBundle\EventBooking\Validator\BookingValidator;
use Symfony\Bundle\SecurityBundle\Security;

final class AddTemplateData
{
    private Adapter $memberModelAdapter;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly BookingValidator $bookingValidator,
    ) {
        $this->memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
    }

    /**
     * Used to augment template with more event properties.
     *
     * @throws Exception
     */
    public function getTemplateData(EventConfig $eventConfig): array
    {
        $data = [];

        $data['canRegister'] = $this->bookingValidator->validateCanRegister($eventConfig);

        $data['bookingMin'] = $eventConfig->getBookingMin();

        $data['bookingMax'] = $eventConfig->getBookingMax();

        $data['bookingStartDate'] = $eventConfig->getBookingStartDate('date');

        $data['bookingStartDatim'] = $eventConfig->getBookingStartDate('datim');

        $data['bookingStartTimestamp'] = $eventConfig->getBookingStartDate('timestamp');

        $data['getBookingEndDate'] = $eventConfig->getBookingEndDate('date');

        $data['getBookingEndDatim'] = $eventConfig->getBookingEndDate('datim');

        $data['getBookingEndTimestamp'] = $eventConfig->getBookingEndDate('timestamp');

        $data['hasLoggedInUser'] = $this->hasLoggedInFrontendUser();

        $data['getLoggedInUser'] = $this->getLoggedInFrontendUser() ? $this->getLoggedInFrontendUser()->row() : [];

        $data['event'] = $eventConfig->getModel()->row();

        $data['eventConfig'] = $eventConfig;

        $data['isFullyBooked'] = static fn (bool $ignoreRegWithUncompletedCheckout = true): bool => $eventConfig->isFullyBooked($ignoreRegWithUncompletedCheckout);

        $data['numberFreeSeats'] = static fn (bool $ignoreRegWithUncompletedCheckout = true): int => $eventConfig->getNumberOfFreeSeats();

        $data['registrations'] = static fn (array $filter = [], bool $ignoreRegWithUncompletedCheckout = true): array => $eventConfig->getRegistrationsAsArray($filter, $ignoreRegWithUncompletedCheckout);

        $data['numberFreeSeatsWaitingList'] = static fn (bool $ignoreRegWithUncompletedCheckout = true): int => $eventConfig->getNumberOfFreeSeatsWaitingList($ignoreRegWithUncompletedCheckout);

        $data['confirmedBookingsCount'] = static fn (bool $ignoreRegWithUncompletedCheckout = true): int => $eventConfig->getRegistrationTotalCount($ignoreRegWithUncompletedCheckout);

        return $data;
    }

    protected function hasLoggedInFrontendUser(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof FrontendUser;
    }

    protected function getLoggedInFrontendUser(): MemberModel|null
    {
        $user = $this->security->getUser();

        if ($user instanceof FrontendUser) {
            return $this->memberModelAdapter->findById($user->id);
        }

        return null;
    }
}
