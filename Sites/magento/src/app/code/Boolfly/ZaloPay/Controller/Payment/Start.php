<?php
/************************************************************
 * *
 *  * Copyright © Boolfly. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    info@boolfly.com
 * *  @project   ZaloPay
 */
namespace Boolfly\ZaloPay\Controller\Payment;

use Boolfly\ZaloPay\Gateway\Helper\TransactionReader;
use Magento\Framework\App\Action\Action as AppAction;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\PaymentFailuresInterface;
use Psr\Log\LoggerInterface;
use Magento\Quote\Api\CartManagementInterface;

/**
 * Class Get Pay Url
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Start extends AppAction
{
    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PaymentDataObjectFactory
     */
    private $paymentDataObjectFactory;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var SessionManager
     */
    private $sessionManager;

    /**
     * @var PaymentFailuresInterface
     */
    private $paymentFailures;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * Start constructor.
     *
     * @param Context                       $context
     * @param CommandPoolInterface          $commandPool
     * @param LoggerInterface               $logger
     * @param OrderRepositoryInterface      $orderRepository
     * @param PaymentDataObjectFactory      $paymentDataObjectFactory
     * @param Session                       $checkoutSession
     * @param CartRepositoryInterface       $quoteRepository
     * @param SessionManager                $sessionManager
     * @param CartManagementInterface       $cartManagement
     * @param PaymentFailuresInterface|null $paymentFailures
     */
    public function __construct(
        Context $context,
        CommandPoolInterface $commandPool,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        Session $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        SessionManager $sessionManager,
        CartManagementInterface $cartManagement,
        PaymentFailuresInterface $paymentFailures = null
    ) {
        parent::__construct($context);
        $this->commandPool              = $commandPool;
        $this->logger                   = $logger;
        $this->quoteRepository          = $quoteRepository;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->checkoutSession          = $checkoutSession;
        $this->sessionManager           = $sessionManager;
        $this->paymentFailures          = $paymentFailures ?: $this->_objectManager->get(PaymentFailuresInterface::class);
        $this->cartManagement           = $cartManagement;
        $this->orderRepository          = $orderRepository;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        try {
            $orderId = $this->checkoutSession->getLastOrderId();
            if (!$orderId) {
                $this->logger->critical('ZaloPay start failed: Missing lastOrderId in checkout session', [
                    'last_quote_id' => $this->checkoutSession->getLastQuoteId(),
                    'last_real_order_id' => $this->checkoutSession->getLastRealOrderId(),
                ]);
                $this->messageManager->addErrorMessage(__('Không tìm thấy đơn hàng vừa tạo để thanh toán ZaloPay. Vui lòng thử lại.'));

                return $this->_redirect('checkout/cart/index');
            }

            /** @var \Magento\Sales\Model\Order $order */
            $order   = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();
            ContextHelper::assertOrderPayment($payment);
            $paymentDataObject = $this->paymentDataObjectFactory->create($payment);

            $this->logger->debug('ZaloPay start creating payment URL', [
                'order_id' => $order->getEntityId(),
                'increment_id' => $order->getIncrementId(),
                'payment_method' => $payment->getMethod(),
                'amount' => $order->getTotalDue(),
            ]);

            $commandResult = $this->commandPool->get('get_pay_url')->execute(
                [
                    'payment' => $paymentDataObject,
                    'amount' => $order->getTotalDue(),
                ]
            );

            if ($commandResult instanceof \Magento\Payment\Gateway\Command\ResultInterface) {
                throw new \Exception('ZaloPay command returned ResultInterface instead of payment URL data.');
            }

            if ($commandResult === null) {
                throw new \Exception('ZaloPay command result is null.');
            }

            $payUrl = TransactionReader::readPayUrl($commandResult);
            if (!$payUrl) {
                throw new \Exception('ZaloPay payment URL is empty.');
            }

            $this->logger->debug('ZaloPay redirecting customer to payment URL', [
                'increment_id' => $order->getIncrementId(),
                'pay_url' => $payUrl,
            ]);

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setUrl($payUrl);

            return $resultRedirect;
        } catch (\Exception $e) {
            $this->paymentFailures->handle((int)$this->checkoutSession->getLastQuoteId(), $e->getMessage());
            $this->logger->critical($e);

            $this->messageManager->addErrorMessage(__('Sorry, but something went wrong.'));
            $this->messageManager->addErrorMessage(__($e->getMessage()));
            return $this->_redirect('checkout/cart/index');
        }
    }
}
