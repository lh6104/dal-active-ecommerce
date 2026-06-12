<?php
namespace Boolfly\ZaloPay\Controller\Payment;

use Boolfly\ZaloPay\Gateway\Helper\Authorization;
use Boolfly\ZaloPay\Gateway\Helper\DebugLog;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Psr\Log\LoggerInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\CsrfAwareActionInterface;

/**
 * Class Ipn
 *
 * @package Boolfly\ZaloPay\Controller\Payment
 */
class Ipn implements CsrfAwareActionInterface, HttpPostActionInterface
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
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var SerializerJson
     */
    private $serializer;

    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var HttpRequest
     */
    private $request;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var Authorization
     */
    private $authorization;

    /**
     * @var DebugLog
     */
    private $debugLog;

    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * Ipn constructor.
     *
     * @param Session                  $checkoutSession
     * @param MethodInterface          $method
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderFactory             $orderFactory
     * @param SerializerJson           $serializer
     * @param CommandPoolInterface     $commandPool
     * @param ResultFactory            $resultFactory
     * @param HttpRequest              $request
     * @param LoggerInterface          $logger
     * @param ManagerInterface         $messageManager
     */
    public function __construct(
        Session $checkoutSession,
        MethodInterface $method,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        OrderRepositoryInterface $orderRepository,
        OrderFactory $orderFactory,
        SerializerJson $serializer,
        CommandPoolInterface $commandPool,
        ResultFactory $resultFactory,
        HttpRequest $request,
        LoggerInterface $logger,
        ManagerInterface $messageManager,
        Authorization $authorization,
        DebugLog $debugLog,
        InvoiceSender $invoiceSender
    ) {
        $this->commandPool              = $commandPool;
        $this->checkoutSession          = $checkoutSession;
        $this->orderRepository          = $orderRepository;
        $this->method                   = $method;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->orderFactory             = $orderFactory;
        $this->serializer               = $serializer;
        $this->resultFactory            = $resultFactory;
        $this->request                  = $request;
        $this->logger                   = $logger;
        $this->messageManager           = $messageManager;
        $this->authorization            = $authorization;
        $this->debugLog                 = $debugLog;
        $this->invoiceSender            = $invoiceSender;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $rawBody = (string)$this->request->getContent();
            $payload = $rawBody !== '' ? $this->serializer->unserialize($rawBody) : [];

            $this->logger->debug('ZaloPay IPN received', [
                'payload' => $payload,
            ]);
            $this->debugLog->log('ipn_received', ['payload' => $payload]);

            if (empty($payload['data']) || empty($payload['mac'])) {
                throw new \InvalidArgumentException('Missing ZaloPay callback data or mac.');
            }

            $expectedMac = $this->authorization->getMacKey2((string)$payload['data']);
            if (!hash_equals($expectedMac, (string)$payload['mac'])) {
                $this->logger->critical('ZaloPay IPN failed: mac not equal');
                $this->debugLog->log('ipn_failed', ['message' => 'mac not equal']);

                return $resultJson->setData([
                    'return_code' => -1,
                    'return_message' => 'mac not equal'
                ]);
            }

            $transData = $this->serializer->unserialize((string)$payload['data']);
            if (empty($transData['app_trans_id']) || !str_contains((string)$transData['app_trans_id'], '_')) {
                throw new \InvalidArgumentException('Invalid ZaloPay app_trans_id.');
            }

            $parts = explode('_', (string)$transData['app_trans_id'], 2);
            $orderIncrementId = $parts[1] ?? '';
            if ($orderIncrementId === '') {
                throw new \InvalidArgumentException('Cannot resolve Magento order from ZaloPay app_trans_id.');
            }

            $order            = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
            if (!$order || !$order->getId()) {
                throw new \InvalidArgumentException('Magento order not found: ' . $orderIncrementId);
            }

            $payment          = $order->getPayment();
            if (!$payment instanceof InfoInterface) {
                throw new \InvalidArgumentException('Invalid payment type');
            }

            ContextHelper::assertOrderPayment($payment);

            if ($payment->getMethod() !== $this->method->getCode()) {
                throw new \InvalidArgumentException('Order payment method is not ZaloPay.');
            }

            if (isset($transData['amount'])
                && (int)$transData['amount'] !== (int)round((float)$order->getGrandTotal())
            ) {
                throw new \InvalidArgumentException('ZaloPay callback amount does not match Magento order.');
            }

            $payment->setTransactionId((string)($transData['zp_trans_id'] ?? $payload['mac']));
            $payment->setIsTransactionClosed(false);
            $payment->setAdditionalInformation('zalopay_app_trans_id', (string)$transData['app_trans_id']);
            $payment->setAdditionalInformation('zalopay_callback_data', $transData);

            $this->createInvoiceIfPossible($order);

            if ($order->getState() !== Order::STATE_PROCESSING) {
                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING) ?: Order::STATE_PROCESSING);
                $order->addCommentToStatusHistory(
                    __('ZaloPay payment confirmed by IPN. Transaction: %1', $payment->getTransactionId())
                );
                $order->setCanSendNewEmailFlag(false);
                $this->orderRepository->save($order);
            }

            $this->logger->debug('ZaloPay IPN accepted', [
                'increment_id' => $order->getIncrementId(),
                'state' => $order->getState(),
                'status' => $order->getStatus(),
            ]);
            $this->debugLog->log('ipn_accepted', [
                'increment_id' => $order->getIncrementId(),
                'state' => $order->getState(),
                'status' => $order->getStatus(),
            ]);

            return $resultJson->setData([
                'return_code' => 1,
                'return_message' => 'success'
            ]);
        } catch (\Exception $e) {
            $this->logger->critical('ZaloPay IPN failed: ' . $e->getMessage());
            $this->debugLog->log('ipn_failed', ['message' => $e->getMessage()]);

            return $resultJson->setData([
                'return_code' => 0,
                'return_message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        // Returning null as default behavior
        return null;
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
            $this->logger->warning('ZaloPay invoice email failed: ' . $e->getMessage());
        }

        $order->addCommentToStatusHistory(__('Invoice was created after ZaloPay IPN confirmation.'));
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return boolean|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
