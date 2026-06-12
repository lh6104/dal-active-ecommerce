<?php

namespace Dalactive\ShoppingAssistant\Model\Groq;

use Dalactive\ShoppingAssistant\Model\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class GroqClient
{
    private Config $config;
    private Curl $curl;
    private Json $json;
    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        Curl $curl,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->curl = $curl;
        $this->json = $json;
        $this->logger = $logger;
    }

    public function generate(string $userMessage, array $context): ?string
    {
        if (!$this->config->isAiEnabled()) {
            return null;
        }

        try {
            return $this->chat($this->config->getSystemPrompt(), [
                'user_message' => $userMessage,
                'safe_context' => $context,
            ], $this->config->getTemperature(), $this->config->getMaxTokens());
        } catch (\Throwable $e) {
            $this->logger->error('Groq request exception: ' . $e->getMessage());
            return null;
        }
    }

    public function generateWithSystem(string $systemPrompt, array $payload, ?float $temperature = null, ?int $maxTokens = null): ?string
    {
        if (!$this->config->isAiEnabled()) {
            return null;
        }

        try {
            return $this->chat(
                $systemPrompt,
                $payload,
                $temperature ?? $this->config->getTemperature(),
                $maxTokens ?? $this->config->getMaxTokens()
            );
        } catch (\Throwable $e) {
            $this->logger->error('Groq request exception: ' . $e->getMessage());
            return null;
        }
    }

    private function chat(string $systemPrompt, array $payload, float $temperature, int $maxTokens): ?string
    {
        $request = [
            'model' => $this->config->getGroqModel(),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $this->json->serialize($payload)],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => false,
        ];

        $this->curl->setTimeout($this->config->getTimeout());
        $this->curl->addHeader('Authorization', 'Bearer ' . $this->config->getGroqApiKey());
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->post($this->config->getGroqEndpoint(), $this->json->serialize($request));

        if ($this->curl->getStatus() < 200 || $this->curl->getStatus() >= 300) {
            $this->logger->warning('Groq request failed', ['status' => $this->curl->getStatus()]);
            return null;
        }

        $response = $this->json->unserialize($this->curl->getBody());
        return $response['choices'][0]['message']['content'] ?? null;
    }
}
