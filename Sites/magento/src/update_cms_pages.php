<?php
use Magento\Framework\App\Bootstrap;

require '/var/www/html/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$state = $om->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

$pageRepo = $om->get(\Magento\Cms\Api\PageRepositoryInterface::class);

$pages = [];

// ── 1. Điều khoản sử dụng ────────────────────────────────────────────────────
$pages['dieu-khoan-su-dung'] = <<<HTML
<div class="dalactive-policy-page">
    <div class="dalactive-policy-hero">
        <h1 class="dalactive-policy-hero__title">Điều khoản Sử dụng</h1>
        <p class="dalactive-policy-hero__sub">Vui lòng đọc kỹ các điều khoản trước khi sử dụng dịch vụ của chúng tôi.</p>
    </div>
    <div class="dalactive-policy-body">
        <p>Chào mừng bạn đến với <strong>DAL Active</strong> – thời trang thể thao chính hãng. Khi truy cập và sử dụng website, bạn đồng ý tuân thủ các điều khoản dưới đây.</p>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">1</span> Chấp nhận Điều khoản</h2>
            <p>Khi sử dụng dịch vụ của chúng tôi, bạn đồng ý với các điều khoản được quy định. Nếu không đồng ý, vui lòng ngừng sử dụng website.</p>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">2</span> Sử dụng Dịch vụ</h2>
            <ul>
                <li>Người sử dụng phải chịu trách nhiệm với hành động của mình trên website.</li>
                <li>Không được phép sử dụng website để thực hiện các hành vi bất hợp pháp.</li>
            </ul>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">3</span> Thông tin Sản phẩm</h2>
            <ul>
                <li>Chúng tôi cam kết cung cấp thông tin chính xác nhất về sản phẩm. Tuy nhiên có thể có lỗi hoặc thông tin chưa đầy đủ trong một số trường hợp.</li>
                <li>Hình ảnh sản phẩm chỉ mang tính chất minh họa – màu sắc thực tế có thể khác so với màn hình.</li>
            </ul>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">4</span> Giá cả và Thanh toán</h2>
            <ul>
                <li>Giá hiển thị có thể đã hoặc chưa bao gồm thuế tùy theo quy định.</li>
                <li>Chúng tôi có quyền thay đổi giá mà không cần thông báo trước.</li>
                <li>Khách hàng chịu trách nhiệm cung cấp thông tin thanh toán chính xác.</li>
            </ul>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">5</span> Chính sách Đổi/Trả</h2>
            <p>Chúng tôi chấp nhận đổi hoặc trả sản phẩm trong vòng <strong>7 ngày</strong> kể từ ngày nhận hàng, với điều kiện sản phẩm còn nguyên tem, nhãn mác và chưa qua sử dụng.</p>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">6</span> Bảo mật Thông tin</h2>
            <p>Thông tin cá nhân của bạn được bảo mật theo chính sách của chúng tôi. Chúng tôi không chia sẻ thông tin cá nhân cho bên thứ ba khi chưa có sự đồng ý.</p>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">7</span> Quyền Sở hữu Trí tuệ</h2>
            <p>Tất cả nội dung, hình ảnh và thương hiệu trên website thuộc sở hữu của chúng tôi hoặc bên thứ ba được cấp phép. Không được phép sao chép khi chưa có sự đồng ý bằng văn bản.</p>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">8</span> Thay đổi Điều khoản</h2>
            <p>Chúng tôi có quyền thay đổi, cập nhật điều khoản bất kỳ lúc nào. Việc tiếp tục sử dụng website đồng nghĩa với việc bạn chấp nhận các điều khoản mới.</p>
        </div>

        <div class="dalactive-policy-contact">
            <span class="dalactive-policy-contact__icon">&#9993;</span>
            <div>
                <p>Có câu hỏi? Liên hệ với chúng tôi:</p>
                <p><strong>Email:</strong> <a href="mailto:dalactive166@gmail.com">dalactive166@gmail.com</a></p>
                <p><strong>Hotline:</strong> <a href="tel:0886698386">0886 698 386</a></p>
            </div>
        </div>
    </div>
</div>
HTML;

