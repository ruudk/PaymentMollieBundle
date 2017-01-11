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
use Ruudk\Payment\MollieBundle\Exception\IdealIssuerTemporarilyUnavailableException;
use Ruudk\Payment\MollieBundle\Exception\MollieTemporarilyUnavailableException;
use Ruudk\Payment\MollieBundle\Form\CreditcardType;
use Ruudk\Payment\MollieBundle\Form\IdealType;
use Ruudk\Payment\MollieBundle\Form\KbcType;
use Ruudk\Payment\MollieBundle\Form\MistercashType;
use Ruudk\Payment\MollieBundle\Form\SofortType;
use Ruudk\Payment\MollieBundle\Form\BanktransferType;
use Ruudk\Payment\MollieBundle\Form\BelfiusType;

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
        return $name !== IdealType::class &&
               $name !== 'mollie_ideal' &&
               (
                   preg_match('/Mollie/', $name) ||
                   preg_match('/^mollie_/', $name)
               );
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

                    if (!empty($data['details']['cardFingerprint'])) {
                        $transaction->getExtendedData()->set('card_fingerprint', $data['details']['cardFingerprint']);
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

            if($response->isRedirect()) {
                $ex = new ActionRequiredException('Redirect the user to Mollie.');
                $ex->setFinancialTransaction($transaction);
                $ex->setAction(new VisitUrl($response->getRedirectUrl()));

                if($this->logger) {
                    $this->logger->info(sprintf(
                        'Create a new redirect exception for transaction "%s".',
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

        $ex = new FinancialException('Payment failed.');
        $ex->setFinancialTransaction($transaction);
        $transaction->setResponseCode('FAILED');
        $transaction->setReasonCode('FAILED');
        $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

        throw $ex;
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @return CommunicationException|IdealIssuerTemporarilyUnavailableException|MollieTemporarilyUnavailableException
     */
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

        if(!$response->isSuccessful()) {
            $data = $response->getData();

            if(isset($data['error']) && isset($data['error']['type']) && $data['error']['type'] == 'system') {
                if(isset($data['error']['field']) && $data['error']['field'] == 'issuer') {
                    return new IdealIssuerTemporarilyUnavailableException("Can't start payment because of an issue with the issuer. Other issuers may work.");
                }

                return new MollieTemporarilyUnavailableException($response->getMessage());
            }
        }

        return new CommunicationException("Can't create Mollie payment");
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
            'paymentMethod' => $this->getMethod($transaction),
        );

        return $parameters;
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @return string
     */
    protected function getMethod(FinancialTransactionInterface $transaction)
    {
        switch ($transaction->getPayment()->getPaymentInstruction()->getPaymentSystemName()) {
            case IdealType::class:
                return 'ideal';
            case CreditcardType::class:
                return 'creditcard';
            case MistercashType::class:
                return 'mistercash';
            case SofortType::class:
                return 'sofort';
            case BanktransferType::class:
                return 'banktransfer';
            case BelfiusType::class:
                return 'belfius';
            case KbcType::class:
                return 'kbc';
        }

        return substr($transaction->getPayment()->getPaymentInstruction()->getPaymentSystemName(), 7);
    }
}
