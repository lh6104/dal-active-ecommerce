<?php

namespace Dalactive\ExchangeRate\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ExchangeRate extends Template
{
    protected $curl;
    protected $logger;
    protected $cache;
    protected $scopeConfig;
    protected $cacheKeyPrefix = 'exchange_rate_vietcombank_';

    public function __construct(
        Template\Context $context,
        Curl $curl,
        LoggerInterface $logger,
        CacheInterface $cache,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->curl = $curl;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * Get Exchange Rates from Vietcombank
     *
     * @return array
     */
    public function getExchangeRates()
    {
        $cacheKey = $this->cacheKeyPrefix . 'vietcombank_rates';
        $cachedData = $this->cache->load($cacheKey);

        if ($cachedData) {
            return unserialize($cachedData);
        }

        try {
            $xmlUrl = $this->scopeConfig->getValue('exchangerate/api/api_endpoint');
            if (!$xmlUrl) {
                $xmlUrl = 'https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=68';
            }

            $this->curl->setHeaders([
                'User-Agent' => 'DalactiveExchangeRateModule/1.0 (https://dalactive.test)'
            ]);
            $this->curl->setOption(\CURLOPT_CONNECTTIMEOUT, 1);
            $this->curl->setOption(\CURLOPT_TIMEOUT, 1);

            $this->curl->get($xmlUrl);
            $response = $this->curl->getBody();

            if (empty($response)) {
                throw new \Exception('Empty response from API endpoint.');
            }

            $xml = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOCDATA);

            if ($xml === false) {
                throw new \Exception('Failed to parse XML response.');
            }

            $exchangeRates = [];

            foreach ($xml->Exrate as $exrate) {
                $currencyCode = (string) $exrate['CurrencyCode'];
                $buy = (string) $exrate['Buy'];
                $transfer = (string) $exrate['Transfer'];
                $sell = (string) $exrate['Sell'];

                $exchangeRates[$currencyCode] = [
                    'buy' => $buy !== '-' ? floatval(str_replace(',', '', $buy)) : null,
                    'transfer' => $transfer !== '-' ? floatval(str_replace(',', '', $transfer)) : null,
                    'sell' => $sell !== '-' ? floatval(str_replace(',', '', $sell)) : null,
                ];
            }

            $cacheTtl = (int) $this->scopeConfig->getValue('exchangerate/api/cache_ttl');
            if (!$cacheTtl) {
                $cacheTtl = 3600;
            }

            $this->cache->save(serialize($exchangeRates), $cacheKey, [], $cacheTtl);

            return $exchangeRates;
        } catch (\Exception $e) {
            $this->logger->error('ExchangeRate API Error: ' . $e->getMessage());
            return $this->getFallbackExchangeRates();
        }

        return [];
    }

    private function getFallbackExchangeRates()
    {
        return [
            'USD' => ['buy' => 25250.00, 'transfer' => 25280.00, 'sell' => 25580.00],
            'EUR' => ['buy' => 28750.00, 'transfer' => 28840.00, 'sell' => 30010.00],
            'GBP' => ['buy' => 33790.00, 'transfer' => 34130.00, 'sell' => 35225.00],
            'JPY' => ['buy' => 169.50, 'transfer' => 171.20, 'sell' => 179.40],
            'AUD' => ['buy' => 16470.00, 'transfer' => 16585.00, 'sell' => 17115.00],
            'SGD' => ['buy' => 19565.00, 'transfer' => 19705.00, 'sell' => 20340.00],
        ];
    }

    /**
     * Get Specific Exchange Rate by Currency Code
     *
     * @param string $currencyCode
     * @return array|null
     */
    public function getExchangeRateByCurrency($currencyCode)
    {
        $rates = $this->getExchangeRates();
        $currencyCode = strtoupper($currencyCode);

        if (isset($rates[$currencyCode])) {
            return $rates[$currencyCode];
        }

        return null;
    }

    /**
     * Compact exchange rates for homepage strip.
     *
     * @return array<string, array<string, float|null>>
     */
    public function getHomepageRates(): array
    {
        $preferred = ['USD', 'EUR', 'GBP', 'JPY', 'SGD'];
        $rates = $this->getExchangeRates();
        $homepageRates = [];

        foreach ($preferred as $currencyCode) {
            if (!empty($rates[$currencyCode])) {
                $homepageRates[$currencyCode] = $rates[$currencyCode];
            }
        }

        if (!empty($homepageRates)) {
            return $homepageRates;
        }

        return array_slice($rates, 0, 5, true);
    }

    /**
     * Full exchange rate page URL.
     *
     * @return string
     */
    public function getViewAllUrl(): string
    {
        return $this->getUrl('exchangerate/index/index');
    }

    /**
     * Format VND rate for display.
     *
     * @param float|null $value
     * @return string
     */
    public function formatRate($value): string
    {
        if ($value === null) {
            return '-';
        }

        return number_format((float) $value, $value >= 1000 ? 0 : 2, '.', ',');
    }
}
