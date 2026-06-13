<?php

namespace Dalactive\GhnShipping\Controller\Address;

use Magento\Framework\App\Action\HttpGetActionInterface;

class Districts extends AbstractAddress implements HttpGetActionInterface
{
    public function execute()
    {
        try {
            $provinceId = (int)$this->request->getParam('province_id');
            if ($provinceId <= 0) {
                return $this->jsonFactory->create()
                    ->setHttpResponseCode(400)
                    ->setData([
                        'ok' => false,
                        'message' => 'Missing province_id.',
                    ]);
            }

            $response = $this->client->getDistricts($provinceId);
            $items = [];

            foreach (($response['data'] ?? []) as $district) {
                $id = (int)($district['DistrictID'] ?? 0);
                $name = trim((string)($district['DistrictName'] ?? ''));
                if (!$id || $name === '') {
                    continue;
                }

                $items[] = [
                    'id' => $id,
                    'name' => $name,
                    'province_id' => (int)($district['ProvinceID'] ?? $provinceId),
                ];
            }

            return $this->success($items);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }
}
