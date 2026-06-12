<?php

namespace Dalactive\CheckoutFix\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class OrderCancelAfter implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        
        // If order was cancelled
        if ($order->getState() === Order::STATE_CANCELED) {
            $payment = $order->getPayment();
            if ($payment) {
                $method = $payment->getMethod();
                $onlineMethods = ['sepay', 'vnpay', 'zalopay', 'momo_wallet'];
                
                // If it's an online payment method
                if (in_array($method, $onlineMethods)) {
                    // And if it was in pending_payment state before getting canceled
                    if ($order->getOrigData('state') === Order::STATE_PENDING_PAYMENT || $order->getState() === Order::STATE_PENDING_PAYMENT) {
                        $order->setStatus('dalactive_payment_failed');
                        // Note: The order will be saved shortly after the event
                    }
                }
            }
        }
    }
}
