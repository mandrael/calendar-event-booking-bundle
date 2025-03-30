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

namespace Markocupic\CalendarEventBookingBundle\Storage;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionStorage
{
    public const SESSION_KEY = 'contao.cebb.checkout';

    public const EVENT_ID = 'event_id';

    public const CART_ID = 'cart_id';

    public const ORDER_ID = 'order_id';

    public function __construct(private readonly Request $request)
    {
    }

    public function storeData(array $data): void
    {
        $this->writeToSession($data);
    }

    public function killSession(): void
    {
        $this->writeToSession([]);
    }

    public function getData(): array
    {
        return $this->readFromSession();
    }

    private function writeToSession(array $data): void
    {
        if (null === ($session = $this->getSession())) {
            return;
        }

        $session->set($this->getSessionKey(), $data);
    }

    private function readFromSession(bool $checkPrevious = false): array
    {
        $empty = [];

        if (null === ($session = $this->getSession($checkPrevious))) {
            return $empty;
        }

        return $session->get($this->getSessionKey(), $empty);
    }

    private function getSessionKey(): string
    {
        return self::SESSION_KEY;
    }

    private function getSession(bool $checkPrevious = false): SessionInterface|null
    {
        if ($checkPrevious && !$this->request->hasPreviousSession()) {
            return null;
        }

        if (!$this->request->hasSession()) {
            return null;
        }

        return $this->request->getSession();
    }
}
