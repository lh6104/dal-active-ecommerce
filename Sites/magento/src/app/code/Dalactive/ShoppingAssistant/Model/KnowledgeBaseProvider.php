<?php

namespace Dalactive\ShoppingAssistant\Model;

use Magento\Framework\App\ResourceConnection;

class KnowledgeBaseProvider
{
    private ResourceConnection $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function getByCategory(string $category): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('dalactive_chatbot_knowledge_base');
        return $connection->fetchAll(
            $connection->select()
                ->from($table, ['title', 'question', 'answer', 'category'])
                ->where('status = ?', 1)
                ->where('category = ?', $category)
                ->order('priority DESC')
                ->limit(3)
        );
    }

    public function search(string $message): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('dalactive_chatbot_knowledge_base');
        $like = '%' . $message . '%';
        return $connection->fetchAll(
            $connection->select()
                ->from($table, ['title', 'question', 'answer', 'category'])
                ->where('status = ?', 1)
                ->where('(keywords LIKE ? OR question LIKE ? OR title LIKE ?)', $like, $like, $like)
                ->order('priority DESC')
                ->limit(3)
        );
    }
}
