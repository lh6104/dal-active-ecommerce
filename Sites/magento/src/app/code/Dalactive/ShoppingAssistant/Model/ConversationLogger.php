<?php

namespace Dalactive\ShoppingAssistant\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ResourceConnection;

class ConversationLogger
{
    private ResourceConnection $resource;
    private CustomerSession $customerSession;
    private Config $config;

    public function __construct(
        ResourceConnection $resource,
        CustomerSession $customerSession,
        Config $config
    ) {
        $this->resource = $resource;
        $this->customerSession = $customerSession;
        $this->config = $config;
    }

    public function log(string $sessionId, string $userMessage, array $response, int $responseTimeMs, ?string $error = null): void
    {
        if (!$this->config->isLoggingEnabled()) {
            return;
        }

        $this->resource->getConnection()->insert(
            $this->resource->getTableName('dalactive_chatbot_conversation_log'),
            [
                'session_id' => $sessionId,
                'customer_id' => $this->customerSession->isLoggedIn() ? (int)$this->customerSession->getCustomerId() : null,
                'user_message' => mb_substr($userMessage, 0, 2000),
                'bot_response' => mb_substr((string)($response['message'] ?? ''), 0, 4000),
                'intent' => (string)($response['intent'] ?? ''),
                'used_ai' => !empty($response['used_ai']) ? 1 : 0,
                'model' => (string)($response['model'] ?? ''),
                'response_time_ms' => $responseTimeMs,
                'error_message' => $error ? mb_substr($error, 0, 2000) : null,
            ]
        );
    }

    public function countRecentMessages(string $sessionId, int $seconds = 300): int
    {
        $connection = $this->resource->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('dalactive_chatbot_conversation_log'), ['cnt' => 'COUNT(*)'])
                ->where('session_id = ?', $sessionId)
                ->where('created_at >= ?', gmdate('Y-m-d H:i:s', time() - $seconds))
        );
    }
}
