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

namespace Markocupic\CalendarEventBookingBundle\EventListener\PostBooking;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\CalendarEventBookingBundle\Event\PostBookingEvent;
use Markocupic\CalendarEventBookingBundle\EventBooking\Notification\Notification;
use Markocupic\CalendarEventBookingBundle\Model\CebbRegistrationModel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PostBookingEvent::class, priority: 900)]
final class SendNotification
{
    private Adapter $stringUtilAdapter;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly Notification $notification,
    ) {
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
    }

    /**
     * Notify upon new registration.
     *
     * @throws \Exception
     */
    public function __invoke(PostBookingEvent $event): void
    {
        $eventConfig = $event->getEventConfig();

        if (!$eventConfig->get('enableBookingNotification')) {
            return;
        }

        $arrIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_cebb_registration WHERE orderUuid = :orderUuid',
            [
                'orderUuid' => $event->getOrder()->uuid,
            ],
            [
                'orderUuid' => Types::STRING,
            ],
        );

        foreach ($arrIds as $id) {
            $registration = CebbRegistrationModel::findById($id);

            if (null === $registration) {
                return;
            }

            $arrNotificationIds = $this->stringUtilAdapter->deserialize($eventConfig->get('eventBookingNotification'), true);
            $arrNotificationIds = array_map('intval', $arrNotificationIds);

            if (!empty($arrNotificationIds)) {
                $this->notification->setTokens($eventConfig, $registration, (int) $eventConfig->get('eventBookingNotificationSender'));
                $this->notification->notify($arrNotificationIds);
            }
        }
    }
}
