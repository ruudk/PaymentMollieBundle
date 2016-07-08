<?php

namespace Ruudk\Payment\MollieBundle\Plugin;

use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use JMS\Payment\CoreBundle\Plugin\ErrorBuilder;
use Ruudk\Payment\MollieBundle\Form\IdealType;

class IdealPlugin extends DefaultPlugin
{
    public function processes($name)
    {
        return $name === IdealType::class || $name === 'mollie_ideal';
    }

    public function checkPaymentInstruction(PaymentInstructionInterface $instruction)
    {
        $errorBuilder = new ErrorBuilder();

        /**
         * @var \JMS\Payment\CoreBundle\Entity\ExtendedData $data
         */
        $data = $instruction->getExtendedData();

        if(!$data->get('bank')) {
            $errorBuilder->addDataError('data_Ruudk_Payment_MollieBundle_Form_IdealType.bank', 'form.error.bank_required');
        }

        if ($errorBuilder->hasErrors()) {
            throw $errorBuilder->getException();
        }
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @return array
     */
    protected function getPurchaseParameters(FinancialTransactionInterface $transaction)
    {
        /**
         * @var \JMS\Payment\CoreBundle\Model\ExtendedDataInterface $data
         */
        $data = $transaction->getExtendedData();

        $parameters = parent::getPurchaseParameters($transaction);
        $parameters['issuer'] = $data->get('bank');

        return $parameters;
    }
}
