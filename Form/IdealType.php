<?php

namespace Ruudk\Payment\MollieBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IdealType extends MollieType
{
    /**
     * @var \Omnipay\Common\Issuer[]
     */
    protected $issuers = array();

    /**
     * @param string $name
     * @param string $cacheDir
     */
    public function __construct($name, $cacheDir)
    {
        parent::__construct($name);

        $this->name = $name;

        if (null !== $cacheDir && is_file($cache = $cacheDir . '/ruudk_payment_mollie_issuers.php')) {
            $this->issuers = require $cache;
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $banks = array();
        $defaultBank = null;
        foreach($this->issuers AS $issuer) {
            if('ideal' !== $issuer->getPaymentMethod()) {
                continue;
            }

            $banks[$issuer->getId()] = $issuer->getName();
            $defaultBank = $issuer->getId();
        }

        if(1 !== count($banks)) {
            $defaultBank = null;
        }

        if (!empty($options['bank'])) {
            $defaultBank = $options['bank'];
        }

        $builder->add('bank', 'choice', array(
            'label'       => 'ruudk_payment_mollie.ideal.bank.label',
            'data'        => $defaultBank,
            'empty_value' => 'ruudk_payment_mollie.ideal.bank.empty_value',
            'choices'     => $banks
        ));
    }

    /**
     * Configures the options for this type.
     *
     * @param OptionsResolver $resolver The resolver for the options.
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'bank' => ''
        ));

        $resolver->setAllowedTypes('bank', 'string');
    }
}
