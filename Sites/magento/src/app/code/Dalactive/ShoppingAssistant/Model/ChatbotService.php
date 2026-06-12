<?php

namespace Dalactive\ShoppingAssistant\Model;

use Dalactive\ShoppingAssistant\Api\ChatbotServiceInterface;
use Dalactive\ShoppingAssistant\Model\Groq\GroqClient;
use Magento\Framework\Session\SessionManagerInterface;

class ChatbotService implements ChatbotServiceInterface
{
    private const STATE_KEY = 'dalactive_assistant_state';
    private const STATE_TTL = 600;
    private const FINAL_RESPONSE_PROMPT = 'You are DAL Assistant, a Vietnamese shopping assistant. Write a short natural response based only on the provided product data. Do not invent products, prices, stock, discounts, links, or availability. If no exact results exist, explain briefly and suggest the fallback products provided by backend. Do not output markdown tables or HTML.';

    private Config $config;
    private IntentDetector $intentDetector;
    private ContextBuilder $contextBuilder;
    private SizeAdvisor $sizeAdvisor;
    private PromotionProvider $promotionProvider;
    private KnowledgeBaseProvider $knowledgeBaseProvider;
    private MessageSuggestionProvider $suggestionProvider;
    private ConversationLogger $conversationLogger;
    private GroqClient $groqClient;
    private ModuleDataProvider $moduleDataProvider;
    private AiIntentExtractor $aiIntentExtractor;
    private ChatbotProductService $productService;
    private SessionManagerInterface $session;

    public function __construct(
        Config $config,
        IntentDetector $intentDetector,
        ContextBuilder $contextBuilder,
        SizeAdvisor $sizeAdvisor,
        PromotionProvider $promotionProvider,
        KnowledgeBaseProvider $knowledgeBaseProvider,
        MessageSuggestionProvider $suggestionProvider,
        ConversationLogger $conversationLogger,
        GroqClient $groqClient,
        ModuleDataProvider $moduleDataProvider,
        AiIntentExtractor $aiIntentExtractor,
        ChatbotProductService $productService,
        SessionManagerInterface $session
    ) {
        $this->config = $config;
        $this->intentDetector = $intentDetector;
        $this->contextBuilder = $contextBuilder;
        $this->sizeAdvisor = $sizeAdvisor;
        $this->promotionProvider = $promotionProvider;
        $this->knowledgeBaseProvider = $knowledgeBaseProvider;
        $this->suggestionProvider = $suggestionProvider;
        $this->conversationLogger = $conversationLogger;
        $this->groqClient = $groqClient;
        $this->moduleDataProvider = $moduleDataProvider;
        $this->aiIntentExtractor = $aiIntentExtractor;
        $this->productService = $productService;
        $this->session = $session;
    }

