<?php

namespace Dalactive\GhnShipping\Controller\Address;

use Dalactive\GhnShipping\Logger\Logger;
use Dalactive\GhnShipping\Model\Api\GhnClient;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

abstract class AbstractAddress
{
    public function __construct(
        protected readonly GhnClient $client,
        protected readonly JsonFactory $jsonFactory,
        protected readonly RequestInterface $request,
        protected readonly Logger $logger
    ) {
    }

    protected function success(array $items): Json
    {
        return $this->jsonFactory->create()->setData([
            'ok' => true,
            'items' => $items,
        ]);
    }

    protected function failure(\Throwable $exception): Json
    {
        $this->logger->warning('GHN address proxy failed', [
            'controller' => static::class,
            'message' => $exception->getMessage(),
        ]);

        return $this->jsonFactory->create()
            ->setHttpResponseCode(503)
            ->setData([
                'ok' => false,
                'message' => 'GHN address data is temporarily unavailable.',
            ]);
    }
}
