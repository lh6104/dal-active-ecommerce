<?php

namespace Dalactive\Weather\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\CacheInterface as CacheManager;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Weather extends Template
{
    protected $curl;
    protected $logger;
    protected $cacheManager;
    protected $serializer;
    protected $scopeConfig;
    protected $useFallbackWeather = false;

    const CACHE_TAG = 'dalactive_weather_data';
    const CACHE_LIFETIME = 600; // 10 minutes
    const API_KEY_CONFIG_PATH = 'weather/api/api_key';
    const DEFAULT_CITY_CONFIG_PATH = 'weather/api/city';
    const MULTIPLE_CITIES_CONFIG_PATH = 'weather/api/cities';

    public function __construct(
        Template\Context $context,
        Curl $curl,
        LoggerInterface $logger,
        CacheManager $cacheManager,
        SerializerInterface $serializer,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->curl = $curl;
        $this->logger = $logger;
        $this->cacheManager = $cacheManager;
        $this->serializer = $serializer;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * @param string $city
     * @return array
     */
    public function getWeatherData($city = 'HaNoi')
    {
        if ($this->useFallbackWeather) {
            return $this->getFallbackWeatherData($city);
        }

        $cacheKey = self::CACHE_TAG . '_' . strtolower(str_replace(' ', '_', $city));
        $cachedData = $this->cacheManager->load($cacheKey);

        if ($cachedData) {
            $weatherData = $this->serializer->unserialize($cachedData);
            return $weatherData;
        }

        $apiKey = $this->scopeConfig->getValue(self::API_KEY_CONFIG_PATH);
        if (!$apiKey) {
            $this->logger->error('Weather API key is not configured in admin settings');
            return $this->getFallbackWeatherData($city);
        }
        $apiUrl = 'https://api.openweathermap.org/data/2.5/weather?q=' . urlencode($city) . '&units=metric&appid=' . $apiKey;

        try {
            $this->curl->setHeaders([
                'User-Agent' => 'DalactiveWeatherModule/1.0 (https://dalactive.test)'
            ]);
            $this->curl->setOption(\CURLOPT_CONNECTTIMEOUT, 1);
            $this->curl->setOption(\CURLOPT_TIMEOUT, 1);

            $this->curl->get($apiUrl);
            $response = $this->curl->getBody();
            $weatherData = json_decode($response, true);

            if ($weatherData === null) {
                throw new \Exception('Failed to decode JSON response: ' . json_last_error_msg());
            }

            if (isset($weatherData['cod']) && $weatherData['cod'] == 200) {
                // Serialize and save to cache
                $serializedData = $this->serializer->serialize($weatherData);
                $this->cacheManager->save($serializedData, $cacheKey, [self::CACHE_TAG], self::CACHE_LIFETIME);
                return $weatherData;
            } else {
                $errorMessage = isset($weatherData['message']) ? $weatherData['message'] : 'Unknown error';
                throw new \Exception('API Error: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            $this->logger->error('Weather API Error: ' . $e->getMessage());
            $this->useFallbackWeather = true;
            return $this->getFallbackWeatherData($city);
        }
    }

    /**
     * @param array $cities
     * @return array
     */
    public function getMultipleWeatherData(array $cities)
    {
        $allWeatherData = [];

        foreach ($cities as $city) {
            $data = $this->getWeatherData($city);
            if (!empty($data)) {
                $allWeatherData[] = $data;
            }
        }

        return $allWeatherData;
    }

    private function getFallbackWeatherData($city)
    {
        $fallback = [
            'hanoi' => ['name' => 'HaNoi', 'country' => 'VN', 'temp' => 31, 'description' => 'partly cloudy', 'humidity' => 72, 'wind' => 2.8],
            'ho chi minh city' => ['name' => 'Ho Chi Minh City', 'country' => 'VN', 'temp' => 32, 'description' => 'cloudy', 'humidity' => 76, 'wind' => 3.1],
            'bangkok' => ['name' => 'Bangkok', 'country' => 'TH', 'temp' => 33, 'description' => 'scattered clouds', 'humidity' => 70, 'wind' => 2.6],
            'singapore' => ['name' => 'Singapore', 'country' => 'SG', 'temp' => 30, 'description' => 'light rain', 'humidity' => 80, 'wind' => 3.4],
            'tokyo' => ['name' => 'Tokyo', 'country' => 'JP', 'temp' => 24, 'description' => 'clear sky', 'humidity' => 58, 'wind' => 2.1],
            'london' => ['name' => 'London', 'country' => 'GB', 'temp' => 17, 'description' => 'overcast clouds', 'humidity' => 68, 'wind' => 4.0],
            'new york' => ['name' => 'New York', 'country' => 'US', 'temp' => 22, 'description' => 'clear sky', 'humidity' => 55, 'wind' => 3.7],
        ];

        $normalizedCity = strtolower(trim($city));
        $data = $fallback[$normalizedCity] ?? [
            'name' => $city,
            'country' => '--',
            'temp' => 28,
            'description' => 'updating',
            'humidity' => 65,
            'wind' => 2.5,
        ];

        return [
            'name' => $data['name'],
            'sys' => ['country' => $data['country']],
            'main' => [
                'temp' => $data['temp'],
                'humidity' => $data['humidity'],
            ],
            'weather' => [
                ['description' => $data['description']],
            ],
            'wind' => ['speed' => $data['wind']],
        ];
    }

    /**
     * Get weather data for multiple cities from config
     */
    public function getMultipleCitiesWeather()
    {
        $citiesConfig = $this->scopeConfig->getValue('weather/api/cities');
        if (!$citiesConfig) {
            return [];
        }

        // Parse cities from newline-separated list
        $cities = array_filter(array_map('trim', explode("\n", $citiesConfig)));

        return $this->getMultipleWeatherData($cities);
    }

    /**
     * Simple weather icon from OpenWeather description.
     */
    public function getWeatherIcon(string $description): string
    {
        $desc = strtolower($description);

        if (str_contains($desc, 'thunder') || str_contains($desc, 'storm')) {
            return '⛈️';
        }
        if (str_contains($desc, 'rain') || str_contains($desc, 'drizzle')) {
            return '🌧️';
        }
        if (str_contains($desc, 'snow')) {
            return '❄️';
        }
        if (str_contains($desc, 'mist') || str_contains($desc, 'fog') || str_contains($desc, 'haze')) {
            return '🌫️';
        }
        if (str_contains($desc, 'clear')) {
            return '☀️';
        }
        if (str_contains($desc, 'few clouds')) {
            return '🌤️';
        }
        if (str_contains($desc, 'cloud')) {
            return '☁️';
        }

        return '🌡️';
    }
}
