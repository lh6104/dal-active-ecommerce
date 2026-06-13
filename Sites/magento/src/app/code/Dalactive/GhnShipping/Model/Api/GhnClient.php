<?php

namespace Dalactive\GhnShipping\Model\Api;

use Dalactive\GhnShipping\Logger\Logger;
use Dalactive\GhnShipping\Model\Config;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;

class GhnClient
{
    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly Logger $logger,
        private readonly CacheInterface $cache
    ) {
    }

    public function getAvailableServices(array $payload, ?int $storeId = null): array
    {
        return $this->cachedRequest('services_' . md5($this->json->serialize($payload)), 'POST', '/v2/shipping-order/available-services', $payload, $storeId);
    }

    public function calculateFee(array $payload, ?int $storeId = null): array
    {
        return $this->request('POST', '/v2/shipping-order/fee', $payload, $storeId);
    }

    public function getDistricts(?int $provinceId = null, ?int $storeId = null): array
    {
        $payload = $provinceId ? ['province_id' => $provinceId] : [];
        return $this->cachedRequest('districts_' . ($provinceId ?: 'all'), 'GET', '/master-data/district', $payload, $storeId);
    }

    public function getWards(int $districtId, ?int $storeId = null): array
    {
        return $this->cachedRequest('wards_' . $districtId, 'GET', '/master-data/ward', ['district_id' => $districtId], $storeId);
    }

    public function getProvinces(?int $storeId = null): array
    {
        return $this->cachedRequest('provinces', 'GET', '/master-data/province', [], $storeId);
    }

    public function clearAddressCache(): void
    {
        $this->cache->clean(['DALACTIVE_GHN_ADDRESS']);
    }

    private function cachedRequest(string $cacheKey, string $method, string $path, array $payload, ?int $storeId = null): array
    {
        $key = 'dalactive_ghn_' . $cacheKey;
        $cached = $this->cache->load($key);
        if ($cached) {
            $data = $this->json->unserialize($cached);
            return is_array($data) ? $data : [];
        }

        $response = $this->request($method, $path, $payload, $storeId);
        $this->cache->save(
            $this->json->serialize($response),
            $key,
            ['DALACTIVE_GHN_ADDRESS'],
            $this->config->getCacheTtl($storeId)
        );

        return $response;
    }

    private function request(string $method, string $path, array $payload, ?int $storeId = null): array
    {
        $token = $this->config->getToken($storeId);
        $baseUrl = $this->config->getBaseUrl($storeId);
        $url = $baseUrl . $path;
        $curl = $this->curlFactory->create();

        if ($token === '') {
            throw new \RuntimeException('GHN token is not configured.');
        }

        $curl->setTimeout(8);
        $curl->addHeader('Content-Type', 'application/json');
        $curl->addHeader('Token', $token);

        $shopId = $this->config->getInt('shop_id', 0, $storeId);
        if ($shopId > 0 && $path === '/v2/shipping-order/fee') {
            $curl->addHeader('ShopId', (string)$shopId);
        }

        if ($this->config->isDebug($storeId)) {
            $this->logger->debug('GHN request', [
                'method' => $method,
                'url' => $url,
                'payload' => $payload,
                'token' => $this->mask($token),
            ]);
        }

        $body = $this->json->serialize($payload);
        if ($method === 'GET') {
            $curl->get($url . ($payload ? '?' . http_build_query($payload) : ''));
        } else {
            $curl->post($url, $body);
        }

        $status = $curl->getStatus();
        $responseBody = (string)$curl->getBody();
        $response = [];

        if ($responseBody !== '') {
            $response = $this->json->unserialize($responseBody);
        }

        if ($this->config->isDebug($storeId)) {
            $this->logger->debug('GHN response', [
                'status' => $status,
                'body' => $response,
            ]);
        }

        if ($status < 200 || $status >= 300) {
            $message = is_array($response) ? (string)($response['message'] ?? '') : '';
            throw new \RuntimeException(trim('GHN API returned HTTP ' . $status . ' ' . $message));
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
