<?php

namespace Dalactive\Navbar\Observer;

use Magento\Framework\App\Cache\Type\Block;
use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Cache\Type\Layout;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\PageCache\Model\Cache\Type as FullPageCache;
use Psr\Log\LoggerInterface;

class CleanNavigationCache implements ObserverInterface
{
    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param TypeListInterface $cacheTypeList
     * @param LoggerInterface $logger
     */
    public function __construct(
        TypeListInterface $cacheTypeList,
        LoggerInterface $logger
    ) {
        $this->cacheTypeList = $cacheTypeList;
        $this->logger = $logger;
    }

    /**
     * Clean cached menu HTML after catalog category changes.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            foreach ([Block::TYPE_IDENTIFIER, FullPageCache::TYPE_IDENTIFIER, Layout::TYPE_IDENTIFIER, Config::TYPE_IDENTIFIER] as $type) {
                $this->cacheTypeList->cleanType($type);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Dalactive Navbar cache clean failed: ' . $exception->getMessage());
        }
    }
}
