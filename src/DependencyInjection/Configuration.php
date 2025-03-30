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

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const ROOT_KEY = 'markocupic_calendar_event_booking';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT_KEY);

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('checkout_temp_locking_time')
                    ->info('Here you can set how long (in seconds) a booking is blocked if the booking process has not been completed correctly.')
                    ->defaultValue(60 * 60)->cannotBeEmpty()
                ->end()
                ->scalarNode('checkout_step_parameter_name')
                    ->defaultValue('action')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('checkout_manager_factory')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('checkout')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('steps')
                                ->useAttributeAsKey('name')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('step')
                                            ->info('The FQDN to the step service.')
                                            ->example('Markocupic\CalendarEventBookingBundle\Checkout\Step\SubscriptionStep')
                                        ->end()
                                        ->integerNode('priority')
                                            ->info('Set an integer for the step priority. The step with the higher priority is called first.')
                                            ->example('100')
                                            ->defaultValue(0)
                                        ->end()
                                        ->scalarNode('template')
                                            ->info('Set the path for the view.')
                                            ->example('@App/Checkout/Step/customer.html.twig')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->append($this->addMemberListNode())
            ->end()
        ;

        return $treeBuilder;
    }

    private function addMemberListNode(): NodeDefinition
    {
        return (new TreeBuilder('member_list_export'))
            ->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enable_output_conversion')
                    ->defaultFalse()
                ->end()
                ->scalarNode('convert_from')
                    ->defaultValue('UTF-8')
                    ->cannotBeEmpty()
                    ->example('UTF-8')
                ->end()
                ->scalarNode('convert_to')
                    ->defaultValue('ISO-8859-1')
                    ->cannotBeEmpty()
                    ->info('Convert data upon csv export to a specific charset e.g. ISO-8859-1.')
                    ->example('ISO-8859-1')
                ->end()
            ->end()
        ;
    }
}
