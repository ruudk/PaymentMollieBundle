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

        $rootNode
            ->children()
                ->scalarNode('api_key')
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
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->prototype('scalar')
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
