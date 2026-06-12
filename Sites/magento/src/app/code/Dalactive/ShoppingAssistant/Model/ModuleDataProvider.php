<?php

namespace Dalactive\ShoppingAssistant\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ModuleDataProvider
{
    private ResourceConnection $resource;
    private Curl $curl;
    private ScopeConfigInterface $scopeConfig;
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resource,
        Curl $curl,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function getStores(int $limit = 3): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('dalactive_storelocator_store');

        if (!$connection->isTableExists($table)) {
            return [];
        }

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['name', 'address', 'city', 'region', 'phone', 'opening_hours', 'google_maps_url'])
                ->where('is_active = ?', 1)
                ->order(['sort_order ASC', 'name ASC'])
                ->limit($limit)
        );

        return array_map(static function (array $row): array {
            return [
                'title' => (string)$row['name'],
                'subtitle' => trim((string)($row['city'] ?: $row['region'])),
                'description' => (string)$row['address'],
                'meta' => trim((string)($row['opening_hours'] ?: $row['phone'])),
                'url' => (string)($row['google_maps_url'] ?: '/find-a-store'),
            ];
        }, $rows);
    }

    public function getEconomicNews(int $limit = 3): array
    {
        $rssUrl = (string)$this->scopeConfig->getValue('economicnews/rss/rss_feed_url');
        if ($rssUrl === '') {
            $rssUrl = 'https://vnexpress.net/rss/kinh-doanh.rss';
        }

        try {
            $this->curl->setHeaders(['User-Agent' => 'DalactiveShoppingAssistant/1.0']);
            $this->curl->setOption(\CURLOPT_CONNECTTIMEOUT, 2);
            $this->curl->setOption(\CURLOPT_TIMEOUT, 5);
            $this->curl->setOption(\CURLOPT_FOLLOWLOCATION, true);
            $this->curl->setOption(\CURLOPT_IPRESOLVE, \CURL_IPRESOLVE_V4);
            $this->curl->get($rssUrl);
            $xml = simplexml_load_string($this->curl->getBody());
            if (!$xml || empty($xml->channel->item)) {
                return $this->getFallbackNews();
            }

            $items = [];
            foreach ($xml->channel->item as $item) {
                $description = (string)$item->description;
                $items[] = [
                    'title' => trim(strip_tags((string)$item->title)),
                    'subtitle' => $this->formatDate((string)$item->pubDate),
                    'description' => trim(preg_replace('/\s+/', ' ', strip_tags($description))),
                    'image' => $this->extractImage($description),
                    'url' => trim(strip_tags((string)$item->link)),
                ];

                if (count($items) >= $limit) {
                    break;
                }
            }

            return $items ?: $this->getFallbackNews();
        } catch (\Throwable $e) {
            $this->logger->warning('ShoppingAssistant news fetch failed: ' . $e->getMessage());
            return $this->getFallbackNews();
        }
    }

    public function getExchangeRates(int $limit = 6): array
    {
        $endpoint = (string)$this->scopeConfig->getValue('exchangerate/api/api_endpoint');
        if ($endpoint === '') {
            $endpoint = 'https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=68';
        }

        try {
            $this->curl->setHeaders(['User-Agent' => 'DalactiveShoppingAssistant/1.0']);
            $this->curl->setOption(\CURLOPT_CONNECTTIMEOUT, 2);
            $this->curl->setOption(\CURLOPT_TIMEOUT, 5);
            $this->curl->setOption(\CURLOPT_IPRESOLVE, \CURL_IPRESOLVE_V4);
            $this->curl->get($endpoint);
            $xml = simplexml_load_string($this->curl->getBody());
            if (!$xml) {
                return $this->getFallbackRates();
            }

            $priority = ['USD', 'EUR', 'GBP', 'JPY', 'AUD', 'SGD'];
            $rates = [];
            foreach ($xml->Exrate as $rate) {
                $code = strtoupper((string)$rate['CurrencyCode']);
                if (!in_array($code, $priority, true)) {
                    continue;
                }

                $rates[$code] = [
                    'title' => $code,
                    'subtitle' => 'Vietcombank',
                    'description' => 'Mua: ' . (string)$rate['Buy'] . ' | Chuyển khoản: ' . (string)$rate['Transfer'] . ' | Bán: ' . (string)$rate['Sell'],
                    'url' => '/exchangerate/index/index/',
                ];
            }

            $ordered = [];
            foreach ($priority as $code) {
                if (isset($rates[$code])) {
                    $ordered[] = $rates[$code];
                }
            }

            return array_slice($ordered, 0, $limit) ?: $this->getFallbackRates();
        } catch (\Throwable $e) {
            $this->logger->warning('ShoppingAssistant exchange fetch failed: ' . $e->getMessage());
            return $this->getFallbackRates();
        }
    }

    public function getWeather(string $city = ''): array
    {
        $city = trim($city) ?: (string)$this->scopeConfig->getValue('weather/api/city') ?: 'HaNoi';
        $apiKey = (string)$this->scopeConfig->getValue('weather/api/api_key');

        if ($apiKey !== '') {
            try {
                $this->curl->setHeaders(['User-Agent' => 'DalactiveShoppingAssistant/1.0']);
                $this->curl->setOption(\CURLOPT_CONNECTTIMEOUT, 2);
                $this->curl->setOption(\CURLOPT_TIMEOUT, 5);
                $this->curl->get('https://api.openweathermap.org/data/2.5/weather?q=' . urlencode($city) . '&units=metric&appid=' . $apiKey);
                $data = json_decode($this->curl->getBody(), true);
                if (isset($data['cod']) && (int)$data['cod'] === 200) {
                    return [$this->formatWeather($data)];
                }
            } catch (\Throwable $e) {
                $this->logger->warning('ShoppingAssistant weather fetch failed: ' . $e->getMessage());
            }
        }

        $fallback = [
            'name' => $city,
            'sys' => ['country' => 'VN'],
            'main' => ['temp' => 31, 'humidity' => 72],
            'weather' => [['description' => 'partly cloudy']],
            'wind' => ['speed' => 2.8],
        ];

        return [$this->formatWeather($fallback)];
    }

    private function formatWeather(array $data): array
    {
        return [
            'title' => (string)($data['name'] ?? 'Thời tiết'),
            'subtitle' => (string)($data['sys']['country'] ?? ''),
            'description' => round((float)($data['main']['temp'] ?? 0)) . '°C, '
                . (string)($data['weather'][0]['description'] ?? 'đang cập nhật')
                . '. Độ ẩm ' . (int)($data['main']['humidity'] ?? 0) . '%, gió '
                . (float)($data['wind']['speed'] ?? 0) . ' m/s.',
            'url' => '/weather/index/index/',
        ];
    }

    private function getFallbackNews(): array
    {
        return [
            [
                'title' => 'Tin kinh tế đang được cập nhật',
                'subtitle' => 'DAL Active',
                'description' => 'Bạn có thể mở trang Tin tức để xem danh sách bài viết mới nhất.',
                'image' => '',
                'url' => '/economicnews/index/index/',
            ],
        ];
    }

    private function getFallbackRates(): array
    {
        return [
            ['title' => 'USD', 'subtitle' => 'Tham khảo', 'description' => 'Mở trang Tỷ giá để xem dữ liệu chi tiết.', 'url' => '/exchangerate/index/index/'],
            ['title' => 'EUR', 'subtitle' => 'Tham khảo', 'description' => 'Mở trang Tỷ giá để xem dữ liệu chi tiết.', 'url' => '/exchangerate/index/index/'],
        ];
    }

    private function formatDate(string $date): string
    {
        $timestamp = strtotime($date);
        return $timestamp ? date('d/m/Y H:i', $timestamp) : '';
    }

    private function extractImage(string $html): string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $match)) {
            return (string)$match[1];
        }

        return '';
    }
}
