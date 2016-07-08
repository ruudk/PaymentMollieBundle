<?php

namespace Ruudk\Payment\MollieBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class RuudkPaymentMollieExtension extends Extension
{
    /**
     * @param array            $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $container->setParameter('ruudk_payment_mollie.api_key', $config['api_key']);

        foreach($config['methods'] AS $method) {
            $this->addFormType($config, $container, $method);
        }

        /**
         * When logging is disabled, remove logger and setLogger calls
         */
        if(false === $config['logger']) {
            $container->getDefinition('ruudk_payment_mollie.controller.notification')->removeMethodCall('setLogger');
            $container->getDefinition('ruudk_payment_mollie.plugin.default')->removeMethodCall('setLogger');
            $container->getDefinition('ruudk_payment_mollie.plugin.ideal')->removeMethodCall('setLogger');
            $container->removeDefinition('monolog.logger.ruudk_payment_mollie');
        }
    }

    protected function addFormType(array $config, ContainerBuilder $container, $method)
    {
        $mollieMethod = 'mollie_' . $method;

        $definition = new Definition();
        $definition->setClass(sprintf('%%ruudk_payment_mollie.form.%s_type.class%%', $method));
        $definition->addArgument($mollieMethod);

        if($method === 'ideal') {
            $definition->addArgument(sprintf(
                '%%ruudk_payment_mollie.ideal.issuers.%s%%',
                substr($config['api_key'], 0, 4) == 'live' ? 'live' : 'test'
            ));
        }

        $definition->addTag('payment.method_form_type');
        $definition->addTag('form.type', array(
            'alias' => $mollieMethod
        ));

        $container->setDefinition(
            sprintf('ruudk_payment_mollie.form.%s_type', $method),
            $definition
        );
    }
}