    public function respond(string $message, array $metadata = []): array
    {
        $startedAt = microtime(true);
        $sessionId = (string)($metadata['session_id'] ?? 'guest');

        if ($this->conversationLogger->countRecentMessages($sessionId) >= 20) {
            return [
                'success' => false,
                'message' => 'Bạn đang gửi tin nhắn quá nhanh. Vui lòng thử lại sau ít phút nhé.',
                'code' => 'RATE_LIMITED',
                'suggestions' => $this->suggestionProvider->getSuggestions('fallback', 'fallback'),
            ];
        }

        $stateResponse = $this->handlePendingSelection($message, $sessionId, $startedAt);
        if ($stateResponse) {
            return $stateResponse;
        }

        $extracted = $this->aiIntentExtractor->extract($message);
        $intent = $this->mapIntent($extracted['intent']);
        $legacyIntent = $this->mapIntent($this->intentDetector->detect($message));
        $forcedIntent = $this->forceIntentFromShortCommand($message);
        if ($forcedIntent !== null) {
            $intent = $forcedIntent;
        }
        if ($intent === 'unknown' || ($intent === 'general_help' && $legacyIntent !== 'unknown')) {
            $intent = $this->mapIntent($legacyIntent);
        }

        $context = $this->contextBuilder->buildBaseContext($intent, $message);
        $context['extracted_filters'] = $extracted;

        $response = [
            'success' => true,
            'intent' => $intent,
            'message' => $this->config->getFallbackMessage(),
            'products' => [],
            'promotions' => [],
            'size_advice' => null,
            'knowledge_base' => [],
            'module_items' => [],
            'suggestions' => [],
            'used_ai' => false,
            'model' => null,
        ];

        switch ($intent) {
            case 'newest_products':
                $response['products'] = $this->productService->getNewestProducts($this->config->getProductLimit());
                $response['message'] = $response['products']
                    ? 'Dưới đây là các sản phẩm mới nhất hiện có:'
                    : 'Hiện mình chưa tìm thấy sản phẩm mới phù hợp.';
                break;
            case 'size_recommendation':
                $response['size_advice'] = $this->sizeAdvisor->advise($message);
                $response['message'] = $response['size_advice']['message'];
                break;
            case 'promotion_products':
                $response['products'] = $this->productService->getPromotionProducts($this->config->getProductLimit());
                if ($response['products']) {
                    $response['message'] = 'Dưới đây là các sản phẩm đang có giá ưu đãi hôm nay:';
                } else {
                    $response['promotions'] = $this->promotionProvider->getActivePromotions();
                    $response['message'] = $response['promotions']
                        ? 'Hiện tại mình tìm thấy các ưu đãi đang hoạt động bên dưới.'
                        : 'Hiện mình chưa tìm thấy sản phẩm hoặc ưu đãi đang hoạt động trong dữ liệu admin.';
                }
                break;
            case 'stock_check':
                $response = array_merge($response, $this->handleStockCheck($extracted));
                break;
            case 'similar_products':
                $key = (string)($extracted['sku'] ?: $extracted['product_name'] ?: $this->getLastProductSku() ?: $extracted['query']);
                $response['products'] = $key ? $this->productService->getSimilarProducts($key, $this->config->getProductLimit()) : [];
                $response['message'] = $response['products']
                    ? 'Mình tìm thấy vài mẫu tương tự bên dưới:'
                    : 'Mình chưa xác định được sản phẩm gốc để tìm mẫu tương tự. Bạn gửi link hoặc tên sản phẩm giúp mình nhé.';
                break;
            case 'product_search':
            case 'product_recommendation':
                $search = $this->productService->searchProducts($extracted, $this->config->getProductLimit(), true);
                $response['products'] = $search['products'];
                $response['message'] = $this->buildProductSearchMessage($extracted, $search);
                break;
            case 'store_locator':
                $response['module_items'] = $this->moduleDataProvider->getStores();
                $response['message'] = $response['module_items']
                    ? 'Dưới đây là các cửa hàng DAL Active hiện có:'
                    : 'Mình chưa tìm thấy dữ liệu cửa hàng đang hoạt động.';
                break;
            case 'exchange_rate':
                $response['module_items'] = $this->moduleDataProvider->getExchangeRates();
                $response['message'] = 'Dưới đây là một số tỷ giá tham khảo mới nhất:';
                break;
            case 'economic_news':
                $response['module_items'] = $this->moduleDataProvider->getEconomicNews();
                $response['message'] = 'Dưới đây là 3 tin nổi bật hiện có:';
                break;
            case 'weather':
                $response['module_items'] = $this->moduleDataProvider->getWeather($this->extractWeatherCity($message));
                $response['message'] = 'Dưới đây là thông tin thời tiết hiện có:';
                break;
            case 'order_help':
            case 'shipping_policy':
            case 'return_policy':
            case 'payment_guide':
                $category = $intent === 'order_help' ? 'order_guide' : $intent;
                $response['knowledge_base'] = $this->knowledgeBaseProvider->getByCategory($category);
                $response['message'] = $this->buildKnowledgeMessage($response['knowledge_base']);
                break;
            default:
                $response['knowledge_base'] = $this->knowledgeBaseProvider->search($message);
                if ($response['knowledge_base']) {
                    $response['intent'] = 'general_faq';
                    $response['message'] = $this->buildKnowledgeMessage($response['knowledge_base']);
                }
                break;
        }

        $context['product_context'] = $response['products'];
        $context['promotion_context'] = $response['promotions'];
        $context['size_context'] = $response['size_advice'];
        $context['knowledge_base_context'] = $response['knowledge_base'];
        $context['module_context'] = $response['module_items'];

        if ($this->shouldUseAi($response['intent'])) {
            $aiMessage = $this->generateFinalMessage($message, $response, $context);
            if ($aiMessage) {
                $response['message'] = $aiMessage;
                $response['used_ai'] = true;
                $response['model'] = $this->config->getGroqModel();
            }
        }

        $dbSuggestions = $this->suggestionProvider->getSuggestions(
            'after_' . $response['intent'],
            $response['intent']
        );
        $response['suggestions'] = $dbSuggestions ?: $this->defaultSuggestions($response['intent'], (bool)$response['products']);

        if ($response['products'] && !$this->hasPendingStockSelection()) {
            $this->rememberLastProducts($response['products']);
        }

        $this->conversationLogger->log(
            $sessionId,
            $message,
            $response,
            (int)((microtime(true) - $startedAt) * 1000)
        );

        return $response;
    }

