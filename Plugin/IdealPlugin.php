<?php

namespace Ruudk\Payment\MollieBundle\Plugin;

use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\ErrorBuilder;
use JMS\Payment\CoreBundle\Plugin\Exception\BlockedException;
use JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use AMNL\Mollie\IDeal\IDealGateway;

class IdealPlugin extends DefaultPlugin
{
    public function processes($name)
    {
        return $name === 'mollie_ideal';
    }

    public function checkPaymentInstruction(PaymentInstructionInterface $instruction)
    {
        $errorBuilder = new ErrorBuilder();

        /**
         * @var \JMS\Payment\CoreBundle\Entity\ExtendedData $data
         */
        $data = $instruction->getExtendedData();

        if(!$data->get('bank')) {
            $errorBuilder->addDataError('bank', 'form.error.bank_required');
        }

        if ($errorBuilder->hasErrors()) {
            throw $errorBuilder->getException();
        }
    }

    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        if($transaction->getState() === FinancialTransactionInterface::STATE_NEW) {
            throw $this->createMollieRedirectActionException($transaction);
        }

        if(null !== $trackingId = $transaction->getTrackingId()) {
            $status = $this->api->checkPayment($trackingId);

            if($this->logger) {
                $this->logger->info('TransactionStatus: Paid=' . $status->isPaid() . ", Status=" . $status->getStatus() . ", TransactionId=" . $status->getTransactionId() . ", Amount=" . $status->getAmount());
            }

            if($status->isPaid()) {
                $transaction->setReferenceNumber($status->getTransactionId());
                $transaction->setProcessedAmount($status->getAmount() / 100);
                $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
                $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);

                $consumer = $status->getConsumer();
                $transaction->getExtendedData()->set('consumer_name', $consumer->getName());
                $transaction->getExtendedData()->set('consumer_account_number', $consumer->getAccountNumber());

                if($this->logger) {
                    $this->logger->info(sprintf(
                        'Payment is successful for transaction "%s".',
                        $status->getTransactionId()
                    ));
                }

                return;
            }

            if($status->getStatus() === 'Cancelled') {
                $ex = new FinancialException('Payment cancelled.');
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('CANCELLED');
                $transaction->setReasonCode('CANCELLED');
                $transaction->setState(FinancialTransactionInterface::STATE_CANCELED);

                if($this->logger) {
                    $this->logger->info(sprintf(
                        'Payment cancelled for transaction "%s".',
                        $status->getTransactionId()
                    ));
                }

                throw $ex;
            }

            if($status->getStatus() === 'Failure') {
                $ex = new FinancialException('Payment failed.');
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('FAILED');
                $transaction->setReasonCode('FAILED');
                $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

                if($this->logger) {
                    $this->logger->info(sprintf(
                        'Payment failed for transaction "%s".',
                        $status->getTransactionId()
                    ));
                }

                throw $ex;
            }

            if($status->getStatus() === 'Expired') {
                $ex = new FinancialException('Payment expired.');
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('EXPIRED');
                $transaction->setReasonCode('EXPIRED');
                $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

                if($this->logger) {
                    $this->logger->info(sprintf(
                        'Payment is expired for transaction "%s".',
                        $status->getTransactionId()
                    ));
                }

                throw $ex;
            }

            if($this->logger) {
                $this->logger->info(sprintf(
                    'Waiting for notification from Mollie for transaction "%s".',
                    $status->getTransactionId()
                ));
            }

            throw new BlockedException("Waiting for notification from Mollie.");
        }
    }

    public function createMollieRedirectActionException(FinancialTransactionInterface $transaction)
    {
        /**
         * @var \JMS\Payment\CoreBundle\Model\PaymentInterface $payment
         */
        $payment = $transaction->getPayment();

        /**
         * @var \JMS\Payment\CoreBundle\Model\ExtendedDataInterface $data
         */
        $data = $transaction->getExtendedData();

        $mollieResponse = $this->api->preparePayment(
            $payment->getTargetAmount() * 100,
            $this->reportUrl,
            $data->get('return_url'),
            $data->has('description') ? $data->get('description') : 'Transaction ' . $payment->getId(),
            array(
                'bank' => $data->get('bank')
            )
        );

        if(null === $mollieTransactionId = $mollieResponse->getTransactionId()) {
            throw new CommunicationException('Mollie did not return a transaction id');
        }

        $transaction->setTrackingId($mollieTransactionId);

        $actionRequest = new ActionRequiredException('Redirect the user to Mollie.');
        $actionRequest->setFinancialTransaction($transaction);
        $actionRequest->setAction(new VisitUrl($mollieResponse->getDestination()));

        if($this->logger) {
            $this->logger->info(sprintf(
                'Create a new redirect exception for transaction "%s".',
                $mollieTransactionId
            ));
        }

        return $actionRequest;
    }
}