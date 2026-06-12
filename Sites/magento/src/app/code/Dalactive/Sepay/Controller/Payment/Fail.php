<?php

namespace Dalactive\Sepay\Controller\Payment;

use Dalactive\Sepay\Model\PaymentMethod;
use Dalactive\Sepay\Setup\Patch\Data\RegisterPaymentStatuses;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\RequestInterface as MagentoRequestInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Fail implements HttpPostActionInterface, CsrfAwareActionInterface
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
            $payment = $order->getPayment();

            if ($payment->getMethod() !== PaymentMethod::CODE) {
                return $result->setHttpResponseCode(404)->setData(['ok' => false, 'message' => 'Order not found']);
            }

            if ((bool) $payment->getAdditionalInformation('sepay_confirmed')) {
                return $result->setData(['ok' => true, 'paid' => true, 'failed' => false]);
            }

            if (!in_array($order->getState(), [Order::STATE_CANCELED, Order::STATE_COMPLETE], true)) {
                $order->setState(Order::STATE_CANCELED);
                $order->setStatus(RegisterPaymentStatuses::STATUS_FAILED);
                $order->addCommentToStatusHistory('SePay payment expired before confirmation.');
                $this->orderRepository->save($order);
            }

            return $result->setData([
                'ok' => true,
                'paid' => false,
                'failed' => true,
                'payment_status' => 'failed',
                'payment_status_label' => 'Thất bại',
            ]);
        } catch (\Throwable) {
            return $result->setHttpResponseCode(404)->setData(['ok' => false, 'message' => 'Order not found']);
        }
    }

    public function createCsrfValidationException(MagentoRequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(MagentoRequestInterface $request): ?bool
    {
        return true;
    }
}
