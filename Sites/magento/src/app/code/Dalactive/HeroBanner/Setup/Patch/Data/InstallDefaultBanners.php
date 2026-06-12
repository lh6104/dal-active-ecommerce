<?php

namespace Dalactive\HeroBanner\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class InstallDefaultBanners implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('dalactive_herobanner_slide');
        $rows = [
            [
                'name' => 'DAL Active Hero 1',
                'media_type' => 'image',
                'media_path' => 'dalactive/herobanner/bizkick-home-banner-1.png',
                'headline' => 'BREAK YOUR LIMIT',
                'subtitle' => 'Fast gear for training, football, running and every active day.',
                'button1_text' => 'Shop Shoes',
                'button1_url' => '/giay.html',
                'button2_text' => '',
                'button2_url' => '',
                'timeout_ms' => null,
                'sort_order' => 10,
                'status' => 1,
            ],
            [
                'name' => 'DAL Active Hero 2',
                'media_type' => 'image',
                'media_path' => 'dalactive/herobanner/bizkick-home-banner-2.png',
                'headline' => 'MOVE FASTER',
                'subtitle' => 'Performance sportswear built for everyday athletes.',
                'button1_text' => 'Shop Apparel',
                'button1_url' => '/quan-ao.html',
                'button2_text' => '',
                'button2_url' => '',
                'timeout_ms' => null,
                'sort_order' => 20,
                'status' => 1,
            ],
        ];

        foreach ($rows as $row) {
            $connection->insertOnDuplicate(
                $table,
                $row,
                ['media_type', 'media_path', 'headline', 'subtitle', 'button1_text', 'button1_url', 'button2_text', 'button2_url', 'timeout_ms', 'sort_order', 'status']
            );
        }

        $this->moduleDataSetup->endSetup();
        return $this;
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
