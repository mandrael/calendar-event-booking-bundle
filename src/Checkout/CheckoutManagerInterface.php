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

use Markocupic\CalendarEventBookingBundle\Checkout\Step\CheckoutStepInterface;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Symfony\Component\HttpFoundation\Request;

interface CheckoutManagerInterface
{
    public function addCheckoutStep(CheckoutStepInterface $step, int $priority): void;

    /**
     * @return array<string>
     */
    public function getSteps(): array;

    public function getStep(string $identifier): CheckoutStepInterface|null;

    public function getNextStep(string $identifier): CheckoutStepInterface|null;

    public function hasNextStep(string $identifier): bool;

    public function getPreviousStep(string $identifier): CheckoutStepInterface|null;

    public function hasPreviousStep(string $identifier): bool;

    public function getFirstStep(): CheckoutStepInterface|null;

    /**
     * @return array<CheckoutStepInterface>
     */
    public function getPreviousSteps(string $identifier): array;

    public function validateStep(CheckoutStepInterface $step, EventConfig $eventConfig, Request $request): bool;

    public function prepareStep(CheckoutStepInterface $step, EventConfig $eventConfig, Request $request): array;

    public function getStepIndex(string $identifier): int;

    public function commitStep(CheckoutStepInterface $step, EventConfig $eventConfig, Request $request): bool;
}
