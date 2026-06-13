<?php

namespace Dalactive\GhtkShipping\Controller\Webhook;

use Dalactive\GhtkShipping\Logger\Logger;
use Dalactive\GhtkShipping\Model\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class Status implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly Logger $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $expectedHash = $this->config->getWebhookHash();

        if ($expectedHash !== '') {
            $incomingHash = trim((string)$this->request->getParam('hash'));
            if (!hash_equals($expectedHash, $incomingHash)) {
                $this->logger->warning('GHTK webhook unauthorized', [
                    'remote_addr' => $this->request->getServer('REMOTE_ADDR'),
                ]);

                return $result
                    ->setHttpResponseCode(401)
                    ->setData(['ok' => false, 'message' => 'Unauthorized']);
            }
        }

        $payload = $this->request->getParams();
        unset($payload['hash']);

        $this->logger->info('GHTK webhook status received', [
            'payload' => $payload,
            'remote_addr' => $this->request->getServer('REMOTE_ADDR'),
        ]);

        return $result->setData(['ok' => true, 'message' => 'received']);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
