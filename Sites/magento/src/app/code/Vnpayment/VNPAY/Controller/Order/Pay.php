<?php

namespace Vnpayment\VNPAY\Controller\Order;

use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;

class Pay extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var InvoiceSender
     */
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

    public function execute()
    {
        $params = $this->getRequest()->getParams();

        $vnpSecureHash = $this->getRequest()->getParam('vnp_SecureHash', '');
        $vnpResponseCode = $this->getRequest()->getParam('vnp_ResponseCode', '');
        $vnpTransactionStatus = $this->getRequest()->getParam('vnp_TransactionStatus', '');
        $vnpTxnRef = $this->getRequest()->getParam('vnp_TxnRef', '');

        $hashSecret = trim((string)$this->scopeConfig->getValue('payment/vnpay/hash_code'));

        if ($vnpSecureHash === '' || $hashSecret === '' || $vnpTxnRef === '') {
            $this->messageManager->addError('Thanh toán qua VNPAY thất bại. Thiếu dữ liệu phản hồi.');
            return $this->resultRedirectFactory->create()->setPath('checkout/onepage/failure');
        }

        /*
         * Remove checksum fields before rebuilding hash data.
         */
        unset($params['vnp_SecureHash']);
        unset($params['vnp_SecureHashType']);

        ksort($params);

        $hashData = '';
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $hashData .= ($hashData ? '&' : '') . urlencode($key) . '=' . urlencode($value);
            }
        }

        /*
         * VNPAY 2.1.x uses HMAC-SHA512 with vnp_HashSecret.
         */
        $calculatedHash = hash_hmac('sha512', $hashData, $hashSecret);

        if (!hash_equals($calculatedHash, $vnpSecureHash)) {
            $this->messageManager->addError('Thanh toán qua VNPAY thất bại. Sai chữ ký phản hồi.');
            return $this->resultRedirectFactory->create()->setPath('checkout/onepage/failure');
        }

        /*
         * VNPAY success condition:
         * vnp_ResponseCode = 00 and vnp_TransactionStatus = 00.
         */
        if ($vnpResponseCode === '00' && ($vnpTransactionStatus === '00' || $vnpTransactionStatus === '')) {
            $order = $this->order->loadByIncrementId($vnpTxnRef);

            if ($order && $order->getId()) {
                try {
                    $order->addStatusHistoryComment(
                        'Thanh toán thành công qua VNPAY. Mã giao dịch VNPAY: ' .
                        $this->getRequest()->getParam('vnp_TransactionNo', '')
                    );

                    /*
                     * Keep this conservative for demo.
                     * If your order workflow expects processing after payment, uncomment setState/setStatus.
                     */
                    $this->createInvoiceIfPossible($order);

                    $order->setState(Order::STATE_PROCESSING)
                        ->setStatus(Order::STATE_PROCESSING);

                    $order->save();
                } catch (\Exception $e) {
                    $this->messageManager->addError('Thanh toán VNPAY thành công nhưng cập nhật đơn hàng thất bại.');
                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/failure');
                }
            }

            $this->messageManager->addSuccess('Thanh toán thành công qua VNPAY');
            return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
        }

        $this->messageManager->addError(
            'Thanh toán qua VNPAY thất bại. ' . $this->getResponseDescription($vnpResponseCode)
        );

        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/failure');
    }

    public function getResponseDescription($responseCode)
    {
        switch ($responseCode) {
            case '00':
                return 'Giao dịch thành công';
            case '01':
                return 'Giao dịch đã tồn tại';
            case '02':
                return 'Merchant không hợp lệ (kiểm tra lại vnp_TmnCode)';
            case '03':
                return 'Dữ liệu gửi sang không đúng định dạng';
            case '04':
                return 'Khởi tạo giao dịch không thành công do Website đang bị tạm khóa';
            case '05':
                return 'Giao dịch không thành công do khách hàng nhập sai mật khẩu quá số lần quy định';
            case '06':
                return 'Giao dịch không thành công do khách hàng nhập sai OTP';
            case '07':
                return 'Giao dịch bị nghi ngờ gian lận';
            case '08':
                return 'Giao dịch không thành công do hệ thống ngân hàng đang bảo trì';
            case '09':
                return 'Thẻ/Tài khoản chưa đăng ký Internet Banking';
            case '10':
                return 'Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần';
            case '11':
                return 'Đã hết hạn chờ thanh toán';
            case '12':
                return 'Thẻ/Tài khoản bị khóa';
            case '24':
                return 'Khách hàng hủy giao dịch';
            case '51':
                return 'Tài khoản không đủ số dư';
            case '65':
                return 'Tài khoản đã vượt quá hạn mức giao dịch trong ngày';
            case '75':
                return 'Ngân hàng thanh toán đang bảo trì';
            case '79':
                return 'Khách hàng nhập sai mật khẩu thanh toán quá số lần quy định';
            case '99':
                return 'Có lỗi xảy ra trong quá trình thực hiện giao dịch';
            default:
                return 'Giao dịch thất bại';
        }
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
            $order->addStatusHistoryComment('Không gửi được email invoice VNPAY: ' . $e->getMessage());
        }

        $order->addStatusHistoryComment('Invoice được tạo sau khi VNPAY xác nhận thanh toán.');
    }
}
