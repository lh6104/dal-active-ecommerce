<?php

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/../src/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

$blockFactory = $objectManager->get(\Magento\Cms\Model\BlockFactory::class);
$blockResource = $objectManager->get(\Magento\Cms\Model\ResourceModel\Block::class);

$blocks = [
    'bizkick-below' => <<<'HTML'
{{widget type="Magento\Cms\Block\Widget\Block" template="widget/static_block/default.phtml" block_id="home-images-two"}}
<div class="page-main">
{{widget type="Magento\Catalog\Block\Product\Widget\NewWidget" display_type="all_products" show_pager="0" products_count="10" template="product/widget/new/content/new_grid.phtml"}}
</div>
{{widget type="Magento\Cms\Block\Widget\Block" template="widget/static_block/default.phtml" block_id="home-sports-news"}}
{{widget type="Magento\Cms\Block\Widget\Block" template="widget/static_block/default.phtml" block_id="home-economic-news"}}
{{widget type="Magento\Cms\Block\Widget\Block" template="widget/static_block/default.phtml" block_id="home_blogs"}}
{{widget type="Magento\Cms\Block\Widget\Block" template="widget/static_block/default.phtml" block_id="home_testimonials"}}
{{widget type="Magento\Cms\Block\Widget\Block" template="widget/static_block/default.phtml" block_id="home-services"}}
{{widget type="Magento\Cms\Block\Widget\Block" template="widget/static_block/default.phtml" block_id="home-brands"}}
HTML,
    'home-sports-news' => '{{block class="Dalactive\\SportsNews\\Block\\News" name="home.sports.news" template="Dalactive_SportsNews::home-news.phtml"}}',
    'home-economic-news' => '{{block class="Dalactive\\EconomicNews\\Block\\News" name="home.economic.news" template="Dalactive_EconomicNews::home-news.phtml"}}',
];

foreach ($blocks as $identifier => $content) {
    $block = $blockFactory->create();
    $blockResource->load($block, $identifier, 'identifier');

    if (!$block->getId()) {
        $block->setTitle(ucwords(str_replace('-', ' ', $identifier)));
        $block->setIdentifier($identifier);
        $block->setIsActive(true);
        $block->setStores([0]);
    }

    $block->setContent($content);
    $blockResource->save($block);
    echo "Updated CMS block: {$identifier}\n";
}
