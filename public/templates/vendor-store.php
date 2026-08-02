<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من وجود $vendor ──
if (!isset($vendor) || !$vendor) {
    $resolved_slug = '';

    if (!empty($GLOBALS['vmp_current_vendor_slug'])) {
        $resolved_slug = sanitize_text_field((string) $GLOBALS['vmp_current_vendor_slug']);
    }

    if (empty($resolved_slug) && function_exists('get_query_var')) {
        $resolved_slug = sanitize_text_field((string) get_query_var('vendor_store', ''));
    }

    if (empty($resolved_slug) && !empty($_GET['vendor_store'])) {
        $resolved_slug = sanitize_text_field((string) $_GET['vendor_store']);
    }

    if (empty($resolved_slug) && !empty($_SERVER['REQUEST_URI'])) {
        $uri = trim((string) $_SERVER['REQUEST_URI']);
        $uri = wp_parse_url($uri, PHP_URL_PATH) ?: $uri;
        $uri = trim((string) $uri, '/');
        if (preg_match('#^store/([^/]+)$#', $uri, $matches)) {
            $resolved_slug = sanitize_text_field($matches[1]);
        }
    }

    if (!empty($resolved_slug)) {
        try {
            $container = \VMP\Core\Container::getInstance();
            $vendor_repo = $container->make(\VMP\Contracts\VendorRepositoryInterface::class);
            $vendor = $vendor_repo->findBySlug($resolved_slug);
        } catch (\Throwable $e) {
            $vendor = null;
        }
    }
}

if (!isset($vendor) || !$vendor || !isset($vendor->status) || $vendor->status !== 'approved') {
    echo '<p class="vmp-not-found">' . __('المتجر غير موجود.', 'vmp') . '</p>';
    return;
}

// ── استخدام الحاوية للحصول على المستودعات (Dependency Injection) ──
// ✅ استخدام الواجهات (Contracts) بدلاً من الـ Concrete classes
$container = \VMP\Core\Container::getInstance();
$product_repo = $container->make(\VMP\Contracts\ProductRepositoryInterface::class);
$sub_repo = $container->make(\VMP\Contracts\SubscriptionRepositoryInterface::class);
$plan_repo = $container->make(\VMP\Contracts\SubscriptionPlanRepositoryInterface::class);

// ── جلب خطة الاشتراك والميزات (مع التخزين المؤقت) ──
$cache_key = 'vmp_store_' . $vendor->id . '_data';
$store_data = get_transient($cache_key);

if (false === $store_data) {
    $active_sub = $sub_repo->findActiveByVendor((int) $vendor->id);
    $plan = $active_sub ? $plan_repo->find((int) $active_sub->plan_id) : null;
    $features = $plan ? $plan_repo->getFeatures((int) $plan->id) : [];

    $store_data = [
        'features' => $features,
        'plan' => $plan,
        'active_sub' => $active_sub,
    ];
    set_transient($cache_key, $store_data, 300); // 5 دقائق
} else {
    $features = $store_data['features'];
    $plan = $store_data['plan'];
    $active_sub = $store_data['active_sub'];
}

$has_whatsapp = !empty($features['whatsapp_button']);

// ── رقم واتساب (من whatsapp_number أو store_phone) ──
$wa_number = !empty($vendor->whatsapp_number) ? $vendor->whatsapp_number : ($vendor->store_phone ?? '');
$wa_number_clean = ltrim(preg_replace('/[^0-9+]/', '', $wa_number), '+');

// ── الصور (باستخدام الأحجام المناسبة) ──
$logo_url = !empty($vendor->store_logo) 
    ? wp_get_attachment_image_url($vendor->store_logo, 'medium') 
    : VMP_PLUGIN_URL . 'assets/images/default-logo.png';
$banner_url = !empty($vendor->store_banner) 
    ? wp_get_attachment_image_url($vendor->store_banner, 'large') 
    : VMP_PLUGIN_URL . 'assets/images/default-banner.jpg';

// ── جلب المنتجات عبر Repository ──
$paged = get_query_var('paged') ?: 1;
$limit = 12;
$offset = ($paged - 1) * $limit;

$products = $product_repo->getByVendor((int) $vendor->id, [
    'status' => 'approved',
    'limit'  => $limit,
    'offset' => $offset,
]);

$total_products = $product_repo->countByVendor((int) $vendor->id, 'approved');
$pages = (int) ceil($total_products / $limit);

