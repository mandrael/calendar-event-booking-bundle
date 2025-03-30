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

final class PrioritizedServiceRegistry implements PrioritizedServiceRegistryInterface
{
    private PriorityMap $priorityMap;

    public function __construct(
        private string $interface,
        private string $context = 'service',
    ) {
        $this->priorityMap = new PriorityMap();
        $this->priorityMap->setDescOrder();
    }

    public function all(): array
    {
        return $this->priorityMap->toArray();
    }

    public function register(string $identifier, int $priority, object $service): void
    {
        if ($this->has($identifier)) {
            throw new ExistingServiceException($this->context, $identifier);
        }

        if (!\in_array($this->interface, class_implements($service), true)) {
            throw new \InvalidArgumentException(sprintf('%s needs to implement "%s", "%s" given.', ucfirst($this->context), $this->interface, $service::class));
        }
        $this->priorityMap->set($identifier, $service, $priority);
    }

    public function unregister(string $identifier): void
    {
        if (!$this->has($identifier)) {
            throw new NonExistingServiceException($this->context, $identifier, $this->priorityMap->getKeys());
        }

        $this->priorityMap->remove($identifier);
    }

    public function has(string $identifier): bool
    {
        return $this->priorityMap->has($identifier);
    }

    public function get(string $identifier): object
    {
        if (!$this->has($identifier)) {
            throw new NonExistingServiceException($this->context, $identifier, $this->priorityMap->getKeys());
        }

        return $this->priorityMap->get($identifier);
    }

    public function getNextTo(string $identifier): object|null
    {
        $keys = $this->priorityMap->getKeys();
        $nextIndex = -1;

        foreach ($keys as $index => $key) {
            if ($key === $identifier) {
                $nextIndex = $index + 1;

                break;
            }
        }

        if (\count($keys) > $nextIndex) {
            return $this->get($keys[$nextIndex]);
        }

        return null;
    }

    public function hasNextTo(string $identifier): bool
    {
        $keys = $this->priorityMap->getKeys();
        $nextIndex = -1;

        foreach ($keys as $index => $key) {
            if ($key === $identifier) {
                $nextIndex = $index + 1;

                break;
            }
        }

        if (!isset($keys[$nextIndex])) {
            return false;
        }

        return $this->has($keys[$nextIndex]);
    }

    public function getPreviousTo(string $identifier): object|null
    {
        $keys = $this->priorityMap->getKeys();
        $prevIndex = $this->getPreviousIndex($identifier);

        if ($prevIndex >= 0) {
            return $this->get($keys[$prevIndex]);
        }

        return null;
    }

    public function hasPreviousTo(string $identifier): bool
    {
        $prevIndex = $this->getPreviousIndex($identifier);

        return $prevIndex >= 0;
    }

    public function getAllPreviousTo(string $identifier): array
    {
        $keys = $this->priorityMap->getKeys();

        $prevIndex = $this->getPreviousIndex($identifier);

        if ($prevIndex >= 0) {
            $previousElements = [];

            for ($i = $prevIndex; $i >= 0; --$i) {
                $previousElements[] = $this->get($keys[$i]);
            }

            return $previousElements;
        }

        return [];
    }

    public function getIndex($identifier): int
    {
        $keys = $this->priorityMap->getKeys();

        return array_search($identifier, $keys, true);
    }

    private function getPreviousIndex(string $identifier): int
    {
        $keys = $this->priorityMap->getKeys();
        $prevIndex = -1;

        foreach ($keys as $index => $key) {
            if ($key === $identifier) {
                $prevIndex = $index - 1;

                break;
            }
        }

        return $prevIndex >= 0 ? $prevIndex : -1;
    }
}
