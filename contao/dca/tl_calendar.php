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

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Markocupic\CalendarEventBookingBundle\EventBooking\Booking\BookingState;

PaletteManipulator::create()
    ->addField(['eventUnsubscribePage'], 'event_unsubscribe_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['calculateTotalFrom'], 'booking_options_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_calendar');

$GLOBALS['TL_DCA']['tl_calendar']['fields']['eventUnsubscribePage'] = [
    'exclude'    => true,
    'inputType'  => 'pageTree',
    'foreignKey' => 'tl_page.title',
    'eval'       => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['calculateTotalFrom'] = [
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => [BookingState::STATE_WAITING_FOR_PAYMENT, BookingState::STATE_NOT_CONFIRMED, BookingState::STATE_CONFIRMED, BookingState::STATE_UNDEFINED],
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'eval'      => ['mandatory' => true, 'multiple' => true, 'chosen' => true, 'tl_class' => 'clr w50'],
    'sql'       => "varchar(255) NOT NULL default '".serialize([BookingState::STATE_CONFIRMED])."'",
];
