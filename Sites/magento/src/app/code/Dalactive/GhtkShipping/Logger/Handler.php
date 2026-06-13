<?php

namespace Dalactive\GhtkShipping\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Handler extends Base
{
    protected $fileName = '/var/log/ghtk_shipping.log';
    protected $loggerType = Logger::DEBUG;
}
