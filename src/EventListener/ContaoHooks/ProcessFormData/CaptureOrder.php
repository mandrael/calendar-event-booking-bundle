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

namespace Markocupic\CalendarEventBookingBundle\EventListener\ContaoHooks\ProcessFormData;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Form;
use Contao\FrontendUser;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Markocupic\CalendarEventBookingBundle\Checkout\Step\SubscriptionStep;
use Markocupic\CalendarEventBookingBundle\Event\BookingFormSubmitEvent;
use Markocupic\CalendarEventBookingBundle\EventBooking\Booking\BookingState;
use Markocupic\CalendarEventBookingBundle\EventBooking\Booking\BookingType;
use Markocupic\CalendarEventBookingBundle\EventListener\ContaoHooks\AbstractHook;
use Markocupic\CalendarEventBookingBundle\Model\CebbRegistrationModel;
use Markocupic\CalendarEventBookingBundle\Util\CartUtil;
use Markocupic\CalendarEventBookingBundle\Util\CheckoutUtil;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsHook(CaptureOrder::HOOK, priority: 1000)]
final class CaptureOrder extends AbstractHook
{
    public const HOOK = 'processFormData';

    private Adapter $messageAdapter;

    public function __construct(
        private readonly CartUtil $cartUtil,
        private readonly CheckoutUtil $checkoutUtil,
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
        $this->messageAdapter = $this->framework->getAdapter(Message::class);
    }

    /**
     * Add registrations from event subscription form.
     */
    public function __invoke(array $arrSubmitted, array $formData, array|null $files, array $labels, Form $form): void
    {

        if (empty($formData['isCalendarEventBookingForm'])) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $eventConfig = $this->checkoutUtil->getEventConfig($request);

        $arrExisting = $this->cartUtil->getRegistrations($request);
        $arrNew = $this->getRegistrationsFromFormSubmit($arrSubmitted);

        $numSeats = 0;

        // Add more data to the record and check quantity
        foreach ($arrNew as $index => $dataCustomer) {
            if (empty($dataCustomer['quantity']) || ((string)$dataCustomer['quantity'] !== (string)(int)$dataCustomer['quantity'])) {
                $arrNew[$index]['quantity'] = 1;
            }

            $numSeats += $arrNew[$index]['quantity'];

            $arrNew[$index]['pid'] = $eventConfig->getModel()->id;
            $arrNew[$index]['formId'] = $form->id;
            $arrNew[$index]['uuid'] = Uuid::uuid4()->toString();
            $arrNew[$index]['checkoutCompleted'] = 0;
            $arrNew[$index]['dateAdded'] = time();
            $arrNew[$index]['tstamp'] = time();
            $arrNew[$index]['bookingType'] = $this->security->getUser() instanceof FrontendUser ? BookingType::TYPE_MEMBER : BookingType::TYPE_GUEST;
        }

        $eventStatus = $eventConfig->getEventStatus($numSeats);

        $blnWaitingList = false;

        if (SubscriptionStep::CASE_WAITING_LIST_POSSIBLE === $eventStatus) {
            $strMessageSuccess = $this->translator->trans('MSC.successfully_placed_on_the_waiting_list', [], 'contao_default');
            $blnWaitingList = true;
        } elseif (SubscriptionStep::CASE_BOOKING_POSSIBLE === $eventStatus) {
            $strMessageSuccess = $this->translator->trans('MSC.participant_has_been_captured_successfully', [], 'contao_default');
        } else {
            $form->addError($this->translator->trans('MSC.registration_failed_please_check_free_places', [], 'contao_default'));

            return;
        }

        $cart = $this->cartUtil->getCart($request);

        foreach ($arrNew as $index => $dataCustomer) {
            $arrNew[$index]['waitingList'] = $blnWaitingList;
            $arrNew[$index]['bookingState'] = $blnWaitingList ? BookingState::STATE_WAITING_LIST : $eventConfig->get('bookingState');
            $arrNew[$index]['cartUuid'] = $cart->uuid;
        }

        // Dispatch event e.g. add an error to the form object to prevent inserting
        // new registrations.
        $event = new BookingFormSubmitEvent($request, $form, $cart, $eventConfig, $arrExisting, $arrNew, $arrSubmitted);

        $this->eventDispatcher->dispatch($event);

        if ($form->hasErrors()) {
            return;
        }

        $arrNew = $event->getNewRegistrations();
        $arrExisting = $event->getExistingRegistrations();

        $this->connection->beginTransaction();

        try {
            foreach ($arrNew as $index => $dataCustomer) {
                // Do temporary registration
                $this->temporaryRegistration($arrNew[$index], $dataCustomer);
            }

            $cart->registrations = serialize(array_merge($arrExisting, $arrNew));
            $cart->tstamp = time();
            $cart->save();

            $this->connection->commit();
        } catch (\Exception $e) {
            $form->addError($this->translator->trans('ERR.text_booking_request_failed_due_to_unexpected_error', [], 'contao_default'));
            $this->connection->rollBack();
            $cart->refresh();
        }

        if ($form->hasErrors()) {
            return;
        }

        $this->messageAdapter->addInfo($strMessageSuccess);
    }

    /**
     * Support fieldset duplication
     * https://github.com/inspiredminds/contao-fieldset-duplication.
     *
     * @return array<array>
     */
    protected function getRegistrationsFromFormSubmit(array $submittedData): array
    {
        $hasDuplication = str_contains(implode('', array_keys($submittedData)), '_duplicate_');

        if ($hasDuplication) {
            $keys = array_map(static fn($key) => preg_replace('/_duplicate_([1-9]+)$/', '', $key), array_keys($submittedData));
            $keys = array_unique(array_filter($keys));

            $arrSubmittedData = $submittedData;
            $keys = array_unique(array_filter($keys));

            $maxIndex = $this->getMaxIntegerFromString(implode('', array_keys($submittedData)));

            $itemsTotal = \count($arrSubmittedData);
            $i = 0;
            $sets = [];

            for ($ii = 0; $ii < $maxIndex + 1; ++$ii) {
                $set = [];

                foreach ($keys as $key) {
                    if (isset($arrSubmittedData[$key])) {
                        $set[$key] = $arrSubmittedData[$key];
                        unset($arrSubmittedData[$key]);
                        ++$i;
                    } elseif (isset($arrSubmittedData[$key.'_duplicate_'.$ii])) {
                        $set[$key] = $arrSubmittedData[$key.'_duplicate_'.$ii];
                        unset($arrSubmittedData[$key.'_duplicate_'.$ii]);
                        ++$i;
                    }
                    if ($i === $itemsTotal) {
                        break;
                    }
                }

                if (\count($set) > 0) {
                    $sets[] = $set;
                }
            }
        } else {
            $sets = [$submittedData];
        }

        return $sets;
    }

    protected function getMaxIntegerFromString(string $inputString): int
    {
        $matches = [];
        preg_match_all('/_duplicate_(\d+)/', $inputString, $matches); // Extract numeric values

        // return [0] if no integer was found
        return max(array_map('intval', !empty($matches[1]) ? $matches[1] : [0]));
    }

    protected function temporaryRegistration(array $arrRegistration, array $formData): CebbRegistrationModel
    {
        // Save original form data as a json encoded string
        $formData = array_map(static fn($value) => \is_string($value) ? mb_convert_encoding($value, 'UTF-8', 'UTF-8') : $value, $formData);
        $arrRegistration['formData'] = serialize($formData);

        $registration = new CebbRegistrationModel();
        $registration->setRow($arrRegistration);

        return $registration->save();
    }
}
