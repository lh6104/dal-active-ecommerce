<?php

namespace Dalactive\Sepay\Controller\Payment;

use Dalactive\Sepay\Model\PaymentMethod;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Sales\Api\OrderRepositoryInterface;

class Pay implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly Registry $registry
    ) {
    }

    public function execute(): ResultInterface
    {
        $orderId = (int) $this->request->getParam('order_id');
        $order = $orderId ? $this->orderRepository->get($orderId) : $this->checkoutSession->getLastRealOrder();

        if (!$order || !$order->getEntityId() || $order->getPayment()->getMethod() !== PaymentMethod::CODE) {
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('checkout/cart');
        }

        $this->registry->register('current_order', $order);

        return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
    }
}
