<?php

namespace Dalactive\EconomicNews\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;
use Magento\Framework\Data\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

class News extends Template
{
    private const CACHE_KEY = 'dalactive_economic_news_rss_v3';
    private const CACHE_TAG = 'dalactive_economic_news';
    private const CACHE_TTL = 3600;
    private const DEFAULT_PAGE_SIZE = 12;
    private const MAX_PAGE_SIZE = 12;
    private const HTML_FALLBACK_URLS = [
        'https://vnexpress.net/kinh-doanh',
        'https://vnexpress.net/kinh-doanh-p2',
        'https://vnexpress.net/kinh-doanh-p3',
    ];

    protected $curl;
    protected $logger;
    protected $collectionFactory;
    protected $scopeConfig;
    protected $cache;

    public function __construct(
        Template\Context $context,
        Curl $curl,
        LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->curl = $curl;
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->cache = $context->getCache();
        parent::__construct($context, $data);
    }

    /**
     * Fetch and process economic news from RSS feed
     *
     * @return \Magento\Framework\Data\Collection
     */
    public function getEconomicNewsCollection()
    {
        $rssUrl = $this->scopeConfig->getValue('economicnews/rss/rss_feed_url');
        if (!$rssUrl) {
            $rssUrl = 'https://vnexpress.net/rss/kinh-doanh.rss';
        }

        try {
            // Set the User-Agent header with your actual website URL
            $this->curl->setHeaders([
                'User-Agent' => 'DalactiveEconomicNews/1.0 (https://dalactive.test)'
            ]);
            $this->curl->setOption(\CURLOPT_CONNECTTIMEOUT, 2);
            $this->curl->setOption(\CURLOPT_TIMEOUT, 5);
            $this->curl->setOption(\CURLOPT_FOLLOWLOCATION, true);
            $this->curl->setOption(\CURLOPT_IPRESOLVE, \CURL_IPRESOLVE_V4);

            $this->curl->get($rssUrl);
            $response = $this->curl->getBody();

            // Load the XML response and parse it
            libxml_use_internal_errors(true); // Suppress XML parsing errors
            $xml = simplexml_load_string($response);
            if ($xml === false) {
                $errors = libxml_get_errors();
                $errorMessage = 'Failed to load XML: ';
                foreach ($errors as $error) {
                    $errorMessage .= $error->message . '; ';
                }
                libxml_clear_errors();
                throw new \Exception($errorMessage);
            }

            $newsData = [];
            foreach ($xml->channel->item as $item) {
                // Strip HTML tags from title and description
                $rawDescription = (string) $item->description;
                $cleanTitle = strip_tags((string) $item->title);
                $cleanDescription = trim(preg_replace('/\s+/', ' ', strip_tags($rawDescription)));
                $cleanLink = strip_tags((string) $item->link);
                $cleanPubDate = strip_tags((string) $item->pubDate);
                $cleanImage = $this->extractImageUrl($item, $rawDescription);

                $newsData[] = [
                    'title' => $cleanTitle,
                    'description' => $cleanDescription,
                    'link' => $cleanLink,
                    'pubDate' => $cleanPubDate,
                    'image' => $cleanImage
                ];

                // Stop collecting if we've reached 200 items
                if (count($newsData) >= 400) {
                    break;
                }
            }

            if (!empty($newsData)) {
                $newsData = $this->enrichMissingImages($newsData);
                $this->cache->save(
                    json_encode($newsData),
                    self::CACHE_KEY,
                    [self::CACHE_TAG],
                    self::CACHE_TTL
                );

                return $this->createCollectionFromItems($newsData);
            }
        } catch (\Exception $e) {
            // Log the error instead of echoing
            $this->logger->error('EconomicNews Module Error: ' . $e->getMessage());
        }

        $htmlItems = $this->getHtmlNewsItems();
        if (!empty($htmlItems)) {
            $htmlItems = $this->enrichMissingImages($htmlItems);
            $this->cache->save(
                json_encode($htmlItems),
                self::CACHE_KEY,
                [self::CACHE_TAG],
                self::CACHE_TTL
            );

            return $this->createCollectionFromItems($htmlItems);
        }

        $cachedItems = $this->getCachedNewsItems();
        if (!empty($cachedItems)) {
            return $this->createCollectionFromItems($cachedItems);
        }

        return $this->createCollectionFromItems($this->enrichMissingImages($this->getFallbackNewsItems()));
    }

