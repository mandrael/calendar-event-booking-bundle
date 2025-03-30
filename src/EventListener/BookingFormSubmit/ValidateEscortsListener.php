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

use Doctrine\DBAL\Connection;
use Markocupic\CalendarEventBookingBundle\Event\BookingFormSubmitEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: BookingFormSubmitEvent::class, priority: 100)]
final class ValidateEscortsListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(BookingFormSubmitEvent $event): void
    {
        if (!$this->tableAndColumnsExist('tl_cebb_registration', ['escorts'])) {
            return;
        }

        $arrRegs = $event->getNewRegistrations();

        $eventConfig = $event->getEventConfig();

        if ($eventConfig->get('allowDuplicateEmail')) {
            return;
        }

        foreach ($arrRegs as $index => $arrReg) {
            if (empty($arrReg['escorts'])) {
                $arrRegs[$index]['escorts'] = '0';
            }

            if ($arrReg['escorts'] !== (string) (int) $arrReg['escorts']) {
                $form = $event->getForm();
                $errorMsg = $this->translator->trans('MSC.enter_positive_integer', [], 'contao_default');

                $form->addError($errorMsg);

                return;
            }

            if ($eventConfig->get('maxEscortsPerMember') < $arrReg['escorts']) {
                $form = $event->getForm();
                $errorMsg = $this->translator->trans('MSC.max_escorts_possible', [$eventConfig->get('maxEscortsPerMember')], 'contao_default');

                $form->addError($errorMsg);

                return;
            }
        }

        $event->setNewRegistrations($arrRegs);
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
