<?php

namespace Dalactive\ShoppingAssistant\Api;

interface ChatbotServiceInterface
{
    /**
     * @param string $message
     * @param array $metadata
     * @return array
     */
    public function respond(string $message, array $metadata = []): array;
}
