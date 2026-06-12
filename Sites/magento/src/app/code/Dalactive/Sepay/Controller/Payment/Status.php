<?php

namespace Dalactive\Sepay\Controller\Payment;

use Dalactive\Sepay\Model\PaymentMethod;
use Dalactive\Sepay\Setup\Patch\Data\RegisterPaymentStatuses;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Sales\Api\OrderRepositoryInterface;

class Status implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $orderId = (int) $this->request->getParam('order_id');

        try {
            $order = $this->orderRepository->get($orderId);
            $paid = $order->getPayment()->getMethod() === PaymentMethod::CODE
                && (bool) $order->getPayment()->getAdditionalInformation('sepay_confirmed');
            $failed = $order->getPayment()->getMethod() === PaymentMethod::CODE
                && in_array($order->getStatus(), [RegisterPaymentStatuses::STATUS_FAILED, 'canceled'], true);
            $paymentStatus = $failed ? 'failed' : ($paid ? 'success' : 'processing');
            $paymentStatusLabel = match ($paymentStatus) {
                'success' => 'Thành công',
                'failed' => 'Thất bại',
                default => 'Đang xử lý',
            };

            return $result->setData([
                'ok' => true,
                'paid' => $paid,
                'failed' => $failed,
                'payment_status' => $paymentStatus,
                'payment_status_label' => $paymentStatusLabel,
                'state' => $order->getState(),
                'status' => $order->getStatus(),
            ]);
        } catch (\Throwable) {
            return $result->setData(['ok' => false, 'paid' => false]);
        }
    }
}
