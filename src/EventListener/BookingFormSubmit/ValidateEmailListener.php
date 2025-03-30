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
use Doctrine\DBAL\Types\Types;
use Markocupic\CalendarEventBookingBundle\Event\BookingFormSubmitEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: BookingFormSubmitEvent::class, priority: 100)]
final class ValidateEmailListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(BookingFormSubmitEvent $event): void
    {
        if (!$this->tableAndColumnsExist('tl_cebb_registration', ['email'])) {
            return;
        }

        $arrRegs = $event->getNewRegistrations();

        // Normalize email
        foreach ($arrRegs as $index => $arrReg) {
            if (empty($arrReg['email'])) {
                continue;
            }

            $arrRegs[$index]['email'] = strtolower(trim($arrReg['email']));
        }

        // Append changes made to the event
        $event->setNewRegistrations($arrRegs);

        $eventConfig = $event->getEventConfig();

        if ($eventConfig->get('allowDuplicateEmail')) {
            return;
        }

        foreach ($arrRegs as $arrReg) {
            if (empty($arrReg['email'])) {
                continue;
            }

            // Check if there is already a registration with the given email address.
            $result = $this->connection->fetchOne(
                'SELECT id FROM tl_cebb_registration WHERE pid = :pid AND email = :email',
                [
                    'pid' => $eventConfig->getModel()->id,
                    'email' => $arrReg['email'],
                ],
                [
                    'pid' => Types::INTEGER,
                    'email' => Types::STRING,
                ],
            );

            if (false !== $result) {
                $errorMsg = $this->translator->trans('MSC.you_have_already_subscribed_to_this_event', [$arrReg['email']], 'contao_default');

                $form = $event->getForm();
                $form->addError($errorMsg);

                return;
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
