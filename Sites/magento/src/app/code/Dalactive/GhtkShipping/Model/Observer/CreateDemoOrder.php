<?php

namespace Dalactive\GhtkShipping\Model\Observer;

use Dalactive\GhtkShipping\Logger\Logger;
use Dalactive\GhtkShipping\Model\Api\GhtkClient;
use Dalactive\GhtkShipping\Model\Config;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class CreateDemoOrder implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly GhtkClient $client,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Logger $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $storeId = $order->getStoreId() ? (int)$order->getStoreId() : null;
        if (!$this->config->shouldCreateOrder($storeId)) {
            return;
        }

        if (!str_starts_with((string)$order->getShippingMethod(), Config::CARRIER_CODE . '_')) {
            return;
        }

        try {
            $payload = $this->buildPayload($order, $storeId);
            $response = $this->client->createOrder($payload, $storeId);

            $label = $response['order']['label'] ?? $response['label'] ?? null;
            $message = 'GHTK staging order creation response: ' . json_encode($response, JSON_UNESCAPED_UNICODE);
            if ($label) {
                $message = 'GHTK staging order created. Label: ' . $label;
            }

            $order->addCommentToStatusHistory($message);
            $this->orderRepository->save($order);
        } catch (\Throwable $exception) {
            $this->logger->error('GHTK staging order creation failed', [
                'order' => $order->getIncrementId(),
                'message' => $exception->getMessage(),
            ]);

            try {
                $order->addCommentToStatusHistory('GHTK staging order creation failed: ' . $exception->getMessage());
                $this->orderRepository->save($order);
            } catch (\Throwable $saveException) {
                $this->logger->error('Unable to save GHTK failure comment', [
                    'order' => $order->getIncrementId(),
                    'message' => $saveException->getMessage(),
                ]);
            }
        }
    }

    private function buildPayload(OrderInterface $order, ?int $storeId): array
    {
        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress) {
            throw new \RuntimeException('Order has no shipping address.');
        }

        $street = $shippingAddress->getStreet();
        $streetLine = is_array($street) ? implode(', ', array_filter($street)) : (string)$street;
        $district = (string)($shippingAddress->getData('district') ?: $this->config->get('demo_to_district', $storeId));
        $ward = (string)($shippingAddress->getData('ward') ?: $this->config->get('demo_to_ward', $storeId));
        $province = (string)($shippingAddress->getRegion() ?: $this->config->get('demo_to_province', $storeId));

        return [
            'products' => $this->buildProducts($order, $storeId),
            'order' => [
                'id' => $order->getIncrementId(),
                'pick_name' => (string)$this->config->get('pick_name', $storeId),
                'pick_money' => 0,
                'pick_address' => (string)$this->config->get('pick_address', $storeId),
                'pick_province' => (string)$this->config->get('pick_province', $storeId),
                'pick_district' => (string)$this->config->get('pick_district', $storeId),
                'pick_ward' => (string)$this->config->get('pick_ward', $storeId),
                'pick_tel' => (string)$this->config->get('pick_tel', $storeId),
                'name' => trim($shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname()),
                'address' => $streetLine !== '' ? $streetLine : (string)$this->config->get('demo_to_address', $storeId),
                'province' => $province,
                'district' => $district,
                'ward' => $ward,
                'tel' => (string)$shippingAddress->getTelephone(),
                'email' => (string)($shippingAddress->getEmail() ?: $order->getCustomerEmail()),
                'is_freeship' => 0,
                'value' => max(0, (int)round((float)$order->getGrandTotal())),
                'transport' => (string)($this->config->get('transport', $storeId) ?: 'road'),
            ],
        ];
    }

    private function buildProducts(OrderInterface $order, ?int $storeId): array
    {
        $products = [];
        $defaultWeight = $this->config->getInt('default_weight', 500, $storeId);

        foreach ($order->getAllVisibleItems() as $item) {
            $weight = (float)($item->getWeight() ?: 0);
            $products[] = [
                'name' => mb_substr((string)$item->getName(), 0, 120),
                'weight' => max($defaultWeight, (int)round($weight > 0 ? $weight * 1000 : $defaultWeight)),
                'quantity' => (int)$item->getQtyOrdered(),
                'product_code' => (string)$item->getSku(),
            ];
        }

        return $products;
    }
}
