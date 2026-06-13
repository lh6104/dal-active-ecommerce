<?php

namespace Dalactive\GhnShipping\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Handler extends Base
{
    protected $fileName = '/var/log/ghn_shipping.log';
    protected $loggerType = Logger::DEBUG;
}
