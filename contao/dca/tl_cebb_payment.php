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

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_cebb_payment'] = [
    'config'   => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'list'     => [
        'sorting'           => [
            'mode'        => DataContainer::MODE_SORTABLE,
            'fields'      => ['datePayment ASC'],
            'flag'        => DataContainer::SORT_DAY_DESC,
            'panelLayout' => 'filter;sort,search',
        ],
        'label'             => [
            'fields'      => ['uuid', 'number', 'totalAmount', 'currencyCode', 'state'],
            'showColumns' => true,
        ],
        'global_operations' => [
            'all' => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations'        => [
            'edit'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_cebb_payment']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg',
            ],
            'copy'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_cebb_payment']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.svg',
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_cebb_payment']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
            ],
            'show'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_cebb_payment']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{payment_legend},uuid,datePayment,paymentProvider,totalAmount,currencyCode,number,state,details,description',
    ],
    'fields'   => [
        'id'              => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'tstamp'          => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        'datePayment'     => [
            'default'   => time(),
            'eval'      => ['doNotCopy' => true, 'rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 clr wizard'],
            'flag'      => DataContainer::SORT_DAY_DESC,
            'inputType' => 'text',
            'sorting'   => true,
            'sql'       => ['type' => 'integer', 'unsigned' => true, 'notnull' => true, 'default' => 0],
        ],
        'uuid'            => [
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'exclude'   => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'number'          => [
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'exclude'   => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'state'           => [
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'exclude'   => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'totalAmount'     => [
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => "DOUBLE PRECISION DEFAULT 0 NOT NULL",
        ],
        'currencyCode'    => [
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'select',
            'options'   => ['CHF', 'EUR', 'GBP', 'USD'],
            'search'    => true,
            'sorting'   => true,
            'sql'       => "varchar(255) NOT NULL default 'EUR'",
        ],
        'paymentProvider' => [
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'details'         => [
            'eval'      => ['mandatory' => false, 'tl_class' => 'w50 clr', 'useRawRequestData' => true],
            'exclude'   => true,
            'inputType' => 'textarea',
            'search'    => true,
            'sql'       => "mediumtext NULL",
        ],
        'description'     => [
            'eval'      => ['mandatory' => false, 'tl_class' => 'w50', 'useRawRequestData' => true],
            'exclude'   => true,
            'inputType' => 'textarea',
            'search'    => true,
            'sql'       => "mediumtext NULL",
        ],
    ],
];
