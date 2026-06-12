<?php

namespace Dalactive\ShoppingAssistant\Model;

use Magento\Framework\App\ResourceConnection;

class SizeAdvisor
{
    private ResourceConnection $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function advise(string $message): array
    {
        $data = $this->parseMessage($message);
        if (!$data['product_type']) {
            return [
                'found' => false,
                'message' => 'Bạn đang cần chọn size cho áo, quần hay giày để mình tư vấn chính xác hơn nhé?',
                'context' => $data,
            ];
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('dalactive_chatbot_size_rule');
        $select = $connection->select()
            ->from($table)
            ->where('status = ?', 1)
            ->where('product_type = ?', $data['product_type'])
            ->order('priority DESC')
            ->limit(20);

        $rules = $connection->fetchAll($select);
        foreach ($rules as $rule) {
            if (!$this->matches($rule, $data)) {
                continue;
            }

            return [
                'found' => true,
                'recommended_size' => $rule['recommended_size'],
                'note' => $rule['note'],
                'product_type' => $rule['product_type'],
                'message' => sprintf(
                    'Với thông tin hiện có, mình gợi ý bạn chọn size %s. %s',
                    $rule['recommended_size'],
                    $rule['note'] ?: ''
                ),
                'context' => $data,
            ];
        }

        return [
            'found' => false,
            'message' => 'Mình chưa tìm thấy rule size chính xác cho thông tin này. Bạn có thể cho mình biết thêm loại sản phẩm hoặc xem bảng size trên trang sản phẩm nhé.',
            'context' => $data,
        ];
    }

    private function parseMessage(string $message): array
    {
        $text = mb_strtolower($message);
        $height = null;
        $weight = null;
        $footLength = null;

        if (preg_match('/(\d(?:m|m\\s*)\\d{1,2})/iu', $text, $match)) {
            $height = (float)str_replace(['m', ' '], ['.', ''], $match[1]) * 100;
        } elseif (preg_match('/(\d{2,3})\\s*cm/iu', $text, $match)) {
            $height = (float)$match[1];
        }

        if (preg_match('/(\d{2,3})\\s*kg/iu', $text, $match)) {
            $weight = (float)$match[1];
        }

        if (preg_match('/(\d{2}(?:[\\.,]\\d)?)\\s*(?:cm|centimet)/iu', $text, $match)) {
            $candidate = (float)str_replace(',', '.', $match[1]);
            if ($candidate < 40) {
                $footLength = $candidate;
            }
        }

        $productType = null;
        if (preg_match('/giày|shoe|sneaker|boot|dép/iu', $text)) {
            $productType = 'shoes';
        } elseif (preg_match('/áo|shirt|tee|tshirt|hoodie|tank/iu', $text)) {
            $productType = 'tshirt';
        } elseif (preg_match('/quần|short|pants/iu', $text)) {
            $productType = 'pants';
        } elseif ($footLength !== null) {
            $productType = 'shoes';
        }

        $fit = 'regular';
        if (preg_match('/rộng|oversize|relaxed/iu', $text)) {
            $fit = 'relaxed';
        } elseif (preg_match('/ôm|slim|body/iu', $text)) {
            $fit = 'slim';
        }

        $gender = 'unisex';
        if (preg_match('/\\bnam\\b|men/iu', $text)) {
            $gender = 'nam';
        } elseif (preg_match('/\\bnữ\\b|women/iu', $text)) {
            $gender = 'nữ';
        } elseif (preg_match('/trẻ em|kids/iu', $text)) {
            $gender = 'trẻ em';
        }

        return [
            'height' => $height,
            'weight' => $weight,
            'foot_length' => $footLength,
            'product_type' => $productType,
            'fit_type' => $fit,
            'gender' => $gender,
        ];
    }

    private function matches(array $rule, array $data): bool
    {
        foreach (['height', 'weight', 'foot_length'] as $key) {
            $value = $data[$key] ?? null;
            $min = $rule[$key . '_min'] ?? null;
            $max = $rule[$key . '_max'] ?? null;
            if ($value !== null && $min !== null && $value < (float)$min) {
                return false;
            }
            if ($value !== null && $max !== null && $value > (float)$max) {
                return false;
            }
        }

        if (!empty($rule['gender']) && !in_array($rule['gender'], [$data['gender'], 'unisex'], true)) {
            return false;
        }

        if (!empty($rule['fit_type']) && $rule['fit_type'] !== 'regular' && $rule['fit_type'] !== $data['fit_type']) {
            return false;
        }

        return true;
    }
}
