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

class PriorityMap implements \Iterator, \Countable
{
    public const ORDER_ASC = 'asc';

    public const ORDER_DESC = 'desc';

    private int $lastSequence = 0;

    private array $list = [];

    private string $order = self::ORDER_ASC;

    /**
     * Add new item to map.
     */
    public function set(string $key, mixed $value, int $priority = 0): \stdClass
    {
        $key = $this->getScalarKey($key);
        $this->list[$key] = new \stdClass();
        $this->list[$key]->value = $value;
        $this->list[$key]->priority = $priority;
        $this->list[$key]->sequence = $this->lastSequence++;

        return $this->list[$key];
    }

    /**
     * Get item from map.
     */
    public function get(string $key): mixed
    {
        $key = $this->getScalarKey($key);

        return isset($this->list[$key]) ? $this->list[$key]->value : null;
    }

    /**
     * Check if item exists in map.
     */
    public function has(string $key): bool
    {
        $key = $this->getScalarKey($key);

        return isset($this->list[$key]);
    }

    /**
     * Remove item from map.
     */
    public function remove(string $key): void
    {
        $key = $this->getScalarKey($key);

        if (isset($this->list[$key])) {
            unset($this->list[$key]);
        }
    }

    /**
     * Get list of keys.
     *
     * @return array<int, int|string>
     */
    public function getKeys(): array
    {
        $callback = self::ORDER_ASC === $this->order ? 'ascSortStrategy' : 'descSortStrategy';
        uasort($this->list, [$this, $callback]);

        return array_keys($this->list);
    }

    /**
     * Return number of items in map.
     */
    public function count(): int
    {
        return \count($this->list);
    }

    /**
     * Set ASC direction of sorting.
     */
    public function setAscOrder(): self
    {
        $this->order = self::ORDER_ASC;

        return $this;
    }

    /**
     * Set DESC direction of sorting.
     */
    public function setDescOrder(): self
    {
        $this->order = self::ORDER_DESC;

        return $this;
    }

    /**
     * Reset iterator.
     */
    public function rewind(): void
    {
        uasort($this->list, [$this, $this->order.'SortStrategy']);
        reset($this->list);
    }

    /**
     * Get current item.
     */
    public function current(): mixed
    {
        $item = current($this->list);

        return $item->value;
    }

    /**
     * Get current key.
     */
    public function key(): int|string|null
    {
        return key($this->list);
    }

    /**
     * Move iterator next.
     */
    public function next(): void
    {
        next($this->list);
    }

    /**
     * Check if current key is valid.
     */
    public function valid(): bool
    {
        return null !== $this->key();
    }

    /**
     * Convert map to array.
     */
    public function toArray(): array
    {
        return iterator_to_array($this);
    }

    /**
     * Get scalar key from mixed.
     */
    protected function getScalarKey($key): string
    {
        if (\is_object($key)) {
            return spl_object_hash($key);
        }

        return $key;
    }

    /**
     * ASC sort strategy.
     */
    protected function ascSortStrategy(\stdClass $declaration1, \stdClass $declaration2): int
    {
        if ($declaration1->priority === $declaration2->priority) {
            return $declaration1->sequence < $declaration2->sequence ? 1 : -1;
        }

        return $declaration1->priority > $declaration2->priority ? 1 : -1;
    }

    /**
     * DESC sort strategy.
     */
    protected function descSortStrategy(\stdClass $declaration1, \stdClass $declaration2): int
    {
        if ($declaration1->priority === $declaration2->priority) {
            return $declaration1->sequence < $declaration2->sequence ? 1 : -1;
        }

        return $declaration1->priority < $declaration2->priority ? 1 : -1;
    }
}
