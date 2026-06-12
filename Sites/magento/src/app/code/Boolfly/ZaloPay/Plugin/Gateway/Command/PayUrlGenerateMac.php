<?php
/************************************************************
 * Copyright © Boolfly. All rights reserved.
 * See COPYING.txt for license details.
 ************************************************************/

namespace Boolfly\ZaloPay\Plugin\Gateway\Command;

use Boolfly\ZaloPay\Gateway\Helper\Authorization;
use Boolfly\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Magento\Payment\Gateway\Request\BuilderComposite;

class PayUrlGenerateMac
{
    /**
     * @var Authorization
     */
    private $authorization;

    public function __construct(
        Authorization $authorization
    ) {
        $this->authorization = $authorization;
    }

    /**
     * Generate MAC for ZaloPay v2 Create Order.
     *
     * hmac_input:
     * app_id|app_trans_id|app_user|amount|app_time|embed_data|item
     */
    public function afterBuildRequestData($subject, $result)
    {
        if (is_array($result)) {
            $params = [];

            foreach ($this->getMacKeys() as $key) {
                $params[] = isset($result[$key]) ? (string)$result[$key] : '';
            }

            $result[AbstractDataBuilder::MAC] = $this->authorization->getMac($params);
        }

        return $result;
    }

    protected function getMacKeys()
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
}