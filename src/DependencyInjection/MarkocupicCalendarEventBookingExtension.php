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

namespace Markocupic\CalendarEventBookingBundle\DependencyInjection;

use Markocupic\CalendarEventBookingBundle\Checkout\CheckoutManagerFactoryInterface;
use Markocupic\CalendarEventBookingBundle\Checkout\DefaultCheckoutManagerFactory;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

class MarkocupicCalendarEventBookingExtension extends Extension
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../config'),
        );

        $loader->load('services.yaml');

        $container->setParameter($this->getAlias().'.checkout_temp_locking_time', $config['checkout_temp_locking_time']);
        $container->setParameter($this->getAlias().'.checkout_step_parameter_name', $config['checkout_step_parameter_name']);
        $container->setParameter($this->getAlias().'.checkout', $config['checkout']);
        $container->setParameter($this->getAlias().'.member_list_export.enable_output_conversion', $config['member_list_export']['enable_output_conversion']);
        $container->setParameter($this->getAlias().'.member_list_export.convert_from', $config['member_list_export']['convert_from']);
        $container->setParameter($this->getAlias().'.member_list_export.convert_to', $config['member_list_export']['convert_to']);

        if (\array_key_exists('checkout', $config)) {
            $this->registerCheckout($container, $config['checkout']);
        }

        if (\array_key_exists('checkout_manager_factory', $config)) {
            $alias = new Alias(sprintf('%s.checkout_manager_services.factory.%s', $this->getAlias(), $config['checkout_manager_factory']), true);
            $alias->setPublic(true);
            $container->setAlias($this->getAlias().'.checkout_manager_services.factory', $alias);
            $container->setAlias(CheckoutManagerFactoryInterface::class, $alias);
        } else {
            throw new \InvalidArgumentException('No valid Checkout Manager has been configured!');
        }
    }

    public function getAlias(): string
    {
        return Configuration::ROOT_KEY;
    }

    private function registerCheckout(ContainerBuilder $container, array $config): void
    {
        $availableCheckoutManagerFactories = [];

        foreach ($config as $checkoutIdentifier => $typeConfiguration) {
            $stepsLocatorId = sprintf('%s.checkout_manager_services.steps.%s', $this->getAlias(), $checkoutIdentifier);
            $checkoutManagerFactoryId = sprintf('%s.checkout_manager_services.factory.%s', $this->getAlias(), $checkoutIdentifier);

            $services = [];
            $priorityMap = [];
            $configMap = [];

            foreach ($typeConfiguration['steps'] as $identifier => $step) {
                $services[$identifier] = new Reference($step['step']);
                $priorityMap[$identifier] = $step['priority'];
                $configMap[$identifier] = $step;
            }

            $stepsLocator = new Definition(ServiceLocator::class, [$services]);
            $stepsLocator->addTag('container.service_locator');
            $container->setDefinition($stepsLocatorId, $stepsLocator);

            $checkoutManagerFactory = new Definition(DefaultCheckoutManagerFactory::class, [
                new Reference($stepsLocatorId),
                $priorityMap,
                $configMap,
            ]);

            $checkoutManagerFactory->addTag('markocupic_calendar_event_booking.checkout_manager_factory', ['key' => $checkoutIdentifier]);

            $container->setDefinition($checkoutManagerFactoryId, $checkoutManagerFactory);

            $availableCheckoutManagerFactories[] = $checkoutManagerFactoryId;
        }

        $container->setParameter($this->getAlias().'.checkout_managers', $availableCheckoutManagerFactories);
    }
}
