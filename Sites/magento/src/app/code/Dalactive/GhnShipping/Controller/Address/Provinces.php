<?php

namespace Dalactive\GhnShipping\Controller\Address;

use Magento\Framework\App\Action\HttpGetActionInterface;

class Provinces extends AbstractAddress implements HttpGetActionInterface
{
    public function execute()
    {
        try {
            $response = $this->client->getProvinces();
            $items = [];

            foreach (($response['data'] ?? []) as $province) {
                $id = (int)($province['ProvinceID'] ?? 0);
                $name = trim((string)($province['ProvinceName'] ?? ''));
                if (!$id || $name === '') {
                    continue;
                }

                $items[] = [
                    'id' => $id,
                    'name' => $name,
                ];
            }

            return $this->success($items);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }
}
