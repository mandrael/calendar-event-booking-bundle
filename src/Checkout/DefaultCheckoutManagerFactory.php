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
use Markocupic\CalendarEventBookingBundle\Checkout\Step\OptionalCheckoutStepInterface;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Markocupic\CalendarEventBookingBundle\Registry\PrioritizedServiceRegistry;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

final class DefaultCheckoutManagerFactory implements CheckoutManagerFactoryInterface
{
    public function __construct(
        private readonly ContainerInterface $steps,
        private readonly array $priorityMap,
        private readonly array $configMap,
    ) {
    }

    public function createCheckoutManager(EventConfig $eventConfig, Request $request): CheckoutManagerInterface
    {
        $serviceRegistry = new PrioritizedServiceRegistry(CheckoutStepInterface::class, 'checkout-manager-steps');

        foreach ($this->priorityMap as $identifier => $priority) {
            $step = $this->steps->get($identifier);

            if (!$step instanceof CheckoutStepInterface) {
                throw new \Exception(sprintf('%s should be an instance of %s.', $step, CheckoutStepInterface::class));
            }

            if (empty($step->getTemplatePath())) {
                $step->setTemplatePath($this->configMap[$identifier]['template']);
            }

            if ($step instanceof OptionalCheckoutStepInterface && !$step->isRequired($eventConfig, $request)) {
                continue;
            }

            $serviceRegistry->register($identifier, $priority, $step);
        }

        return new CheckoutManager($serviceRegistry);
    }
}
