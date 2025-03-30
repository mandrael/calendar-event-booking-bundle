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

namespace Markocupic\CalendarEventBookingBundle\DataContainer;

use Codefog\HasteBundle\UrlParser;
use Contao\Backend;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\CalendarEventBookingBundle\Event\BookingStateChangeEvent;
use Markocupic\CalendarEventBookingBundle\Model\CebbRegistrationModel;
use Markocupic\ExportTable\Config\Config;
use Markocupic\ExportTable\Export\ExportTable;
use Markocupic\ExportTable\Writer\ByteSequence;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class CebbRegistration
{
    public const TABLE = 'tl_cebb_registration';

    private Adapter $systemAdapter;

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UrlParser $urlParser,
        private readonly ExportTable $exportTable,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
        $this->systemAdapter = $this->framework->getAdapter(System::class);
    }

    /**
     * Download the registration list as a csv spreadsheet.
     *
     * @throws \Exception
     */
    #[AsCallback(table: self::TABLE, target: 'config.onload')]
    public function downloadRegistrationList(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('downloadRegistrationList' === $request->query->get('action')) {
            $arrSkip = [];
            $arrSelectedFields = [];

            foreach (array_keys($GLOBALS['TL_DCA'][self::TABLE]['fields']) as $k) {
                if (!\in_array($k, $arrSkip, true)) {
                    $arrSelectedFields[] = $k;
                }
            }

            $exportConfig = (new Config(self::TABLE))
                ->setExportType('csv')
                ->setFilter([[self::TABLE.'.pid = ?'], [$request->query->get('id')]])
                ->setFields($arrSelectedFields)
                ->setAddHeadline(true)
                ->setHeadlineFields($arrSelectedFields)
                ->setOutputBom(ByteSequence::BOM['UTF-8'])
            ;

            // Handle output conversion
            if ($this->systemAdapter->getContainer()->getParameter('markocupic_calendar_event_booking.member_list_export.enable_output_conversion')) {
                $convertFrom = $this->systemAdapter->getContainer()->getParameter('markocupic_calendar_event_booking.member_list_export.convert_from');
                $convertTo = $this->systemAdapter->getContainer()->getParameter('markocupic_calendar_event_booking.member_list_export.convert_to');

                if ('utf-8' !== strtolower($convertTo)) {
                    $exportConfig->setOutputBom('');
                }

                $exportConfig->convertEncoding(true, $convertFrom, $convertTo);
            }

            $this->exportTable->run($exportConfig);
        }
    }

    #[AsCallback(table: self::TABLE, target: 'list.label.label')]
    public function addIcon(array $row, string $label, DataContainer $dc, array $labels): array
    {
        $icon = match ($row['checkoutCompleted']) {
            1 => 'bundles/markocupiccalendareventbooking/icons/circle-check-solid.svg',
            0 => 'bundles/markocupiccalendareventbooking/icons/hourglass.svg',
        };

        $labels[0] = sprintf(
            '<div class="checkout_completed"><img src="%s"></div>',
            $icon,
        );

        return $labels;
    }

    /**
     * Trigger the BookingStateChangeEvent.
     *
     * @throws Exception
     */
    #[AsCallback(table: self::TABLE, target: 'fields.bookingState.save')]
    public function triggerBookingStateChangeHook(string $strBookingStateNew, DataContainer $dc): string
    {
        $registration = CebbRegistrationModel::findById($dc->id);

        if (null !== $registration) {
            if ($strBookingStateNew !== $registration->bookingState) {
                $strBookingStateOld = $registration->bookingState;

                $event = new BookingStateChangeEvent($registration, $strBookingStateOld, $strBookingStateNew);
                $this->eventDispatcher->dispatch($event);
            }
        }

        return $strBookingStateNew;
    }

    #[AsCallback(table: self::TABLE, target: 'list.operations.payment.button')]
    public function paymentOperation(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes, string $table, array $rootRecordIds, array|null $childRecordIds, bool $circularReference, string|null $previous, string|null $next, DataContainer $dc): string
    {
        $paymentUuid = $this->connection->fetchOne('SELECT paymentUuid FROM tl_cebb_order WHERE uuid = ?', [$row['orderUuid']]);
        if ($paymentUuid) {
            $paymentId = $this->connection->fetchOne('SELECT id FROM tl_cebb_payment WHERE uuid = ?', [$paymentUuid]);
            https:// 4ae-racing-team.ch/contao?do=calendar&id=116&table=tl_cebb_registration&act=show&popup=1&rt=d36387a317ad5b15dd9ff9c.dhT-apboRTzS4q40Rid7lLpKyrGBterLK7xOAF2XaWk.AFO1DdqPKlq728dgP2kM4f0Vs4jV5IWkfNgBTCXmKyAzdZwu94MEcJSq6w&ref=rqtmDq_S
            if ($paymentId) {
                $href = Backend::addToUrl($href);
                $href = $this->urlParser->addQueryString('table=tl_cebb_payment', $href);
                $href = $this->urlParser->addQueryString('popup=1', $href);
                $href = $this->urlParser->addQueryString('id='.$paymentId, $href);
                // $attributes .= sprintf("
                // onclick=\"Backend.openModalIframe({'title':'%s','url':this.href});return false\"
                // class=\"show\"", $this->translator->trans('MSC.showOnly', [], 'contao_default'));
            }

            return sprintf(
                '<a href="%s" title="%s"%s>%s</a> ',
                $href,
                StringUtil::specialchars($title),
                $attributes,
                Image::getHtml($icon, $label),
            );
        }

        $icon = str_replace('.svg', '_.svg', $icon);

        return sprintf(
            '%s',
            Image::getHtml($icon, $label),
        );
    }

    #[AsCallback(table: self::TABLE, target: 'list.operations.order.button')]
    public function orderOperation(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes, string $table, array $rootRecordIds, array|null $childRecordIds, bool $circularReference, string|null $previous, string|null $next, DataContainer $dc): string
    {
        $orderId = $this->connection->fetchOne('SELECT id FROM tl_cebb_order WHERE uuid = ?', [$row['orderUuid']]);

        if ($orderId) {
            $href = Backend::addToUrl($href);
            $href = $this->urlParser->addQueryString('table=tl_cebb_order', $href);
            $href = $this->urlParser->addQueryString('id='.$orderId, $href);
            // $attributes .= sprintf("
            // onclick=\"Backend.openModalIframe({'title':'%s','url':this.href});return false\"
            // class=\"show\"", $this->translator->trans('MSC.showOnly', [], 'contao_default'));
        }

        return sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            $href,
            StringUtil::specialchars($title),
            $attributes,
            Image::getHtml($icon, $label),
        );

        $icon = str_replace('.svg', '_.svg', $icon);

        return sprintf(
            '%s',
            Image::getHtml($icon, $label),
        );
    }

    #[AsCallback(table: self::TABLE, target: 'list.operations.cart.button')]
    public function cartOperation(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes, string $table, array $rootRecordIds, array|null $childRecordIds, bool $circularReference, string|null $previous, string|null $next, DataContainer $dc): string
    {
        $cartId = $this->connection->fetchOne('SELECT id FROM tl_cebb_cart WHERE uuid = ?', [$row['cartUuid']]);

        if ($cartId) {
            $href = Backend::addToUrl($href);
            $href = $this->urlParser->addQueryString('table=tl_cebb_cart', $href);
            $href = $this->urlParser->addQueryString('id='.$cartId, $href);
            // $attributes .= sprintf("
            // onclick=\"Backend.openModalIframe({'title':'%s','url':this.href});return false\"
            // class=\"show\"", $this->translator->trans('MSC.showOnly', [], 'contao_default'));
        }

        return sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            $href,
            StringUtil::specialchars($title),
            $attributes,
            Image::getHtml($icon, $label),
        );

        $icon = str_replace('.svg', '_.svg', $icon);

        return sprintf(
            '%s',
            Image::getHtml($icon, $label),
        );
    }
}
