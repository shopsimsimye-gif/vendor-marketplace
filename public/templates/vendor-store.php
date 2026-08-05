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
        $store_base = get_option('vmp_store_base', 'store');
        if (preg_match('#^' . preg_quote($store_base, '#') . '/([^/]+)$#', $uri, $matches)) {
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
    echo '<p class="vmp-not-found">' . esc_html__('المتجر غير موجود.', 'vmp') . '</p>';
    return;
}

// ── استخدام الحاوية للحصول على المستودعات (Dependency Injection) ──
$container = \VMP\Core\Container::getInstance();
$product_repo = $container->make(\VMP\Contracts\ProductRepositoryInterface::class);
$sub_repo = $container->make(\VMP\Contracts\SubscriptionRepositoryInterface::class);
$plan_repo = $container->make(\VMP\Contracts\SubscriptionPlanRepositoryInterface::class);

// ── جلب خطة الاشتراك والميزات (مع التخزين المؤقت ببيانات بسيطة فقط) ──
$cache_key = 'vmp_store_' . (int) $vendor->id . '_data';
$store_data = get_transient($cache_key);

if (false === $store_data || !is_array($store_data)) {
    $active_sub = $sub_repo->findActiveByVendor((int) $vendor->id);
    $plan = $active_sub ? $plan_repo->find((int) $active_sub->plan_id) : null;
    $features = $plan ? $plan_repo->getFeatures((int) $plan->id) : [];

    // ✅ تخزين scalars فقط لتجنب مشاكل serialize/unserialize
    $store_data = [
        'features'     => is_array($features) ? $features : [],
        'plan_name'    => $plan ? (string) $plan->name : null,
        'plan_id'      => $plan ? (int) $plan->id : 0,
        'plan_slug'    => $plan && !empty($plan->slug) ? (string) $plan->slug : 'free',
        'sub_status'   => $active_sub && !empty($active_sub->status) ? (string) $active_sub->status : null,
        'sub_id'       => $active_sub ? (int) $active_sub->id : 0,
    ];
    set_transient($cache_key, $store_data, 300); // 5 دقائق
} else {
    $features = is_array($store_data['features']) ? $store_data['features'] : [];
}

$has_whatsapp = !empty($features['whatsapp_button']);

// ── رقم واتساب (من whatsapp_number أو store_phone) ──
$wa_number = !empty($vendor->whatsapp_number) ? $vendor->whatsapp_number : ($vendor->store_phone ?? '');
$wa_number_clean = ltrim(preg_replace('/[^0-9+]/', '', $wa_number), '+');

// ── الصور (باستخدام الأحجام المناسبة) ──
$default_logo   = trailingslashit(VMP_PLUGIN_URL) . 'assets/images/default-logo.png';
$default_banner = trailingslashit(VMP_PLUGIN_URL) . 'assets/images/default-banner.jpg';

$logo_url = (!empty($vendor->store_logo) && wp_attachment_is_image($vendor->store_logo))
    ? wp_get_attachment_image_url($vendor->store_logo, 'medium')
    : $default_logo;

$banner_url = (!empty($vendor->store_banner) && wp_attachment_is_image($vendor->store_banner))
    ? wp_get_attachment_image_url($vendor->store_banner, 'large')
    : $default_banner;

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
$pages = ($limit > 0) ? (int) ceil($total_products / $limit) : 0;

// ── تجهيز منتجات WooCommerce (حل N+1) ──
$wc_products_by_id = [];
if (!empty($products)) {
    $product_ids = array_filter(array_map('intval', wp_list_pluck($products, 'product_id')));
    if (!empty($product_ids)) {
        foreach (wc_get_products([
            'include' => $product_ids,
            'status'  => 'publish',
            'limit'   => -1,
        ]) as $wc_p) {
            $wc_products_by_id[$wc_p->get_id()] = $wc_p;
        }
    }
}

// ── بيانات مشتركة ──
$currency        = get_woocommerce_currency();
$store_base      = get_option('vmp_store_base', 'store');
$store_page_url  = home_url('/' . $store_base . '/' . $vendor->store_slug . '/');
$vendor_name     = !empty($vendor->store_name) ? $vendor->store_name : '';
$vendor_desc     = !empty($vendor->store_description) ? $vendor->store_description : '';
?>

