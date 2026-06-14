<?php

namespace Dalactive\ExchangeRate\Block;

use Dalactive\ExchangeRate\Model\RateProvider;
use Magento\Framework\View\Element\Template;

class ExchangeRate extends Template
{
    private RateProvider $rateProvider;

    public function __construct(
        Template\Context $context,
        RateProvider $rateProvider,
        array $data = []
    ) {
        $this->rateProvider = $rateProvider;
        parent::__construct($context, $data);
    }

    /**
     * Get Exchange Rates from Vietcombank
     *
     * @return array
     */
    public function getExchangeRates()
    {
        return $this->rateProvider->getExchangeRates();
    }

    /**
     * Get Specific Exchange Rate by Currency Code
     *
     * @param string $currencyCode
     * @return array|null
     */
    public function getExchangeRateByCurrency($currencyCode)
    {
        return $this->rateProvider->getExchangeRateByCurrency((string) $currencyCode);
    }

    /**
     * Top 4 exchange rates for homepage strip.
     *
     * @return array<string, array<string, float|null>>
     */
    public function getHomepageRates(): array
    {
        $preferred = ['USD', 'EUR', 'GBP', 'JPY'];
        $rates = $this->getExchangeRates();
        $result = [];

        foreach ($preferred as $currencyCode) {
            if (!empty($rates[$currencyCode])) {
                $result[$currencyCode] = $rates[$currencyCode];
            }
        }

        return $result;
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
