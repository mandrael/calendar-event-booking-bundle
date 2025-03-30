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

use Markocupic\CalendarEventBookingBundle\EventBooking\Booking\BookingState;
use Markocupic\CalendarEventBookingBundle\Checkout\Step\SubscriptionStep;

// Misc
$GLOBALS['TL_LANG']['MSC']['costs'] = 'Costs';
$GLOBALS['TL_LANG']['MSC']['tickets'] = 'ticket(s)';
$GLOBALS['TL_LANG']['MSC']['bookings'] = 'Bookings';
$GLOBALS['TL_LANG']['MSC']['register'] = 'Register';
$GLOBALS['TL_LANG']['MSC']['your_registrations'] = 'Your registrations';
$GLOBALS['TL_LANG']['MSC']['finalize_registration'] = 'Finalize registration';

// Booking state references
$GLOBALS['TL_LANG']['MSC'][BookingState::STATE_UNDEFINED] = 'Undefined';
$GLOBALS['TL_LANG']['MSC'][BookingState::STATE_NOT_CONFIRMED] = 'Not confirmed';
$GLOBALS['TL_LANG']['MSC'][BookingState::STATE_CONFIRMED] = 'Confirmed';
$GLOBALS['TL_LANG']['MSC'][BookingState::STATE_WAITING_FOR_PAYMENT] = 'Waiting for payment';
$GLOBALS['TL_LANG']['MSC'][BookingState::STATE_UNSUBSCRIBED] = 'Unsubscribed';
$GLOBALS['TL_LANG']['MSC'][BookingState::STATE_REJECTED] = 'Rejected';
$GLOBALS['TL_LANG']['MSC'][BookingState::STATE_WAITING_LIST] = 'Waiting list';

// Form validation
$GLOBALS['TL_LANG']['MSC']['max_quantity_possible'] = 'Only a maximum of %d tickets allowed per registration.';
$GLOBALS['TL_LANG']['MSC']['max_escorts_possible'] = 'Maximum %s escorts per participant possible.';
$GLOBALS['TL_LANG']['MSC']['you_have_already_subscribed_to_this_event'] = 'We have already found a booking with the email address "%s". Booking process aborted.';
$GLOBALS['TL_LANG']['MSC']['enter_positive_integer'] = 'Please enter a positive number.';

// Step navigation
$GLOBALS['TL_LANG']['BTN']['cebb_go_back_lbl'] = 'Go back';
$GLOBALS['TL_LANG']['BTN']['cebb_continue_lbl'] = 'Continue';
$GLOBALS['TL_LANG']['BTN']['cebb_finalize_submit_lbl'] = 'Finalize booking';

// Checkout steps
$GLOBALS['TL_LANG']['CEBB_STEP_LBL']['subscription'] = 'Subscribe';
$GLOBALS['TL_LANG']['CEBB_STEP_LBL']['finalisation'] = 'Finalize';

// Step subscribe
$GLOBALS['TL_LANG']['MSC'][SubscriptionStep::CASE_BOOKING_NOT_YET_POSSIBLE] = 'Booking is only possible from %s.';
$GLOBALS['TL_LANG']['MSC'][SubscriptionStep::CASE_BOOKING_NO_LONGER_POSSIBLE] = 'The registration period for this event has already expired. Registrations can no longer be accepted.';
$GLOBALS['TL_LANG']['MSC'][SubscriptionStep::CASE_EVENT_FULLY_BOOKED] = 'This event is fully booked.';
$GLOBALS['TL_LANG']['MSC'][SubscriptionStep::CASE_WAITING_LIST_POSSIBLE] = 'This event is fully booked. Subscription to the waiting list is possible.';
$GLOBALS['TL_LANG']['MSC'][SubscriptionStep::CASE_BOOKING_POSSIBLE] = 'Free seats available.';
$GLOBALS['TL_LANG']['MSC']['participant_has_been_captured_successfully'] = 'The participant has been successfully captured.';
$GLOBALS['TL_LANG']['MSC']['successfully_placed_on_the_waiting_list'] = 'Successfully placed one record on the waiting list.';
$GLOBALS['TL_LANG']['MSC']['registration_failed_please_check_free_places'] = 'Registration failed. Check whether there are still enough places available for your request.';
$GLOBALS['TL_LANG']['MSC']['registration_failed_please_check_free_places'] = 'Registration failed. Check whether there are still enough places available for your request.';
$GLOBALS['TL_LANG']['MSC']['seats_available'] = 'Seats available';
$GLOBALS['TL_LANG']['MSC']['seats_available_waiting_list'] = 'Seats available on waiting list';

