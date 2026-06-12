<?php

namespace Dalactive\SizeGuide\Block\Adminhtml;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template;

class Listing extends Template
{
    private ResourceConnection $resourceConnection;

    public function __construct(
        Template\Context $context,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $data);
    }

    public function getRows(): array
    {
        $table = (string) $this->getData('table');
        if ($table === '') {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName($table))
            ->limit(100);

        return $connection->fetchAll($select);
    }

    public function getColumns(): array
    {
        $columns = (string) $this->getData('columns');
        return array_filter(array_map('trim', explode(',', $columns)));
    }
}
