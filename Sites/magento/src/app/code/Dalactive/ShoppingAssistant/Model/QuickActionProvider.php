<?php

namespace Dalactive\ShoppingAssistant\Model;

use Magento\Framework\App\ResourceConnection;

class QuickActionProvider
{
    private ResourceConnection $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function getQuickActions(): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('dalactive_chatbot_quick_action');
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['label', 'action_type', 'payload'])
                ->where('status = ?', 1)
                ->order('sort_order ASC')
        );

        return array_map(static function (array $row): array {
            return [
                'label' => (string)$row['label'],
                'message' => (string)$row['label'],
                'action_type' => (string)$row['action_type'],
            ];
        }, $rows);
    }
}
