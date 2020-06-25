<?php

namespace Dhimart\Dhiraagupay\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Checkout\Model\Session;


class DhiraagupayPayment extends \Magento\Payment\Model\Method\AbstractMethod
{
    protected $testapitokenUrl = "https://testapi.dhiraagu.com.mv/v1/apitoken";
    protected $apitokenUrl = "https://api.dhiraagu.com.mv/v1/apitoken";

    protected $testpaymentUrl = 'https://testapi.dhiraagu.com.mv/v1/mfs/payment';
    protected $paymentUrl = 'https://api.dhiraagu.com.mv/v1/mfs/payment';

    protected $testotpverifyUrl = 'https://testapi.dhiraagu.com.mv/v1/mfs/otp/verify';
    protected $otpverifyUrl = 'https://api.dhiraagu.com.mv/v1/mfs/otp/verify';

    protected $testStatusUrl = 'https://testapi.dhiraagu.com.mv/v1/mfs/status/';
    protected $statusUrl = 'https://api.dhiraagu.com.mv/v1/mfs/status/';


    const CODE = 'dhiraagupay';

    protected $_code = 'dhiraagupay';

    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canAuthorize = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_minAmount = null;
    protected $_maxAmount = null;

    /**
     * @var string
     */
    //protected $_infoBlockType = \Dhimart\Dhiraagupay\Block\Info\Cc::class;


    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @param DirectoryHelper $directory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null,
        Session $checkoutSession,
        \Magento\Webapi\Controller\Rest\InputParamsResolver $inputParamsResolver
    )
    {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            null,
            null,
            $data,
            null
        );

        $this->checkoutSession = $checkoutSession;
        $this->_inputParamsResolver = $inputParamsResolver;

    }

    /**
     * Authorize payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
        }

        $order = $payment->getOrder();
        $billing = $order->getBillingAddress();
        try {
            $inputParams = $this->_inputParamsResolver->resolve();
            $mobile = $otpString = "";
            foreach ($inputParams as $inputParam) {
                if ($inputParam instanceof \Magento\Quote\Model\Quote\Payment) {
                    $paymentData = $inputParam->getData('additional_data');
                    if (isset($paymentData['mobile'])) {
                        $mobile = $paymentData['mobile'];
                    }
                    if (isset($paymentData['otp'])) {
                        $otpString = $paymentData['otp'];
                    }

                }
            }

            if ($otpString && $mobile) {
                $config = $this->getConfigaration();
                $verifyResponse = $this->sendOTPtVerifyRequest($config, $otpString, $mobile);

                if ($verifyResponse->transactionStatus) {
                    $_response = $this->checkoutSession->getApiResponse();
                    $transactionId = $verifyResponse->transactionId;
                    $payment->setTransactionId($transactionId)->setIsTransactionClosed(0);
                    $payment->setAdditionalInformation($_response);
                    $this->checkoutSession->setApiResponse('');
                    return $this;
                } else {
                    $transactionDescription = $verifyResponse->transactionDescription;
                    $message = $verifyResponse->resultData->message;
                    if (strpos($message, '1200') !== false) {
                        $message = "Incorrect OTP. Please resend and try again.";
                    } else {
                        $message = $transactionDescription . ' ' . $message;
                    }
                    throw new \Magento\Framework\Validator\Exception(__($message));
                }

            } else {
                throw new \Magento\Framework\Validator\Exception(__('Please enter OTP/Dhiraagupay Number'));
            }

        } catch (\Exception $e) {
            $this->debugData(['exception' => $e->getMessage()]);
            // print_r($e->getMessage());
            /* throw new \Magento\Framework\Validator\Exception(__('Payment capturing error. '.$e->getMessage()));*/
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }

        return $this;
    }

    /**
     * Capture payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canCapture()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The capture action is not available.'));
        }

        $order = $payment->getOrder();
        $billing = $order->getBillingAddress();
        try {
            $inputParams = $this->_inputParamsResolver->resolve();
            $mobile = $otpString = "";
            foreach ($inputParams as $inputParam) {
                if ($inputParam instanceof \Magento\Quote\Model\Quote\Payment) {
                    $paymentData = $inputParam->getData('additional_data');
                    if (isset($paymentData['mobile'])) {
                        $mobile = $paymentData['mobile'];
                    }
                    if (isset($paymentData['otp'])) {
                        $otpString = $paymentData['otp'];
                    }

                }
            }

            if ($otpString && $mobile) {
                $config = $this->getConfigaration();
                $verifyResponse = $this->sendOTPtVerifyRequest($config, $otpString, $mobile);

                if ($verifyResponse->transactionStatus) {
                    $_response = $this->checkoutSession->getApiResponse();
                    $transactionId = $verifyResponse->transactionId;
                    $payment->setTransactionId($transactionId)->setIsTransactionClosed(0);
                    $payment->setAdditionalInformation($_response);
                    $this->checkoutSession->setApiResponse('');
                    return $this;
                } else {
                    $transactionDescription = $verifyResponse->transactionDescription;
                    $message = $verifyResponse->resultData->message;
                    if (strpos($message, '1200') !== false) {
                        $message = "Incorrect OTP. Please resend and try again.";
                    } else {
                        $message = $transactionDescription . ' ' . $message;
                    }
                    throw new \Magento\Framework\Validator\Exception(__($message));
                }

            } else {
                throw new \Magento\Framework\Validator\Exception(__('Please enter OTP/Dhiraagupay Number'));
            }

        } catch (\Exception $e) {
            $this->debugData(['exception' => $e->getMessage()]);
            // print_r($e->getMessage());
            /* throw new \Magento\Framework\Validator\Exception(__('Payment capturing error. '.$e->getMessage()));*/
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }

        return $this;
    }

    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }
        return $this;
    }


    private function getConfigaration($storeId = 0)
    {
        $config = array();

        $username = $this->getConfigData('username');
        $password = $this->getConfigData('password');
        $api_username = $this->getConfigData('api_username');
        $originationNumber = $this->getConfigData('originationNumber');
        $merchantKey = $this->getConfigData('merchantKey');
        $debug = $this->getConfigData('debug');
        $test = $this->getConfigData('sandbox_flag');


        if ($username && $password && $api_username && $originationNumber && $merchantKey) {
            $config['username'] = trim($username);
            $config['password'] = trim($password);
            $config['api_username'] = base64_encode(trim($api_username));
            $config['originationNumber'] = trim($originationNumber);
            $config['merchantKey'] = base64_encode(trim($merchantKey));
            $config['debug'] = $debug;
            $config['test'] = $test;
        }

        return $config;
    }


    public function requestForOtp($mobile, $quote)
    {
        $resultData = [];
        $config = $this->getConfigaration();
        if ($config) {
            $access_token = $this->sendAuthenticationRequest($config);
            //  print_r($access_token);
            if ($access_token) {
                $paymentResponse = $this->sendPaymentRequest($mobile, $config, $quote);

                $_response = $this->checkoutSession->getApiResponse();
                if (isset($_response['payment'])) {
                    $paymentResponse = $_response['payment'];

                    $_GatewayMessage = $paymentResponse->resultData->message;

                    if (strpos($_GatewayMessage, 'Limit Check Failed') !== false) {
                        $message = "You have insufficient balance in your wallet to complete the order.";
                    } elseif (strpos($_GatewayMessage, 'Destination number in wrong format') !== false) {
                        $message = "This number is not registered with Dhiraagu Pay. Please register.";

                    } else {
                        $message = $_GatewayMessage;
                    }
                    $resultData['status'] = $paymentResponse->transactionStatus;
                    $resultData['message'] = $message;
                } else {
                    $resultData['status'] = 0;
                    $resultData['message'] = "Sorry, something went wrong please try again later.";
                }
                //return $resultData;
            } else {
                $resultData['status'] = 0;
                $resultData['message'] = "Sorry, something went wrong, please contact with customer care.";
            }
        }
        return $resultData;
    }


    public function sendRequestGetway($requestBody, $requestUrl, $config)
    {
        return $response = $this->sendTransaction($requestUrl, 'POST', $requestBody, $config);
    }


    public function sendAuthenticationRequest($config)
    {
        $_response = [];

        $requestBody = array();
        $requestUrl = "";
        if ($config['test']) {
            $requestUrl = $this->testapitokenUrl;
        } else {
            $requestUrl = $this->apitokenUrl;
        }
        $fields_string = "";
        $fields = array(
            'grant_type' => urlencode('password'),
            'username' => urlencode($config['username']),
            'password' => urlencode($config['password'])
        );
        foreach ($fields as $key => $value) {
            $fields_string .= $key . '=' . $value . '&';
        }
        rtrim($fields_string, '&');
        $requestBody = $fields_string;
        $config['request_type'] = 'apitoken';

        $response = $this->sendRequestGetway($requestBody, $requestUrl, $config);

        $authResponsedata = json_decode($response);

        if (!empty($authResponsedata) && $authResponsedata->access_token) {
            $accesstoken = $authResponsedata->access_token;
            $_response['access_token'] = $accesstoken;
            $this->checkoutSession->setApiResponse($_response);
            return $accesstoken;
        } else {
            return null;
        }
    }


    public function sendPaymentRequest($mobile, $config, $quote)
    {

        $requestBody = array();
        if ($config['test']) {
            $requestUrl = $this->testpaymentUrl;
        } else {
            $requestUrl = $this->paymentUrl;
        }

        $_response = $this->checkoutSession->getApiResponse();

        $apiToken = $_response['access_token'];

        $config['request_type'] = 'payment';
        $config['content_type'] = 'Content-Type: application/json';
        $config['authorization'] = 'Authorization: Bearer ' . $apiToken;


        $api_username = $config['api_username'];
        $originationNumber = (string)$config['originationNumber'];
        $merchantKey = $config['merchantKey'];


        $amount = $quote->getBaseGrandTotal();
        $amount = number_format($amount, 2);
        // $paymentInvoiceNumber='Dhimart quoteid'.$quote->getId();
        /*$proname="";
        foreach($quote->getItems() as $item){
            $proname .=" ".$item->getName();
        }*/
        // print_r($proname);

        $requestBody['username'] = $api_username;
        $requestBody['merchantKey'] = $merchantKey;
        $requestBody['originationNumber'] = "7359586";
        //$requestBody['originationNumber']=$originationNumber;
        $requestBody['destinationNumber'] = $mobile;
        // $requestBody['destinationNumber']="7944457";
        $requestBody['amount'] = $amount;
        $requestBody['paymentInvoiceNumber'] = "INV/2020-01000000000";
        $requestBody['transactionDescription'] = "Dhimart wallet Payment";

        $requestBody = json_encode($requestBody);

        $response = $this->sendRequestGetway($requestBody, $requestUrl, $config);

        $paymentResponse = json_decode($response);


        $_response['payment'] = $paymentResponse;

        $this->checkoutSession->setApiResponse($_response);

        return $paymentResponse;

    }


    public function sendOTPtVerifyRequest($config, $otpString, $destinationNumber)
    {

        $requestBody = array();
        if ($config['test']) {
            $requestUrl = $this->testotpverifyUrl;
        } else {
            $requestUrl = $this->otpverifyUrl;
        }

        $_response = $this->checkoutSession->getApiResponse();


        $apiToken = $_response['access_token'];
        $paymentResponse = $_response['payment'];

        $referenceId = $paymentResponse->resultData->referenceId;
        $transactionId = $paymentResponse->transactionId;
        $transactionDescription = $paymentResponse->transactionDescription;

        $config['request_type'] = 'otpverify';
        $config['content_type'] = 'Content-Type: application/json';
        $config['authorization'] = 'Authorization: Bearer ' . $apiToken;

        $api_username = $config['api_username'];
        $originationNumber = $config['originationNumber'];
        $merchantKey = $config['merchantKey'];


        $requestBody['username'] = $api_username;
        $requestBody['merchantKey'] = $merchantKey;
        $requestBody['referenceId'] = $referenceId;
        $requestBody['transactionId'] = $transactionId;
        $requestBody['destinationNumber'] = $destinationNumber;
        $requestBody['otpString'] = $otpString;
        $requestBody['transactionDescription'] = $transactionDescription;

        $requestBody = json_encode($requestBody);
        $verifyresponse = $this->sendRequestGetway($requestBody, $requestUrl, $config);

        $verifyResponse = json_decode($verifyresponse);


        $_response['verify_response'] = $verifyResponse;
        $this->checkoutSession->setApiResponse($_response);

        $stats = $this->sendStatusRequest($config);

        return $verifyResponse;
    }

    public function sendStatusRequest($config)
    {

        $requestBody = array();
        if ($config['test']) {
            $requestUrl = $this->testStatusUrl;
        } else {
            $requestUrl = $this->statusUrl;
        }

        $_response = $this->checkoutSession->getApiResponse();

        $apiToken = $_response['access_token'];
        $paymentResponse = $_response['payment'];

        $referenceId = $paymentResponse->resultData->referenceId;

        $requestUrl = $requestUrl . $referenceId;


        $config['request_type'] = 'otpverify';
        $config['content_type'] = 'Content-Type: application/json';
        $config['authorization'] = 'Authorization: Bearer ' . $apiToken;


        $api_username = $config['api_username'];
        // $originationNumber= $config['originationNumber'];
        $merchantKey = $config['merchantKey'];


        $requestBody['username'] = $api_username;
        $requestBody['merchantKey'] = $merchantKey;


        $requestBody = json_encode($requestBody);
        $response = $this->sendRequestGetway($requestBody, $requestUrl, $config);
        $verifyResponse = json_decode($response);

        $_response = $this->checkoutSession->getApiResponse();
        $_response['status_response'] = $verifyResponse;
        $this->checkoutSession->setApiResponse($_response);

        return $verifyResponse;

    }


    public function sendTransaction($url, $method = 'POST', $body = "", $config)
    {

        $authorization = $contenttype = "";


        if ($config['request_type'] == 'apitoken') {
            $headers = array(
                "Content-Type: application/x-www-form-urlencoded"
            );
        } elseif ($config['request_type'] == 'payment' || $config['request_type'] == "otpverify") {
            if (isset($config['authorization'])) {
                $authorization = $config['authorization'];
            }

            if (isset($config['content_type'])) {
                $contenttype = $config['content_type'];
            }

            $headers = array(
                $authorization,
                $contenttype
            );
        }

        $curl = curl_init($url);
        // curl_setopt($post, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
        } else {
            curl_setopt($curl, CURLOPT_POST, 0);
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, 45);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        if ($body) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($curl);


        $info = curl_getinfo($curl);
        $response = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        //  print_r($response);

        /*   print_r($result);
                 print_r($response);
                 exit();*/


        return $result;

        curl_close($curl);
        if (200 === $response || 202 == $response) {

            return $result;
        } else {
            /* throw new CommandException('Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl));
           die('Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl));
             //$errors = curl_error($curl);*/
            return false;
        }

    }

}
