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

use Markocupic\CalendarEventBookingBundle\Model\CebbCartModel;
use Markocupic\CalendarEventBookingBundle\Model\CebbOrderModel;
use Markocupic\CalendarEventBookingBundle\Model\CebbPaymentModel;
use Markocupic\CalendarEventBookingBundle\Model\CebbRegistrationModel;

/*
 * Backend modules
 */
$GLOBALS['BE_MOD']['content']['calendar']['tables'][] = 'tl_cebb_registration';
$GLOBALS['BE_MOD']['content']['calendar']['tables'][] = 'tl_cebb_order';
$GLOBALS['BE_MOD']['content']['calendar']['tables'][] = 'tl_cebb_cart';
$GLOBALS['BE_MOD']['content']['calendar']['tables'][] = 'tl_cebb_payment';
/*
 * Contao models
 */
$GLOBALS['TL_MODELS']['tl_cebb_cart'] = CebbCartModel::class;
$GLOBALS['TL_MODELS']['tl_cebb_order'] = CebbOrderModel::class;
$GLOBALS['TL_MODELS']['tl_cebb_payment'] = CebbPaymentModel::class;
$GLOBALS['TL_MODELS']['tl_cebb_registration'] = CebbRegistrationModel::class;
