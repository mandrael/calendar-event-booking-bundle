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

namespace Markocupic\CalendarEventBookingBundle\EventListener\PostBooking;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Markocupic\CalendarEventBookingBundle\Event\PostBookingEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PostBookingEvent::class, priority: 1100)]
final class ContaoLog
{
    private readonly Adapter $stringUtilAdapter;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
    }

    public function __invoke(PostBookingEvent $event): void
    {
        $registrations = $event->getEventRegistrations();

        foreach ($registrations as $registration) {
            $strText = sprintf(
                'New event registration with ID %d for event with ID %d (%s).',
                $registration->id,
                $event->getEventConfig()->getModel()->id,
                $this->stringUtilAdapter->revertInputEncoding($event->getEventConfig()->getModel()->title),
            );

            $this->contaoGeneralLogger?->info($strText);
        }
    }
}
