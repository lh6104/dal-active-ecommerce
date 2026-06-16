<?php

declare(strict_types=1);

namespace Dalactive\CheckoutFix\Plugin\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\EstimateAddressInterface;
use Magento\Quote\Model\ShippingMethodManagement;
use Psr\Log\LoggerInterface;

class ShippingMethodAddressReferencePlugin
{
    public function __construct(
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly AddressInterfaceFactory $addressFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function beforeEstimateByExtendedAddress(
        ShippingMethodManagement $subject,
        $cartId,
        AddressInterface $address
    ): array {
        $this->normalizeAddress((int)$cartId, $address);

        return [$cartId, $address];
    }

    public function beforeEstimateByAddress(
        ShippingMethodManagement $subject,
        $cartId,
        EstimateAddressInterface $address
    ): array {
        $this->normalizeAddress((int)$cartId, $address);

        return [$cartId, $address];
    }

    public function aroundEstimateByAddressId(
        ShippingMethodManagement $subject,
        callable $proceed,
        $cartId,
        $addressId
    ): array {
        $quote = $this->quoteRepository->getActive((int)$cartId);
        $validAddressId = $this->resolveValidCustomerAddressId($quote, (int)$addressId);

        if ($validAddressId) {
            return $proceed($cartId, $validAddressId);
        }

        $this->logger->warning(
            'Checkout shipping estimation received an invalid customer address id.',
            [
                'quote_id' => $quote->getId(),
                'quote_customer_id' => $quote->getCustomerId() ?: null,
                'customer_address_id' => (int)$addressId,
            ]
        );

        try {
            $fallbackAddress = $quote->getShippingAddress()->getCountryId()
                ? $quote->getShippingAddress()
                : $this->createDefaultFallbackAddress();

            return $subject->estimateByExtendedAddress($cartId, $fallbackAddress);
        } catch (LocalizedException) {
            return [];
        }
    }

    private function normalizeAddress(int $cartId, object $address): void
    {
        $quote = $this->quoteRepository->getActive($cartId);
        $quoteCustomerId = (int)$quote->getCustomerId();

        if (method_exists($address, 'getCustomerAddressId') && method_exists($address, 'setCustomerAddressId')) {
            $customerAddressId = (int)$address->getCustomerAddressId();
            if ($customerAddressId && !$this->addressBelongsToQuoteCustomer($quote, $customerAddressId)) {
                $address->setCustomerAddressId(null);
            }
        }

        if (method_exists($address, 'setCustomerId')) {
            $address->setCustomerId($quoteCustomerId ?: null);
        }
    }

    private function resolveValidCustomerAddressId(object $quote, int $addressId): int
    {
        if (!$addressId) {
            return 0;
        }

        if ($this->addressBelongsToQuoteCustomer($quote, $addressId)) {
            return $addressId;
        }

        $customer = $quote->getCustomer();
        foreach ((array)($customer ? $customer->getAddresses() : []) as $address) {
            return (int)$address->getId();
        }

        return 0;
    }

    private function createDefaultFallbackAddress(): AddressInterface
    {
        $address = $this->addressFactory->create();
        $address->setCountryId('VN');
        $address->setRegion('Ha Noi');
        $address->setCity('Ha Noi');
        $address->setPostcode('11300');
        $address->setStreet(['144 Xuan Thuy, Cau Giay']);
        $address->setFirstname('Khach');
        $address->setLastname('Hang');
        $address->setTelephone('0123456789');

        return $address;
    }

    private function addressBelongsToQuoteCustomer(object $quote, int $customerAddressId): bool
    {
        $customer = $quote->getCustomer();
        if (!$customer || !$customer->getId()) {
            return false;
        }

        foreach ((array)$customer->getAddresses() as $address) {
            if ((int)$address->getId() === $customerAddressId) {
                return true;
            }
        }

        return false;
    }
}
