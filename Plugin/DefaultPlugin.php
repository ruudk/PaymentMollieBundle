<?php

namespace Ruudk\Payment\MollieBundle\Plugin;

use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\Exception\BlockedException;
use JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use Psr\Log\LoggerInterface;
use Omnipay\Mollie\Gateway;

class DefaultPlugin extends AbstractPlugin
{
    /**
     * @var \Omnipay\Mollie\Gateway
     */
    protected $gateway;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(Gateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function processes($name)
    {
        return $name !== 'mollie_ideal' && preg_match('/^mollie_/', $name);
    }

    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        if($transaction->getState() === FinancialTransactionInterface::STATE_NEW) {
            throw $this->createMollieRedirectActionException($transaction);
        }

        if(null !== $trackingId = $transaction->getTrackingId()) {
            $response = $this->gateway->completePurchase(array(
                'transactionReference' => $trackingId
            ))->send();

            if($this->logger) {
                $this->logger->info('TransactionStatus: Status=' . $response->getStatus() . ", TransactionId=" . $response->getTransactionReference() . ", " . json_encode($response->getData()));
            }

            if($response->isSuccessful()) {
                $transaction->setReferenceNumber($response->getTransactionReference());
                $transaction->setProcessedAmount($response->getAmount());
                $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
                $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);

                $data = $response->getData();
                if(!empty($data['details'])) {
                    if(!empty($data['details']['consumerName'])) {
                        $transaction->getExtendedData()->set('consumer_name', $data['details']['consumerName']);
                    }

                    if(!empty($data['details']['consumerAccount'])) {
                        $transaction->getExtendedData()->set('consumer_account_number', $data['details']['consumerAccount']);
                    }
                }

                if($this->logger) {
                    $this->logger->info(sprintf(
                        'Payment is successful for transaction "%s".',
                        $response->getTransactionReference()
                    ));
                }

                return;
            }

            if($response->isCancelled()) {
                $ex = new FinancialException('Payment cancelled.');
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('CANCELLED');
                $transaction->setReasonCode('CANCELLED');
                $transaction->setState(FinancialTransactionInterface::STATE_CANCELED);

                if($this->logger) {
                    $this->logger->info(sprintf(
                        'Payment cancelled for transaction "%s".',
                        $response->getTransactionReference()
                    ));
                }

                throw $ex;
            }

            if($response->isExpired()) {
                $ex = new FinancialException('Payment expired.');
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('EXPIRED');
                $transaction->setReasonCode('EXPIRED');
                $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

                if($this->logger) {
                    $this->logger->info(sprintf(
                        'Payment is expired for transaction "%s".',
                        $response->getTransactionReference()
                    ));
                }

                throw $ex;
            }

            if($this->logger) {
                $this->logger->info(sprintf(
                    'Waiting for notification from Mollie for transaction "%s".',
                    $response->getTransactionReference()
                ));
            }

            throw new BlockedException("Waiting for notification from Mollie.");
        }
    }

    public function createMollieRedirectActionException(FinancialTransactionInterface $transaction)
    {
        $parameters = $this->getPurchaseParameters($transaction);

        $response = $this->gateway->purchase($parameters)->send();

        if($this->logger) {
            $this->logger->info(json_encode($response->getRequest()->getData()));
            $this->logger->info(json_encode($response->getData()));
        }

        if($response->isRedirect()) {
            $transaction->setTrackingId($response->getTransactionReference());

            $actionRequest = new ActionRequiredException('Redirect the user to Molie.');
            $actionRequest->setFinancialTransaction($transaction);
            $actionRequest->setAction(new VisitUrl($response->getRedirectUrl()));

            if($this->logger) {
                $this->logger->info(sprintf(
                    'Create a new redirect exception for transaction "%s".',
                    $response->getTransactionReference()
                ));
            }

            return $actionRequest;
        }

        throw new CommunicationException("Can't create Mollie payment");
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @return array
     */
    protected function getPurchaseParameters(FinancialTransactionInterface $transaction)
    {
        /**
         * @var \JMS\Payment\CoreBundle\Model\PaymentInterface $payment
         */
        $payment = $transaction->getPayment();

        /**
         * @var \JMS\Payment\CoreBundle\Model\ExtendedDataInterface $data
         */
        $data = $transaction->getExtendedData();

        $transaction->setTrackingId($payment->getId());

        $parameters = array(
            'amount'        => $payment->getTargetAmount(),
            'description'   => $data->has('description') ? $data->get('description') : 'Transaction ' . $payment->getId(),
            'returnUrl'     => $data->get('return_url'),
            'method'        => $this->getMethod($transaction),
        );

        return $parameters;
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @return string
     */
    protected function getMethod(FinancialTransactionInterface $transaction)
    {
        return substr($transaction->getPayment()->getPaymentInstruction()->getPaymentSystemName(), 7);
    }
}