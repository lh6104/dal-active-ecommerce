<?php

namespace Dalactive\GhnShipping\Controller\Address;

use Magento\Framework\App\Action\HttpGetActionInterface;

class Wards extends AbstractAddress implements HttpGetActionInterface
{
    public function execute()
    {
        try {
            $districtId = (int)$this->request->getParam('district_id');
            if ($districtId <= 0) {
                return $this->jsonFactory->create()
                    ->setHttpResponseCode(400)
                    ->setData([
                        'ok' => false,
                        'message' => 'Missing district_id.',
                    ]);
            }

            $response = $this->client->getWards($districtId);
            $items = [];

            foreach (($response['data'] ?? []) as $ward) {
                $code = trim((string)($ward['WardCode'] ?? ''));
                $name = trim((string)($ward['WardName'] ?? ''));
                if ($code === '' || $name === '') {
                    continue;
                }

                $items[] = [
                    'code' => $code,
                    'name' => $name,
                    'district_id' => (int)($ward['DistrictID'] ?? $districtId),
                ];
            }

            return $this->success($items);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }
}
