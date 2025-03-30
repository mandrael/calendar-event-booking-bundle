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

namespace Markocupic\CalendarEventBookingBundle\EventListener\BookingFormSubmit;

use Codefog\HasteBundle\Form\Form;
use Codefog\HasteBundle\Util\ArrayPosition;
use Doctrine\DBAL\Connection;
use Markocupic\CalendarEventBookingBundle\Event\CreateFinalizeStepFormEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: CreateFinalizeStepFormEvent::class, priority: 100)]
final class CreateFinalizeFormTestListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(CreateFinalizeStepFormEvent $event): void
    {
        return;
        $form = $event->getForm();

        $form = $form->addFormField('test', ['inputType' => 'text', 'label' => 'Test'], ArrayPosition::first());

        $form->addValidator('test', fn () => $this->test($form));
    }

    private function test(Form $form): bool
    {
        $ff = $form->getWidget('test');

        $ff->addError('Error message! Errormessage');

        return false;
    }
}
