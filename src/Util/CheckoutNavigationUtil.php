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

namespace Markocupic\CalendarEventBookingBundle\Util;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Knp\Menu\Matcher\Matcher;
use Knp\Menu\Renderer\ListRenderer;
use Markocupic\CalendarEventBookingBundle\Checkout\Step\ValidationCheckoutStepInterface;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class CheckoutNavigationUtil
{
    public function __construct(
        private readonly CheckoutUtil $checkoutUtil,
        private readonly FactoryInterface $menuFactory,
        private readonly TranslatorInterface $translator,
        #[Autowire('%markocupic_calendar_event_booking.checkout_step_parameter_name%')]
        private readonly string $stepParameterName,
    ) {
    }

    public function generate(EventConfig $eventConfig, Request $request): ItemInterface
    {
        $menu = $this->menuFactory->createItem('cebb_step_indicator');
        $arrSteps = $this->getSteps($eventConfig, $request);
        $i = 0;

        foreach ($arrSteps as $arrStep) {
            $options = [];
            if (!empty($arrStep['uri'])) {
                $options['uri'] = $arrStep['uri'];
            }

            $lbl = $this->translator->trans('CEBB_STEP_LBL.'.$arrStep['identifier'], [], 'contao_default');

            $child = $menu->addChild($lbl, $options)
                ->setLinkAttribute('title', $lbl)
                ->setAttribute('data-index', $i)
                ->setAttribute('data-step', $arrStep['identifier'])
            ;

            if ($arrStep['isPredecessor']) {
                $child->setAttribute('data-predecessor', 'true');
            }

            if ($arrStep['isCurrent']) {
                $child->setAttribute('data-current', 'true');
            }

            if ($arrStep['isSuccessor']) {
                $child->setAttribute('data-successor', 'true');
            }

            $child->setAttribute('data-reachable', true === $arrStep['isReachable'] ? 'true' : 'false');

            ++$i;
        }

        return $menu;
    }

    public function render(ItemInterface $menuFactory): string
    {
        $renderer = new ListRenderer(new Matcher());

        return $renderer->render($menuFactory);
    }

    protected function getSteps(EventConfig $eventConfig, Request $request): array
    {
        $checkoutManager = $this->checkoutUtil->getCheckoutManager($eventConfig, $request);

        $arrSteps = [];

        $currentStepIdentifier = $request->query->get($this->stepParameterName);

        // All steps
        $steps = $checkoutManager->getSteps();

        // Previous steps
        $arrPreviousSteps = array_map(static fn ($step) => $step->getIdentifier(), $checkoutManager->getPreviousSteps($currentStepIdentifier));

        foreach ($steps as $identifier) {
            $step = $checkoutManager->getStep($identifier);

            $arrStep = [];
            $arrStep['identifier'] = $step->getIdentifier();
            $arrStep['isReachable'] = false;
            $arrStep['isCurrent'] = false;
            $arrStep['isPredecessor'] = false;
            $arrStep['isSuccessor'] = true;
            $arrStep['uri'] = null;

            // Check if step is a predecessor or successor
            if ($currentStepIdentifier === $step->getIdentifier()) {
                $arrStep['isReachable'] = true;
                $arrStep['isCurrent'] = true;
                $arrStep['isSuccessor'] = false;
            }

            if (\in_array($step->getIdentifier(), $arrPreviousSteps, true)) {
                $arrStep['isPredecessor'] = true;
                $arrStep['isSuccessor'] = false;
            }

            $hasInvalidPredecessor = false;

            // Do only make steps accessible if all predecessors have passed validation
            foreach ($checkoutManager->getPreviousSteps($step->getIdentifier()) as $previousStep) {
                if ($previousStep instanceof ValidationCheckoutStepInterface) {
                    if (!$previousStep->validate($eventConfig, $request)) {
                        $hasInvalidPredecessor = true;
                    }
                }
            }

            if (true === $hasInvalidPredecessor) {
                $arrStep['isReachable'] = false;
            } else {
                $arrStep['isReachable'] = true;
                $arrStep['uri'] = $this->checkoutUtil->generateUrlForStep($request, $step->getIdentifier());
            }

            $arrSteps[$checkoutManager->getStepIndex($step->getIdentifier())] = $arrStep;
        }

        return $arrSteps;
    }
}
