<?php

namespace Dalactive\ShoppingAssistant\Model;

use Dalactive\ShoppingAssistant\Model\Groq\GroqClient;
use Magento\Framework\Serialize\Serializer\Json;

class AiIntentExtractor
{
    public const SYSTEM_PROMPT = 'You are an intent extraction engine for a Vietnamese Magento ecommerce chatbot called DAL Assistant. Your job is to convert user messages into strict JSON for backend product search. Do not answer the user. Do not invent products. Do not invent prices. Do not invent stock. Only extract intent, filters, and clarification needs. Return valid JSON only.';

    private GroqClient $groqClient;
    private Json $json;

    public function __construct(GroqClient $groqClient, Json $json)
    {
        $this->groqClient = $groqClient;
        $this->json = $json;
    }

    public function extract(string $message): array
    {
        $content = $this->groqClient->generateWithSystem(self::SYSTEM_PROMPT, [
            'message' => $message,
            'schema' => [
                'intent' => 'product_recommendation | product_search | stock_check | size_recommendation | newest_products | promotion_products | similar_products | order_help | general_help | unknown',
                'query' => 'string|null',
                'category' => 'shoes | shirts | pants | accessories | football | basketball | running | gym | unknown | null',
                'brand' => 'string|null',
                'color' => 'string|null',
                'gender' => 'men | women | kids | unisex | null',
                'min_price' => 'number|null',
                'max_price' => 'number|null',
                'size' => 'string|null',
                'product_name' => 'string|null',
                'sku' => 'string|null',
                'needs_clarification' => 'boolean',
                'clarification_question' => 'string|null',
            ],
            'category_mapping' => [
                'áo, áo thun, áo thể thao, t-shirt' => 'shirts',
                'quần, quần short, shorts' => 'pants',
                'giày, sneaker, giày thể thao, giày chạy bộ' => 'shoes',
                'phụ kiện, tất, vớ, balo, mũ, túi' => 'accessories',
                'bóng đá, đá bóng' => 'football',
                'bóng rổ' => 'basketball',
                'chạy bộ' => 'running',
                'gym, tập luyện' => 'gym',
            ],
            'price_rules' => [
                '400k = 400000',
                '2 triệu = 2000000',
                '1tr5 = 1500000',
                'dưới X = max_price',
                'trên X = min_price',
                'từ X đến Y = min_price and max_price',
            ],
        ], 0.0, 500);

        if ($content) {
            $decoded = $this->decodeJson($content);
            if ($decoded) {
                return $this->normalize($decoded, $message);
            }
        }

        return $this->fallbackExtract($message);
    }