// ── تجهيز منتجات WooCommerce (حل N+1) ──
$wc_products_by_id = [];
if (!empty($products)) {
    $product_ids = array_filter(array_map('intval', wp_list_pluck($products, 'product_id')));
    if (!empty($product_ids)) {
        foreach (wc_get_products([
            'include' => $product_ids,
            'status' => 'publish',
            'limit' => -1,
        ]) as $wc_p) {
            $wc_products_by_id[$wc_p->get_id()] = $wc_p;
        }
    }
}
?>

<div class="vmp-wrap" itemscope itemtype="https://schema.org/Organization">
    <div class="vmp-store-container">

        <!-- ════════════════════════════════════════════════ -->
        <!-- Schema: متجر البائع -->
        <!-- ════════════════════════════════════════════════ -->
        <meta itemprop="name" content="<?php echo esc_attr($vendor->store_name); ?>">
        <meta itemprop="description" content="<?php echo esc_attr($vendor->store_description ?? ''); ?>">
        <meta itemprop="url" content="<?php echo esc_url(home_url('/store/' . $vendor->store_slug)); ?>">
        <?php if (!empty($logo_url)) : ?>
            <meta itemprop="logo" content="<?php echo esc_url($logo_url); ?>">
        <?php endif; ?>

        <!-- ════════════════════════════════════════════════ -->
        <!-- غلاف المتجر -->
        <!-- ════════════════════════════════════════════════ -->
        <div class="vmp-store-cover">
            <img src="<?php echo esc_url($banner_url); ?>" alt="<?php echo esc_attr($vendor->store_name); ?>" class="vmp-store-cover-img" loading="lazy">
            <div class="vmp-store-cover-overlay">
                <div class="vmp-store-cover-content">
                    <h1 class="vmp-store-title" itemprop="name"><?php echo esc_html($vendor->store_name); ?></h1>
                    <?php if (!empty($vendor->store_description)) : ?>
                        <p class="vmp-store-desc" itemprop="description"><?php echo nl2br(esc_html($vendor->store_description)); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════ -->
        <!-- معلومات المتجر -->
        <!-- ════════════════════════════════════════════════ -->
        <div class="vmp-store-info-grid">
            <div class="vmp-store-info-card">
                <div class="vmp-store-logo-wrap">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($vendor->store_name); ?>" class="vmp-store-logo-img" loading="lazy">
                </div>

                <!-- رقم الهاتف -->
                <?php if (!empty($vendor->store_phone)) : ?>
                    <div class="vmp-store-contact">
                        <span class="vmp-icon">📞</span>
                        <a href="tel:<?php echo esc_attr($vendor->store_phone); ?>" rel="noopener noreferrer"><?php echo esc_html($vendor->store_phone); ?></a>
                    </div>
                <?php endif; ?>

                <!-- عنوان المتجر (حسب الخطة) -->
                <?php if (!empty($features['store_address']) && !empty($vendor->store_address)) : ?>
                    <div class="vmp-store-address">
                        <span class="vmp-icon">📍</span>
                        <span itemprop="address"><?php echo esc_html($vendor->store_address); ?></span>
                    </div>
                    <?php if (!empty($vendor->store_latitude) && !empty($vendor->store_longitude)) : ?>
                        <div class="vmp-store-map">
                            <iframe 
                                src="https://www.google.com/maps?q=<?php echo esc_attr($vendor->store_latitude); ?>,<?php echo esc_attr($vendor->store_longitude); ?>&z=15&output=embed" 
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen>
                            </iframe>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- روابط التواصل الاجتماعي (حسب الخطة) -->
                <?php if (!empty($features['social_links'])) : ?>
                    <div class="vmp-store-social">
                        <?php if (!empty($vendor->social_facebook)) : ?>
                            <a href="<?php echo esc_url($vendor->social_facebook); ?>" target="_blank" rel="noopener noreferrer nofollow" class="vmp-social-btn fb" title="Facebook">📘</a>
                        <?php endif; ?>
                        <?php if (!empty($vendor->social_instagram)) : ?>
                            <a href="<?php echo esc_url($vendor->social_instagram); ?>" target="_blank" rel="noopener noreferrer nofollow" class="vmp-social-btn ig" title="Instagram">📸</a>
                        <?php endif; ?>
                        <?php if (!empty($vendor->social_twitter)) : ?>
                            <a href="<?php echo esc_url($vendor->social_twitter); ?>" target="_blank" rel="noopener noreferrer nofollow" class="vmp-social-btn tw" title="Twitter">🐦</a>
                        <?php endif; ?>
                        <?php if (!empty($vendor->social_youtube)) : ?>
                            <a href="<?php echo esc_url($vendor->social_youtube); ?>" target="_blank" rel="noopener noreferrer nofollow" class="vmp-social-btn yt" title="YouTube">▶️</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- ✅ زر واتساب العام (مع تتبع النقرات) -->
                <?php if ($has_whatsapp && !empty($wa_number_clean)) : 
                    $whatsapp_message = rawurlencode(sprintf(__('مرحباً، أريد الاستفسار من متجر %s', 'vmp'), $vendor->store_name));
                    $wa_url = 'https://wa.me/' . $wa_number_clean . '?text=' . $whatsapp_message;
                ?>
                    <a href="<?php echo esc_url($wa_url); ?>" 
                       target="_blank" 
                       rel="noopener noreferrer nofollow" 
                       class="vmp-whatsapp-btn vmp-wa-track" 
                       data-vendor-id="<?php echo (int) $vendor->id; ?>" 
                       data-product-id="0" 
                       data-click-type="store">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        <?php _e('تواصل عبر واتساب', 'vmp'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <!-- فيديو تعريفي (حسب الخطة) -->
            <?php if (!empty($features['product_video']) && !empty($vendor->store_video)) : ?>
                <div class="vmp-store-video">
                    <div class="vmp-video-wrapper">
                        <?php 
                        $embed = wp_oembed_get($vendor->store_video);
                        if ($embed) {
                            echo wp_kses_post($embed);
                        } else {
                            echo '<p style="color:var(--vmp-text-muted);">' . __('رابط الفيديو غير صالح.', 'vmp') . '</p>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ════════════════════════════════════════════════ -->
        <!-- قائمة المنتجات -->
        <!-- ════════════════════════════════════════════════ -->
        <div class="vmp-store-products">
            <?php do_action('woocommerce_before_shop_loop'); ?>

            <h2 class="vmp-products-title">🛍️ <?php _e('المنتجات', 'vmp'); ?></h2>
            <div class="vmp-products-grid">

                <?php if (empty($products)) : ?>
                    <div class="vmp-empty">
                        <p><?php _e('لا توجد منتجات معروضة حالياً.', 'vmp'); ?></p>
                    </div>
                <?php else : ?>
                    <?php foreach ($products as $p) :
                        $wc_p = $wc_products_by_id[(int) $p->product_id] ?? null;
                        if (!$wc_p) {
                            continue;
                        }
                        $img = wp_get_attachment_image_url($wc_p->get_image_id(), 'medium') ?: wc_placeholder_img_src();
                        $product_url = get_permalink($p->product_id);
                        
                        // تعيين $product لـ WooCommerce Hooks بأمان
                        // ✅ استخدام wc_setup_product_data() بدلاً من التلاعب اليدوي
                        //    حتى لا يبقى $product معطلاً عند أي exception أو return مبكر
                        global $product;
                        $old_product = $product;
                        $product = $wc_p;
                        if (function_exists('wc_setup_product_data')) {
                            wc_setup_product_data($wc_p);
                        }
                    ?>
                        <div class="vmp-product-card" itemscope itemtype="https://schema.org/Product">
                            <a href="<?php echo esc_url($product_url); ?>" class="vmp-product-link">
                                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($wc_p->get_name()); ?>" class="vmp-product-img" loading="lazy">
                            </a>
                            <div class="vmp-product-body" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                                <h3 class="vmp-product-name" itemprop="name">
                                    <a href="<?php echo esc_url($product_url); ?>"><?php echo esc_html($wc_p->get_name()); ?></a>
                                </h3>

                                <!-- اسم البائع بخط صغير -->
                                <div class="vmp-product-vendor">
                                    <?php _e('بواسطة', 'vmp'); ?> 
                                    <a href="<?php echo home_url('/store/' . $vendor->store_slug); ?>" rel="noopener noreferrer"><?php echo esc_html($vendor->store_name); ?></a>
                                </div>

                                <!-- السعر مع Schema -->
                                <div class="vmp-product-price" itemprop="price">
                                    <?php echo $wc_p->get_price_html(); ?>
                                    <meta itemprop="priceCurrency" content="<?php echo esc_attr(get_woocommerce_currency()); ?>">
                                </div>

                                <!-- أزرار الإجراءات -->
                                <div class="vmp-product-actions">
                                    <?php 
                                    // استخدام woocommerce_template_loop_add_to_cart()
                                    ob_start();
                                    woocommerce_template_loop_add_to_cart();
                                    $add_to_cart_html = ob_get_clean();
                                    echo $add_to_cart_html;
                                    ?>

                                    <!-- ✅ زر واتساب الخاص بالمنتج (مع تتبع النقرات) -->
                                    <?php if ($has_whatsapp && !empty($wa_number_clean)) :
                                        $product_name = $wc_p->get_name();
                                        $whatsapp_msg = rawurlencode(sprintf(
                                            __('مرحباً، أريد الاستفسار عن منتج "%s" من متجر %s: %s', 'vmp'),
                                            $product_name,
                                            $vendor->store_name,
                                            $product_url
                                        ));
                                        $wa_url = 'https://wa.me/' . $wa_number_clean . '?text=' . $whatsapp_msg;
                                    ?>
                                        <a href="<?php echo esc_url($wa_url); ?>" 
                                           target="_blank" 
                                           rel="noopener noreferrer nofollow" 
                                           class="vmp-btn vmp-btn-success vmp-btn-sm vmp-wa-track" 
                                           data-vendor-id="<?php echo (int) $vendor->id; ?>" 
                                           data-product-id="<?php echo (int) $p->product_id; ?>" 
                                           data-click-type="product">
                                            💬 <?php _e('طلب عبر واتساب', 'vmp'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                        // إعادة تعيين $product بأمان
                        if (function_exists('wc_reset_loop')) {
                            wc_reset_loop();
                        }
                        $product = $old_product;
                    endforeach; 
                    ?>
                <?php endif; ?>
            </div>

            <?php do_action('woocommerce_after_shop_loop'); ?>

            <!-- الترقيم -->
            <?php if ($pages > 1) : ?>
                <div class="vmp-pagination">
                    <?php 
                    $current_page = $paged;
                    $base = trailingslashit(get_permalink()) . '%_%';
                    $format = 'page/%#%/';
                    
                    echo paginate_links([
                        'base'      => $base,
                        'format'    => $format,
                        'current'   => $current_page,
                        'total'     => $pages,
                        'prev_text' => '‹',
                        'next_text' => '›',
                    ]); 
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ════════════════════════════════════════════════ -->
        <!-- Schema: AggregateRating -->
        <!-- ════════════════════════════════════════════════ -->
        <?php if (!empty($vendor->rating) && $vendor->rating > 0) : ?>
            <div itemscope itemtype="https://schema.org/AggregateRating" style="display:none;">
                <meta itemprop="ratingValue" content="<?php echo (float) $vendor->rating; ?>">
                <meta itemprop="reviewCount" content="<?php echo (int) $vendor->review_count; ?>">
            </div>
        <?php endif; ?>

    </div>
</div>

<style>
/* ════════════════════════════════════════════════════════════════════
   Vendor Marketplace — Store Page Styles
   ════════════════════════════════════════════════════════════════════ */

.vmp-store-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.vmp-store-cover {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 30px;
}
.vmp-store-cover-img {
    width: 100%;
    height: 260px;
    object-fit: cover;
}
.vmp-store-cover-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(0deg, rgba(0,0,0,0.6) 0%, transparent 70%);
    display: flex;
    align-items: flex-end;
    padding: 30px;
}
.vmp-store-cover-content {
    color: #fff;
}
.vmp-store-title {
    font-size: 28px;
    font-weight: 800;
    margin: 0;
}
.vmp-store-desc {
    font-size: 14px;
    opacity: 0.9;
    max-width: 600px;
    margin: 6px 0 0;
}

.vmp-store-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 40px;
}
.vmp-store-info-card {
    background: var(--vmp-surface);
    border: 1px solid var(--vmp-border);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    align-items: center;
}
.vmp-store-logo-wrap {
    display: flex;
    justify-content: center;
}
.vmp-store-logo-img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--vmp-primary-light);
}
.vmp-store-contact,
.vmp-store-address {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}
.vmp-store-contact a {
    color: var(--vmp-primary);
    text-decoration: none;
}
.vmp-store-contact a:hover {
    text-decoration: underline;
}
.vmp-store-map iframe {
    width: 100%;
    height: 180px;
    border: 0;
    border-radius: 12px;
    margin-top: 6px;
}

