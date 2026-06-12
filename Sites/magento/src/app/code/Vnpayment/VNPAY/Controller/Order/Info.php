<?php

namespace Vnpayment\VNPAY\Controller\Order;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Info extends \Magento\Framework\App\Action\Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Magento\Framework\Controller\Result\Json
     */
    protected $jsonFac;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\Json $json,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonFac = $json;
        $this->order = $order;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('order_id', 0);
        $order = $this->order->load($id);

        if (!$order->getId()) {
            $this->jsonFac->setData([
                'error' => true,
                'message' => 'Order not found'
            ]);
            return $this->jsonFac;
        }

        $vnpUrl = trim((string)$this->scopeConfig->getValue('payment/vnpay/payment_url'));
        $vnpTmnCode = trim((string)$this->scopeConfig->getValue('payment/vnpay/tmn_code'));
        $vnpHashSecret = trim((string)$this->scopeConfig->getValue('payment/vnpay/hash_code'));

        if ($vnpUrl === '' || $vnpTmnCode === '' || $vnpHashSecret === '') {
            $this->jsonFac->setData([
                'error' => true,
                'message' => 'Missing VNPAY configuration'
            ]);
            return $this->jsonFac;
        }

        $incrementId = $order->getIncrementId();

        /*
         * Local demo setup:
         * - Keep Magento Base URL as https://dalactive.test/
         * - Use ngrok only for VNPAY return/callback URL.
         *
         * IMPORTANT:
         * Change this value every time ngrok URL changes.
         * Later, this should be moved to an Admin config field like "Public Payment Base URL".
         */
        $publicBaseUrl = 'https://cobalt-mulch-update.ngrok-free.dev';
        $returnUrl = rtrim($publicBaseUrl, '/') . '/paymentvnpay/order/pay';

        /*
         * VNPAY amount must be sent in the smallest unit expected by VNPAY:
         * actual VND amount * 100.
         */
        $amount = (int)round((float)$order->getTotalDue() * 100);

        $inputData = [
            'vnp_Version' => '2.1.0',
            'vnp_TmnCode' => $vnpTmnCode,
            'vnp_Amount' => $amount,
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Thanh toan don hang ' . $incrementId,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_TxnRef' => $incrementId,
        ];

        /*
         * Build hash data and query string.
         * All parameters must be fully prepared before signing.
         * Do not change any vnp_* parameter after vnp_SecureHash is generated.
         */
        ksort($inputData);

        $query = '';
        $hashData = '';

        foreach ($inputData as $key => $value) {
            if ($value !== null && $value !== '') {
                $encodedKey = urlencode($key);
                $encodedValue = urlencode($value);

                $query .= $encodedKey . '=' . $encodedValue . '&';
                $hashData .= ($hashData ? '&' : '') . $encodedKey . '=' . $encodedValue;
            }
        }

        $vnpSecureHash = hash_hmac('sha512', $hashData, $vnpHashSecret);
        $paymentUrl = rtrim($vnpUrl, '?') . '?' . $query . 'vnp_SecureHash=' . $vnpSecureHash;

        $this->jsonFac->setData($paymentUrl);
        return $this->jsonFac;
    }
}