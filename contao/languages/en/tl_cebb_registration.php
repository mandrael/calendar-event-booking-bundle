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

// Global operations
$GLOBALS['TL_LANG']['tl_cebb_registration']['new'] = ['New', 'Add a new registration.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['downloadRegistrationList'] = ['Registrations', 'Download registration list.'];

// Operations
$GLOBALS['TL_LANG']['tl_cebb_registration']['edit'] = ['Edit', 'Edit registration with ID %s.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['copy'] = ['Copy', 'Edit registration with ID %s.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['delete'] = ['Delete', 'Delete registration with ID %s.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['show'] = ['Show', 'Show registration with ID %s.'];

// References
$GLOBALS['TL_LANG']['tl_cebb_registration']['salutation_male'] = 'Mr';
$GLOBALS['TL_LANG']['tl_cebb_registration']['salutation_female'] = 'Mrs.';

// Legends
$GLOBALS['TL_LANG']['tl_cebb_registration']['title_legend'] = 'Title';
$GLOBALS['TL_LANG']['tl_cebb_registration']['notes_legend'] = 'Notes';
$GLOBALS['TL_LANG']['tl_cebb_registration']['personal_legend'] = 'Personal legend';
$GLOBALS['TL_LANG']['tl_cebb_registration']['address_legend'] = 'Address';
$GLOBALS['TL_LANG']['tl_cebb_registration']['contact_legend'] = 'Contact';
$GLOBALS['TL_LANG']['tl_cebb_registration']['escort_legend'] = 'Escort';
$GLOBALS['TL_LANG']['tl_cebb_registration']['quantity_legend'] = 'Quantity';

// Fields
$GLOBALS['TL_LANG']['tl_cebb_registration']['checkoutCompleted'] = ['Checkout completed', 'Indicates whether the checkout process has already been completed or not.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['dateAdded'] = ['Booking date', 'Please enter the booking date.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['notes'] = ['Notes', 'Please enter the notes.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['firstname'] = ['Firstname', 'Please enter the firstname.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['lastname'] = ['Lastname', 'Please enter the lastname.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['gender'] = ['Gender', 'Please enter the gender.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['dateOfBirth'] = ['Date of birth', 'Please enter the date of birth.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['street'] = ['Street', 'Please enter the street.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['postal'] = ['Postal code', 'Please enter the postal code.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['city'] = ['City', 'Please enter the postal code.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['phone'] = ['Phone', 'Please enter the phone number.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['email'] = ['Email address', 'Please enter the email address.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['escorts'] = ['Escorts', 'Please enter the escorts.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['bookingType'] = ['Booking type', 'Select the booking type please.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['uuid'] = ['Registration UUID', 'The registration uuid can be used to identify the registration.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['bookingState'] = ['Booking state', 'Select the booking state.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['quantity'] = ['Tickets booked', 'Enter the number of tickets booked.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['unsubscribedOn'] = ['Cancellation date', 'Enter the cancelation date.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['formData'] = ['Form data', 'Enter the form data.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['orderUuid'] = ['Order uuid', 'Enter the order uuid.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['cartUuid'] = ['Cart uuid', 'Enter the cart uuid.'];
$GLOBALS['TL_LANG']['tl_cebb_registration']['formId'] = ['Contao form ID', 'Enter the Contao form ID.'];
