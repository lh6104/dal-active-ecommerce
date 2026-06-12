<?php

namespace Dalactive\ShoppingAssistant\Model;

use Magento\Framework\App\ResourceConnection;

class MessageSuggestionProvider
{
    private ResourceConnection $resource;
    private Config $config;

    public function __construct(ResourceConnection $resource, Config $config)
    {
        $this->resource = $resource;
        $this->config = $config;
    }

    public function getSuggestions(?string $context = 'default', ?string $intent = null): array
    {
        if (!$this->config->isSuggestionsEnabled()) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('dalactive_chatbot_message_suggestion');
        $select = $connection->select()
            ->from($table, ['label', 'message'])
            ->where('status = ?', 1)
            ->order('sort_order ASC')
            ->limit($this->config->getMaxSuggestions());

        if ($this->config->allowDynamicSuggestions() && $context) {
            $select->where('(trigger_context = ? OR trigger_context = ? OR trigger_context IS NULL)', $context, 'default');
        } else {
            $select->where('(trigger_context = ? OR trigger_context IS NULL)', 'default');
        }

        if ($intent) {
            $select->where('(intent = ? OR intent IS NULL)', $intent);
        }

        return array_map(static function (array $row): array {
            return [
                'label' => (string)$row['label'],
                'message' => (string)$row['message'],
            ];
        }, $connection->fetchAll($select));
    }
}
