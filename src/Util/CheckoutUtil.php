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

use Codefog\HasteBundle\UrlParser;
use Contao\CalendarEventsModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\CalendarEventBookingBundle\Checkout\CheckoutManagerFactoryCollection;
use Markocupic\CalendarEventBookingBundle\Checkout\CheckoutManagerInterface;
use Markocupic\CalendarEventBookingBundle\Checkout\Step\ValidationCheckoutStepInterface;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

class CheckoutUtil
{
    public function __construct(
        private readonly CheckoutManagerFactoryCollection $checkoutManagerFactoryCollection,
        private readonly UrlParser $urlParser,
        private readonly EventFactory $eventFactory,
        #[Autowire('%markocupic_calendar_event_booking.checkout_step_parameter_name%')]
        private readonly string $stepParameterName,
    ) {
    }

    public function getCheckoutManager(EventConfig $eventConfig, Request $request): CheckoutManagerInterface
    {
        $moduleModel = $this->getModuleModel($request);
        $checkoutManagerFactories = iterator_to_array($this->checkoutManagerFactoryCollection->getCheckoutManagerFactories());
        $checkoutManagerFactory = $checkoutManagerFactories[$moduleModel->cebb_checkoutType];

        return $checkoutManagerFactory->createCheckoutManager($eventConfig, $request);
    }

    public function getModuleModel(Request $request): ModuleModel
    {
        $id = $request->attributes->get('cebb.checkout_module.frontend_module_model_id', 0);

        $moduleModel = ModuleModel::findById($id);

        if (!$moduleModel instanceof ModuleModel) {
            $message = 'Could not find the Contao frontend module model. Have you properly initialized the CheckoutStepManager service?';

            throw new \Exception($message);
        }

        return $moduleModel;
    }

    public function getPageModel(Request $request): PageModel
    {
        $id = $request->attributes->get('cebb.checkout_module.page_model_id');

        return PageModel::findById($id);
    }

    public function generateUrlForStep(Request $request, string $stepIdentifier): string|null
    {
        $pageModel = $this->getPageModel($request);

        if (null === $pageModel) {
            throw new \Exception('Could not determine the page model from request.');
        }

        $eventConfig = $this->getEventConfig($request);

        if (null === $eventConfig) {
            throw new \Exception('Could not determine the event model from request.');
        }

        $url = $pageModel->getFrontendUrl('/'.$eventConfig->get('alias'));

        return $this->urlParser->addQueryString($this->stepParameterName.'='.$stepIdentifier, $url);
    }

    public function getPreviousStepHref(string $stepIdentifier, EventConfig $eventConfig, Request $request): string|null
    {
        $checkoutManager = $this->getCheckoutManager($eventConfig, $request);

        if (!$checkoutManager->hasPreviousStep($stepIdentifier)) {
            return null;
        }

        $previousStep = $checkoutManager->getPreviousStep($stepIdentifier);

        return $this->generateUrlForStep($request, $previousStep->getIdentifier());
    }

    public function getNextStepHref(string $stepIdentifier, EventConfig $eventConfig, Request $request): string|null
    {
        $checkoutManager = $this->getCheckoutManager($eventConfig, $request);

        $currentStep = $checkoutManager->getStep($stepIdentifier);

        if ($currentStep instanceof ValidationCheckoutStepInterface) {
            if (!$currentStep->validate($eventConfig, $request)) {
                return null;
            }
        }

        if (!$checkoutManager->hasNextStep($stepIdentifier)) {
            return null;
        }

        $nextStep = $checkoutManager->getNextStep($stepIdentifier);

        return $this->generateUrlForStep($request, $nextStep->getIdentifier());
    }

    public function getEventConfig(Request $request): EventConfig
    {
        if (!$request->attributes->has('cebb.checkout_module.event_id')) {
            throw new \Exception('Could not determine the event object from request.');
        }

        $id = $request->attributes->get('cebb.checkout_module.event_id');

        $objEvent = CalendarEventsModel::findById($id);

        if (null === $objEvent) {
            throw new \Exception('Could not determine the event object from request.');
        }

        return $this->eventFactory->create($objEvent);
    }
}
