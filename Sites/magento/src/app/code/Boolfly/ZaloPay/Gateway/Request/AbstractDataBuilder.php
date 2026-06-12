<?php
/************************************************************
 * Copyright © Boolfly. All rights reserved.
 * See COPYING.txt for license details.
 ************************************************************/

namespace Boolfly\ZaloPay\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

abstract class AbstractDataBuilder implements BuilderInterface
{
    /**
     * ZaloPay v2 endpoints.
     */
    const PAY_URL_PATH = 'v2/create';
    const QUERY_URL_PATH = 'v2/query';

    /**
     * Keep refund old for compatibility if refund flow has not been migrated.
     */
    const REFUND_URL_PATH = 'v001/tpe/partialrefund';

    const REFUND = 'refund';

    /**
     * Config field IDs in Magento Admin.
     * Do not change these if system.xml still uses old field IDs.
     */
    const CONFIG_APP_ID = 'appid';
    const CONFIG_APP_USER = 'appuser';

    /**
     * ZaloPay v2 request fields.
     */
    const APP_ID = 'app_id';
    const APP_TIME = 'app_time';
    const APP_TRANS_ID = 'app_trans_id';
    const APP_USER = 'app_user';

    const ITEM = 'item';
    const EMBED_DATA = 'embed_data';

    const DESCRIPTION = 'description';
    const BANK_CODE = 'bank_code';
    const AMOUNT = 'amount';
    const CALLBACK_URL = 'callback_url';

    /**
     * Key config fields.
     */
    const KEY_1 = 'key1';
    const KEY_2 = 'key2';

    /**
     * Response / transaction fields.
     */
    const TRANSACTION_ID = 'transId';
    const TRANS_DATA = 'trans_data';

    const M_REFUND_ID = 'mrefundid';
    const ZP_TRANS_ID = 'zp_trans_id';
    const TIMESTAMP = 'timestamp';

    const MAC = 'mac';
}