<?php

namespace Dalactive\ShoppingAssistant\Block;

use Dalactive\ShoppingAssistant\Model\Config;
use Dalactive\ShoppingAssistant\Model\MessageSuggestionProvider;
use Dalactive\ShoppingAssistant\Model\QuickActionProvider;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;

class Widget extends Template
{
    private Config $config;
    private QuickActionProvider $quickActionProvider;
    private MessageSuggestionProvider $suggestionProvider;
    private FormKey $formKey;
    private Json $json;

    public function __construct(
        Template\Context $context,
        Config $config,
        QuickActionProvider $quickActionProvider,
        MessageSuggestionProvider $suggestionProvider,
        FormKey $formKey,
        Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->quickActionProvider = $quickActionProvider;
        $this->suggestionProvider = $suggestionProvider;
        $this->formKey = $formKey;
        $this->json = $json;
    }

    public function canShow(): bool
    {
        return $this->config->isWidgetEnabled();
    }

    public function getConfigJson(): string
    {
        return $this->json->serialize([
            'endpoint' => $this->getUrl('shoppingassistant/chat/send'),
            'formKey' => $this->formKey->getFormKey(),
            'botName' => $this->config->getBotName(),
            'welcomeMessage' => $this->config->getWelcomeMessage(),
            'maxLength' => $this->config->getMaxMessageLength(),
            'quickActions' => $this->quickActionProvider->getQuickActions(),
            'suggestions' => $this->suggestionProvider->getSuggestions('default'),
        ]);
    }
}
