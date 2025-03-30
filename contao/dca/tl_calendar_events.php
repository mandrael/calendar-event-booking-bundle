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

// Table config
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['ctable'][] = 'tl_cebb_registration';

// Palettes
PaletteManipulator::create()
    ->addLegend('costs_legend', 'source_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('booking_options_legend', 'source_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('waiting_list_legend', 'source_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('notification_legend', 'source_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('unsubscribe_legend', 'source_legend', PaletteManipulator::POSITION_BEFORE)
    ->addField(['costs', 'currencyCode'], 'costs_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['enableBookingForm'], 'booking_options_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['enableUnsubscription'], 'unsubscribe_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_calendar_events');

// Selector
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'enableBookingForm';
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'activateWaitingList';
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'enableBookingNotification';
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'enableUnsubscription';
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'enableUnsubscribeNotification';

// Subpalettes
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['enableBookingForm'] = 'minMembers,maxMembers,maxItemsPerCart,maxQuantityPerRegistration,maxEscortsPerMember,bookingStartDate,bookingEndDate,allowDuplicateEmail,bookingState,activateWaitingList,enableBookingNotification';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['activateWaitingList'] = 'waitingListLimit';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['enableBookingNotification'] = 'eventBookingNotification,eventBookingNotificationSender';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['enableUnsubscription'] = 'unsubscribeLimit,unsubscribeLimitTstamp,enableUnsubscribeNotification';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['enableUnsubscribeNotification'] = 'eventUnsubscribeNotification,eventUnsubscribeNotificationSender';

// Operations
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['registrations'] = [
    'href'  => 'do=calendar&table=tl_cebb_registration',
    'icon'  => 'bundles/markocupiccalendareventbooking/icons/group.svg',
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['registrations'],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['enableBookingForm'] = [
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr m12'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'sql'       => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['allowDuplicateEmail'] = [
    'eval'      => ['tl_class' => 'clr m12'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'sql'       => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['bookingState'] = [
    'eval'      => ['tl_class' => 'w50', 'mandatory' => true],
    'filter'    => true,
    'inputType' => 'select',
    'options'   => BookingState::ALL,
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'search'    => true,
    'sorting'   => true,
    'sql'       => "varchar(64) NOT NULL default '".BookingState::STATE_CONFIRMED."'",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['bookingStartDate'] = [
    'eval'      => ['rgxp' => 'datim', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => true, 'tl_class' => 'clr w50 wizard'],
    'exclude'   => true,
    'inputType' => 'text',
    'sorting'   => true,
    'sql'       => 'int(10) unsigned NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['bookingEndDate'] = [
    'eval'      => ['rgxp' => 'datim', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'exclude'   => true,
    'inputType' => 'text',
    'sorting'   => true,
    'sql'       => 'int(10) unsigned NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['minMembers'] = [
    'eval'      => ['tl_class' => 'w50', 'rgxp' => 'digit', 'mandatory' => true],
    'exclude'   => true,
    'inputType' => 'text',
    'search'    => true,
    'sorting'   => true,
    'sql'       => "smallint(5) unsigned NOT NULL default 0",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['maxMembers'] = [
    'eval'      => ['tl_class' => 'w50', 'rgxp' => 'digit', 'mandatory' => true],
    'exclude'   => true,
    'inputType' => 'text',
    'search'    => true,
    'sorting'   => true,
    'sql'       => "smallint(5) unsigned NOT NULL default 0",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['maxEscortsPerMember'] = [
    'eval'      => ['tl_class' => 'w50', 'rgxp' => 'digit', 'mandatory' => true],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => range(0, 100),
    'search'    => true,
    'sorting'   => true,
    'sql'       => "smallint(5) unsigned NOT NULL default 0",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['maxItemsPerCart'] = [
    'eval'      => ['tl_class' => 'w50', 'rgxp' => 'digit', 'mandatory' => true],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => range(1, 100),
    'search'    => true,
    'sorting'   => true,
    'sql'       => "smallint(5) unsigned NOT NULL default 1",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['maxQuantityPerRegistration'] = [
    'eval'      => ['tl_class' => 'w50', 'rgxp' => 'digit', 'mandatory' => true],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => range(1, 100),
    'search'    => true,
    'sorting'   => true,
    'sql'       => "smallint(5) unsigned NOT NULL default 1",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['activateWaitingList'] = [
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'sql'       => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['waitingListLimit'] = [
    'eval'      => ['rgxp' => 'digit', 'tl_class' => 'clr w50'],
    'exclude'   => true,
    'inputType' => 'text',
    'sql'       => "smallint(3) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['enableBookingNotification'] = [
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr m12'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'sql'       => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventBookingNotification'] = [
    'eval'       => ['mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'multiple' => true, 'tl_class' => 'clr w50'],
    'exclude'    => true,
    'foreignKey' => 'tl_nc_notification.title',
    'inputType'  => 'select',
    'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
    'search'     => true,
    'sql'        => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventBookingNotificationSender'] = [
    'eval'       => ['mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'exclude'    => true,
    'foreignKey' => 'tl_user.name',
    'inputType'  => 'select',
    'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
    'search'     => true,
    'sql'        => "int(10) unsigned NOT NULL default 0",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['enableUnsubscription'] = [
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr m12'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'sql'       => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['unsubscribeLimit'] = [
    'eval'      => ['rgxp' => 'natural', 'nospace' => true, 'tl_class' => 'w50'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'select',
    'options'   => range(0, 720),
    'sorting'   => true,
    'sql'       => "int(10) unsigned NOT NULL default 0",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['unsubscribeLimitTstamp'] = [
    'default'   => null,
    'eval'      => ['rgxp' => 'datim', 'doNotCopy' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'exclude'   => true,
    'inputType' => 'text',
    'sorting'   => true,
    'sql'       => 'int(10) unsigned NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['enableUnsubscribeNotification'] = [
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr m12'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'sql'       => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventUnsubscribeNotification'] = [
    'eval'       => ['mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'multiple' => true, 'tl_class' => 'clr w50'],
    'exclude'    => true,
    'foreignKey' => 'tl_nc_notification.title',
    'inputType'  => 'select',
    'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
    'search'     => true,
    'sql'        => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventUnsubscribeNotificationSender'] = [
    'eval'       => ['mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'exclude'    => true,
    'foreignKey' => 'tl_user.name',
    'inputType'  => 'select',
    'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
    'search'     => true,
    'sql'        => "int(10) unsigned NOT NULL default 0",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['costs'] = [
    'default'   => '0.00',
    'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'text',
    'search'    => true,
    'sorting'   => true,
    'sql'       => "DOUBLE PRECISION DEFAULT 0 NOT NULL",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['currencyCode'] = [
    'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'select',
    'options'   => ['CHF', 'EUR', 'GBP', 'USD'],
    'search'    => true,
    'sorting'   => true,
    'sql'       => "varchar(255) NOT NULL default 'EUR'",
];