.vmp-store-social {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 4px;
}
.vmp-social-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--vmp-bg);
    font-size: 18px;
    text-decoration: none;
    transition: 0.2s;
}
.vmp-social-btn.fb:hover {
    background: #1877f2;
    color: #fff;
}
.vmp-social-btn.ig:hover {
    background: #e4405f;
    color: #fff;
}
.vmp-social-btn.tw:hover {
    background: #000;
    color: #fff;
}
.vmp-social-btn.yt:hover {
    background: #ff0000;
    color: #fff;
}

.vmp-whatsapp-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 24px;
    background: #25D366;
    color: #fff !important;
    border-radius: 9999px;
    font-weight: 700;
    font-size: 15px;
    text-decoration: none !important;
    transition: 0.2s;
    box-shadow: 0 4px 15px rgba(37, 211, 102, .35);
}
.vmp-whatsapp-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(37, 211, 102, .45);
}

.vmp-store-video {
    background: var(--vmp-surface);
    border: 1px solid var(--vmp-border);
    border-radius: 16px;
    padding: 24px;
}
.vmp-video-wrapper {
    position: relative;
    padding-bottom: 56.25%;
    height: 0;
    overflow: hidden;
    border-radius: 12px;
}
.vmp-video-wrapper iframe,
.vmp-video-wrapper video {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: 0;
}

