<?php

namespace Dalactive\ShoppingAssistant\Controller\Chat;

use Dalactive\ShoppingAssistant\Api\ChatbotServiceInterface;
use Dalactive\ShoppingAssistant\Model\Config;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;

class Send implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private Json $json;
    private Config $config;
    private ChatbotServiceInterface $chatbotService;
    private SessionManagerInterface $session;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Json $json,
        Config $config,
        ChatbotServiceInterface $chatbotService,
        SessionManagerInterface $session,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->json = $json;
        $this->config = $config;
        $this->chatbotService = $chatbotService;
        $this->session = $session;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setData([
                'success' => false,
                'message' => 'Trợ lý mua sắm hiện đang tạm tắt.',
                'code' => 'MODULE_DISABLED',
            ]);
        }

        try {
            $payload = $this->getPayload();
            $message = trim((string)($payload['message'] ?? ''));
            $maxLength = $this->config->getMaxMessageLength();

            if ($message === '') {
                return $result->setHttpResponseCode(400)->setData([
                    'success' => false,
                    'message' => 'Bạn vui lòng nhập nội dung cần hỗ trợ nhé.',
                    'code' => 'EMPTY_MESSAGE',
                ]);
            }

            if (mb_strlen($message) > $maxLength) {
                return $result->setHttpResponseCode(400)->setData([
                    'success' => false,
                    'message' => sprintf('Tin nhắn quá dài. Bạn vui lòng nhập tối đa %d ký tự nhé.', $maxLength),
                    'code' => 'MESSAGE_TOO_LONG',
                ]);
            }

            return $result->setData($this->chatbotService->respond($message, [
                'session_id' => $this->session->getSessionId(),
                'quick_action' => $payload['quick_action'] ?? null,
                'suggestion_id' => $payload['suggestion_id'] ?? null,
            ]));
        } catch (\Throwable $e) {
            $this->logger->error('Shopping assistant error: ' . $e->getMessage());
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => 'Trợ lý đang gặp lỗi tạm thời. Bạn vui lòng thử lại sau nhé.',
                'code' => 'SERVER_ERROR',
            ]);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function getPayload(): array
    {
        $content = (string)$this->request->getContent();
        if ($content !== '') {
            try {
                $decoded = $this->json->unserialize($content);
                return is_array($decoded) ? $decoded : [];
            } catch (\InvalidArgumentException $e) {
                return [];
            }
        }

        return $this->request->getPostValue() ?: [];
    }
}
