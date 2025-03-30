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

namespace Markocupic\CalendarEventBookingBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\CalendarEventBookingBundle\Checkout\CheckoutManagerFactoryCollection;
use Markocupic\CalendarEventBookingBundle\Checkout\CheckoutManagerInterface;
use Markocupic\CalendarEventBookingBundle\Checkout\Step\CheckoutStepInterface;
use Markocupic\CalendarEventBookingBundle\Checkout\Step\RedirectCheckoutStepInterface;
use Markocupic\CalendarEventBookingBundle\Checkout\Step\ValidationCheckoutStepInterface;
use Markocupic\CalendarEventBookingBundle\Event\CheckoutEvent;
use Markocupic\CalendarEventBookingBundle\Event\GetStepEvent;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventFactory;
use Markocupic\CalendarEventBookingBundle\EventBooking\Validator\BookingValidator;
use Markocupic\CalendarEventBookingBundle\Storage\SessionStorage;
use Markocupic\CalendarEventBookingBundle\Util\CartUtil;
use Markocupic\CalendarEventBookingBundle\Util\CheckoutNavigationUtil;
use Markocupic\CalendarEventBookingBundle\Util\CheckoutUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsFrontendModule(EventBookingCheckoutController::TYPE, category: 'events', template: 'mod_event_booking_checkout')]
class EventBookingCheckoutController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_booking_checkout';

    public function __construct(
        private readonly BookingValidator $bookingValidator,
        private readonly CartUtil $cartUtil,
        private readonly CheckoutNavigationUtil $checkoutNavigationUtil,
        private readonly CheckoutUtil $checkoutUtil,
        private readonly ContaoFramework $framework,
        private readonly Environment $twig,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EventFactory $eventFactory,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly Security $security,
        private readonly CheckoutManagerFactoryCollection $checkoutManagerFactoryCollection,
        #[Autowire('%markocupic_calendar_event_booking.checkout_step_parameter_name%')]
        private readonly string $stepParameterName,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        if ($page instanceof PageModel && $this->scopeMatcher->isFrontendRequest($request)) {
            $event = EventConfig::getEventFromRequest();

            if (null === $event) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            $eventConfig = $this->eventFactory->create($event);

            if (!$eventConfig->get('published') || !$eventConfig->get('enableBookingForm')) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            $request->attributes->set('cebb.checkout_module.frontend_module_model_id', $model->id);
            $request->attributes->set('cebb.checkout_module.page_model_id', $page->id);
            $request->attributes->set('cebb.checkout_module.event_id', $eventConfig->get('id'));

            // Initialize application, add data to the session, add data to request attributes
            if (!$this->initialized($request)) {
                return $this->initialize($request);
            }
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $eventConfig = $this->checkoutUtil->getEventConfig($request);

        // Select the correct checkout manager factory
        $checkoutManagerFactories = iterator_to_array($this->checkoutManagerFactoryCollection->getCheckoutManagerFactories());
        $checkoutManagerFactory = $checkoutManagerFactories[$model->cebb_checkoutType];

        /** @var CheckoutManagerInterface $checkoutManager */
        $checkoutManager = $checkoutManagerFactory->createCheckoutManager($eventConfig, $request);

        $stepIdentifier = $this->getParameterFromRequest($request, $this->stepParameterName);
        $step = null;

        if (!empty($stepIdentifier)) {
            $step = $checkoutManager->getStep($stepIdentifier);
        }

        $event = new GetStepEvent($request, $step);
        $this->eventDispatcher->dispatch($event);

        $step = $event->getStep();

        if ($event->isStopped() && $event->hasResponse()) {
            return $event->getResponse();
        }

        $dataForStep = [];

        // Redirect to the first step
        if (!$step instanceof CheckoutStepInterface) {
            $steps = $checkoutManager->getSteps();

            $step = $checkoutManager->getStep(array_key_first($steps));

            if (!$step instanceof CheckoutStepInterface) {
                throw new \Exception('Could not find the first checkout-step. Please check your step configuration');
            }

            $urlRedirect = $this->checkoutUtil->generateUrlForStep($request, $step->getIdentifier());

            return $this->redirect($urlRedirect);
        }

        // Check all previous steps. Redirect back if validation fails.
        foreach ($checkoutManager->getPreviousSteps($stepIdentifier) as $previousStep) {
            if ($previousStep instanceof ValidationCheckoutStepInterface && !$previousStep->validate($eventConfig, $request)) {
                $urlRedirect = $this->checkoutUtil->generateUrlForStep($request, $previousStep->getIdentifier());

                return $this->redirect($urlRedirect);
            }
        }

        $isValid = $step instanceof ValidationCheckoutStepInterface && $step->validate($eventConfig, $request);
        if ($isValid && $step->doAutoForward($eventConfig, $request)) {
            $nextStep = $checkoutManager->getNextStep($stepIdentifier);
            if ($nextStep) {
                $urlRedirect = $this->checkoutUtil->generateUrlForStep($request, $nextStep->getIdentifier());

                return $this->redirect($urlRedirect);
            }
        }

        // Initialize step We need a method that we can use to e.g. set properties that
        // we need in commit() and prepare()
        $step->initialize($eventConfig, $request);

        if ($request->isMethod('POST')) {
            try {
                if ($step->commitStep($eventConfig, $request)) {
                    $response = null;

                    if ($step instanceof RedirectCheckoutStepInterface) {
                        // Let the step logic decide the redirection path.
                        $response = $step->getResponse($eventConfig, $request);
                    } else {
                        $nextStep = $checkoutManager->getNextStep($stepIdentifier);

                        if ($nextStep) {
                            $urlRedirect = $this->checkoutUtil->generateUrlForStep($request, $nextStep->getIdentifier());
                            $response = $this->redirect($urlRedirect);
                        }
                    }

                    // Last step needs to tell us where to go!
                    if (!$checkoutManager->hasNextStep($stepIdentifier) && !$response instanceof Response) {
                        throw new \InvalidArgumentException(sprintf('Last step was executed, but no Response has been generated. To solve your issue, have a look at the last Checkout step %s and implement %s interface', $step->getIdentifier(), RedirectCheckoutStepInterface::class));
                    }

                    return $response;
                }
            } catch (\Exception $e) {
                $dataForStep['exception'] = $e->getMessage();
            }
        }

        $preparedData = array_merge($dataForStep, $this->getTemplateData($request), $checkoutManager->prepareStep($step, $eventConfig, $request));

        $dataForStep = array_merge(
            [
                'event' => $eventConfig->getModel()->row(),
                'step' => $step,
                'identifier' => $stepIdentifier,
                'messages' => $this->framework->getAdapter(Message::class)->hasMessages() ? $this->framework->getAdapter(Message::class)->generate() : null,
                'previousStepHref' => $this->checkoutUtil->getPreviousStepHref($stepIdentifier, $eventConfig, $request),
                'nextStepHref' => $this->checkoutUtil->getNextStepHref($stepIdentifier, $eventConfig, $request),
            ],
            $preparedData,
        );

        $cart = $this->cartUtil->hasCart($request) ? $this->cartUtil->getCart($request) : null;
        $event = new CheckoutEvent($request, $eventConfig, $step, $dataForStep, $cart);
        $this->eventDispatcher->dispatch($event);

        if ($event->isStopped()) {
            $this->addEventFlash($event->getMessageType(), $event->getMessage());

            if ($event->hasResponse()) {
                return $event->getResponse();
            }

            $urlRedirect = $this->checkoutUtil->generateUrlForStep(
                $request,
                $checkoutManager->getFirstStep()->getIdentifier(),
            );

            return $this->redirect($urlRedirect ?? $request->getSchemeAndHttpHost());
        }

        $template->setData(array_merge($template->getData(), $this->getTemplateData($request)));
        $template->set('step', $step);
        $template->set('step_markup', $this->renderResponseForCheckoutStep($step, $dataForStep)->getContent());
        $template->set('step_navigation', $this->checkoutNavigationUtil->render($this->checkoutNavigationUtil->generate($eventConfig, $request)));

        return $template->getResponse();
    }

    protected function getParameterFromRequest(Request $request, string $key, $default = null): string|null
    {
        if ($request !== $result = $request->attributes->get($key, $request)) {
            return $result;
        }

        if ($request->query->has($key)) {
            return $request->query->all()[$key];
        }

        if ($request->request->has($key)) {
            return $request->request->all()[$key];
        }

        return $default;
    }

    /**
     * @throws \Exception
     */
    protected function initialized(Request $request): bool
    {
        $sessionStorage = new SessionStorage($request);
        $sessionBag = $sessionStorage->getData();

        if (empty($sessionBag[SessionStorage::EVENT_ID])) {
            return false;
        }

        if ($sessionBag[SessionStorage::EVENT_ID] !== (int) $this->checkoutUtil->getEventConfig($request)->get('id')) {
            return false;
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    protected function initialize(Request $request): Response
    {
        $eventConfig = $this->checkoutUtil->getEventConfig($request);

        $storage = new SessionStorage($request);
        $bag = [
            SessionStorage::EVENT_ID => (int) $eventConfig->get('id'),
        ];

        $storage->storeData($bag);

        return new RedirectResponse($request->getUri());
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected function renderResponseForCheckoutStep(CheckoutStepInterface $step, array $dataForStep): Response
    {
        return new Response($this->twig->render($step->getTemplatePath(), $dataForStep));
    }

    protected function addEventFlash(string $messageType, string $message): void
    {
        switch ($messageType) {
            case CheckoutEvent::TYPE_INFO:
                $this->framework->getAdapter(Message::class)->addInfo($message);
                break;
            case CheckoutEvent::TYPE_ERROR:
                $this->framework->getAdapter(Message::class)->addError($message);
                break;
            case CheckoutEvent::TYPE_CONFIRMATION:
                $this->framework->getAdapter(Message::class)->addConfirmation($message);
                break;
        }
    }

    protected function getTemplateData(Request $request): array
    {
        $eventConfig = $this->checkoutUtil->getEventConfig($request);
        $cart = $this->cartUtil->hasCart($request) ? $this->cartUtil->getCart($request) : null;

        $template = [];
        $template['eventConfig'] = $eventConfig;
        $template['event'] = $eventConfig->getModel()->row();
        $template['cart'] = $cart?->row();
        $template['canRegister'] = $this->bookingValidator->validateCanRegister($eventConfig, $cart);

        $user = $this->security->getUser();

        if ($user instanceof FrontendUser) {
            $template['hasLoggedInUser'] = true;
            $template['getLoggedInUser'] = $this->framework->getAdapter(MemberModel::class)->findById($user->id)->row();
        } else {
            $template['hasLoggedInUser'] = false;
        }

        return $template;
    }
}
