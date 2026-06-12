<?php
/************************************************************
 * *
 *  * Copyright © Boolfly. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    info@boolfly.com
 * *  @project   ZaloPay
 */
namespace Boolfly\ZaloPay\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Psr\Log\LoggerInterface;

/**
 * Class GetPayUrlValidator
 *
 * @package Boolfly\ZaloPay\Gateway\Validator
 */
class GetPayUrlValidator extends AbstractResponseValidator
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($resultFactory);
        $this->logger = $logger;
    }

    /**
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $response         = SubjectReader::readResponse($validationSubject);
        $errorMessages    = [];
        $validationResult = $this->validateReturnCode($response) && $this->validatePayUrl($response);

        if (!$validationResult) {
            $this->logger->error('ZaloPay create order validation failed', [
                self::RETURN_CODE => $response[self::RETURN_CODE] ?? null,
                self::RETURN_MESSAGE => $response[self::RETURN_MESSAGE] ?? null,
                self::SUB_RETURN_CODE => $response[self::SUB_RETURN_CODE] ?? null,
                self::SUB_RETURN_MESSAGE => $response[self::SUB_RETURN_MESSAGE] ?? null,
            ]);

            $errorMessages = [__(
                'ZaloPay did not return a valid payment URL. return_code=%1, return_message=%2, sub_return_code=%3, sub_return_message=%4',
                $response[self::RETURN_CODE] ?? '',
                $response[self::RETURN_MESSAGE] ?? '',
                $response[self::SUB_RETURN_CODE] ?? '',
                $response[self::SUB_RETURN_MESSAGE] ?? ''
            )];
        }

        return $this->createResult($validationResult, $errorMessages);
    }

    /**
     * @param $response
     * @return boolean
     */
    protected function validatePayUrl($response)
    {
        return !empty($response[AbstractResponseValidator::PAY_URL]) && strlen($response[AbstractResponseValidator::PAY_URL]) > 0;
    }
}
