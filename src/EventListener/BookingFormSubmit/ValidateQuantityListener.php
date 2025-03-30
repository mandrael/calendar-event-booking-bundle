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

use Contao\Validator;
use Doctrine\DBAL\Connection;
use Markocupic\CalendarEventBookingBundle\Event\BookingFormSubmitEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: BookingFormSubmitEvent::class, priority: 100)]
final class ValidateQuantityListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(BookingFormSubmitEvent $event): void
    {
        if (!$this->tableAndColumnsExist('tl_cebb_registration', ['quantity'])) {
            return;
        }

        $arrRegs = $event->getNewRegistrations();

        $eventConfig = $event->getEventConfig();

        foreach ($arrRegs as $arrReg) {
            if (empty($arrReg['quantity'])) {
                throw new \RuntimeException('Quantity is empty and must be set.');
            }

            if (!Validator::isNatural($arrReg['quantity']) || (int) $arrReg['quantity'] < 1) {
                $form = $event->getForm();
                $errorMsg = $this->translator->trans('MSC.enter_positive_integer', [], 'contao_default');

                $form->addError($errorMsg);

                return;
            }

            if ((int) $arrReg['quantity'] > (int) $eventConfig->get('maxQuantityPerRegistration')) {
                $errorMsg = $this->translator->trans('MSC.max_quantity_possible', [$eventConfig->get('maxQuantityPerRegistration')], 'contao_default');
                $form = $event->getForm();

                $form->addError($errorMsg);
            }
        }
    }

    protected function tableAndColumnsExist(string $table, array $columns): bool
    {
        $columns = array_map('strtolower', $columns);

        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$table])) {
            return false;
        }

        $tableColumnsAll = $schemaManager->listTableColumns($table);

        foreach ($columns as $column) {
            if (!isset($tableColumnsAll[$column])) {
                return false;
            }
        }

        return true;
    }
}
