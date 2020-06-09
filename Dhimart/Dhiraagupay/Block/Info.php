<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Dhimart\Dhiraagupay\Block;

use Magento\Framework\Phrase;


class Info extends \Magento\Payment\Block\ConfigurableInfo
{
    /**
     * Returns label
     *
     * @param string $field
     * @return Phrase
     */
    protected function getLabel($field)
    {
        return parent::getLabel($field);
    }

    /**
     * Returns value view
     *
     * @param string $field
     * @param string $value
     * @return string | Phrase
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function getValueView($field, $value)
    {
        return parent::getValueView($field, $value);
    }

    /**
     * Prepare Dhiraagupay-specific payment information
     *
     * @param \Magento\Framework\DataObject|array|null $transport
     * @return \Magento\Framework\DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);

        $payment = $this->getInfo();
        //$order = $payment->getOrder();
        //print_r($payment->getLastTransId());
        $_additionalInfo = $payment->getAdditionalInformation();
        $info = array();
        

        return $transport->addData($info);
    }
}
