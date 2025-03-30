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

namespace Markocupic\CalendarEventBookingBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\CalendarEventBookingBundle\EventBooking\Booking\BookingState;
use Markocupic\CalendarEventBookingBundle\Model\CebbRegistrationModel;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalendarEvents
{
    public const TABLE = 'tl_calendar_events';

    private Adapter $registrationAdapter;

    private Adapter $messageAdapter;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
    ) {
        $this->registrationAdapter = $this->framework->getAdapter(CebbRegistrationModel::class);
        $this->messageAdapter = $this->framework->getAdapter(Message::class);
    }

    /**
     * Adjust bookingStartDate and bookingEndDate.
     *
     * @throws Exception
     */
    #[AsCallback(table: self::TABLE, target: 'config.onsubmit')]
    public function adjustBookingDate(DataContainer $dc): void
    {
        // Return if there is no current record (override all)
        if (null === $dc->getCurrentRecord()) {
            return;
        }

        $arrSet['bookingStartDate'] = $dc->getCurrentRecord()['bookingStartDate'] ?: null;
        $arrSet['bookingEndDate'] = $dc->getCurrentRecord()['bookingEndDate'] ?: null;

        // Set end date
        if (!empty((int) $dc->getCurrentRecord()['bookingEndDate'])) {
            if ($dc->getCurrentRecord()['bookingEndDate'] < $dc->getCurrentRecord()['bookingStartDate']) {
                $arrSet['bookingEndDate'] = $dc->getCurrentRecord()['bookingStartDate'];
                $this->messageAdapter->addInfo($GLOBALS['TL_LANG']['MSC']['adjusted_booking_period_end_time']);
            }
        }

        $this->connection->update(self::TABLE, $arrSet, ['id' => $dc->id]);
    }

    #[AsCallback(table: self::TABLE, target: 'fields.unsubscribeLimitTstamp.save')]
    public function saveUnsubscribeLimitTstamp(int|null $intValue, DataContainer $dc): int|null
    {
        if (!empty($intValue)) {
            // Check whether we have an unsubscribeLimit (in days) set as well, notify the
            // user that we cannot have both
            if ($dc->getCurrentRecord()['unsubscribeLimit'] > 0) {
                throw new \InvalidArgumentException($GLOBALS['TL_LANG']['ERR']['conflicting_unsubscribe_limits']);
            }

            // Check whether the timestamp entered makes sense in relation to the event start
            // and end times If the event has an end date (and optional time) that's the last
            // sensible time unsubscription makes sense
            if ($dc->getCurrentRecord()['endDate']) {
                if ($dc->getCurrentRecord()['addTime']) {
                    $intMaxValue = (int) strtotime(date('Y-m-d', (int) $dc->getCurrentRecord()['endDate']).' '.date('H:i:s', (int) $dc->getCurrentRecord()['endTime']));
                } else {
                    $intMaxValue = (int) $dc->getCurrentRecord()['endDate'];
                }
            } else {
                if ($dc->getCurrentRecord()['addTime']) {
                    $intMaxValue = (int) strtotime(date('Y-m-d', (int) $dc->getCurrentRecord()['startDate']).' '.date('H:i:s', (int) $dc->getCurrentRecord()['startTime']));
                } else {
                    $intMaxValue = (int) $dc->getCurrentRecord()['startDate'];
                }
            }

            if ($intValue > $intMaxValue) {
                throw new \InvalidArgumentException($GLOBALS['TL_LANG']['ERR']['invalid_unsubscription_limit']);
            }
        }

        return $intValue;
    }

    #[AsCallback(table: self::TABLE, target: 'list.sorting.child_record', priority: 100)]
    public function childRecordCallback(array $arrRow): string
    {
        $origClass = new \tl_calendar_events();

        $strRegistrationsBadges = $this->getBookingStateBadgesString($arrRow);

        if ($strRegistrationsBadges) {
            $arrRow['title'] .= $strRegistrationsBadges;
        }

        return $origClass->listEvents($arrRow);
    }

    private function getBookingStateBadgesString(array $arrRow): string
    {
        $strRegistrationsBadges = '';

        $intNotConfirmed = 0;
        $intConfirmed = 0;
        $intRejected = 0;
        $intWaitingList = 0;
        $intUnsubscribed = 0;
        $intWaitingForPayment = 0;
        $intUndefined = 0;

        $registration = $this->registrationAdapter->findByPid($arrRow['id']);

        if (null !== $registration) {
            while ($registration->next()) {
                // Do not consider registrations with an uncompleted checkout
                if (!$registration->checkoutCompleted) {
                    continue;
                }

                if (BookingState::STATE_NOT_CONFIRMED === $registration->bookingState) {
                    ++$intNotConfirmed;
                } elseif (BookingState::STATE_CONFIRMED === $registration->bookingState) {
                    ++$intConfirmed;
                } elseif (BookingState::STATE_REJECTED === $registration->bookingState) {
                    ++$intRejected;
                } elseif (BookingState::STATE_WAITING_LIST === $registration->bookingState) {
                    ++$intWaitingList;
                } elseif (BookingState::STATE_UNSUBSCRIBED === $registration->bookingState) {
                    ++$intUnsubscribed;
                } elseif (BookingState::STATE_WAITING_FOR_PAYMENT === $registration->bookingState) {
                    ++$intWaitingForPayment;
                } elseif (BookingState::STATE_UNDEFINED === $registration->bookingState) {
                    ++$intUndefined;
                } else {
                    ++$intUndefined;
                }
            }

            if ($intNotConfirmed > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge not-confirmed blink" title="%dx %s">%sx</span>', $intNotConfirmed, $this->translator->trans('MSC.'.BookingState::STATE_NOT_CONFIRMED, [], 'contao_default'), $intNotConfirmed);
            }

            if ($intConfirmed > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge confirmed" title="%dx %s">%dx</span>', $intConfirmed, $this->translator->trans('MSC.'.BookingState::STATE_CONFIRMED, [], 'contao_default'), $intConfirmed);
            }

            if ($intRejected > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge rejected" title="%dx %s">%dx</span>', $intRejected, $this->translator->trans('MSC.'.BookingState::STATE_REJECTED, [], 'contao_default'), $intRejected);
            }

            if ($intWaitingList > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge waiting-list" title="%dx %s">%dx</span>', $intWaitingList, $this->translator->trans('MSC.'.BookingState::STATE_WAITING_LIST, [], 'contao_default'), $intWaitingList);
            }

            if ($intUnsubscribed > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge unsubscribed" title="%dx %s">%dx</span>', $intUnsubscribed, $this->translator->trans('MSC.'.BookingState::STATE_UNSUBSCRIBED, [], 'contao_default'), $intUnsubscribed);
            }

            if ($intWaitingForPayment > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge waiting-for-payment" title="%dx %s">%dx</span>', $intWaitingForPayment, $this->translator->trans('MSC.'.BookingState::STATE_WAITING_FOR_PAYMENT, [], 'contao_default'), $intWaitingForPayment);
            }

            if ($intUndefined > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge undefined" title="%dx %s">%sx</span>', $intUndefined, $this->translator->trans('MSC.'.BookingState::STATE_UNDEFINED, [], 'contao_default'), $intUndefined);
            }
        }

        return $strRegistrationsBadges;
    }
}
