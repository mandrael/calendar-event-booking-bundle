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

namespace Markocupic\CalendarEventBookingBundle\Controller\FrontendModule;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Exception;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventConfig;
use Markocupic\CalendarEventBookingBundle\EventBooking\Config\EventFactory;
use Markocupic\CalendarEventBookingBundle\EventBooking\EventRegistration\EventRegistration;
use Markocupic\CalendarEventBookingBundle\EventBooking\Template\AddTemplateData;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(EventBookingListController::TYPE, category: 'events', template: 'mod_event_booking_list')]
class EventBookingListController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_booking_list';

    public CalendarEventsModel|null $objEvent = null;

    public function __construct(
        private readonly AddTemplateData $addTemplateData,
        private readonly ContaoFramework $framework,
        private readonly EventFactory $eventFactory,
        private readonly EventRegistration $eventRegistration,
        private readonly ScopeMatcher $scopeMatcher,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        // Is frontend
        if ($page instanceof PageModel && $this->scopeMatcher->isFrontendRequest($request)) {
            $showEmpty = true;

            $this->objEvent = EventConfig::getEventFromRequest();

            // Get the event configuration
            $eventConfig = $this->eventFactory->create($this->objEvent);

            // Get the current event && return empty string if enableBookingForm isn't set or
            // event is not published
            if (null !== $this->objEvent) {
                if ($eventConfig->get('enableBookingForm') && $eventConfig->get('published')) {
                    $showEmpty = false;
                }
            }

            if ($showEmpty) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @throws Exception
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Load language
        $this->framework->getAdapter(System::class)->loadLanguageFile($this->eventRegistration->getTable());

        // Get the event configuration
        $eventConfig = $this->eventFactory->create($this->objEvent);

        $arrAllowedStates = StringUtil::deserialize($model->cebb_memberListAllowedBookingStates, true);
        $arrOptions = [
            'order' => 'dateAdded ASC, firstname ASC, city ASC',
        ];

        $registrations = $eventConfig->getRegistrations($arrAllowedStates, true, $arrOptions);

        if (empty($registrations)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $i = 0;
        $intRegCount = \count($registrations);
        $rows = [];

        foreach ($registrations as $registration) {
            $rows[] = [
                'model' => $registration,
                'row_class' => $this->getRowClass($i, $intRegCount),
            ];

            ++$i;
        }

        $template->set('rows', $rows);
        $template->set('eventConfig', $eventConfig);

        // Augment template with more data
        $template->setData(array_merge($template->getData(), $this->addTemplateData->getTemplateData($eventConfig)));

        return $template->getResponse();
    }

    protected function getRowClass(int $i, int $intRowsTotal): string
    {
        $rowFirst = 0 === $i ? ' row_first' : '';
        $rowLast = $i === $intRowsTotal - 1 ? ' row_last' : '';
        $evenOrOdd = $i % 2 ? ' odd' : ' even';

        return sprintf('row_%s%s%s%s', $i, $rowFirst, $rowLast, $evenOrOdd);
    }
}
