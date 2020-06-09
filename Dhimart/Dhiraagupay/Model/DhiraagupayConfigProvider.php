<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Dhimart\Dhiraagupay\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Checkout\Model\ConfigProviderInterface;
use Psr\Log\LoggerInterface;


/**
 * Config provider for credit limit.
 */
class DhiraagupayConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /**
     * Name for payment method.
     */
    const METHOD_NAME = 'dhiraagupay';

    const TRANSACTION_DATA_URL = 'dhiraagupay/otp/requestforotp';
    const REDIRECT_DATA_URL = 'dhiraagupay/htmlredirect/redirect';

   
    /**
     * Name for config payment action, depending on order status.
     */
    const PAYMENT_ACTION_ORDER = 'order';

    /**
     * @var \Magento\Authorization\Model\UserContextInterface
     */
    private $userContext;


    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $quoteRepository;
  

    /**
     * @var \Magento\Framework\App\Action\Context
     */
    private $context;

    /**
     * @var \Magento\Quote\Api\Data\CartInterface
     */
    private $quote;

    

    /**
     * @param UserContextInterface $userContext
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository     
     * @param \Magento\Framework\App\Action\Context $context
     * @param UrlInterface $urlBuilder
     * @param Repository $assetRepo
     * @param RequestInterface $request  
     * @param LoggerInterface $logger  
     */
    public function __construct(
        UserContextInterface $userContext,       
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,       
        \Magento\Framework\App\Action\Context $context, 
        \Magento\Checkout\Model\Session $session,   
        UrlInterface $urlBuilder,
        Repository $assetRepo,
        RequestInterface $request,
        LoggerInterface $logger
    ) {
        $this->userContext = $userContext;
        $this->quoteRepository = $quoteRepository;        
        $this->context = $context;  
        $this->session = $session;    
        $this->urlBuilder = $urlBuilder;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * Get dhiraagupay config.
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConfig()
    {
        $config=[];         
            $quote = $this->getQuote();
            $grandTotal = number_format($quote->getBaseGrandTotal(),2);

            $config = [
                'payment' => [
                    'dhiraagupay' => [
                     'transactionDataUrl' => $this->urlBuilder->getUrl(self::TRANSACTION_DATA_URL, ['_secure' => true]),
                        'grandTotal' => $grandTotal,
                        'paymentAcceptanceMarkSrc' => $this->getPaymentImageUrl(),
                        'paymentTermsAndConditions'=>$this->getTermsConditionUrl()
                    ]
                ]
            ];
       
        return $config;
    }

    /**
     * Get quote.
     *
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuote()
    {
        if ($this->quote === null) {
            try {
                if($this->userContext->getUserId()){
                    $this->quote = $this->quoteRepository->getActiveForCustomer($this->userContext->getUserId());
                }else{
                    $this->quote  = $this->session->getQuote();
                }
                
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                $id = $this->context->getRequest()->getParam('negotiableQuoteId');
                $this->quote = $this->quoteRepository->get($id);
            }
        }

        return $this->quote;
    }

    /**
     * Retrieve CVV tooltip image url
     *
     * @return string
     */
    public function getPaymentImageUrl()
    {
        return $this->getViewFileUrl('Dhimart_Dhiraagupay::dhiraagupay.png');
    }

    /**
     * Retrieve url of a view file
     *
     * @param string $fileId
     * @param array $params
     * @return string
     */
    public function getViewFileUrl($fileId, array $params = [])
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);
            return $this->assetRepo->getUrlWithParams($fileId, $params);
        } catch (LocalizedException $e) {
            $this->logger->critical($e);
            return $this->urlBuilder->getUrl('', ['_direct' => 'core/index/notFound']);
        }
    }

    /**
     * Retrieve url of a terms and condition file

     * @param array $params
     * @return string
     */
    public function getTermsConditionUrl(array $params = [])
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);
            return $this->urlBuilder->getUrl('terms-and-conditions',$params);

        } catch (LocalizedException $e) {
            $this->logger->critical($e);
            return $this->urlBuilder->getUrl('', ['_direct' => 'core/index/notFound']);
        }
    }
}
