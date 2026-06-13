<?php

namespace Dalactive\ShoppingAssistant\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddRecommendationChatSuggestions implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $this->insertMissing('dalactive_chatbot_quick_action', 'label', [
            [
                'label' => 'Gợi ý sản phẩm',
                'action_type' => 'product_recommendation',
                'sort_order' => 15,
            ],
        ]);

        $this->insertMissing('dalactive_chatbot_message_suggestion', 'label', [
            [
                'label' => 'Gợi ý sản phẩm',
                'message' => 'Gợi ý sản phẩm phù hợp cho tôi',
                'intent' => 'product_recommendation',
                'trigger_context' => 'default',
                'sort_order' => 5,
            ],
            [
                'label' => 'Gợi ý đồ bóng đá',
                'message' => 'Gợi ý đồ bóng đá cho tôi',
                'intent' => 'product_recommendation',
                'trigger_context' => 'default',
                'sort_order' => 25,
            ],
            [
                'label' => 'Gợi ý đồ bóng rổ',
                'message' => 'Gợi ý đồ bóng rổ cho tôi',
                'intent' => 'product_recommendation',
                'trigger_context' => 'default',
                'sort_order' => 26,
            ],
        ]);

        $connection->endSetup();
    }

    private function insertMissing(string $tableName, string $identityColumn, array $rows): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable($tableName);

        foreach ($rows as $row) {
            $exists = (int)$connection->fetchOne(
                $connection->select()
                    ->from($table, ['count' => 'COUNT(*)'])
                    ->where($identityColumn . ' = ?', $row[$identityColumn])
            );

            if ($exists === 0) {
                $connection->insert($table, $row);
            }
        }
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