// ── 2. Phương thức thanh toán ─────────────────────────────────────────────────
$pages['thanh-toan'] = <<<HTML
<div class="dalactive-policy-page">
    <div class="dalactive-policy-hero">
        <h1 class="dalactive-policy-hero__title">Phương thức Thanh toán</h1>
        <p class="dalactive-policy-hero__sub">Chúng tôi hỗ trợ nhiều hình thức thanh toán linh hoạt, an toàn và tiện lợi.</p>
    </div>
    <div class="dalactive-policy-body">
        <div class="dalactive-payment-grid">
            <div class="dalactive-payment-card">
                <div class="dalactive-payment-card__icon">&#128181;</div>
                <h2 class="dalactive-payment-card__title">Thanh toán khi nhận hàng (COD)</h2>
                <p>Bạn thanh toán trực tiếp bằng tiền mặt khi nhận hàng từ nhân viên giao hàng. Áp dụng cho mọi đơn hàng trong lãnh thổ Việt Nam.</p>
            </div>
            <div class="dalactive-payment-card">
                <div class="dalactive-payment-card__icon">&#128242;</div>
                <h2 class="dalactive-payment-card__title">Chuyển khoản ngân hàng (SePay)</h2>
                <p>Quét mã QR hoặc chuyển khoản qua SePay. Đơn hàng được xác nhận tự động sau khi thanh toán thành công.</p>
            </div>
            <div class="dalactive-payment-card">
                <div class="dalactive-payment-card__icon">&#127984;</div>
                <h2 class="dalactive-payment-card__title">Thanh toán qua VNPay</h2>
                <p>VNPay hỗ trợ thanh toán trực tuyến thông qua mã QR và các tài khoản ngân hàng liên kết. Nhanh chóng và dễ dàng.</p>
            </div>
            <div class="dalactive-payment-card">
                <div class="dalactive-payment-card__icon">&#128274;</div>
                <h2 class="dalactive-payment-card__title">Bảo mật thanh toán</h2>
                <p>Tất cả giao dịch được mã hóa SSL. Chúng tôi không lưu trữ thông tin thẻ thanh toán của bạn.</p>
            </div>
        </div>
        <div class="dalactive-policy-contact">
            <span class="dalactive-policy-contact__icon">&#9993;</span>
            <div>
                <p>Cần hỗ trợ thanh toán? Liên hệ ngay:</p>
                <p><strong>Email:</strong> <a href="mailto:dalactive166@gmail.com">dalactive166@gmail.com</a></p>
                <p><strong>Hotline:</strong> <a href="tel:0886698386">0886 698 386</a></p>
            </div>
        </div>
    </div>
</div>
HTML;

// ── 3. Hướng dẫn vận chuyển ───────────────────────────────────────────────────
$pages['huong-dan-van-chuyen'] = <<<HTML
<div class="dalactive-policy-page">
    <div class="dalactive-policy-hero">
        <h1 class="dalactive-policy-hero__title">Hướng dẫn Vận chuyển</h1>
        <p class="dalactive-policy-hero__sub">Chúng tôi cam kết giao hàng nhanh chóng, an toàn đến tay bạn.</p>
    </div>
    <div class="dalactive-policy-body">
        <div class="dalactive-shipping-steps">
            <div class="dalactive-shipping-step">
                <div class="dalactive-shipping-step__num">1</div>
                <div class="dalactive-shipping-step__content">
                    <h3>Đặt hàng &amp; Thanh toán</h3>
                    <p>Đơn hàng được tiếp nhận và xác nhận ngay sau khi thanh toán thành công.</p>
                </div>
            </div>
            <div class="dalactive-shipping-step">
                <div class="dalactive-shipping-step__num">2</div>
                <div class="dalactive-shipping-step__content">
                    <h3>Xử lý &amp; Đóng gói</h3>
                    <p>Chúng tôi kiểm tra, đóng gói cẩn thận để sản phẩm đến tay bạn nguyên vẹn.</p>
                </div>
            </div>
            <div class="dalactive-shipping-step">
                <div class="dalactive-shipping-step__num">3</div>
                <div class="dalactive-shipping-step__content">
                    <h3>Bàn giao vận chuyển</h3>
                    <p>Đơn hàng được bàn giao cho <strong>Xanh Express</strong>. Bạn nhận mã vận đơn qua email/SMS.</p>
                </div>
            </div>
            <div class="dalactive-shipping-step">
                <div class="dalactive-shipping-step__num">4</div>
                <div class="dalactive-shipping-step__content">
                    <h3>Nhận hàng</h3>
                    <p>Giao trong <strong>3–5 ngày làm việc</strong>. Vui lòng kiểm tra kỹ sản phẩm khi nhận.</p>
                </div>
            </div>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">&#128230;</span> Phí vận chuyển</h2>
            <p>Phí ship cố định <strong>10.000 đồng</strong> cho mọi đơn hàng trong lãnh thổ Việt Nam.</p>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">&#9888;</span> Lưu ý khi nhận hàng</h2>
            <ul>
                <li>Kiểm tra sản phẩm đúng với đơn đặt hàng trước khi ký nhận.</li>
                <li>Nếu sản phẩm bị hư hỏng trong vận chuyển, liên hệ ngay với chúng tôi.</li>
                <li>Thời gian giao hàng có thể bị ảnh hưởng bởi thiên tai, dịch bệnh hoặc quy định địa phương.</li>
            </ul>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">&#128269;</span> Theo dõi đơn hàng</h2>
            <p>Theo dõi đơn hàng bằng mã vận đơn được gửi qua email hoặc SMS sau khi đơn hàng được xử lý.</p>
        </div>

        <div class="dalactive-policy-contact">
            <span class="dalactive-policy-contact__icon">&#9993;</span>
            <div>
                <p>Có thắc mắc về vận chuyển? Liên hệ ngay:</p>
                <p><strong>Email:</strong> <a href="mailto:dalactive166@gmail.com">dalactive166@gmail.com</a></p>
                <p><strong>Hotline:</strong> <a href="tel:0886698386">0886 698 386</a></p>
            </div>
        </div>
    </div>