    private function handlePendingSelection(string $message, string $sessionId, float $startedAt): ?array
    {
        $value = trim($message);
        if (!preg_match('/^\d+$/', $value)) {
            return null;
        }

        $state = $this->getConversationState();
        if (($state['pendingAction'] ?? '') !== 'select_product_for_stock') {
            return null;
        }

        if ((int)($state['createdAt'] ?? 0) < (time() - self::STATE_TTL)) {
            $this->clearConversationState();
            return null;
        }

        $index = (int)$value;
        $products = $state['recommendedProducts'] ?? [];
        if (!isset($products[$index - 1])) {
            return [
                'success' => true,
                'intent' => 'stock_check',
                'message' => 'Mình chưa thấy số thứ tự này trong danh sách vừa gợi ý. Bạn chọn lại số trên card sản phẩm nhé.',
                'products' => array_map(static function (array $product): array {
                    return $product['product'] ?? $product;
                }, $products),
                'promotions' => [],
                'size_advice' => null,
                'knowledge_base' => [],
                'module_items' => [],
                'suggestions' => $this->defaultSuggestions('stock_check', true),
                'used_ai' => false,
                'model' => null,
            ];
        }

        $selected = $products[$index - 1];
        $stock = $this->productService->getStockInfo((string)$selected['sku']);
        $this->clearConversationState();

        $response = [
            'success' => true,
            'intent' => 'stock_check',
            'message' => $this->buildStockMessage($stock),
            'products' => $stock ? [$stock['product']] : [],
            'promotions' => [],
            'size_advice' => null,
            'knowledge_base' => [],
            'module_items' => [],
            'suggestions' => $this->defaultSuggestions('stock_check', (bool)$stock),
            'used_ai' => false,
            'model' => null,
        ];

        $this->conversationLogger->log(
            $sessionId,
            $message,
            $response,
            (int)((microtime(true) - $startedAt) * 1000)
        );

        return $response;
    }

    private function handleStockCheck(array $filters): array
    {
        $search = $this->productService->searchProducts($filters, 5, false);
        $products = $search['products'];
        if (!$products) {
            $fallbackFilters = $filters;
            $fallbackFilters['max_price'] = null;
            $fallbackFilters['min_price'] = null;
            $search = $this->productService->searchProducts($fallbackFilters, 5, true);
            $products = $search['products'];
        }

        if (!$products) {
            return [
                'message' => 'Mình chưa tìm thấy sản phẩm khớp chính xác. Bạn có thể gửi link sản phẩm, SKU hoặc tên sản phẩm đầy đủ hơn để mình kiểm tra tồn hàng.',
                'products' => [],
            ];
        }

        if (count($products) === 1) {
            return [
                'message' => $this->buildStockMessage([
                    'name' => $products[0]['name'],
                    'sku' => $products[0]['sku'],
                    'stockStatus' => $products[0]['stockStatus'],
                    'qty' => $products[0]['qty'],
                    'product' => $products[0],
                ]),
                'products' => $products,
            ];
        }

        $this->setConversationState([
            'pendingAction' => 'select_product_for_stock',
            'recommendedProducts' => array_map(static function (array $product, int $index): array {
                return [
                    'index' => $index + 1,
                    'sku' => $product['sku'],
                    'name' => $product['name'],
                    'product' => $product,
                ];
            }, $products, array_keys($products)),
            'createdAt' => time(),
        ]);

        return [
            'message' => 'Mình tìm thấy một vài sản phẩm có thể khớp với yêu cầu. Bạn chọn sản phẩm bên dưới hoặc nhập số thứ tự, ví dụ “2”, để mình kiểm tra tồn kho chính xác nhé.',
            'products' => $products,
        ];
    }

