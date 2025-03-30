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

namespace Markocupic\CalendarEventBookingBundle\Checkout\Step;

use Codefog\HasteBundle\Form\Form;
use Codefog\HasteBundle\UrlParser;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\Message;
use Contao\Model\Collection;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Markocupic\CalendarEventBookingBundle\Checkout\Exception\CheckoutException;
use Markocupic\CalendarEventBookingBundle\Event\CreateFinalizeStepFormEvent;
use Markocupic\CalendarEventBookingBundle\Event\PostBookingEvent;
use Markocupic\CalendarEventBookingBundle\EventBooking\Booking\BookingState;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Markocupic\CalendarEventBookingBundle\Model\CebbCartModel;
use Markocupic\CalendarEventBookingBundle\Model\CebbOrderModel;
use Markocupic\CalendarEventBookingBundle\Model\CebbRegistrationModel;
use Markocupic\CalendarEventBookingBundle\Storage\SessionStorage;
use Markocupic\CalendarEventBookingBundle\Util\CartUtil;
use Markocupic\CalendarEventBookingBundle\Util\CheckoutUtil;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Contracts\Translation\TranslatorInterface;

class FinalisationStep implements CheckoutStepInterface, RedirectCheckoutStepInterface
{
    private const STEP_IDENTIFIER = 'finalisation';

    private readonly Adapter $cebbRegistrationModelAdapter;

    private readonly Adapter $messageAdapter;

    private readonly Adapter $orderAdapter;

    private readonly Adapter $pageModelAdapter;

    private readonly Adapter $stringUtilAdapter;

    private string $templatePath = '';

    private Form|null $form = null;

    public function __construct(
        private readonly CartUtil $cartUtil,
        private readonly CheckoutUtil $checkoutUtil,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        private readonly UrlParser $urlParser,
    ) {
        $this->cebbRegistrationModelAdapter = $this->framework->getAdapter(CebbRegistrationModel::class);
        $this->messageAdapter = $this->framework->getAdapter(Message::class);
        $this->orderAdapter = $this->framework->getAdapter(CebbOrderModel::class);
        $this->pageModelAdapter = $this->framework->getAdapter(PageModel::class);
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
    }

    public function initialize(EventConfig $eventConfig, Request $request): void
    {
        $cart = $this->cartUtil->getCart($request);

        $this->form = $this->createForm($request, $eventConfig, $cart);
    }

    public function getIdentifier(): string
    {
        return self::STEP_IDENTIFIER;
    }

    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    public function setTemplatePath(string $templatePath = ''): void
    {
        $this->templatePath = $templatePath;
    }

    public function doAutoForward(EventConfig $eventConfig, Request $request): bool
    {
        return false;
    }

    public function commitStep(EventConfig $eventConfig, Request $request): bool
    {
        if (!$this->form->validate()) {
            return false;
        }

        $cart = $this->cartUtil->getCart($request);
        $arrRegistrations = $this->stringUtilAdapter->deserialize($cart->registrations, true);
        $regModels = [];

        $this->connection->beginTransaction();

        try {
            // Create the order entity
            $order = new CebbOrderModel();
            $order->eventId = $eventConfig->get('id');
            $order->dateAdded = time();
            $order->tstamp = time();
            $order->uuid = Uuid::uuid4()->toString();

            $user = $this->security->getUser();

            if ($user instanceof FrontendUser) {
                $order->memberId = $user->id;
            }

            $order->save();

            foreach ($arrRegistrations as $arrRegistration) {
                $regModels[] = $this->finalizeRegistration($arrRegistration, $order);
            }

            $cart->checkoutCompleted = true;
            $cart->tstamp = time();
            $cart->save();

            // Dispatch the PostBookingEvent
            $event = new PostBookingEvent($eventConfig, $order, $cart, new Collection($regModels, CebbRegistrationModel::getTable()), $request);
            $this->eventDispatcher->dispatch($event);
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();

            $cart->refresh();

            if ($e instanceof CheckoutException) {
                $this->messageAdapter->addError($e->getTranslatableText());
            } else {
                $this->messageAdapter->addError($this->translator->trans('ERR.text_booking_request_failed_due_to_unexpected_error', [], 'contao_default'));
            }

            return false;
        }

        $sessionStorage = new SessionStorage($request);
        $bag = $sessionStorage->getData();
        $bag[SessionStorage::ORDER_ID] = $order->id;
        $sessionStorage->storeData($bag);

        return true;
    }

    public function prepareStep(EventConfig $eventConfig, Request $request): array
    {
        $template = [];
        $template['registrations'] = $this->cartUtil->getRegistrations($request);
        $template['form'] = $this->form?->generate();

        return $template;
    }

    public function getResponse(EventConfig $eventConfig, Request $request): RedirectResponse
    {
        $module = $this->checkoutUtil->getModuleModel($request);

        // Get the redirection page
        $jumpToPage = $this->pageModelAdapter->findById($module->cebb_jumpToOnCheckoutCompletion);

        if (null === $jumpToPage) {
            throw new \Exception('No redirect page or invalid redirect page selected in your module settings. Because this is the last step of the checkout process, you have to tell the browser where to go.');
        }

        $sessionStorage = new SessionStorage($request);
        $bag = $sessionStorage->getData();
        $order = $this->orderAdapter->findById($bag[SessionStorage::ORDER_ID]);

        $redirectUrl = $this->urlParser->addQueryString(sprintf('events=%s&order=%s', $eventConfig->get('alias'), $order->uuid), $jumpToPage->getAbsoluteUrl());

        // This is the last task before we leave the checkout process.
        $sessionStorage->killSession();

        return new RedirectResponse($this->uriSigner->sign($redirectUrl));
    }

    protected function createForm(Request $request, EventConfig $eventConfig, CebbCartModel $cart): Form
    {
        $form = new Form('cebb_finalize', 'POST');

        $form->addSubmitFormField($this->translator->trans('BTN.cebb_finalize_submit_lbl', [], 'contao_default'), 'cebb_finalize:subscribe_btn');

        // Add more fields or add custom validation...
        $event = new CreateFinalizeStepFormEvent($form, $request, $eventConfig, $cart);

        $this->eventDispatcher->dispatch($event);

        return $form;
    }

    protected function finalizeRegistration(array $arrRegistration, CebbOrderModel $order): CebbRegistrationModel
    {
        $registration = $this->cebbRegistrationModelAdapter->findOneByUuid($arrRegistration['uuid']);

        if (null === $registration) {
            throw new CheckoutException('Could not find your registration.', $this->translator->trans('ERR.cebb_checkout_exception::registration_not_found', [], 'contao_default'));
        }

        $registration->checkoutCompleted = true;
        $registration->orderUuid = $order->uuid;
        $registration->tstamp = time();
        $registration->dateAdded = time();

        if (BookingState::STATE_CONFIRMED === $registration->bookingState) {
            $registration->confirmedOn = time();
        }

        $registration->save();

        return $registration;
    }
}