</div>
HTML;

// ── 4. Hoàn hàng & Đổi trả ───────────────────────────────────────────────────
$pages['hoan-hang-doi-tra'] = <<<HTML
<div class="dalactive-policy-page">
    <div class="dalactive-policy-hero">
        <h1 class="dalactive-policy-hero__title">Hoàn hàng &amp; Đổi trả</h1>
        <p class="dalactive-policy-hero__sub">Sự hài lòng của bạn là ưu tiên hàng đầu – hỗ trợ đổi trả trong vòng 7 ngày.</p>
    </div>
    <div class="dalactive-policy-body">
        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">1</span> Điều kiện Hoàn hàng &amp; Đổi trả</h2>
            <ul>
                <li>Sản phẩm còn nguyên vẹn, chưa qua sử dụng, còn đầy đủ tem và nhãn mác.</li>
                <li>Yêu cầu thực hiện trong vòng <strong>7 ngày</strong> kể từ ngày nhận hàng.</li>
                <li>Cung cấp hóa đơn hoặc bằng chứng mua hàng khi yêu cầu đổi trả.</li>
            </ul>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">2</span> Các trường hợp được Đổi trả</h2>
            <ul>
                <li>Sản phẩm bị lỗi do nhà sản xuất.</li>
                <li>Sản phẩm không đúng với đơn đặt hàng (sai màu, sai kích thước, sai mẫu mã).</li>
                <li>Sản phẩm bị hư hỏng trong quá trình vận chuyển.</li>
            </ul>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">3</span> Quy trình Hoàn hàng &amp; Đổi trả</h2>
            <ol>
                <li>Liên hệ bộ phận chăm sóc khách hàng qua email hoặc hotline bên dưới.</li>
                <li>Cung cấp thông tin chi tiết về sản phẩm và lý do đổi trả.</li>
                <li>Gửi sản phẩm về: <strong>144 Xuân Thủy, Cầu Giấy, Hà Nội</strong>.</li>
                <li>Chờ xác nhận và hoàn tất hoàn tiền hoặc gửi sản phẩm thay thế.</li>
            </ol>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">4</span> Chi phí Hoàn hàng</h2>
            <ul>
                <li><strong>Lỗi nhà sản xuất / vận chuyển:</strong> Chúng tôi chịu toàn bộ phí vận chuyển đổi trả.</li>
                <li><strong>Lý do cá nhân:</strong> Khách hàng chịu phí vận chuyển.</li>
            </ul>
        </div>

        <div class="dalactive-policy-section">
            <h2><span class="dalactive-policy-section__num">5</span> Phương thức Hoàn tiền</h2>
            <p>Tiền sẽ được hoàn lại qua hình thức thanh toán ban đầu trong vòng <strong>10–15 ngày làm việc</strong> sau khi yêu cầu được xử lý.</p>
        </div>

        <div class="dalactive-policy-contact">
            <span class="dalactive-policy-contact__icon">&#9993;</span>
            <div>
                <p>Cần hỗ trợ đổi trả? Liên hệ ngay:</p>
                <p><strong>Email:</strong> <a href="mailto:dalactive166@gmail.com">dalactive166@gmail.com</a></p>
                <p><strong>Hotline:</strong> <a href="tel:0886698386">0886 698 386</a></p>
            </div>
        </div>
    </div>
</div>
HTML;

// ── Update pages ──────────────────────────────────────────────────────────────
foreach ($pages as $identifier => $content) {
    $searchCriteriaBuilder = $om->get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
    $criteria = $searchCriteriaBuilder->addFilter('identifier', $identifier)->create();
    $items = $pageRepo->getList($criteria)->getItems();
    foreach ($items as $page) {
        $page->setContent($content);
        $page->setPageLayout('1column');
        $pageRepo->save($page);
        echo "Updated: $identifier\n";
    }
}
echo "Done.\n";
