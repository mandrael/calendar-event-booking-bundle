<?php

declare(strict_types=1);

/*
 * This file is part of the Calendar Event Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/calendar-event-booking-bundle
 */

namespace Markocupic\CalendarEventBookingBundle\Domain\Unsubscribe;

final class ValidationResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly mixed $value,
        public readonly string|null $message = null,
        public readonly string|null $severity = null,
        public readonly string|null $cssClass = null,
        public readonly array|null $flags = null,
    ) {
    }

    public static function ok(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function fail(string $message, string $severity, string|null $cssClass = null, array|null $flags = null): self
    {
        return new self(false, null, $message, $severity, $cssClass, $flags);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function isError(): bool
    {
        return !$this->ok;
    }
}
