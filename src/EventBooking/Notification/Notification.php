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

namespace Markocupic\CalendarEventBookingBundle\EventBooking\Notification;

use Codefog\HasteBundle\Formatter;
use Codefog\HasteBundle\UrlParser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Contao\UserModel;
use Markocupic\CalendarEventBookingBundle\Event\SetEventBookingNotificationTokensEvent;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Markocupic\CalendarEventBookingBundle\Model\CebbOrderModel;
use Markocupic\CalendarEventBookingBundle\Model\CebbRegistrationModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Terminal42\NotificationCenterBundle\NotificationCenter;

class Notification
{
    private Adapter $configAdapter;

    private Adapter $controllerAdapter;

    private Adapter $pageModelAdapter;

    private Adapter $userModelAdapter;

    private array $arrTokens = [];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Formatter $formatter,
        private readonly NotificationCenter $notificationCenter,
        private readonly RequestStack $requestStack,
        private readonly UrlParser $urlParser,
    ) {
        $this->configAdapter = $this->framework->getAdapter(Config::class);
        $this->controllerAdapter = $this->framework->getAdapter(Controller::class);
        $this->pageModelAdapter = $this->framework->getAdapter(PageModel::class);
        $this->userModelAdapter = $this->framework->getAdapter(UserModel::class);
    }

    public function getTokens(): array
    {
        return $this->arrTokens;
    }

    /**
     * @throws \Exception
     */
    public function setTokens(EventConfig $eventConfig, CebbRegistrationModel $registration, int|null $senderId): void
    {
        $arrTokens = [];

        $strRegistrationTable = CebbRegistrationModel::getTable();
        $strOrderTable = CebbOrderModel::getTable();
        $strEventsTable = CalendarEventsModel::getTable();

        // Get admin email
        $arrTokens['admin_email'] = $GLOBALS['TL_ADMIN_EMAIL'] ?? $this->configAdapter->get('adminEmail');

        // Prepare tokens for the order use "order_*" as prefix
        $this->controllerAdapter->loadDataContainer($strOrderTable);

        $order = CebbOrderModel::findByUuid($registration->orderUuid);

        if (null !== $order) {
            $row = $order->row();

            foreach ($row as $k => $v) {
                if (isset($GLOBALS['TL_DCA'][$strOrderTable]['fields'][$k])) {
                    $arrTokens['order_'.$k] = $this->formatter->dcaValue($strOrderTable, $k, $v);
                } else {
                    $arrTokens['order_'.$k] = html_entity_decode((string) $v);
                }
            }
        }

        // Prepare tokens for the order use "member_*" as prefix
        $this->controllerAdapter->loadDataContainer($strRegistrationTable);

        $row = $registration->row();

        foreach ($row as $k => $v) {
            if (isset($GLOBALS['TL_DCA'][$strRegistrationTable]['fields'][$k])) {
                $arrTokens['member_'.$k] = $this->formatter->dcaValue($strRegistrationTable, $k, $v);
            } else {
                $arrTokens['member_'.$k] = html_entity_decode((string) $v);
            }
        }

        $arrTokens['member_salutation'] = html_entity_decode((string) ($GLOBALS['TL_LANG'][$strRegistrationTable]['salutation_'.$registration->gender] ?? ''));

        // Prepare tokens for event and use "event_*" as prefix
        $this->controllerAdapter->loadDataContainer($strEventsTable);

        $arrFields = array_keys($eventConfig->getModel()->row());

        foreach ($arrFields as $fieldName) {
            if (isset($GLOBALS['TL_DCA'][$strEventsTable]['fields'][$fieldName])) {
                $arrTokens['event_'.$fieldName] = $this->formatter->dcaValue($strEventsTable, $fieldName, $eventConfig->get($fieldName));
            } else {
                $arrTokens['event_'.$fieldName] = html_entity_decode((string) $eventConfig->get($fieldName));
            }
        }

        if ($senderId) {
            // Prepare tokens for the sender and use "sender_*" as prefix
            $sender = $this->userModelAdapter->findById($senderId);

            if (null !== $sender) {
                $this->controllerAdapter->loadDataContainer('tl_user');

                $row = $sender->row();

                foreach ($row as $k => $v) {
                    if ('password' === $k || 'session' === $k) {
                        continue;
                    }

                    if (isset($GLOBALS['TL_DCA']['tl_user']['fields'][$k])) {
                        $arrTokens['sender_'.$k] = html_entity_decode((string) $this->formatter->dcaValue('tl_user', $k, $v));
                    } else {
                        $arrTokens['sender'.$k] = html_entity_decode((string) $v);
                    }
                }
            }
        }

        // Generate unsubscribe url
        $arrTokens['member_cancelRegistrationUrl'] = '';

        // An order can have multiple registrations This makes it possible to create a
        // function that can be used to cancel all registrations of an order.
        $arrTokens['member_cancelOrderUrl'] = '';

        if ($eventConfig->get('enableUnsubscription')) {
            $objCalendar = $eventConfig->getModel()->getRelated('pid');

            if (null !== $objCalendar) {
                $objPage = $this->pageModelAdapter->findById($objCalendar->eventUnsubscribePage);

                if (null !== $objPage) {
                    $url = $this->urlParser->addQueryString('regUuid='.$registration->uuid, $objPage->getAbsoluteUrl());
                    $arrTokens['member_cancelRegistrationUrl'] = $url;
                    $url = $this->urlParser->addQueryString('orderUuid='.$registration->orderUuid, $objPage->getAbsoluteUrl());
                    $arrTokens['member_cancelOrderUrl'] = $url;
                }
            }
        }

        $event = new SetEventBookingNotificationTokensEvent($arrTokens, $eventConfig, $registration, $this->requestStack->getCurrentRequest());
        $this->eventDispatcher->dispatch($event);

        $this->arrTokens = $event->getTokens();
    }

    /**
     * @throws \Exception
     */
    public function notify(array $arrNotifications): void
    {
        if (!empty($arrNotifications)) {
            // Send notification (multiple notifications possible)
            foreach ($arrNotifications as $notificationId) {
                $this->notificationCenter->sendNotification($notificationId, $this->getTokens());
            }
        }
    }
}
