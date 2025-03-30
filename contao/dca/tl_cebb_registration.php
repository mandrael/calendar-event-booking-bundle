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

use Contao\DC_Table;
use Contao\DataContainer;
use Markocupic\CalendarEventBookingBundle\EventBooking\Booking\BookingState;
use Markocupic\CalendarEventBookingBundle\EventBooking\Booking\BookingType;
use Ramsey\Uuid\Uuid;

$GLOBALS['TL_DCA']['tl_cebb_registration'] = [
    'config'   => [
        'dataContainer'    => DC_Table::class,
        'ptable'           => 'tl_calendar_events',
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id'        => 'primary',
                'email,pid' => 'index',
            ],
        ],
    ],
    'list'     => [
        'sorting'           => [
            'mode'        => DataContainer::MODE_SORTABLE,
            'fields'      => ['dateAdded ASC'],
            'flag'        => DataContainer::SORT_DAY_DESC,
            'panelLayout' => 'filter;sort,search',
        ],
        'label'             => [
            'fields'      => ['checkoutCompleted', 'dateAdded', 'bookingState', 'firstname', 'lastname', 'street', 'city', 'email'],
            'showColumns' => true,
        ],
        'global_operations' => [
            'all'                      => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'downloadRegistrationList' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_cebb_registration']['downloadRegistrationList'],
                'href'       => 'action=downloadRegistrationList',
                'class'      => 'download_booking_list',
                'icon'       => 'bundles/markocupiccalendareventbooking/icons/excel.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations'        => [
            'edit'    => [
                'label' => &$GLOBALS['TL_LANG']['tl_cebb_registration']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg',
            ],
            'copy'    => [
                'label' => &$GLOBALS['TL_LANG']['tl_cebb_registration']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.svg',
            ],
            'delete'  => [
                'label'      => &$GLOBALS['TL_LANG']['tl_cebb_registration']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
            ],
            'show'    => [
                'label' => &$GLOBALS['TL_LANG']['tl_cebb_registration']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ],
            'order'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_cebb_registration']['order'],
                'href'  => 'act=edit',
                'icon'  => 'bundles/markocupiccalendareventbooking/icons/order.svg',
            ],
            'payment' => [
                'label' => &$GLOBALS['TL_LANG']['tl_cebb_registration']['payment'],
                'href'  => 'act=edit',
                'icon'  => 'bundles/markocupiccalendareventbooking/icons/payment.svg',
            ],
            'cart'    => [
                'label' => &$GLOBALS['TL_LANG']['tl_cebb_registration']['cart'],
                'href'  => 'act=edit',
                'icon'  => 'bundles/markocupiccalendareventbooking/icons/cart.svg',
            ],
        ],
    ],
    'palettes' => [
        'default' => '
        {title_legend},dateAdded,bookingState;
        {notes_legend},notes;{personal_legend},firstname,lastname,gender,dateOfBirth;
        {address_legend:hide},street,postal,city;
        {contact_legend},phone,email;
        {quantity_legend},quantity;
        {escort_legend},escorts
        ',
    ],
    'fields'   => [
        'id'                => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'pid'               => [
            'eval'       => ['readonly' => true],
            'foreignKey' => 'tl_calendar_events.title',
            'sql'        => "int(10) unsigned NOT NULL default 0",
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp'            => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        'formId'            => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        'dateAdded'         => [
            'exclude'   => true,
            'default'   => time(),
            'eval'      => ['doNotCopy' => true, 'rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'flag'      => DataContainer::SORT_DAY_DESC,
            'inputType' => 'text',
            'sorting'   => true,
            'sql'       => ['type' => 'integer', 'unsigned' => true, 'notnull' => true, 'default' => 0],
        ],
        'bookingType'       => [
            'exclude'   => true,
            'eval'      => ['includeBlankOption' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'select',
            'options'   => [BookingType::TYPE_GUEST, BookingType::TYPE_MEMBER, BookingType::TYPE_MANUALLY],
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => BookingType::TYPE_MANUALLY],
        ],
        'uuid'              => [
            'exclude'   => true,
            'eval'      => ['doNotCopy' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => ''],
        ],
        'cartUuid'          => [
            'exclude'   => true,
            'eval'      => ['doNotCopy' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => ''],
        ],
        'orderUuid'         => [
            'exclude'   => true,
            'eval'      => ['doNotCopy' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => ''],
        ],
        'bookingState'      => [
            'exclude'   => true,
            'eval'      => ['tl_class' => 'w50', 'mandatory' => true],
            'filter'    => true,
            'inputType' => 'select',
            'options'   => BookingState::ALL,
            'reference' => &$GLOBALS['TL_LANG']['MSC'],
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 64, 'notnull' => true, 'default' => BookingState::STATE_CONFIRMED],
        ],
        'notes'             => [
            'default'   => null,
            'eval'      => ['tl_class' => 'clr', 'mandatory' => false],
            'exclude'   => true,
            'inputType' => 'textarea',
            'search'    => true,
            'sql'       => 'text NULL',
        ],
        'firstname'         => [
            'exclude'   => true,
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => ''],
        ],
        'lastname'          => [
            'exclude'   => true,
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => ''],
        ],
        'gender'            => [
            'exclude'   => true,
            'eval'      => ['includeBlankOption' => true, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'select',
            'options'   => ['male', 'female', 'other'],
            'reference' => &$GLOBALS['TL_LANG']['MSC'],
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 32, 'notnull' => true, 'default' => ''],
        ],
        'dateOfBirth'       => [
            'exclude'   => true,
            'eval'      => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 11, 'notnull' => true, 'default' => ''],
        ],
        'street'            => [
            'exclude'   => true,
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => ''],
        ],
        'postal'            => [
            'exclude'   => true,
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 32, 'notnull' => true, 'default' => ''],
        ],
        'city'              => [
            'exclude'   => true,
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => ''],
        ],
        'phone'             => [
            'exclude'   => true,
            'eval'      => ['maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 64, 'notnull' => true, 'default' => ''],
        ],
        'email'             => [
            'exclude'   => true,
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'rgxp' => 'email', 'decodeEntities' => true, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => ''],
        ],
        'quantity'          => [
            'exclude'   => true,
            'eval'      => ['maxlength' => 4, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'integer', 'unsigned' => true, 'notnull' => true, 'default' => 1],
        ],
        'escorts'           => [
            'exclude'   => true,
            'eval'      => ['maxlength' => 3, 'tl_class' => 'w50'],
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => ['type' => 'integer', 'unsigned' => true, 'notnull' => true, 'default' => 0],
        ],
        'formData'          => [
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => ['readonly' => true],
            'sql'       => ['type' => 'blob', 'notnull' => false],
        ],
        'confirmedOn'       => [
            'exclude' => true,
            'filter'  => true,
            'sorting' => true,
            'flag'    => DataContainer::SORT_DAY_DESC,
            'eval'    => ['rgxp' => 'datim'],
            'sql'     => ['type' => 'integer', 'unsigned' => true, 'notnull' => true, 'default' => 0],
        ],
        'unsubscribedOn'    => [
            'exclude' => true,
            'filter'  => true,
            'sorting' => true,
            'flag'    => DataContainer::SORT_DAY_DESC,
            'eval'    => ['rgxp' => 'datim'],
            'sql'     => ['type' => 'integer', 'unsigned' => true, 'notnull' => true, 'default' => 0],
        ],
        'checkoutCompleted' => [
            'exclude'   => true,
            'filter'    => true,
            'sorting'   => true,
            'inputType' => 'checkbox',
            'sql'       => ['type' => 'boolean', 'default' => true],
        ],
    ],
];
