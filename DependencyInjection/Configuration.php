<?php

namespace Ruudk\Payment\MollieBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ruudk_payment_mollie');

        $methods = array('ideal');

        $rootNode
            ->children()
                ->scalarNode('partner_id')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('profile_key')
                    ->defaultNull()
                ->end()
                ->booleanNode('test')
                    ->defaultTrue()
                ->end()
                ->scalarNode('report_url')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('logger')
                    ->defaultTrue()
                ->end()
            ->end()

            ->fixXmlConfig('method')
            ->children()
                ->arrayNode('methods')
                    ->defaultValue($methods)
                    ->prototype('scalar')
                        ->validate()
                            ->ifNotInArray($methods)
                            ->thenInvalid('%s is not a valid method.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
