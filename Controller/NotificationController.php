<?php

namespace Ruudk\Payment\MollieBundle\Controller;

use Doctrine\ORM\EntityManager;
use JMS\Payment\CoreBundle\Model\PaymentInterface;
use JMS\Payment\CoreBundle\PluginController\PluginController;
use JMS\Payment\CoreBundle\PluginController\Result;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController
{
    /**
     * @var \JMS\Payment\CoreBundle\PluginController\PluginController
     */
    protected $pluginController;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param PluginController $pluginController
     * @param EntityManager    $entityManager
     */
    public function __construct(PluginController $pluginController, EntityManager $entityManager)
    {
        $this->pluginController = $pluginController;
        $this->entityManager = $entityManager;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function processNotification(Request $request)
    {
        if($this->logger) {
            $this->logger->info(print_r($request->request->all(), true));
        }

        if(false === $request->request->has('id')) {
            return new Response('[no id]', 200);
        }

        try
        {
            $financialTransaction = $this->entityManager->getRepository('JMS\Payment\CoreBundle\Entity\FinancialTransaction')->findOneByTrackingId($request->request->get('id'));

            /**
             * @var \JMS\Payment\CoreBundle\Entity\Payment $payment
             */
            if(null !== $financialTransaction)
            {
                /**
                 * @var \JMS\Payment\CoreBundle\Entity\FinancialTransaction $financialTransaction
                 */
                $payment = $financialTransaction->getPayment();

                if($payment->getState() === PaymentInterface::STATE_APPROVING)
                {
                    $instruction = $payment->getPaymentInstruction();

                    $result = $this->pluginController->approveAndDeposit($payment->getId(), $financialTransaction->getRequestedAmount());

                    if($this->logger) {
                        $status = array(null, 'STATUS_FAILED', 'STATUS_PENDING', 'STATUS_SUCCESS', 'STATUS_UNKNOWN');
                        $this->logger->info('Result -> ' . $status[$result->getStatus()]);
                    }

                    if(Result::STATUS_SUCCESS === $result->getStatus()) {
                        $this->pluginController->closePaymentInstruction($instruction);

                        if($this->logger) {
                            $this->logger->info('closePaymentInstruction');
                        }
                    }
                }
                else
                {
                    if($this->logger) {
                        $states = array(null, 'STATE_APPROVED', 'STATE_APPROVING', 'STATE_CANCELED' , 'STATE_EXPIRED', 'STATE_FAILED' , 'STATE_NEW', 'STATE_DEPOSITING', 'STATE_DEPOSITED');
                        $this->logger->info('Payment state is not STATE_APPROVING but  -> ' . $states[$payment->getState()]);
                    }
                }
            }

            return new Response('[accepted]', 201);
        }
        catch(\Exception $e)
        {
            if($this->logger) {
                $this->logger->info($e->getMessage());
            }

            return new Response('[failed]', 500);
        }
    }
}
