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

        $methods = array('ideal', 'creditcard', 'mistercash', 'paysafecard');

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