.vmp-store-products {
    margin-top: 30px;
}
.vmp-products-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 20px;
}
.vmp-products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 24px;
}
.vmp-product-card {
    background: var(--vmp-surface);
    border: 1px solid var(--vmp-border);
    border-radius: 12px;
    overflow: hidden;
    transition: 0.2s;
}
.vmp-product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}
.vmp-product-img {
    width: 100%;
    aspect-ratio: 4/3;
    object-fit: cover;
    display: block;
}
.vmp-product-body {
    padding: 16px;
}
.vmp-product-name {
    font-weight: 700;
    font-size: 14px;
    margin: 0 0 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.vmp-product-name a {
    color: inherit;
    text-decoration: none;
}
.vmp-product-name a:hover {
    color: var(--vmp-primary);
}

.vmp-product-vendor {
    font-size: 12px;
    color: var(--vmp-text-muted);
    margin-top: 2px;
    margin-bottom: 6px;
}
.vmp-product-vendor a {
    color: var(--vmp-primary);
    text-decoration: none;
}
.vmp-product-vendor a:hover {
    text-decoration: underline;
}

.vmp-product-price {
    font-size: 18px;
    font-weight: 800;
    color: var(--vmp-primary);
}
.vmp-product-price del {
    font-size: 13px;
    color: var(--vmp-text-light);
    font-weight: 400;
}

.vmp-product-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    flex-wrap: wrap;
}
.vmp-product-actions .vmp-btn {
    flex: 1;
    min-width: 100px;
    padding: 8px 12px;
    font-size: 13px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: background 0.2s, transform 0.1s;
}
.vmp-product-actions .vmp-btn:hover {
    transform: translateY(-2px);
}
.vmp-product-actions .vmp-btn-primary {
    background: var(--vmp-primary);
    color: #fff;
}
.vmp-product-actions .vmp-btn-primary:hover {
    background: var(--vmp-primary-dark);
}
.vmp-product-actions .vmp-btn-success {
    background: #25D366;
    color: #fff;
}
.vmp-product-actions .vmp-btn-success:hover {
    background: #128C7E;
}
.vmp-product-actions .add_to_cart_button {
    background: var(--vmp-primary);
    color: #fff;
    border: none;
    padding: 8px 12px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
    font-size: 13px;
    text-decoration: none;
}
.vmp-product-actions .add_to_cart_button:hover {
    background: var(--vmp-primary-dark);
}