    private function decodeJson(string $content): ?array
    {
        $content = trim($content);
        if (preg_match('/```(?:json)?\s*(.*?)```/is', $content, $match)) {
            $content = trim($match[1]);
        }

        try {
            $decoded = $this->json->unserialize($content);
            return is_array($decoded) ? $decoded : null;
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    private function normalize(array $data, string $message): array
    {
        $defaults = $this->emptyResult($message);
        $result = array_merge($defaults, array_intersect_key($data, $defaults));
        $result['intent'] = $this->normalizeIntent((string)$result['intent']);
        $result['category'] = $this->normalizeCategory($result['category'] ? (string)$result['category'] : null);
        $result['min_price'] = $result['min_price'] !== null ? (float)$result['min_price'] : null;
        $result['max_price'] = $result['max_price'] !== null ? (float)$result['max_price'] : null;
        $result['needs_clarification'] = (bool)$result['needs_clarification'];
        return $result;
    }

    private function fallbackExtract(string $message): array
    {
        $result = $this->emptyResult($message);
        $text = mb_strtolower($message);
        $plain = $this->removeAccents($text);

        $result['category'] = $this->detectCategory($plain);
        $result['brand'] = $this->detectBrand($message);
        $this->applyPrice($plain, $result);
        $result['size'] = $this->detectSize($message);
        $result['product_name'] = $this->extractProductName($message);
        $result['query'] = $result['product_name'] ?: $message;

        if (preg_match('/(?:san pham moi|hang moi|moi nhat|new arrival)/iu', $plain)) {
            $result['intent'] = 'newest_products';
        } elseif (preg_match('/(?:uu dai|khuyen mai|sale|giam gia|voucher|coupon)/iu', $plain)) {
            $result['intent'] = 'promotion_products';
        } elseif (preg_match('/(?:ton hang|ton kho|con hang|con khong|con ko|kiem tra|check stock|stock)/iu', $plain)) {
            $result['intent'] = 'stock_check';
        } elseif (preg_match('/(?:size|kich co|chon so|chan dai|cm|kg|cao|can nang)/iu', $plain)) {
            $result['intent'] = 'size_recommendation';
        } elseif (preg_match('/(?:cua hang|find a store|dia chi shop|chi nhanh)/iu', $plain)) {
            $result['intent'] = 'store_locator';
        } elseif (preg_match('/(?:ty gia|ngoai te|usd|eur|vnd)/iu', $plain)) {
            $result['intent'] = 'exchange_rate';
        } elseif (preg_match('/(?:tin tuc|tin kinh te|bai viet|news)/iu', $plain)) {
            $result['intent'] = 'economic_news';
        } elseif (preg_match('/(?:thoi tiet|weather|mua|nang|nhiet do)/iu', $plain)) {
            $result['intent'] = 'weather';
        } elseif (preg_match('/(?:dat hang|mua online|checkout|thanh toan|giao hang|doi tra)/iu', $plain)) {
            $result['intent'] = 'order_help';
        } elseif (preg_match('/(?:tuong tu|mau tuong tu|cung tam gia|same price|similar)/iu', $plain)) {
            $result['intent'] = 'similar_products';
        } elseif (preg_match('/(?:goi y|tim|mua|duoi|tren|tu |giay|ao|quan|phu kien|nike|adidas|new balance)/iu', $plain)) {
            $result['intent'] = preg_match('/(?:tim|search)/iu', $plain) ? 'product_search' : 'product_recommendation';
        } else {
            $result['intent'] = 'unknown';
        }

        if (preg_match('/(\d{2}(?:[\.,]\d)?)\s*cm/iu', $plain, $match)) {
            $cm = (float)str_replace(',', '.', $match[1]);
            if ($cm < 40) {
                $result['foot_length_cm'] = $cm;
                $result['category'] = 'shoes';
            }
        }

        return $result;
    }

    private function emptyResult(string $message): array
    {
        return [
            'intent' => 'unknown',
            'query' => $message,
            'category' => null,
            'brand' => null,
            'color' => null,
            'gender' => null,
            'min_price' => null,
            'max_price' => null,
            'size' => null,
            'product_name' => null,
            'sku' => null,
            'needs_clarification' => false,
            'clarification_question' => null,
            'foot_length_cm' => null,
        ];
    }

    private function normalizeIntent(string $intent): string
    {
        $map = [
            'latest_products' => 'newest_products',
            'promotion_list' => 'promotion_products',
            'size_advisor' => 'size_recommendation',
            'fallback' => 'unknown',
        ];
        return $map[$intent] ?? $intent;
    }

    private function normalizeCategory(?string $category): ?string
    {
        if (!$category || $category === 'unknown') {
            return null;
        }
        return in_array($category, ['shoes', 'shirts', 'pants', 'accessories', 'football', 'basketball', 'running', 'gym'], true)
            ? $category
            : null;
    }

    private function detectCategory(string $plain): ?string
    {
        $map = [
            'shoes' => '/\b(giay|sneaker|shoe|boot|dep)\b/u',
            'shirts' => '/\b(ao|t-shirt|tee|hoodie|shirt)\b/u',
            'pants' => '/\b(quan|short|pants)\b/u',
            'accessories' => '/\b(phu kien|tat|vo|balo|mu|tui|binh nuoc)\b/u',
            'football' => '/\b(bong da|da bong|football)\b/u',
            'basketball' => '/\b(bong ro|basketball)\b/u',
            'running' => '/\b(chay bo|running|runner)\b/u',
            'gym' => '/\b(gym|tap luyen|training)\b/u',
        ];
        foreach ($map as $category => $pattern) {
            if (preg_match($pattern, $plain)) {
                return $category;
            }
        }
        return null;
    }

    private function detectBrand(string $message): ?string
    {
        foreach (['Nike', 'Adidas', 'New Balance', 'Puma', 'Asics', 'Chelsea', 'NBA'] as $brand) {
            if (stripos($message, $brand) !== false) {
                return $brand;
            }
        }
        return null;
    }

    private function applyPrice(string $plain, array &$result): void
    {
        $money = '(\\d+(?:[\\.,]\\d+)?)\\s*(k|nghin|ngan|tr|trieu|m|million)?';
        if (preg_match('/(?:duoi|it hon|nho hon|<=)\s*' . $money . '/iu', $plain, $match)) {
            $result['max_price'] = $this->parseMoney($match[1], $match[2] ?? '');
        }
        if (preg_match('/(?:tren|lon hon|>=)\s*' . $money . '/iu', $plain, $match)) {
            $result['min_price'] = $this->parseMoney($match[1], $match[2] ?? '');
        }
        if (preg_match('/(?:tu)\s*' . $money . '\s*(?:den|toi|-)\s*' . $money . '/iu', $plain, $match)) {
            $result['min_price'] = $this->parseMoney($match[1], $match[2] ?? '');
            $result['max_price'] = $this->parseMoney($match[3], $match[4] ?? '');
        }
    }

    private function parseMoney(string $amount, string $unit): float
    {
        $value = (float)str_replace(',', '.', $amount);
        $unit = $this->removeAccents(mb_strtolower($unit));
        if (in_array($unit, ['k', 'nghin', 'ngan'], true)) {
            return $value * 1000;
        }
        if (in_array($unit, ['tr', 'trieu', 'm', 'million'], true)) {
            return $value * 1000000;
        }
        return $value;
    }

    private function detectSize(string $message): ?string
    {
        if (preg_match('/\b(?:EU\s*)?(\d{2}(?:\.\d)?)\b/iu', $message, $match)) {
            return 'EU ' . $match[1];
        }
        if (preg_match('/\b(XS|S|M|L|XL|XXL)\b/iu', $message, $match)) {
            return strtoupper($match[1]);
        }
        return null;
    }

    private function extractProductName(string $message): ?string
    {
        if (preg_match('#https?://[^\s]+#i', $message, $match)) {
            $path = (string)(parse_url($match[0], PHP_URL_PATH) ?: '');
            return trim(str_replace('-', ' ', preg_replace('/\.html$/i', '', basename($path))));
        }

        $clean = preg_replace('/\b(kiểm tra|kiem tra|tồn hàng|ton hang|tồn kho|ton kho|còn hàng|con hang|còn không|con khong|còn ko|check|stock|tìm|tim|gợi ý|goi y|cho tôi|cho toi|mua|sản phẩm|san pham|dưới|duoi|trên|tren|ưu đãi|uu dai|hôm nay|hom nay)\b/iu', ' ', $message);
        $clean = preg_replace('/\b(?:EU\s*)?\d{2}(?:\.\d)?\b/iu', ' ', (string)$clean);
        $clean = preg_replace('/\d+(?:[\.,]\d+)?\s*(k|nghìn|ngan|tr|triệu|trieu)?/iu', ' ', (string)$clean);
        $clean = trim(preg_replace('/\s+/u', ' ', (string)$clean));

        return mb_strlen($clean) >= 3 ? $clean : null;
    }

    private function removeAccents(string $value): string
    {
        $map = [
            'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
            'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
            'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
            'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
            'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
            'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d',
        ];
        return strtr(mb_strtolower($value), $map);
    }
}
