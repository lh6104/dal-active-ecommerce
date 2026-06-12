<?php
/************************************************************
 * Copyright © Boolfly. All rights reserved.
 * See COPYING.txt for license details.
 ************************************************************/

namespace Boolfly\ZaloPay\Gateway\Validator;

use Boolfly\ZaloPay\Gateway\Helper\Authorization;
use Boolfly\ZaloPay\Gateway\Helper\Rate;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

abstract class AbstractResponseValidator extends AbstractValidator
{
    /**
     * ZaloPay v2 response fields.
     */
    const PAY_URL = 'order_url';
    const RETURN_CODE = 'return_code';
    const RETURN_MESSAGE = 'return_message';
    const SUB_RETURN_CODE = 'sub_return_code';
    const SUB_RETURN_MESSAGE = 'sub_return_message';
    const ZP_TRANS_TOKEN = 'zp_trans_token';
    const ORDER_TOKEN = 'order_token';
    const TRANSACTION_ID = 'transId';
    const TRANS_DATA = 'trans_data';
    const ZP_TRANS_ID = 'zp_trans_id';
    const TOTAL_AMOUNT = 'amount';
    const REFUND_ID = 'refund_id';
    const RETURN_CODE_ACCEPT = 1;
    const REFUND_CODE_ACCEPT = 1;

    /**
     * @var Authorization|null
     */
    protected $authorization;

    /**
     * @var Rate|null
     */
    protected $helperRate;

    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Authorization $authorization = null,
        Rate $helperRate = null
    ) {
        parent::__construct($resultFactory);
        $this->authorization = $authorization;
        $this->helperRate = $helperRate;
    }

    /**
     * ZaloPay v2 success return_code = 1.
     *
     * @param array $response
     * @return bool
     */
    protected function validateReturnCode($response)
    {
        return isset($response[self::RETURN_CODE]) && (int)$response[self::RETURN_CODE] === 1;
    }
}
