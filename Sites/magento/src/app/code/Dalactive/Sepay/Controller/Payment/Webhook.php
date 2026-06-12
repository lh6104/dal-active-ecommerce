<?php

namespace Dalactive\Sepay\Controller\Payment;

use Dalactive\Sepay\Model\PaymentMethod;
use Dalactive\Sepay\Model\SepayConfig;
use Dalactive\Sepay\Setup\Patch\Data\RegisterPaymentStatuses;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\App\RequestInterface as MagentoRequestInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class Webhook implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly SepayConfig $sepayConfig,
        private readonly CollectionFactory $orderCollectionFactory,
        private readonly OrderRepository $orderRepository,
        private readonly InvoiceSender $invoiceSender
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $auth = trim((string) $this->request->getHeader('Authorization'));
        $secret = $this->sepayConfig->getWebhookSecret();
        $payload = json_decode((string) $this->request->getContent(), true);

        if (!is_array($payload)) {
            return $result->setHttpResponseCode(400)->setData(['ok' => false, 'message' => 'Invalid JSON']);
        }

        if (!$this->isAuthorized($auth, $secret) && !$this->isTrustedBankPayload($payload)) {
            $this->logUnauthorizedWebhook($auth, $secret);

            return $result->setHttpResponseCode(401)->setData(['ok' => false, 'message' => 'Unauthorized']);
        }

        if (isset($payload['transferType']) && strtolower((string) $payload['transferType']) !== 'in') {
            return $result->setHttpResponseCode(201)->setData(['ok' => true, 'ignored' => true]);
        }

        $content = implode(' ', array_filter([
            $payload['content'] ?? null,
            $payload['description'] ?? null,
            $payload['code'] ?? null,
        ]));

        if (!preg_match('/(?:DH|ORDER)?\s*([0-9]{6,})/i', $content, $matches)) {
            return $result->setHttpResponseCode(422)->setData(['ok' => false, 'message' => 'Order code not found']);
        }

        $incrementId = $matches[1];
        $amount = (float) ($payload['amount'] ?? $payload['transferAmount'] ?? $payload['transactionAmount'] ?? 0);
        $order = $this->findOrder($incrementId);

        if (!$order || $order->getPayment()->getMethod() !== PaymentMethod::CODE) {
            return $result->setHttpResponseCode(404)->setData(['ok' => false, 'message' => 'Order not found']);
        }

        if ($amount > 0 && $amount + 0.01 < (float) $order->getGrandTotal()) {
            return $result->setHttpResponseCode(422)->setData(['ok' => false, 'message' => 'Amount is lower than order total']);
        }

        if (in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true)) {
            return $result->setHttpResponseCode(201)->setData(['ok' => true, 'duplicate' => true]);
        }

        $this->markOrderPaid($order, $payload);

        return $result->setHttpResponseCode(201)->setData(['ok' => true]);
    }

    public function createCsrfValidationException(MagentoRequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(MagentoRequestInterface $request): ?bool
    {
        return true;
    }

    private function findOrder(string $incrementId): ?Order
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('increment_id', $incrementId);
        $order = $collection->getFirstItem();

        return $order && $order->getEntityId() ? $order : null;
    }

    private function isAuthorized(string $authorization, string $secret): bool
    {
        if ($secret === '' || $authorization === '') {
            return false;
        }

        if (!preg_match('/^apikey\s+(.+)$/i', $authorization, $matches)) {
            return false;
        }

        return hash_equals($secret, trim((string) $matches[1]));
    }

    private function isTrustedBankPayload(array $payload): bool
    {
        $configuredAccount = preg_replace('/\D+/', '', (string) $this->sepayConfig->getValue('account_no'));
        $payloadAccount = preg_replace('/\D+/', '', (string) ($payload['accountNumber'] ?? ''));
        $amount = (float) ($payload['amount'] ?? $payload['transferAmount'] ?? $payload['transactionAmount'] ?? 0);
        $transferType = strtolower((string) ($payload['transferType'] ?? 'in'));
        $content = implode(' ', array_filter([
            $payload['content'] ?? null,
            $payload['description'] ?? null,
            $payload['code'] ?? null,
        ]));

        $trusted = $configuredAccount !== ''
            && $payloadAccount !== ''
            && hash_equals($configuredAccount, $payloadAccount)
            && $amount > 0
            && $transferType === 'in'
            && preg_match('/(?:DH|ORDER)?\s*([0-9]{6,})/i', $content);

        if ($trusted) {
            @file_put_contents(
                BP . '/var/log/sepay_webhook.log',
                '[' . date('c') . '] accepted_by_bank_payload ' . json_encode([
                    'account_match' => true,
                    'amount' => $amount,
                    'code' => (string) ($payload['code'] ?? ''),
                    'referenceCode' => (string) ($payload['referenceCode'] ?? ''),
                    'id' => (string) ($payload['id'] ?? ''),
                ], JSON_UNESCAPED_SLASHES) . PHP_EOL,
                FILE_APPEND
            );
        }

        return (bool) $trusted;
    }

    private function logUnauthorizedWebhook(string $authorization, string $secret): void
    {
        $scheme = '';
        $token = '';

        if (preg_match('/^(\S+)(?:\s+(.+))?$/', $authorization, $matches)) {
            $scheme = (string) ($matches[1] ?? '');
            $token = trim((string) ($matches[2] ?? ''));
        }

        $context = [
            'has_authorization' => $authorization !== '',
            'authorization_scheme' => $scheme,
            'token_present' => $token !== '',
            'token_length' => strlen($token),
            'token_hash' => $token !== '' ? substr(hash('sha256', $token), 0, 12) : '',
            'expected_secret_present' => $secret !== '',
            'expected_secret_length' => strlen($secret),
            'user_agent' => (string) $this->request->getServer('HTTP_USER_AGENT'),
            'remote_addr' => (string) $this->request->getServer('REMOTE_ADDR'),
        ];

        @file_put_contents(
            BP . '/var/log/sepay_webhook.log',
            '[' . date('c') . '] unauthorized ' . json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }

    private function markOrderPaid(Order $order, array $payload): void
    {
        $transactionId = (string) ($payload['id'] ?? $payload['referenceCode'] ?? $payload['transactionId'] ?? uniqid('sepay_', true));
        $payment = $order->getPayment();
        $payment->setTransactionId($transactionId);
        $payment->setIsTransactionClosed(true);
        $payment->setAdditionalInformation('sepay_confirmed', true);
        $payment->setAdditionalInformation('sepay_confirmed_at', date('c'));
        $payment->setAdditionalInformation('sepay_webhook_payload', json_encode($payload));

        if ($order->canInvoice()) {
            $invoice = $order->prepareInvoice();
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $order->addRelatedObject($invoice);
        }

        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus(Order::STATE_PROCESSING);
        $order->addCommentToStatusHistory('SePay webhook confirmed bank transfer. Transaction: ' . $transactionId);
        $this->orderRepository->save($order);
    }
}
