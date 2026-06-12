<?php
/************************************************************
 * Copyright © Boolfly. All rights reserved.
 * See COPYING.txt for license details.
 ************************************************************/

namespace Boolfly\ZaloPay\Gateway\Helper;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\ConfigInterface;
use Boolfly\ZaloPay\Gateway\Request\AbstractDataBuilder;

class Authorization
{
    /**
     * @var string
     */
    protected $params;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var Json
     */
    private $serializer;

    public function __construct(
        Json $serializer,
        ConfigInterface $config
    ) {
        $this->config = $config;
        $this->serializer = $serializer;
    }

    /**
     * Create order MAC with key1.
     *
     * ZaloPay v2:
     * mac = HMACSHA256(key1, app_id|app_trans_id|app_user|amount|app_time|embed_data|item)
     */
    public function getMac(array $params)
    {
        return hash_hmac('sha256', implode('|', $params), trim((string)$this->getKey1()));
    }

    /**
     * Callback MAC with key2.
     */
    public function getMacKey2(string $transData)
    {
        return hash_hmac('sha256', $transData, trim((string)$this->getKey2()));
    }

    public function getMacData()
    {
        return [
            AbstractDataBuilder::APP_ID,
            AbstractDataBuilder::APP_TRANS_ID,
            AbstractDataBuilder::APP_USER,
            AbstractDataBuilder::AMOUNT,
            AbstractDataBuilder::APP_TIME,
            AbstractDataBuilder::EMBED_DATA,
            AbstractDataBuilder::ITEM
        ];
    }

    public function getParameter()
    {
        return $this->params;
    }

    public function getHeaders()
    {
        return [
            'Content-Type: application/x-www-form-urlencoded'
        ];
    }

    private function getKey1()
    {
        return $this->config->getValue(AbstractDataBuilder::KEY_1);
    }

    private function getKey2()
    {
        return $this->config->getValue(AbstractDataBuilder::KEY_2);
    }
}