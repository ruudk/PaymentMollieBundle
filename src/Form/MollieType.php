<?php

namespace Ruudk\Payment\MollieBundle\Form;

use Symfony\Component\Form\AbstractType;

class MollieType extends AbstractType
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}