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

use Markocupic\CalendarEventBookingBundle\Controller\FrontendModule\EventBookingCheckoutController;
use Markocupic\CalendarEventBookingBundle\Controller\FrontendModule\EventBookingListController;
use Markocupic\CalendarEventBookingBundle\Controller\FrontendModule\EventUnsubscribeController;

// Frontend modules
$GLOBALS['TL_LANG']['FMD'][EventBookingCheckoutController::TYPE] = ['Event booking form'];
$GLOBALS['TL_LANG']['FMD'][EventBookingListController::TYPE] = ['Event member listing'];
$GLOBALS['TL_LANG']['FMD'][EventUnsubscribeController::TYPE] = ['Event unsubscription form'];
