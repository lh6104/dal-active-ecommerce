<?php
/************************************************************
 * Copyright © Boolfly. All rights reserved.
 * See COPYING.txt for license details.
 ************************************************************/

namespace Boolfly\ZaloPay\Controller\Payment;

use Boolfly\ZaloPay\Gateway\Helper\DebugLog;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action as AppAction;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class ReturnAction extends AppAction
{
    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var MethodInterface
     */
    private $method;

    /**
     * @var PaymentDataObjectFactory
     */
    private $paymentDataObjectFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var DebugLog
     */
    private $debugLog;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        MethodInterface $method,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        OrderRepositoryInterface $orderRepository,
        CommandPoolInterface $commandPool,
        LoggerInterface $logger,
        OrderFactory $orderFactory,
        DebugLog $debugLog
    ) {
        parent::__construct($context);
        $this->commandPool              = $commandPool;
        $this->checkoutSession          = $checkoutSession;
        $this->orderRepository          = $orderRepository;
        $this->method                   = $method;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->logger                   = $logger;
        $this->orderFactory             = $orderFactory;
        $this->debugLog                 = $debugLog;
    }

    /**
     * ZaloPay browser redirect is not a payment confirmation. IPN is the
     * source of truth. This controller avoids relying only on checkout session
     * because the return URL is the public ngrok domain during local testing.
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();

        $this->logger->debug('ZaloPay returnAction received', ['params' => $params]);
        $this->debugLog->log('return_received', ['params' => $params]);

        try {
            $order = $this->resolveOrder($params);
            if (!$order || !$order->getId()) {
                $this->debugLog->log('return_no_order', ['params' => $params]);

                return $this->renderPendingPage(null, true);
            }

            $payment = $order->getPayment();
            if (!$payment instanceof InfoInterface) {
                throw new \InvalidArgumentException('Invalid payment type');
            }

            ContextHelper::assertOrderPayment($payment);
            if ($payment->getMethod() !== $this->method->getCode()) {
                throw new \InvalidArgumentException('Order payment method is not ZaloPay.');
            }

            $this->logger->debug('ZaloPay returnAction resolved order', [
                'increment_id' => $order->getIncrementId(),
                'state' => $order->getState(),
                'status' => $order->getStatus(),
                'params' => $params,
            ]);
            $this->debugLog->log('return_resolved_order', [
                'increment_id' => $order->getIncrementId(),
                'state' => $order->getState(),
                'status' => $order->getStatus(),
            ]);

            if ($order->getState() === Order::STATE_PROCESSING
                || $order->getState() === Order::STATE_COMPLETE
            ) {
                $this->_redirect('checkout/onepage/success');

                return;
            }

            return $this->renderPendingPage($order);
        } catch (\Exception $e) {
            $this->logger->critical('ZaloPay returnAction failed: ' . $e->getMessage(), ['params' => $params]);
            $this->debugLog->log('return_failed', [
                'message' => $e->getMessage(),
                'params' => $params,
            ]);

            return $this->renderPendingPage(null, true);
        }
    }

    private function resolveOrder(array $params): ?Order
    {
        $appTransId = $this->getAppTransIdFromParams($params);
        if ($appTransId && strpos($appTransId, '_') !== false) {
            $parts = explode('_', $appTransId, 2);
            $incrementId = $parts[1] ?? '';
            if ($incrementId !== '') {
                $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
                if ($order && $order->getId()) {
                    return $order;
                }
            }
        }

        $lastRealOrderId = (string)$this->checkoutSession->getLastRealOrderId();
        if ($lastRealOrderId !== '') {
            $order = $this->orderFactory->create()->loadByIncrementId($lastRealOrderId);
            if ($order && $order->getId()) {
                return $order;
            }
        }

        $orderId = $this->checkoutSession->getLastOrderId();
        if ($orderId) {
            return $this->orderRepository->get($orderId);
        }

        return null;
    }

    private function getAppTransIdFromParams(array $params): ?string
    {
        foreach (['app_trans_id', 'apptransid', 'appTransId', 'app_transid'] as $key) {
            if (!empty($params[$key])) {
                return (string)$params[$key];
            }
        }

        return null;
    }

    private function renderPendingPage(?Order $order, bool $hasError = false)
    {
        $resultRaw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $resultRaw->setHeader('Content-Type', 'text/html; charset=UTF-8', true);

        $orderHtml = '';
        if ($order && $order->getId()) {
            $orderHtml = '<p>Mã đơn hàng: <strong>#'
                . htmlspecialchars((string)$order->getIncrementId(), ENT_QUOTES, 'UTF-8')
                . '</strong></p>';
        }

        $title = $hasError ? 'Đang kiểm tra thanh toán ZaloPay' : 'Thanh toán ZaloPay đang được xác nhận';
        $message = $hasError
            ? 'Hệ thống chưa xác định được đơn hàng từ lượt quay về này. Nếu bạn đã thanh toán, vui lòng chờ IPN từ ZaloPay hoặc kiểm tra lại đơn hàng trong tài khoản.'
            : 'Nếu bạn đã hoàn tất thanh toán trên ZaloPay, hệ thống sẽ cập nhật đơn hàng sau khi nhận callback xác nhận từ ZaloPay.';

        $html = '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<style>body{font-family:Arial,sans-serif;background:#f5f8fc;color:#082642;margin:0;padding:40px 16px}'
            . '.box{max-width:720px;margin:8vh auto;background:#fff;border:1px solid #d8e6f7;border-radius:12px;padding:32px;box-shadow:0 16px 40px rgba(8,38,66,.12)}'
            . 'h1{font-size:28px;margin:0 0 14px}.actions{margin-top:26px;display:flex;gap:12px;flex-wrap:wrap}'
            . 'a{display:inline-block;background:#1477ff;color:#fff;text-decoration:none;padding:12px 18px;border-radius:6px;font-weight:700}'
            . 'a.secondary{background:#082642}</style></head><body><main class="box">'
            . '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . $orderHtml
            . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<div class="actions"><a href="/sales/order/history/">Xem đơn hàng</a>'
            . '<a class="secondary" href="/">Về trang chủ</a></div>'
            . '</main></body></html>';

        $resultRaw->setContents($html);

        return $resultRaw;
    }
}
