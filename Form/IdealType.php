<?php

namespace Ruudk\Payment\MollieBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;

class IdealType extends MollieType
{
    /**
     * @var array
     */
    protected $banks = array();

    /**
     * @param string $name
     * @param string $cacheDir
     */
    public function __construct($name, $cacheDir)
    {
        $this->name = $name;

        if (null !== $cacheDir && is_file($cache = $cacheDir . '/ruudk_payment_mollie_ideal.php')) {
            $this->banks = require $cache;
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('bank', 'choice', array(
            'label'       => 'ruudk_payment_mollie.ideal.bank.label',
            'empty_value' => 'ruudk_payment_mollie.ideal.bank.empty_value',
            'choices'     => $this->banks
        ));
    }
}