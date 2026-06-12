<?php
/************************************************************
 * Copyright © Boolfly. All rights reserved.
 * See COPYING.txt for license details.
 ************************************************************/

namespace Boolfly\ZaloPay\Gateway\Request;

use Boolfly\ZaloPay\Gateway\Helper\PublicUrl;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ZaloAppInfoDataBuilder extends AbstractDataBuilder implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    private $publicUrl;

    public function __construct(
        ConfigInterface $config,
        PublicUrl $publicUrl
    ) {
        $this->config = $config;
        $this->publicUrl = $publicUrl;
    }

    /**
     * Build ZaloPay v2 app information.
     *
     * Required v2 fields:
     * app_id, app_trans_id, app_user, app_time, callback_url
     *
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject)
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $orderIncrementId = $payment->getOrder()->getOrderIncrementId();
        $storeId = (int)$payment->getOrder()->getStoreId();

        return [
            self::APP_ID => (int)$this->getConfig(self::CONFIG_APP_ID),
            self::APP_TIME => $this->getAppTime(),
            self::APP_TRANS_ID => $this->getAppTransId($orderIncrementId),
            self::APP_USER => (string)$this->getConfig(self::CONFIG_APP_USER),
            self::CALLBACK_URL => $this->publicUrl->getRouteUrl('zalopay/payment/ipn', $storeId),
        ];
    }

    /**
     * ZaloPay app_trans_id format should start with yyMMdd.
     *
     * @param string $orderIncrementId
     * @return string
     */
    private function getAppTransId($orderIncrementId)
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh'));

        return $now->format('ymd') . '_' . $orderIncrementId;
    }

    /**
     * Milliseconds timestamp.
     *
     * @return int
     */
    private function getAppTime()
    {
        return (int)round(microtime(true) * 1000);
    }

    /**
     * Get payment config by field id.
     *
     * @param string $path
     * @return mixed
     */
    private function getConfig($path)
    {
        return $this->config->getValue($path);
    }
}
