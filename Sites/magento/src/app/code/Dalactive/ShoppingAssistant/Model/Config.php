<?php

namespace Dalactive\ShoppingAssistant\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH = 'dalactive_shoppingassistant/';

    private ScopeConfigInterface $scopeConfig;
    private EncryptorInterface $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    public function isEnabled(): bool
    {
        return $this->isSetFlag('general/enabled');
    }

    public function isWidgetEnabled(): bool
    {
        return $this->isEnabled() && $this->isSetFlag('general/widget_enabled');
    }

    public function isLoggingEnabled(): bool
    {
        return $this->isSetFlag('general/logging_enabled');
    }

    public function getMaxMessageLength(): int
    {
        return max(50, min(1000, (int)$this->getValue('general/max_message_length') ?: 500));
    }

    public function getBotName(): string
    {
        return (string)($this->getValue('general/bot_name') ?: 'DAL Assistant');
    }

    public function getWelcomeMessage(): string
    {
        return (string)$this->getValue('general/welcome_message');
    }

    public function getFallbackMessage(): string
    {
        return (string)($this->getValue('general/fallback_message') ?: 'Mình chưa tìm thấy thông tin chính xác cho yêu cầu này.');
    }

    public function isAiEnabled(): bool
    {
        return $this->isSetFlag('ai/enabled') && $this->getGroqApiKey() !== '';
    }

    public function getGroqApiKey(): string
    {
        $value = (string)$this->getValue('ai/api_key');
        return $value !== '' ? (string)$this->encryptor->decrypt($value) : '';
    }

    public function getGroqEndpoint(): string
    {
        return (string)($this->getValue('ai/endpoint') ?: 'https://api.groq.com/openai/v1/chat/completions');
    }

    public function getGroqModel(): string
    {
        return (string)($this->getValue('ai/model') ?: 'openai/gpt-oss-120b');
    }

    public function getTemperature(): float
    {
        return (float)($this->getValue('ai/temperature') ?: 0.3);
    }

    public function getMaxTokens(): int
    {
        return (int)($this->getValue('ai/max_tokens') ?: 700);
    }

    public function getTimeout(): int
    {
        return max(3, (int)($this->getValue('ai/timeout') ?: 15));
    }

    public function getSystemPrompt(): string
    {
        return (string)$this->getValue('ai/system_prompt');
    }

    public function isFeatureEnabled(string $feature): bool
    {
        return $this->isSetFlag('features/' . $feature);
    }

    public function isSuggestionsEnabled(): bool
    {
        return $this->isSetFlag('suggestions/enabled') && $this->isFeatureEnabled('suggested_messages');
    }

    public function getMaxSuggestions(): int
    {
        return max(1, min(10, (int)($this->getValue('suggestions/max_shown') ?: 5)));
    }

    public function allowDynamicSuggestions(): bool
    {
        return $this->isSetFlag('suggestions/dynamic');
    }

    public function getProductLimit(): int
    {
        $limit = (int)($this->getValue('recommendation/default_limit') ?: 4);
        $max = (int)($this->getValue('recommendation/max_products') ?: 4);
        return max(1, min($limit, max(1, $max)));
    }

    public function includeOutOfStock(): bool
    {
        return $this->isSetFlag('recommendation/include_out_of_stock');
    }

    private function getValue(string $path): mixed
    {
        return $this->scopeConfig->getValue(self::XML_PATH . $path, ScopeInterface::SCOPE_STORE);
    }

    private function isSetFlag(string $path): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH . $path, ScopeInterface::SCOPE_STORE);
    }
}