// Post booking messages MSC.post_booking_confirm_cebb_booking_state_confirmed
$GLOBALS['TL_LANG']['MSC']['post_booking_confirm_'.BookingState::STATE_NOT_CONFIRMED] = 'Booking process has been successfully completed. Your current booking status: <strong>Waiting for confirmation</strong>';
$GLOBALS['TL_LANG']['MSC']['post_booking_confirm_'.BookingState::STATE_CONFIRMED] = 'Booking process has been successfully completed. Your current booking status: <strong>Booking confirmed</strong>';
$GLOBALS['TL_LANG']['MSC']['post_booking_confirm_'.BookingState::STATE_WAITING_LIST] = 'Booking process has been successfully completed. Your current booking status: <strong>On waiting list</strong>';
$GLOBALS['TL_LANG']['MSC']['post_booking_confirm_'.BookingState::STATE_WAITING_FOR_PAYMENT] = 'Booking process has been successfully completed. Your current booking status: <strong>Waiting for payment</strong>';
$GLOBALS['TL_LANG']['MSC']['post_booking_confirm_'.BookingState::STATE_UNDEFINED] = 'Booking process has been successfully completed. Your current booking status: <strong>Undefined</strong>';
$GLOBALS['TL_LANG']['MSC']['post_booking_confirm_'.BookingState::STATE_UNSUBSCRIBED] = 'Booking process has been successfully completed. Your current booking status: <strong>Unsubscribed</strong>';
$GLOBALS['TL_LANG']['MSC']['post_booking_confirm_'.BookingState::STATE_REJECTED] = 'Booking process has been successfully completed. Your current booking status: <strong>Rejected</strong>';

// Form validation backend
$GLOBALS['TL_LANG']['MSC']['adjusted_booking_period_end_time'] = 'The end date for the booking period has been adjusted.';

// Unsubscribe from event
$GLOBALS['TL_LANG']['MSC']['unsubscribe_info'] = 'You\'ve been successfully unsubscribed from event "%s".';
$GLOBALS['TL_LANG']['MSC']['unsubscribe_confirm'] = 'Dear <span class="event-member-name">%s %s</span><br>Are you sure you want to unsubscribe from event "%s"?';
$GLOBALS['TL_LANG']['BTN']['unsubscribe_from_event_submit_lbl'] = 'Unsubscribe from event';
$GLOBALS['TL_LANG']['BTN']['cancel_submit_lbl'] = 'Cancel';

// Errors
$GLOBALS['TL_LANG']['ERR']['unsubscription_limit_expired'] = 'The unsubscription limit for event "%s" has expired.';
$GLOBALS['TL_LANG']['ERR']['event_not_found'] = 'Invalid booking token or could not find assigned event.';
$GLOBALS['TL_LANG']['ERR']['event_unsubscription_not_allowed'] = 'You\'re not allowed to unsubscribe from event "%s".';
$GLOBALS['TL_LANG']['ERR']['invalid_unsubscription_limit'] = 'This unsubscription limit is too far in the future (see event start and end date and time).';
$GLOBALS['TL_LANG']['ERR']['conflicting_unsubscribe_limits'] = 'You cannot indicate both an unsubscription limit in days before the event and fixed limit at the same time. Please set unsubscription limit in days to 0 or delete the fixed limit.';
$GLOBALS['TL_LANG']['ERR']['already_unsubscribed'] = 'You have already been unsubscribed from the event "%s".';
$GLOBALS['TL_LANG']['ERR']['text_booking_request_failed_due_to_unexpected_error'] = 'Your booking request could not be processed due to an unexpected error.';
$GLOBALS['TL_LANG']['ERR']['text_booking_request_failed'] = 'Your booking request could not be processed because there are not enough free places available.';
$GLOBALS['TL_LANG']['ERR']['cebb_checkout_exception::registration_not_found'] = 'We could not found your registration. Registration process aborted.';