<div class="vmp-wrap" itemscope itemtype="https://schema.org/Organization">
    <div class="vmp-store-container">

        <!-- ════════════════════════════════════════════════ -->
        <!-- Schema: متجر البائع (مخفي) -->
        <!-- ════════════════════════════════════════════════ -->
        <meta itemprop="name" content="<?php echo esc_attr($vendor_name); ?>">
        <meta itemprop="description" content="<?php echo esc_attr($vendor_desc); ?>">
        <meta itemprop="url" content="<?php echo esc_url($store_page_url); ?>">
        <?php if (!empty($logo_url)) : ?>
            <meta itemprop="logo" content="<?php echo esc_url($logo_url); ?>">
        <?php endif; ?>

        <!-- ════════════════════════════════════════════════ -->
        <!-- غلاف المتجر -->
        <!-- ════════════════════════════════════════════════ -->
        <div class="vmp-store-cover">
            <!-- ✅ إزالة loading="lazy" من الصورة الأولى (Above the Fold / LCP) -->
            <img src="<?php echo esc_url($banner_url); ?>" alt="<?php echo esc_attr($vendor_name); ?>" class="vmp-store-cover-img">
            <div class="vmp-store-cover-overlay">
                <div class="vmp-store-cover-content">
                    <!-- ✅ إزالة itemprop="name" من العنصر المرئي لتجنب التكرار مع meta -->
                    <h1 class="vmp-store-title"><?php echo esc_html($vendor_name); ?></h1>
                    <?php if (!empty($vendor_desc)) : ?>
                        <p class="vmp-store-desc"><?php echo nl2br(esc_html($vendor_desc)); ?></p>
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
                    <!-- ✅ إزالة loading="lazy" من الشعار (Above the Fold) -->
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($vendor_name); ?>" class="vmp-store-logo-img">
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
                    $whatsapp_message = rawurlencode(
                        sprintf(
                            /* translators: %s: store name */
                            __('مرحباً، أريد الاستفسار من متجر %s', 'vmp'),
                            $vendor_name
                        )
                    );
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
                        <?php echo esc_html__('تواصل عبر واتساب', 'vmp'); ?>
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
                            echo '<p style="color:var(--vmp-text-muted);">' . esc_html__('رابط الفيديو غير صالح.', 'vmp') . '</p>';
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
            <!-- ⚠️ تحذير: هذا hook مخصص للـ main query. لأننا نستخدم loop مخصصاً، قد لا تعمل بعض الإضافات بشكل صحيح. -->
            <?php do_action('vmp_before_store_products', $vendor, $products); ?>

            <h2 class="vmp-products-title">🛍️ <?php echo esc_html__('المنتجات', 'vmp'); ?></h2>
            <div class="vmp-products-grid">

                <?php if (empty($products)) : ?>
                    <div class="vmp-empty">
                        <p><?php echo esc_html__('لا توجد منتجات معروضة حالياً.', 'vmp'); ?></p>
                    </div>
                <?php else : ?>
                    <?php 
                    foreach ($products as $p) :
                        $wc_p = $wc_products_by_id[(int) $p->product_id] ?? null;
                        if (!$wc_p) {
                            continue;
                        }
                        $img = wp_get_attachment_image_url($wc_p->get_image_id(), 'medium') ?: wc_placeholder_img_src();
                        $product_url = get_permalink($p->product_id);
                        
                        // تعيين $product لـ WooCommerce Hooks بأمان
                        global $product;
                        $old_product = $product;
                        $product = $wc_p;
                        if (function_exists('wc_setup_product_data')) {
                            wc_setup_product_data($wc_p);
                        }
                    ?>
                        <div class="vmp-product-card" itemscope itemtype="https://schema.org/Product">
                            <!-- ✅ Schema image مخفي -->
                            <meta itemprop="image" content="<?php echo esc_url($img); ?>">
                            <meta itemprop="url" content="<?php echo esc_url($product_url); ?>">
                            
                            <a href="<?php echo esc_url($product_url); ?>" class="vmp-product-link">
                                <!-- ✅ loading="lazy" على صور المنتجات (Below the Fold) -->
                                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($wc_p->get_name()); ?>" class="vmp-product-img" loading="lazy">
                            </a>
                            <div class="vmp-product-body" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                                <h3 class="vmp-product-name">
                                    <a href="<?php echo esc_url($product_url); ?>"><span itemprop="name"><?php echo esc_html($wc_p->get_name()); ?></span></a>
                                </h3>

                                <!-- اسم البائع بخط صغير -->
                                <div class="vmp-product-vendor">
                                    <?php echo esc_html__('بواسطة', 'vmp'); ?> 
                                    <a href="<?php echo esc_url($store_page_url); ?>" rel="noopener noreferrer"><?php echo esc_html($vendor_name); ?></a>
                                </div>

                                <!-- ✅ السعر مع Schema صحيح -->
                                <div class="vmp-product-price">
                                    <?php echo $wc_p->get_price_html(); ?>
                                    <meta itemprop="price" content="<?php echo esc_attr($wc_p->get_price()); ?>">
                                    <meta itemprop="priceCurrency" content="<?php echo esc_attr($currency); ?>">
                                    <link itemprop="availability" href="<?php echo $wc_p->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'; ?>" />
                                    <meta itemprop="url" content="<?php echo esc_url($product_url); ?>">
                                </div>

                                <!-- أزرار الإجراءات -->
                                <div class="vmp-product-actions">
                                    <?php 
                                    // استخدام woocommerce_template_loop_add_to_cart()
                                    ob_start();
                                    woocommerce_template_loop_add_to_cart();
                                    $add_to_cart_html = ob_get_clean();
                                    echo $add_to_cart_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce handles its own escaping
                                    ?>

                                    <!-- ✅ زر واتساب الخاص بالمنتج (مع تتبع النقرات) -->
                                    <?php if ($has_whatsapp && !empty($wa_number_clean)) :
                                        $product_name = $wc_p->get_name();
                                        $whatsapp_msg = rawurlencode(
                                            sprintf(
                                                /* translators: 1: product name, 2: store name, 3: product URL */
                                                __('مرحباً، أريد الاستفسار عن منتج "%1$s" من متجر %2$s: %3$s', 'vmp'),
                                                $product_name,
                                                $vendor_name,
                                                $product_url
                                            )
                                        );
                                        $wa_url = 'https://wa.me/' . $wa_number_clean . '?text=' . $whatsapp_msg;
                                    ?>
                                        <a href="<?php echo esc_url($wa_url); ?>" 
                                           target="_blank" 
                                           rel="noopener noreferrer nofollow" 
                                           class="vmp-btn vmp-btn-success vmp-btn-sm vmp-wa-track" 
                                           data-vendor-id="<?php echo (int) $vendor->id; ?>" 
                                           data-product-id="<?php echo (int) $p->product_id; ?>" 
                                           data-click-type="product">
                                            💬 <?php echo esc_html__('طلب عبر واتساب', 'vmp'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                        // ✅ إعادة تعيين $product داخل loop فقط (بدون wc_reset_loop هنا)
                        $product = $old_product;
                    endforeach; 

                    // ✅ إعادة تعيين loop WooCommerce مرة واحدة بعد انتهاء loop كل المنتجات
                    if (function_exists('wc_reset_loop')) {
                        wc_reset_loop();
                    }
                    ?>
                <?php endif; ?>
            </div>

            <?php do_action('vmp_after_store_products', $vendor, $products); ?>

            <!-- الترقيم -->
            <?php if ($pages > 1) : ?>
                <div class="vmp-pagination">
                    <?php 
                    $current_page = max(1, (int) $paged);
                    $pagination_base = trailingslashit($store_page_url) . '%_%';
                    $format = 'page/%#%/';
                    
                    echo paginate_links([
                        'base'      => $pagination_base,
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
        <?php if (!empty($vendor->rating) && $vendor->rating > 0 && !empty($vendor->review_count)) : ?>
            <div itemscope itemtype="https://schema.org/AggregateRating" style="display:none;">
                <meta itemprop="ratingValue" content="<?php echo (float) $vendor->rating; ?>">
                <meta itemprop="reviewCount" content="<?php echo (int) $vendor->review_count; ?>">
                <meta itemprop="bestRating" content="5">
                <meta itemprop="worstRating" content="1">
            </div>
        <?php endif; ?>

    </div>
</div>


