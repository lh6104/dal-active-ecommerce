<?php
declare(strict_types=1);

namespace Dalactive\ExchangeRate\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class RateProvider
{
    private const CACHE_KEY = 'dalactive_exchange_rate_vietcombank_rates';
    private const CACHE_TAG = 'DALACTIVE_EXCHANGE_RATE';
    private const DEFAULT_ENDPOINT = 'https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=68';
    private const DEFAULT_TTL = 3600;

    private const DISPLAY_CURRENCIES = [
        'VND' => 'Vietnamese Dong',
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'JPY' => 'Japanese Yen',
        'KRW' => 'Korean Won',
    ];

    private Curl $curl;
    private LoggerInterface $logger;
    private CacheInterface $cache;
    private ScopeConfigInterface $scopeConfig;
    private Json $json;

    public function __construct(
        Curl $curl,
        LoggerInterface $logger,
        CacheInterface $cache,
        ScopeConfigInterface $scopeConfig,
        Json $json
    ) {
        $this->curl = $curl;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->scopeConfig = $scopeConfig;
        $this->json = $json;
    }

    public function getDisplayCurrencies(): array
    {
        return self::DISPLAY_CURRENCIES;
    }

    public function getExchangeRates(): array
    {
        $cachedData = $this->cache->load(self::CACHE_KEY);

        if ($cachedData) {
            try {
                $rates = $this->json->unserialize($cachedData);
                return is_array($rates) ? $rates : $this->getFallbackExchangeRates();
            } catch (\InvalidArgumentException $exception) {
                $this->logger->warning('ExchangeRate cache decode error: ' . $exception->getMessage());
            }
        }

        try {
            $rates = $this->fetchExchangeRates();
            $this->cache->save(
                $this->json->serialize($rates),
                self::CACHE_KEY,
                [self::CACHE_TAG],
                $this->getCacheTtl()
            );

            return $rates;
        } catch (\Throwable $exception) {
            $this->logger->error('ExchangeRate API Error: ' . $exception->getMessage());
            return $this->getFallbackExchangeRates();
        }
    }

    public function getExchangeRateByCurrency(string $currencyCode): ?array
    {
        $currencyCode = strtoupper($currencyCode);
        $rates = $this->getExchangeRates();

        return $rates[$currencyCode] ?? null;
    }

    public function getConversionRates(): array
    {
        $sourceRates = $this->getExchangeRates();
        $conversionRates = ['VND' => 1.0];

        foreach (array_keys(self::DISPLAY_CURRENCIES) as $currencyCode) {
            if ($currencyCode === 'VND' || empty($sourceRates[$currencyCode])) {
                continue;
            }

            $referenceRate = $this->resolveReferenceRate($sourceRates[$currencyCode]);
            if ($referenceRate !== null && $referenceRate > 0) {
                $conversionRates[$currencyCode] = $referenceRate;
            }
        }

        return $conversionRates;
    }

    public function isCurrencyAvailable(string $currencyCode): bool
    {
        $currencyCode = strtoupper($currencyCode);

        if ($currencyCode === 'VND') {
            return true;
        }

        return isset($this->getConversionRates()[$currencyCode]);
    }

    public function getFallbackExchangeRates(): array
    {
        return [
            'VND' => ['buy' => 1.0, 'transfer' => 1.0, 'sell' => 1.0],
            'USD' => ['buy' => 25250.0, 'transfer' => 25280.0, 'sell' => 25580.0],
            'EUR' => ['buy' => 28750.0, 'transfer' => 28840.0, 'sell' => 30010.0],
            'JPY' => ['buy' => 169.5, 'transfer' => 171.2, 'sell' => 179.4],
            'KRW' => ['buy' => 18.0, 'transfer' => 18.8, 'sell' => 19.8],
        ];
    }

    private function fetchExchangeRates(): array
    {
        $xmlUrl = (string) $this->scopeConfig->getValue(
            'exchangerate/api/api_endpoint',
            ScopeInterface::SCOPE_STORE
        );

        if ($xmlUrl === '') {
            $xmlUrl = self::DEFAULT_ENDPOINT;
        }

        $this->curl->setHeaders([
            'User-Agent' => 'DalactiveExchangeRateModule/1.0 (https://dalactive.test)',
        ]);
        $this->curl->setOption(\CURLOPT_CONNECTTIMEOUT, 5);
        $this->curl->setOption(\CURLOPT_TIMEOUT, 10);
        $this->curl->get($xmlUrl);

        $response = $this->curl->getBody();
        if (!$response) {
            throw new \RuntimeException('Empty response from exchange rate endpoint.');
        }

        $xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false || !isset($xml->Exrate)) {
            throw new \RuntimeException('Invalid exchange rate XML response.');
        }

        $exchangeRates = [];
        foreach ($xml->Exrate as $exrate) {
            $currencyCode = strtoupper((string) $exrate['CurrencyCode']);
            if ($currencyCode === '') {
                continue;
            }

            $exchangeRates[$currencyCode] = [
                'buy' => $this->normaliseRate((string) $exrate['Buy']),
                'transfer' => $this->normaliseRate((string) $exrate['Transfer']),
                'sell' => $this->normaliseRate((string) $exrate['Sell']),
            ];
        }

        if (!$exchangeRates) {
            throw new \RuntimeException('Exchange rate XML did not contain usable rates.');
        }

        return $exchangeRates;
    }

    private function getCacheTtl(): int
    {
        $cacheTtl = (int) $this->scopeConfig->getValue(
            'exchangerate/api/cache_ttl',
            ScopeInterface::SCOPE_STORE
        );

        return $cacheTtl > 0 ? $cacheTtl : self::DEFAULT_TTL;
    }

    private function normaliseRate(string $value): ?float
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $number = (float) str_replace(',', '', $value);

        return $number > 0 ? $number : null;
    }

    private function resolveReferenceRate(array $rate): ?float
    {
        foreach (['sell', 'transfer', 'buy'] as $field) {
            if (!empty($rate[$field]) && (float) $rate[$field] > 0) {
                return (float) $rate[$field];
            }
        }

        return null;
    }
}
