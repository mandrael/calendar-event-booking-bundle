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

namespace Markocupic\CalendarEventBookingBundle\Registry;

interface PrioritizedServiceRegistryInterface
{
    public function all(): array;

    public function register(string $identifier, int $priority, object $service): void;

    public function unregister(string $identifier): void;

    public function has(string $identifier): bool;

    public function get(string $identifier): object;

    public function getPreviousTo(string $identifier): object|null;

    public function hasPreviousTo(string $identifier): bool;

    public function getAllPreviousTo(string $identifier): array;

    public function getNextTo(string $identifier): object|null;

    public function hasNextTo(string $identifier): bool;

    public function getIndex(string $identifier): int;
}
