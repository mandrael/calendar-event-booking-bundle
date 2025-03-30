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

namespace Markocupic\CalendarEventBookingBundle\Checkout;

final class CheckoutManagerFactoryCollection
{
    /**
     * @param iterable<CheckoutManagerFactoryInterface> $checkoutManagerFactories
     */
    public function __construct(
        private iterable $checkoutManagerFactories,
    ) {
    }

    public function getCheckoutManagerFactories(): \Traversable
    {
        return $this->checkoutManagerFactories;
    }
}