    private function getCachedNewsItems()
    {
        $cachedData = $this->cache->load(self::CACHE_KEY);
        if (!$cachedData) {
            return [];
        }

        try {
            $items = json_decode($cachedData, true);
            return is_array($items) ? $items : [];
        } catch (\Exception $e) {
            $this->logger->error('EconomicNews cache decode error: ' . $e->getMessage());
            return [];
        }
    }

    private function createCollectionFromItems(array $items)
    {
        $collection = $this->collectionFactory->create();
        foreach ($items as $item) {
            $collection->addItem(new \Magento\Framework\DataObject($item));
        }

        return $collection;
    }

    private function getHtmlNewsItems(): array
    {
        $items = [];
        $seenLinks = [];

        foreach (self::HTML_FALLBACK_URLS as $url) {
            try {
                $this->curl->setHeaders([
                    'User-Agent' => 'DalactiveEconomicNews/1.0 (https://dalactive.test)'
                ]);
                $this->curl->setOption(\CURLOPT_CONNECTTIMEOUT, 2);
                $this->curl->setOption(\CURLOPT_TIMEOUT, 5);
                $this->curl->setOption(\CURLOPT_FOLLOWLOCATION, true);
                $this->curl->setOption(\CURLOPT_IPRESOLVE, \CURL_IPRESOLVE_V4);
                $this->curl->get($url);
                $html = $this->curl->getBody();
            } catch (\Exception $exception) {
                $this->logger->error('EconomicNews HTML fallback error: ' . $exception->getMessage());
                continue;
            }

            foreach ($this->extractHtmlArticles($html) as $item) {
                if (empty($item['link']) || isset($seenLinks[$item['link']])) {
                    continue;
                }

                $seenLinks[$item['link']] = true;
                $items[] = $item;

                if (count($items) >= 72) {
                    return $items;
                }
            }
        }

        return $items;
    }

