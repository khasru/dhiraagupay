<?php
namespace Dhimart\Dhiraagupay\Controller\Otp;


use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\UrlInterface;
use Dhimart\Dhiraagupay\Helper\Data;
use Dhimart\Dhiraagupay\Model\DhiraagupayPayment;
use Dhimart\Dhiraagupay\Model\DhiraagupayConfigProvider;



class Requestforotp extends Action
{
    

    /**
     * Constructor
     *
     * @param Context $context
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        UrlInterface $urlInterface, 
        Data $helperData,
        DhiraagupayPayment $dhiraaguPayment, 
        DhiraagupayConfigProvider $dhiraagupayConfigProvider,     
        Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->session = $checkoutSession;
        $this->resultFactory = $resultFactory;
        $this->urlInterface=$urlInterface;
        $this->helperData=$helperData;
        $this->dhiraaguPayment=$dhiraaguPayment;
        $this->dhiraagupayConfigProvider=$dhiraagupayConfigProvider;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();

        $mobile = $this->getRequest()->getParam('mobile');
        if ($mobile) {
            $quote=$this->dhiraagupayConfigProvider->getQuote();
            $response=$this->dhiraaguPayment->requestForOtp($mobile, $quote);
        }
        else
        {
            $response['status'] = 0;
            $response['message'] = __("Please enter valid dhiraagupay number.");
        }
        return $resultJson->setData($response);
    }
}