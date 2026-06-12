<?php

namespace Vnpayment\VNPAY\Controller\Order;

use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;

class Ipn extends \Magento\Framework\App\Action\Action {

    /** @var  \Magento\Sales\Model\Order */
    protected $order;

    /** @var  \Magento\Checkout\Model\Session */
    protected $checkoutSession;

    /** @var  \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $scopeConfig;

    /** @var InvoiceSender */
    protected $invoiceSender;

    public function __construct(
        Context $context,
        Order $order,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        InvoiceSender $invoiceSender
    ) {
        parent::__construct($context);
        $this->order = $order;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->invoiceSender = $invoiceSender;
    }

    /**
     * Order success action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute() {
        $vnp_SecureHash = $this->getRequest()->getParam('vnp_SecureHash', '');
        $SECURE_SECRET = trim((string)$this->scopeConfig->getValue('payment/vnpay/hash_code'));
        $responseParams = $this->getRequest()->getParams();
        $vnp_ResponseCode = $this->getRequest()->getParam('vnp_ResponseCode', '');
        $vnpTransactionStatus = $this->getRequest()->getParam('vnp_TransactionStatus', '');
        $inputData = $responseParams;
        unset($inputData['vnp_SecureHashType'], $inputData['vnp_SecureHash']);
        ksort($inputData);

        $hashData = '';
        foreach ($inputData as $key => $value) {
            if ($value !== null && $value !== '') {
                $hashData .= ($hashData ? '&' : '') . urlencode($key) . '=' . urlencode($value);
            }
        }
        $returnData = array();
        $secureHash = hash_hmac('sha512', $hashData, $SECURE_SECRET);
        try {
            if ($SECURE_SECRET !== '' && hash_equals($secureHash, $vnp_SecureHash)) {
                $vnp_TxnRef = $this->getRequest()->getParam('vnp_TxnRef', '000000000');
                $order = $this->order->loadByIncrementId($vnp_TxnRef);
                if ($order->getId()) {
                    if (!in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CLOSED, Order::STATE_CANCELED], true)) {

                        if ($vnp_ResponseCode === '00' && ($vnpTransactionStatus === '00' || $vnpTransactionStatus === '')) {
                            $amount = $this->getRequest()->getParam('vnp_Amount', '0');
                            $order->setTotalPaid(floatval($amount) / 100);
                            $this->createInvoiceIfPossible($order);

                            $orderState = Order::STATE_PROCESSING;
                            $order->setState($orderState)->setStatus(Order::STATE_PROCESSING);
                            $order->save();
                        } else {
                            $order->addStatusHistoryComment('Giao dịch VNPAY thất bại');
                            if ($order->canCancel()) {
                                $order->cancel();
                            }
                            $order->setState(Order::STATE_CANCELED)->setStatus('dalactive_payment_failed');
                            $order->save();
                        }
                        $returnData['RspCode'] = '00';
                        $returnData['Message'] = 'Confirm Success';
                    } else {
                        $returnData['RspCode'] = '02';
                        $returnData['Message'] = 'Order already confirmed';
                    }
                } else {
                    $returnData['RspCode'] = '01';
                    $returnData['Message'] = 'Order not found';
                }
            } else {
                $returnData['RspCode'] = '97';
                $returnData['Message'] = 'Chu ky khong hop le';
            }
        } catch (\Exception $e) {
            $returnData['RspCode'] = '99';
            $returnData['Message'] = 'Unknow error';
        }
//Trả lại VNPAY theo định dạng JSON
        echo json_encode($returnData);
    }

    private function createInvoiceIfPossible(Order $order): void
    {
        if (!$order->canInvoice()) {
            return;
        }

        $invoice = $order->prepareInvoice();
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $invoice->getOrder()->setCustomerNoteNotify(false);
        $order->addRelatedObject($invoice);

        try {
            $this->invoiceSender->send($invoice);
        } catch (\Throwable $e) {
            $order->addStatusHistoryComment('Không gửi được email invoice VNPAY IPN: ' . $e->getMessage());
        }

        $order->addStatusHistoryComment('Invoice được tạo sau khi VNPAY IPN xác nhận thanh toán.');
    }

}
