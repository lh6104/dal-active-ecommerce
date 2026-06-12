<?php
/************************************************************
 * Copyright © Boolfly. All rights reserved.
 * See COPYING.txt for license details.
 ************************************************************/

namespace Boolfly\ZaloPay\Gateway\Request;

use Boolfly\ZaloPay\Gateway\Helper\PublicUrl;
use Boolfly\ZaloPay\Gateway\Helper\Rate;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderAdditionalInformationDataBuilder extends AbstractDataBuilder implements BuilderInterface
{
    /**
     * Description shown on ZaloPay payment page.
     */
    const DESCRIPTION_TEXT = 'DAL Active - Thanh toan don hang';

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var Rate
     */
    private $helperRate;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    private $publicUrl;

    public function __construct(
        Json $serializer,
        Rate $helperRate,
        OrderRepositoryInterface $orderRepository,
        PublicUrl $publicUrl
    ) {
        $this->serializer = $serializer;
        $this->helperRate = $helperRate;
        $this->orderRepository = $orderRepository;
        $this->publicUrl = $publicUrl;
    }

    /**
     * Build ZaloPay v2 additional order data.
     *
     * Required by v2 create order:
     * - embed_data: JSON string
     * - amount: VND integer
     * - description: string
     * - bank_code: optional, leave empty to open ZaloPay Gateway
     *
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $orderAdapter = $paymentDataObject->getOrder();
        $orderId = $orderAdapter->getId();

        $order = $this->orderRepository->get($orderId);
        $amount = (int)$this->helperRate->getVndAmount(
            $order,
            round((float)SubjectReader::readAmount($buildSubject), 2)
        );

        return [
            self::EMBED_DATA => $this->serializer->serialize($this->getEmbedData((int)$order->getStoreId())),
            self::AMOUNT => $amount,
            self::DESCRIPTION => self::DESCRIPTION_TEXT . ' #' . $order->getIncrementId(),

            /*
             * ZaloPay v2 Gateway:
             * leave bank_code empty so ZaloPay shows available payment methods.
             */
            self::BANK_CODE => ''
        ];
    }

    /**
     * embed_data must be a JSON string.
     * redirecturl is used by ZaloPay Gateway to redirect customer back to Magento.
     *
     * @return array
     */
    private function getEmbedData(?int $storeId = null)
    {
        return [
            'redirecturl' => $this->publicUrl->getRouteUrl('zalopay/payment/returnAction', $storeId),
            'merchantinfo' => 'DAL Active'
        ];
    }
}
