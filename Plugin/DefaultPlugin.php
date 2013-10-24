<?php

namespace Ruudk\Payment\MollieBundle\Plugin;

use JMS\Payment\CoreBundle\Entity\PaymentInstruction;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\ErrorBuilder;
use JMS\Payment\CoreBundle\Plugin\Exception\BlockedException;
use JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use Monolog\Logger;
use AMNL\Mollie\IDeal\IDealGateway;

class DefaultPlugin extends AbstractPlugin
{
    /**
     * @var \AMNL\Mollie\IDeal\IDealGateway
     */
    protected $api;

    /**
     * @var \Monolog\Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $reportUrl;

    public function __construct(IDealGateway $api, $reportUrl)
    {
        $this->api = $api;
        $this->reportUrl = $reportUrl;
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger = null)
    {
        $this->logger = $logger;
    }

    public function processes($name)
    {
        return $name !== 'mollie_ideal' && preg_match('/^mollie_/', $name);
    }
}