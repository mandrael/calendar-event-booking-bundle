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

namespace Markocupic\CalendarEventBookingBundle\Event;

use Markocupic\CalendarEventBookingBundle\Checkout\Step\CheckoutStepInterface;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Markocupic\CalendarEventBookingBundle\Model\CebbCartModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

class CheckoutEvent extends Event
{
    public const TYPE_ERROR = 'error';

    public const TYPE_INFO = 'info';

    public const TYPE_CONFIRMATION = 'confirmation';

    private string $messageType = '';

    private string $message = '';

    private array $messageParameters = [];

    private int $errorCode = 500;

    private Response|null $response = null;

    public function __construct(
        private readonly Request $request,
        private readonly EventConfig $eventConfig,
        private readonly CheckoutStepInterface $step,
        private readonly array $dataForStep,
        private readonly CebbCartModel|null $cart,
    ) {
    }

    public function getEventConfig(): EventConfig
    {
        return $this->eventConfig;
    }

    public function getCart(): CebbCartModel|null
    {
        return $this->cart;
    }

    public function getStep(): CheckoutStepInterface
    {
        return $this->step;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getDataForStep(): array
    {
        return $this->dataForStep;
    }

    public function stop(string $message, string $type = self::TYPE_ERROR, array $parameters = [], int $errorCode = 500): void
    {
        $this->messageType = $type;
        $this->message = $message;
        $this->messageParameters = $parameters;
        $this->errorCode = $errorCode;
        $this->stopPropagation();
    }

    public function isStopped(): bool
    {
        return $this->isPropagationStopped();
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }

    public function setMessageType(string $messageType): void
    {
        $this->messageType = $messageType;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function setErrorCode(int $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    public function hasResponse(): bool
    {
        return null !== $this->response;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
