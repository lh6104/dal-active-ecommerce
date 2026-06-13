<?php

namespace Dalactive\GhtkShipping\Model\Api;

use Dalactive\GhtkShipping\Logger\Logger;
use Dalactive\GhtkShipping\Model\Config;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;

class GhtkClient
{
    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly Logger $logger
    ) {
    }

    public function calculateFee(array $query, ?int $storeId = null): array
    {
        return $this->request('GET', '/services/shipment/fee', $query, $storeId);
    }

    public function createOrder(array $payload, ?int $storeId = null): array
    {
        return $this->request('POST', '/services/shipment/order', $payload, $storeId);
    }

    public function getTrackingStatus(string $trackingOrder, ?int $storeId = null): array
    {
        return $this->request('GET', '/services/shipment/v2/' . rawurlencode($trackingOrder), [], $storeId);
    }

    private function request(string $method, string $path, array $data, ?int $storeId = null): array
    {
        $token = $this->config->getToken($storeId);
        $baseUrl = $this->config->getBaseUrl($storeId);
        $url = $baseUrl . $path;
        $curl = $this->curlFactory->create();

        if ($token === '') {
            throw new \RuntimeException('GHTK token is not configured.');
        }

        $curl->setTimeout(8);
        $curl->addHeader('Token', $token);
        $curl->addHeader('Content-Type', 'application/json');

        $clientSource = trim((string)$this->config->get('client_source', $storeId));
        if ($clientSource !== '') {
            $curl->addHeader('X-Client-Source', $clientSource);
        }

        if ($method === 'GET' && $data) {
            $url .= '?' . http_build_query($data);
        }

        if ($this->config->isDebug($storeId)) {
            $this->logger->debug('GHTK request', [
                'method' => $method,
                'url' => $url,
                'data' => $data,
                'token' => $this->mask($token),
            ]);
        }

        if ($method === 'GET') {
            $curl->get($url);
        } else {
            $curl->post($url, $this->json->serialize($data));
        }

        $status = $curl->getStatus();
        $body = (string)$curl->getBody();
        $response = $body !== '' ? $this->json->unserialize($body) : [];

        if ($this->config->isDebug($storeId)) {
            $this->logger->debug('GHTK response', [
                'status' => $status,
                'body' => $response,
            ]);
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('GHTK API returned HTTP ' . $status);
        }

        return is_array($response) ? $response : [];
    }

    private function mask(string $token): string
    {
        if (strlen($token) <= 8) {
            return '***';
        }

        return substr($token, 0, 4) . '...' . substr($token, -4);
    }
}
