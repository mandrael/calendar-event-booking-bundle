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

use Contao\Model\Collection;
use Markocupic\CalendarEventBookingBundle\Event\PostBookingEvent;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\SessionConfig;
use Markocupic\CalendarEventBookingBundle\Model\CebbCartModel;
use Markocupic\CalendarEventBookingBundle\Model\CebbOrderModel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEventListener(event: PostBookingEvent::class, priority: 800)]
final class AddToSession
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Add registration data to the session.
     *
     * @throws \Exception
     */
    public function __invoke(PostBookingEvent $event): void
    {
        $this->addToSession($event->getEventConfig(), $event->getOrder(), $event->getCart(), $event->getEventRegistrations());
    }

    /**
     * @throws \Exception
     */
    private function addToSession(EventConfig $eventConfig, CebbOrderModel $order, CebbCartModel $cart, Collection $registrations): void
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();

        if (!$session->isStarted()) {
            $session->start();
        }

        $bag = [];
        $bag['order'] = $order->row();
        $bag['cart'] = $cart->row();
        $bag['event'] = $eventConfig->getModel()->row();
        $bag['registrations'] = [];

        while ($registrations->next()) {
            $bag['registrations'][] = $registrations->current()->row();
        }

        $flashBag = $session->getFlashBag();

        $flashBag->set(SessionConfig::FLASH_KEY.'.'.$cart->uuid, $bag);
    }
}