    private function extractHtmlArticles(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $articles = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " item-news ")]');
        $items = [];

        foreach ($articles as $article) {
            $titleNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " title-news ")]//a', $article)->item(0);
            if (!$titleNode) {
                continue;
            }

            $title = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
            $link = $this->normalizeArticleUrl((string) $titleNode->getAttribute('href'));
            if ($title === '' || $link === '') {
                continue;
            }

            $descriptionNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " description ")]//a | .//*[contains(concat(" ", normalize-space(@class), " "), " description ")]', $article)->item(0);
            $description = $descriptionNode ? trim(preg_replace('/\s+/', ' ', $descriptionNode->textContent)) : '';

            $imageNode = $xpath->query('.//img', $article)->item(0);
            $image = '';
            if ($imageNode) {
                foreach (['data-src', 'data-original', 'src'] as $attribute) {
                    $image = $this->normalizeImageUrl((string) $imageNode->getAttribute($attribute));
                    if ($image !== '') {
                        break;
                    }
                }
            }

            $items[] = [
                'title' => $title,
                'description' => $description,
                'link' => $link,
                'pubDate' => date(DATE_RSS),
                'image' => $image,
            ];
        }

        return $items;
    }

    private function normalizeArticleUrl(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        if ($url === '') {
            return '';
        }

        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }

        if (strpos($url, '/') === 0) {
            return 'https://vnexpress.net' . $url;
        }

        return preg_match('/^https?:\/\//i', $url) ? $url : '';
    }

    private function getFallbackNewsItems(): array
    {
        return [
            [
                'title' => 'Giá dầu bật tăng khi ông Trump dọa không kích Iran thêm 2-3 tuần',
                'description' => 'Giá dầu tăng khoảng 6 USD mỗi thùng khi Tổng thống Mỹ Donald Trump cảnh báo tiếp tục không kích Iran trong 2-3 tuần tới.',
                'link' => 'https://vnexpress.net/gia-dau-bat-tang-khi-ong-trump-doa-khong-kich-iran-them-2-3-tuan-5057475.html',
                'pubDate' => date(DATE_RSS, strtotime('2026-04-02 10:00:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'VN-Index lên cao nhất nửa tháng',
                'description' => 'Dòng tiền rót mạnh vào cổ phiếu vốn hóa lớn, nhất là nhóm Vingroup, giúp VN-Index tăng 28 điểm và lấy lại mốc 1.700 điểm.',
                'link' => 'https://vnexpress.net/vn-index-len-cao-nhat-nua-thang-5057263.html',
                'pubDate' => date(DATE_RSS, strtotime('2026-04-01 16:11:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'VNG lần đầu công bố doanh thu của Zalo và Zalopay',
                'description' => 'VNG lần đầu công bố doanh thu của ứng dụng Zalo và Zalopay, lần lượt đạt 1.718 tỷ đồng và 1.111 tỷ đồng trong năm ngoái.',
                'link' => 'https://vnexpress.net/vng-lan-dau-cong-bo-doanh-thu-cua-zalo-va-zalopay-5057154.html',
                'pubDate' => date(DATE_RSS, strtotime('2026-04-01 14:11:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'Vinhomes muốn trả cổ tức tiền mặt kỷ lục gần 1 tỷ USD',
                'description' => 'Sau 3 năm giữ lại lợi nhuận, Vinhomes muốn chia cổ tức ở mức cao kỷ lục trong năm 2026, riêng tiền mặt tỷ lệ 60%.',
                'link' => 'https://vnexpress.net/vinhomes-muon-tra-co-tuc-tien-mat-ky-luc-gan-1-ty-usd-5057066.html',
                'pubDate' => date(DATE_RSS, strtotime('2026-04-01 12:22:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'V-Green hợp tác Vikki phát triển hạ tầng sạc, tủ đổi pin xe điện',
                'description' => 'Ngân hàng số Vikki chia sẻ mặt bằng để V-Green đầu tư, lắp đặt và vận hành trạm sạc ôtô điện, tủ đổi pin xe máy điện.',
                'link' => 'https://vnexpress.net/v-green-hop-tac-vikki-phat-trien-ha-tang-sac-tu-doi-pin-xe-dien-5057109.html',
                'pubDate' => date(DATE_RSS, strtotime('2026-04-01 13:20:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'Thiên Long bắt đầu tiếp quản Nhà sách Phương Nam',
                'description' => 'Nhà sách Phương Nam thay mới dàn lãnh đạo cấp cao, trong đó có nhân sự của Thiên Long sau kế hoạch sáp nhập PNC.',
                'link' => 'https://vnexpress.net/thien-long-bat-dau-tiep-quan-nha-sach-phuong-nam-4894558.html',
                'pubDate' => date(DATE_RSS, strtotime('2025-06-04 18:54:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'Giá cà phê lao dốc',
                'description' => 'Tuần này, giá cà phê dao động quanh mốc thấp hơn cùng kỳ, phản ánh áp lực nguồn cung và nhu cầu xuất khẩu.',
                'link' => 'https://vnexpress.net/kinh-doanh',
                'pubDate' => date(DATE_RSS, strtotime('2026-04-01 10:30:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'Cổ phiếu Vinhomes đỡ thị trường',
                'description' => 'Nhóm vốn hóa lớn hỗ trợ chỉ số, trong khi dòng tiền tiếp tục phân hóa giữa các nhóm ngành.',
                'link' => 'https://vnexpress.net/kinh-doanh/chung-khoan',
                'pubDate' => date(DATE_RSS, strtotime('2026-04-01 09:45:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'Đề xuất thuế VAT, môi trường với dầu hỏa, mazut về 0',
                'description' => 'Cơ quan quản lý đề xuất điều chỉnh chính sách thuế để hỗ trợ thị trường năng lượng và hoạt động sản xuất.',
                'link' => 'https://vnexpress.net/kinh-doanh/vi-mo',
                'pubDate' => date(DATE_RSS, strtotime('2026-03-31 16:20:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'Thu phí không dừng VETC lãi hơn trăm tỷ đồng',
                'description' => 'Doanh nghiệp vận hành dịch vụ thu phí không dừng ghi nhận kết quả kinh doanh tích cực nhờ lưu lượng giao thông phục hồi.',
                'link' => 'https://vnexpress.net/kinh-doanh/doanh-nghiep',
                'pubDate' => date(DATE_RSS, strtotime('2026-03-31 15:40:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'SpaceX nộp đơn IPO',
                'description' => 'SpaceX bí mật nộp đơn xin phát hành cổ phiếu lần đầu ra công chúng tại Mỹ, theo nguồn tin quốc tế.',
                'link' => 'https://vnexpress.net/kinh-doanh/quoc-te',
                'pubDate' => date(DATE_RSS, strtotime('2026-03-31 14:25:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'Lợi nhuận chủ đầu tư Aqua City giảm gần 5 lần',
                'description' => 'Số lượng bàn giao nhà giảm khiến lợi nhuận của chủ đầu tư dự án bất động sản sụt giảm đáng kể.',
                'link' => 'https://vnexpress.net/kinh-doanh/bat-dong-san',
                'pubDate' => date(DATE_RSS, strtotime('2026-03-31 13:50:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'Trần thu nhập mua nhà xã hội có thể lên 25 triệu đồng mỗi tháng',
                'description' => 'Bộ Xây dựng đề xuất nới điều kiện thu nhập mua nhà ở xã hội nhằm mở rộng nhóm người đủ điều kiện tiếp cận.',
                'link' => 'https://vnexpress.net/tran-thu-nhap-mua-nha-xa-hoi-co-the-len-25-trieu-dong-moi-thang-5052378.html',
                'pubDate' => date(DATE_RSS, strtotime('2026-03-31 12:35:00 +0700')),
                'image' => 'https://vcdn1-vnexpress.vnecdn.net/2026/03/19/DJI-0548-7250-1773901064.jpg?dpr=1&fit=crop&h=0&q=100&s=jLxXGdOchzqKN7be8wNmyQ&w=680'
            ],
            [
                'title' => 'Giá xăng tại Mỹ tăng vọt vì xung đột ở Trung Đông',
                'description' => 'Giá xăng, dầu diesel tại Mỹ tăng mạnh khi chuỗi cung ứng năng lượng toàn cầu chịu tác động từ căng thẳng địa chính trị.',
                'link' => 'https://vnexpress.net/kinh-doanh/quoc-te',
                'pubDate' => date(DATE_RSS, strtotime('2026-03-30 17:15:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'Giá vàng thế giới tăng mạnh, dầu thô vượt 90 USD',
                'description' => 'Các tài sản trú ẩn và hàng hóa năng lượng biến động mạnh trong phiên giao dịch quốc tế.',
                'link' => 'https://vnexpress.net/kinh-doanh/hang-hoa',
                'pubDate' => date(DATE_RSS, strtotime('2026-03-30 16:05:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'Citigroup hạ dự báo giá Bitcoin',
                'description' => 'Ngân hàng đầu tư phố Wall điều chỉnh mục tiêu giá Bitcoin do lo ngại chính sách và triển vọng thị trường.',
                'link' => 'https://vnexpress.net/kinh-doanh/tien-cua-toi',
                'pubDate' => date(DATE_RSS, strtotime('2026-03-30 15:10:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'Nhiều nông sản rớt giá còn vài nghìn đồng một kg',
                'description' => 'Dư cung và xuất khẩu gặp khó khiến một số mặt hàng nông sản giảm giá sâu tại vùng sản xuất.',
                'link' => 'https://vnexpress.net/kinh-doanh/hang-hoa',
                'pubDate' => date(DATE_RSS, strtotime('2026-03-30 14:30:00 +0700')),
                'image' => ''
            ],
            [
                'title' => 'CEO Nvidia dự báo doanh thu lớn từ đơn hàng chip',
                'description' => 'Lãnh đạo Nvidia nhận định nhu cầu suy luận AI có thể tiếp tục thúc đẩy doanh thu chip trong thời gian tới.',
                'link' => 'https://vnexpress.net/kinh-doanh/quoc-te',
                'pubDate' => date(DATE_RSS, strtotime('2026-03-30 13:20:00 +0700')),
                'image' => ''
            ],
        ];
    }

    private function enrichMissingImages(array $items): array
    {
        $lookupLimit = 6;
        $lookups = 0;

        foreach ($items as &$item) {
            if (
                !empty($item['image'])
                || empty($item['link'])
                || $lookups >= $lookupLimit
                || !$this->isVnExpressArticleUrl((string) $item['link'])
            ) {
                continue;
            }

            $image = $this->getArticleImageFromPage((string) $item['link']);
            if ($image !== '') {
                $item['image'] = $image;
            }
            $lookups++;
        }
        unset($item);

        return $items;
    }

    private function getArticleImageFromPage(string $url): string
    {
        if (!$this->isVnExpressArticleUrl($url)) {
            return '';
        }

        try {
            $this->curl->setHeaders([
                'User-Agent' => 'DalactiveEconomicNews/1.0 (https://dalactive.test)'
            ]);
            $this->curl->setOption(\CURLOPT_CONNECTTIMEOUT, 1);
            $this->curl->setOption(\CURLOPT_TIMEOUT, 2);
            $this->curl->setOption(\CURLOPT_FOLLOWLOCATION, true);
            $this->curl->setOption(\CURLOPT_IPRESOLVE, \CURL_IPRESOLVE_V4);
            $this->curl->get($url);
            $html = $this->curl->getBody();
        } catch (\Exception $exception) {
            $this->logger->error('EconomicNews article image error: ' . $exception->getMessage());
            return '';
        }

        return $this->extractImageFromArticleHtml($html);
    }

    private function extractImageFromArticleHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $queries = [
            '//meta[@property="og:image"]/@content',
            '//meta[@name="twitter:image"]/@content',
            '//link[@rel="image_src"]/@href',
            '//*[contains(concat(" ", normalize-space(@class), " "), " fck_detail ")]//img/@data-src',
            '//*[contains(concat(" ", normalize-space(@class), " "), " fck_detail ")]//img/@src',
            '//article//img/@data-src',
            '//article//img/@src',
        ];

        foreach ($queries as $query) {
            $node = $xpath->query($query)->item(0);
            if (!$node) {
                continue;
            }

            $image = $this->normalizeImageUrl((string) $node->nodeValue);
            if ($image !== '') {
                return $image;
            }
        }

        if (preg_match('/<meta[^>]+(?:property|name)=["\'](?:og:image|twitter:image)["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $this->normalizeImageUrl($matches[1]);
        }

        return '';
    }

    private function isVnExpressArticleUrl(string $url): bool
    {
        return (bool) preg_match('/^https?:\/\/([^\/]+\.)?vnexpress\.net\/.+-\d+\.html(?:\?.*)?$/i', $url);
    }

    private function extractImageUrl(\SimpleXMLElement $item, string $rawDescription): string
    {
        $imageUrl = '';

        if (isset($item->enclosure['url'])) {
            $imageUrl = (string) $item->enclosure['url'];
        }

        if ($imageUrl === '') {
            $media = $item->children('media', true);
            if (isset($media->content) && isset($media->content->attributes()->url)) {
                $imageUrl = (string) $media->content->attributes()->url;
            } elseif (isset($media->thumbnail) && isset($media->thumbnail->attributes()->url)) {
                $imageUrl = (string) $media->thumbnail->attributes()->url;
            }
        }

        if ($imageUrl === '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $rawDescription, $matches)) {
            $imageUrl = $matches[1];
        }

        return $this->normalizeImageUrl($imageUrl);
    }

    private function normalizeImageUrl(string $imageUrl): string
    {
        $imageUrl = trim(html_entity_decode(strip_tags($imageUrl), ENT_QUOTES, 'UTF-8'));
        if ($imageUrl === '') {
            return '';
        }

        if (strpos($imageUrl, '//') === 0) {
            return 'https:' . $imageUrl;
        }

        if (!preg_match('/^https?:\/\//i', $imageUrl)) {
            return '';
        }

        return $imageUrl;
    }

    /**
     * Get paginated economic news
     *
     * @return \Magento\Framework\Data\Collection
     */
    public function getPaginatedEconomicNews()
    {
        $collection = $this->getEconomicNewsCollection();

        $pageSize = (int) $this->scopeConfig->getValue('economicnews/rss/items_per_page');
        if (!$pageSize) {
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }
        $pageSize = max(1, min($pageSize, self::MAX_PAGE_SIZE));

        // Get current page from request, default to 1
        $currentPage = (int) $this->getRequest()->getParam('p') ?: 1;

        // Calculate total items and pages
        $totalItems = $collection->getSize();
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));

        // Ensure current page is within bounds
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        } elseif ($currentPage < 1) {
            $currentPage = 1;
        }

        // Calculate offset
        $offset = ($currentPage - 1) * $pageSize;

        // Slice the collection items
        $items = $collection->getItems();
        $pagedItems = array_slice($items, $offset, $pageSize);

        // Create a new collection for paged items
        $pagedCollection = $this->collectionFactory->create();
        foreach ($pagedItems as $item) {
            $pagedCollection->addItem($item);
        }

        // Prepare pagination data
        $paginationData = [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'page_size' => $pageSize,
            'total_items' => $totalItems
        ];

        // Set pagination data to the block for use in the template
        $this->setData('pagination', $paginationData);

        return $pagedCollection;
    }

    /**
     * Get pagination data
     *
     * @return array
     */
    public function getPaginationData()
    {
        return $this->getData('pagination');
    }

    /**
     * Get Pager HTML
     *
     * @return string
     */
    public function getPagerHtml()
    {
        return $this->getChildHtml('economic.news.pager');
    }

    /**
     * Generate URL for a specific page number
     *
     * @param int $page
     * @return string
     */
    public function getPagerUrl($page)
    {
        $params = $this->getRequest()->getParams();
        $params['p'] = $page;

        return $this->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true, '_query' => $params]);
    }

    /**
     * Latest news items for homepage teaser section.
     *
     * @param int|null $limit
     * @return \Magento\Framework\DataObject[]
     */
    public function getHomepageNewsItems(?int $limit = null): array
    {
        if ($limit === null) {
            $limit = 4;
        }

        $limit = max(1, min(8, $limit));
        $items = array_values($this->getEconomicNewsCollection()->getItems());

        return array_slice($items, 0, $limit);
    }

    /**
     * Full economic news listing page URL.
     *
     * @return string
     */
    public function getViewAllUrl(): string
    {
        return $this->getUrl('economicnews/index/index');
    }

    /**
     * Format RSS/article date for homepage cards.
     *
     * @param string $date
     * @return string
     */
    public function formatNewsDate(string $date): string
    {
        if ($date === '') {
            return '';
        }

        try {
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                return '';
            }

            return $this->formatDate(date('Y-m-d H:i:s', $timestamp), \IntlDateFormatter::MEDIUM);
        } catch (\Exception $exception) {
            return '';
        }
    }
}
