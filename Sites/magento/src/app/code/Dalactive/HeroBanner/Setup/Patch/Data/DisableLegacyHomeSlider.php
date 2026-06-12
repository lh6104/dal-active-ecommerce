<?php

namespace Dalactive\HeroBanner\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class DisableLegacyHomeSlider implements DataPatchInterface
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
        $table = $this->moduleDataSetup->getTable('cms_block');
        if ($connection->isTableExists($table)) {
            $connection->update($table, ['is_active' => 0], ['identifier = ?' => 'home-slider']);
        }
        $this->moduleDataSetup->endSetup();
        return $this;
    }

    public static function getDependencies(): array
    {
        return [InstallDefaultBanners::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