    private function buildStockMessage(?array $stock): string
    {
        if (!$stock) {
            return 'Mình chưa tìm thấy sản phẩm này trong catalog hiện tại.';
        }

        if ($stock['stockStatus'] === 'in_stock') {
            $qty = (float)$stock['qty'];
            return 'Sản phẩm ' . $stock['name'] . ' hiện còn hàng. SKU: ' . $stock['sku'] . '.'
                . ($qty > 0 ? ' Số lượng tồn kho: ' . rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') . ' sản phẩm.' : '');
        }

        return 'Sản phẩm ' . $stock['name'] . ' hiện đang hết hàng. SKU: ' . $stock['sku'] . '.';
    }

    private function buildProductSearchMessage(array $filters, array $search): string
    {
        if ($search['products'] && empty($search['fallback'])) {
            return 'Mình tìm thấy các sản phẩm phù hợp với yêu cầu của bạn. Bạn xem bên dưới nhé.';
        }

        if ($search['products']) {
            return 'Mình chưa thấy kết quả khớp hoàn toàn, nhưng có vài lựa chọn gần ngân sách hoặc liên quan bên dưới.';
        }

        if (!empty($filters['needs_clarification']) && !empty($filters['clarification_question'])) {
            return (string)$filters['clarification_question'];
        }

        return 'Mình chưa tìm thấy sản phẩm phù hợp với yêu cầu này. Bạn có thể nói rõ hơn loại sản phẩm, ngân sách hoặc size nhé.';
    }

    private function mapIntent(string $intent): string
    {
        $map = [
            'latest_products' => 'newest_products',
            'promotion_list' => 'promotion_products',
            'size_advisor' => 'size_recommendation',
            'order_guide' => 'order_help',
            'fallback' => 'unknown',
        ];
        return $map[$intent] ?? $intent;
    }

    private function forceIntentFromShortCommand(string $message): ?string
    {
        $plain = $this->removeAccents(mb_strtolower(trim($message)));
        if (preg_match('/\b(mau tuong tu|tuong tu|xem them mau|cung tam gia|san pham cung tam gia)\b/u', $plain)) {
            return 'similar_products';
        }

        if (preg_match('/\b(san pham moi nhat|hang moi|moi nhat)\b/u', $plain)) {
            return 'newest_products';
        }

        if (preg_match('/\b(uu dai hom nay|khuyen mai hom nay|sale hom nay)\b/u', $plain)) {
            return 'promotion_products';
        }

        return null;
    }

    private function generateFinalMessage(string $message, array $response, array $context): ?string
    {
        return $this->groqClient->generateWithSystem(self::FINAL_RESPONSE_PROMPT, [
            'user_message' => $message,
            'detected_intent' => $response['intent'],
            'result_count' => count($response['products']) + count($response['module_items']) + count($response['promotions']),
            'draft_message' => $response['message'],
            'real_products' => array_map(static function (array $product): array {
                return [
                    'name' => $product['name'] ?? '',
                    'price' => $product['price'] ?? null,
                    'regularPrice' => $product['regularPrice'] ?? null,
                    'stockStatus' => $product['stockStatus'] ?? '',
                ];
            }, $response['products']),
            'real_module_items' => $response['module_items'],
            'real_promotions' => $response['promotions'],
            'size_advice' => $response['size_advice'],
            'safe_context' => $context,
        ], 0.2, 300);
    }

    private function defaultSuggestions(string $intent, bool $hasProducts): array
    {
        if ($hasProducts) {
            return [
                ['label' => 'Xem thêm mẫu tương tự', 'message' => 'Xem thêm mẫu tương tự'],
                ['label' => 'Kiểm tra tồn hàng', 'message' => 'Kiểm tra tồn hàng'],
                ['label' => 'Sản phẩm cùng tầm giá', 'message' => 'Sản phẩm cùng tầm giá'],
                ['label' => 'Chọn size/số', 'message' => 'Tư vấn chọn size'],
            ];
        }

        if ($intent === 'unknown') {
            return [
                ['label' => 'Sản phẩm mới nhất', 'message' => 'Sản phẩm mới nhất'],
                ['label' => 'Giày dưới 2 triệu', 'message' => 'Tìm giày dưới 2 triệu'],
                ['label' => 'Áo dưới 400k', 'message' => 'Gợi ý cho tôi vài mẫu áo dưới 400k'],
                ['label' => 'Ưu đãi hôm nay', 'message' => 'Ưu đãi hôm nay'],
                ['label' => 'Kiểm tra tồn hàng', 'message' => 'Kiểm tra tồn hàng Nike Dunk'],
            ];
        }

        return [
            ['label' => 'Thử sản phẩm dưới 500k', 'message' => 'Gợi ý sản phẩm dưới 500k'],
            ['label' => 'Xem ưu đãi hôm nay', 'message' => 'Ưu đãi hôm nay'],
            ['label' => 'Sản phẩm mới nhất', 'message' => 'Sản phẩm mới nhất'],
        ];
    }

    private function getConversationState(): array
    {
        $state = $this->session->getData(self::STATE_KEY);
        return is_array($state) ? $state : [];
    }

    private function setConversationState(array $state): void
    {
        $this->session->setData(self::STATE_KEY, $state);
    }

    private function clearConversationState(): void
    {
        $this->session->unsetData(self::STATE_KEY);
    }

    private function hasPendingStockSelection(): bool
    {
        $state = $this->getConversationState();
        return ($state['pendingAction'] ?? '') === 'select_product_for_stock';
    }

    private function rememberLastProducts(array $products): void
    {
        $state = $this->getConversationState();
        $state['lastProducts'] = array_map(static function (array $product): array {
            return [
                'sku' => $product['sku'] ?? '',
                'name' => $product['name'] ?? '',
            ];
        }, array_slice($products, 0, 5));
        $state['lastProductsCreatedAt'] = time();
        $this->setConversationState($state);
    }

    private function getLastProductSku(): ?string
    {
        $state = $this->getConversationState();
        if ((int)($state['lastProductsCreatedAt'] ?? 0) < (time() - self::STATE_TTL)) {
            return null;
        }
        $products = $state['lastProducts'] ?? [];
        return $products[0]['sku'] ?? null;
    }

    private function buildKnowledgeMessage(array $items): string
    {
        if (!$items) {
            return 'Hiện mình chưa có đủ thông tin chính xác cho phần này. Bạn có thể liên hệ bộ phận hỗ trợ hoặc thử hỏi theo cách cụ thể hơn nhé.';
        }

        return (string)$items[0]['answer'];
    }

    private function shouldUseAi(string $intent): bool
    {
        return !in_array($intent, [
            'newest_products',
            'product_search',
            'product_recommendation',
            'stock_check',
            'promotion_products',
            'similar_products',
            'store_locator',
            'exchange_rate',
            'economic_news',
            'weather',
            'size_recommendation',
            'unknown',
        ], true);
    }

    private function extractWeatherCity(string $message): string
    {
        if (preg_match('/(?:ở|tai|tại|weather in)\\s+([\\p{L}\\s]+)$/iu', trim($message), $match)) {
            return trim($match[1]);
        }

        return '';
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
