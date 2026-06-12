<?php

namespace Dalactive\Sepay\Model;

use Magento\Payment\Model\Method\AbstractMethod;

class PaymentMethod extends AbstractMethod
{
    public const CODE = 'sepay';

    protected $_code = self::CODE;
    protected $_isOffline = true;
    protected $_canAuthorize = false;
    protected $_canCapture = false;
    protected $_canUseCheckout = true;
    protected $_canUseInternal = true;
    protected $_canRefund = false;
    protected $_canVoid = false;

    /**
     * @param string $paymentAction
     * @param object $stateObject
     * @return $this
     */
    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
        return $this;
    }}
