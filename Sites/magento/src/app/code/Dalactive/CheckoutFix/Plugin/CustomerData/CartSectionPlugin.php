<?php

namespace Dalactive\CheckoutFix\Plugin\CustomerData;

use Magento\Checkout\CustomerData\Cart;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class CartSectionPlugin
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function aroundGetSectionData(Cart $subject, callable $proceed): array
    {
        try {
            return $proceed();
        } catch (NoSuchEntityException $exception) {
            $this->logger->warning(
                'Customer cart section requested with an invalid or empty quote id.',
                ['exception' => $exception->getMessage()]
            );

            return [
                'summary_count' => 0,
                'subtotalAmount' => 0,
                'subtotal' => 0,
                'possible_onepage_checkout' => false,
                'items' => [],
                'extra_actions' => '',
                'isGuestCheckoutAllowed' => true,
                'website_id' => null,
                'storeId' => null,
            ];
        }
    }
}