.vmp-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--vmp-text-muted);
}

.vmp-pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-top: 24px;
}
.vmp-pagination a,
.vmp-pagination span {
    display: inline-block;
    padding: 8px 14px;
    border: 1px solid var(--vmp-border);
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    color: var(--vmp-text-muted);
    text-decoration: none;
    transition: 0.2s;
}
.vmp-pagination a:hover {
    background: var(--vmp-primary);
    color: #fff;
    border-color: var(--vmp-primary);
}
.vmp-pagination .current {
    background: var(--vmp-primary);
    color: #fff;
    border-color: var(--vmp-primary);
}

@media (max-width: 768px) {
    .vmp-store-info-grid {
        grid-template-columns: 1fr;
    }
    .vmp-store-cover-img {
        height: 160px;
    }
    .vmp-store-title {
        font-size: 22px;
    }
    .vmp-store-logo-img {
        width: 70px;
        height: 70px;
    }
    .vmp-products-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 16px;
    }
    .vmp-product-actions {
        flex-direction: column;
    }
    .vmp-product-actions .vmp-btn {
        min-width: 100%;
    }
    .vmp-store-cover-overlay {
        padding: 16px;
    }
    .vmp-whatsapp-btn {
        font-size: 13px;
        padding: 10px 18px;
    }
}
</style>