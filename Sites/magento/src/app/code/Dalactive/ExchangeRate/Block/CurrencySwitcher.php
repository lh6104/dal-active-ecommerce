<?php
declare(strict_types=1);

namespace Dalactive\ExchangeRate\Block;

use Dalactive\ExchangeRate\Model\RateProvider;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class CurrencySwitcher extends Template
{
    private RateProvider $rateProvider;
    private Json $json;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Template\Context $context,
        RateProvider $rateProvider,
        Json $json,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->rateProvider = $rateProvider;
        $this->json = $json;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    public function getCurrencies(): array
    {
        $rates = $this->rateProvider->getConversionRates();
        $currencies = [];

        foreach ($this->rateProvider->getDisplayCurrencies() as $code => $label) {
            $currencies[$code] = [
                'code' => $code,
                'label' => $label,
                'rate' => $rates[$code] ?? null,
                'available' => $code === 'VND' || isset($rates[$code]),
            ];
        }

        return $currencies;
    }

    public function getJsonConfig(): string
    {
        $currencies = $this->getCurrencies();
        $rates = [];
        $labels = [];
        $available = [];

        foreach ($currencies as $code => $currency) {
            $labels[$code] = $currency['label'];
            $available[$code] = (bool) $currency['available'];
            if ($currency['rate'] !== null) {
                $rates[$code] = (float) $currency['rate'];
            }
        }

        return $this->json->serialize([
            'defaultCurrency' => 'VND',
            'storageKey' => 'dalactive_display_currency',
            'cookieName' => 'dalactive_display_currency',
            'currencies' => $labels,
            'available' => $available,
            'rates' => $rates,
            'checkoutNote' => (string) __('Thanh toán cuối cùng vẫn bằng VND.'),
        ]);
    }

    public function getStoreOptions(): array
    {
        $stores = [];
        $currentStoreId = (int) $this->storeManager->getStore()->getId();

        foreach ($this->storeManager->getWebsite()->getStores() as $store) {
            if (!$store->isActive()) {
                continue;
            }

            $stores[] = [
                'id' => (int) $store->getId(),
                'name' => $store->getName(),
                'url' => $store->getBaseUrl(),
                'current' => (int) $store->getId() === $currentStoreId,
            ];
        }

        return $stores;
    }
}
