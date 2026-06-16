<?php

declare(strict_types=1);

namespace Dalactive\CheckoutFix\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Psr\Log\LoggerInterface;

class ShippingAddressReferencePlugin
{
    public function __construct(
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function beforeSaveAddressInformation(
        ShippingInformationManagement $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ): array {
        $quote = $this->quoteRepository->getActive((int)$cartId);

        $this->normalizeAddress($quote, $addressInformation->getShippingAddress(), 'shipping');
        $this->normalizeAddress($quote, $addressInformation->getBillingAddress(), 'billing');

        return [$cartId, $addressInformation];
    }

    private function normalizeAddress(CartInterface $quote, ?AddressInterface $address, string $type): void
    {
        if (!$address) {
            return;
        }

        $quoteCustomerId = (int)$quote->getCustomerId();
        $customerAddressId = (int)$address->getCustomerAddressId();

        if ($customerAddressId && (!$quoteCustomerId || !$this->addressBelongsToQuoteCustomer($quote, $customerAddressId))) {
            $this->logger->warning(
                'Removed stale customer address reference from checkout address.',
                [
                    'address_type' => $type,
                    'quote_id' => $quote->getId(),
                    'quote_customer_id' => $quoteCustomerId ?: null,
                    'customer_address_id' => $customerAddressId,
                ]
            );
            $address->setCustomerAddressId(null);
        }

        if (method_exists($address, 'setCustomerId')) {
            $address->setCustomerId($quoteCustomerId ?: null);
        }

        $quoteAddressId = $address->getId();
        if ($quoteAddressId !== null && $quote->getAddressById((int)$quoteAddressId) === false && method_exists($address, 'setId')) {
            $address->setId(null);
        }
    }

    private function addressBelongsToQuoteCustomer(CartInterface $quote, int $customerAddressId): bool
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
