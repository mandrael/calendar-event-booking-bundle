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

namespace Markocupic\CalendarEventBookingBundle\Util;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Markocupic\CalendarEventBookingBundle\Model\CebbCartModel;
use Markocupic\CalendarEventBookingBundle\Storage\SessionStorage;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

class CartUtil
{
    private Adapter $cartModelAdapter;

    private Adapter $stringUtilAdapter;

    public function __construct(
        private readonly CheckoutUtil $checkoutUtil,
        private readonly ContaoFramework $framework,
    ) {
        $this->cartModelAdapter = $this->framework->getAdapter(CebbCartModel::class);
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
    }

    public function hasRegistrations(Request $request): bool
    {
        return !empty($this->getRegistrations($request));
    }

    public function countRegistrations(Request $request): int
    {
        return \count($this->getRegistrations($request));
    }

    public function getRegistrations(Request $request): array
    {
        if (!$this->hasCart($request)) {
            return [];
        }

        $cart = $this->getCart($request);

        return $this->stringUtilAdapter->deserialize($cart->registrations, true);
    }

    public function hasCart(Request $request): bool
    {
        $storage = new SessionStorage($request);

        $bag = $storage->getData();

        if (empty($bag['cart_id']) || null === $this->cartModelAdapter->findById($bag['cart_id'])) {
            return false;
        }

        return true;
    }

    public function getCart(Request $request): CebbCartModel
    {
        $storage = new SessionStorage($request);

        $bag = $storage->getData();

        if (!$this->hasCart($request)) {
            // Create a new cart record
            return $this->createCart($request);
        }

        return $this->cartModelAdapter->findById($bag['cart_id']);
    }

    protected function createCart(Request $request): CebbCartModel
    {
        $eventConfig = $this->checkoutUtil->getEventConfig($request);

        $cart = new CebbCartModel();
        $cart->eventId = $eventConfig->get('id');
        $cart->dateAdded = time();
        $cart->tstamp = time();
        $cart->uuid = Uuid::uuid4()->toString();
        $cart->save();

        // Save the cart id to the session
        $storage = new SessionStorage($request);
        $bag = $storage->getData();
        $bag['cart_id'] = $cart->id;
        $storage->storeData($bag);

        return $cart;
    }
}
