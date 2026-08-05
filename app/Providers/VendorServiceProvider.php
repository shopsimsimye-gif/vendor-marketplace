<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;
use VMP\Core\Container;

/**
 * VendorServiceProvider — مسؤول عن:
 * • تسجيل الشورت كودات (dashboard, register, store)
 * • إضافة rewrite rules لـ /store/{slug}
 * • تحميل الأصول (CSS/JS) للواجهة الأمامية
 * • ربط WooCommerce hooks
 * • عرض اسم البائع في صفحات المنتجات
 *
 * @package VMP\Providers
 * @since 1.0.0
 */
class VendorServiceProvider extends ServiceProvider
{
    /**
     * Boot functionality helper.
     * ✅ يُنفذ بعد تسجيل كل الـ Providers
     * ✅ Kernel::boot()
     *
     * @return void
     */
    public function boot(): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // 1. استبعاد المنشورات الوهمية (ID -999 و 0) من الاستعلامات العامة
        // ═══════════════════════════════════════════════════════════════════
        // السبب: بعض الإضافات أو السكربتات تُنشئ منشورات وهمية بمعرف 0 أو -999
        // لمنع ظهورها في المدونة أو الأرشيف أو البحث
        add_filter('pre_get_posts', function (\WP_Query $query) {
            // لا نُطبق على لوحة التحكم أو الاستعلامات الثانوية
            if (is_admin() || !$query->is_main_query()) {
                return;
            }

            $excluded_ids = [-999, 0];
            $current_excluded = $query->get('post__not_in', []);

            // دمج المعرفات المستبعدة مع الموجودة مسبقاً
            $query->set('post__not_in', array_merge($current_excluded, $excluded_ids));
        }, 20);

        // استبعاد من خرائط الموقع (sitemaps) - WordPress 5.5+
        add_filter('wp_sitemaps_posts_query_args', function (array $args) {
            $args['post__not_in'] = array_merge($args['post__not_in'] ?? [], [-999, 0]);
            return $args;
        });

        // استبعاد من استعلامات الصفحات في لوحة التحكم (تنظيف قوائم الصفحات)
        add_filter('parse_query', function (\WP_Query $query) {
            if (!is_admin()) {
                return;
            }

            // فقط للاستعلامات التي تطلب نوع 'page'
            if (isset($query->query['post_type']) && $query->query['post_type'] === 'page') {
                $excluded_ids = [-999, 0];
                $current_excluded = $query->get('post__not_in', []);
                $query->set('post__not_in', array_merge($current_excluded, $excluded_ids));
            }
        }, 20);

        // ─── تسجيل مسارات REST API للتسجيل أحادي الخطوة ───
        // (نقل المنطق إلى البنية الجديدة: RestVendorRegistrationController عبر الحاوية)
        add_action('rest_api_init', function () {
            $regController = $this->container->make(\VMP\Controllers\RestVendorRegistrationController::class);

            register_rest_route('vmp/v1', '/vendor/register-guest', [
                'methods' => 'POST',
                'callback' => [$regController, 'registerGuest'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('vmp/v1', '/vendor/apply', [
                'methods' => 'POST',
                'callback' => [$regController, 'apply'],
                'permission_callback' => '__return_true',
            ]);
        });

        // ─── منع ووكومرس من إنشاء بيانات عميل وهمية للبائعين ───
        if (class_exists('WooCommerce')) {
            // فلتر لتعديل بيانات العميل الجديد - إزالة حقول الفوترة/الشحن الافتراضية الفارغة للبائعين
            add_filter('woocommerce_new_customer_data', function (array $new_customer_data) {
                // إذا كان المستخدم له دور بائع، لا ننشئ بيانات عميل وهمية
                $user_id = $new_customer_data['user_id'] ?? 0;
                if ($user_id) {
                    $user = get_userdata($user_id);
                    if ($user && in_array('vmp_vendor', (array) $user->roles, true)) {
                        // البائعون ليسوا عملاء — نترك البيانات تمر وننظف الـ meta لاحقاً
                        // (التنظيف الفعلي في woocommerce_created_customer)
                    }
                }
                return $new_customer_data;
            }, 10, 1);

            // إجراء بعد إنشاء العميل - تنظيف البيانات الوهمية للبائعين
            add_action('woocommerce_created_customer', function (int $customer_id, array $new_customer_data, bool $password_generated) {
                $user = get_userdata($customer_id);
                if ($user && in_array('vmp_vendor', (array) $user->roles, true)) {
                    // حذف بيانات الفوترة والشحن الافتراضية الفارغة
                    $billing_fields = [
                        'billing_first_name', 'billing_last_name', 'billing_company',
                        'billing_address_1', 'billing_address_2', 'billing_city',
                        'billing_postcode', 'billing_country', 'billing_state',
                        'billing_email', 'billing_phone',
                    ];
                    $shipping_fields = [
                        'shipping_first_name', 'shipping_last_name', 'shipping_company',
                        'shipping_address_1', 'shipping_address_2', 'shipping_city',
                        'shipping_postcode', 'shipping_country', 'shipping_state',
                    ];

                    foreach (array_merge($billing_fields, $shipping_fields) as $field) {
                        delete_user_meta($customer_id, $field);
                    }
                }
            }, 10, 3);
        }

        // ─── Add rewrite rule for /store/{slug} to populate vendor_store query_var ───
        add_action('init', function(): void {
            add_rewrite_rule('^store/([^/]+)/?$', 'index.php?vendor_store=$matches[1]', 'top');
        }, 5);

        // ─── 1. إضافة vendor_store إلى query_vars ───
        // (المصدر الوحيد — حُذفت النسخة المكررة من vendor-marketplace.php)
        add_filter('query_vars', function (array $vars): array {
            if (!in_array('vendor_store', $vars, true)) {
                $vars[] = 'vendor_store';
            }
            return $vars;
        });

        // ─── 2. استخدام قالب الصفحة العادي لعرض المتجر ───
        add_filter('template_include', function ($template) {
            if (get_query_var('vendor_store')) {
                $new_template = locate_template(['page.php', 'singular.php', 'index.php']);
                return $new_template ?: $template;
            }
            return $template;
        }, 99);

        // ─── 3. منع إعادة التوجيه غير المرغوب فيه ───
        // (المصدر الوحيد — حُذفت النسخة المكررة من vendor-marketplace.php)
        add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
            if (get_query_var('vendor_store')) {
                return false;
            }
            return $redirect_url;
        }, 10, 2);

        // ─── 4. استبدال محتوى صفحة vendor-store بالشورت كود مع slug ───
        // (نُقل من vendor-marketplace.php ليكون هنا المصدر الوحيد)
        add_filter('the_content', function ($content) {
            if (is_page('vendor-store') && get_query_var('vendor_store')) {
                $slug = sanitize_text_field(get_query_var('vendor_store'));
                return do_shortcode('[vmp_vendor_store slug="' . esc_attr($slug) . '"]');
            }
            return $content;
        }, 10, 1);

        // ─── 5. تسجيل الشورت كودات (تعمل دائماً) ───
        $this->registerShortcodes();

        // ─── 6. التحقق من WooCommerce ───
        $woocommerceActive = $this->container->has('woocommerce.active')
            && (bool) $this->container->make('woocommerce.active');

        // ─── 6. تسجيل الهوكات المعتمدة على WooCommerce ───
        if ($woocommerceActive) {
            $this->registerWooCommerceHooks();
        }

        // ─── 7. تحميل الأصول (CSS/JS) ───
        $this->registerAssets();

        // ─── 8. عرض اسم البائع في صفحات WooCommerce العامة ───
        $this->registerVendorNameInWooCommerce();
    }

    /**
     * دالة مساعدة لعرض القوالب مع تعيين العلم والصفحة الحالية تلقائياً
     * ✅ يضمن تحميل الأصول في أي شورت كود جديد
     * ✅ يضمن دقة الصفحة الحالية مع جميع أنواع الـ permalinks
     * ✅ يسمح بتمرير متغيرات إضافية إلى القالب
     *
     * @param string $template اسم ملف القالب (مثل 'vendor-dashboard.php')
     * @param string $page معرف الصفحة (مثل 'dashboard', 'products', 'edit-product')
     * @param array $vars متغيرات إضافية يتم تمريرها إلى القالب (مثل ['vendor' => $vendor])
     * @return string محتوى القالب
     */
    private function renderTemplate(string $template, string $page = '', array $vars = []): string
    {
        // تعيين العلم بأن هذه صفحة VMP (لـ Page Builders و has_shortcode)
        $GLOBALS['vmp_is_active'] = true;

        // تخزين الصفحة الحالية للاستخدام في registerAssets (موثوق مع جميع أنواع الـ permalinks)
        if ($page) {
            $GLOBALS['vmp_current_page'] = $page;
        }

        // استخراج المتغيرات إلى النطاق المحلي (آمن لأن المصدر موثوق)
        if (!empty($vars)) {
            extract($vars); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        }

        ob_start();
        $file = VMP_PLUGIN_DIR . 'public/templates/' . $template;
        if (file_exists($file)) {
            require_once $file;
        } else {
            // رسالة خطأ في حال عدم وجود القالب (للتطوير)
            echo '<div class="vmp-notice vmp-notice-error">';
            echo sprintf(__('قالب %s غير موجود.', 'vmp'), esc_html($template));
            echo '</div>';
        }
        return ob_get_clean();
    }

    /**
     * تسجيل جميع الشورت كودات الإضافة
     *
     * @return void
     */
    private function registerShortcodes(): void
    {
        // خريطة الصفحات للوحة التحكم (يُستخدم داخل شورت كود vmp_vendor_dashboard)
        $vmp_page_map = [
            'dashboard' => 'vendor-dashboard.php',
            'products' => 'vendor-products.php',
            'add-product' => 'vendor-add-product.php',
            'ai-create-product' => 'vendor-ai-create-product.php',
            'edit-product' => 'vendor-edit-product.php',
            'orders' => 'vendor-orders.php',
            'profile' => 'vendor-profile.php',
            'withdrawals' => 'vendor-withdrawals.php',
            'subscriptions' => 'vendor-subscriptions.php',
        ];

        // ── شورت كود لوحة التحكم ──
        add_shortcode('vmp_vendor_dashboard', function () use ($vmp_page_map): string {
            $page = sanitize_key($_GET['vmp_page'] ?? 'dashboard');
            $allowed_pages = array_keys($vmp_page_map);
            if (!in_array($page, $allowed_pages, true)) {
                $page = 'dashboard';
            }
            $template = $vmp_page_map[$page] ?? 'vendor-dashboard.php';
            return $this->renderTemplate($template, $page);
        });

        // ── شورت كود تسجيل البائع (نموذج أحادي الخطوة) ──
        add_shortcode('vmp_vendor_register', function (): string {
            return $this->renderTemplate('vendor-register.php', 'register', [
                'current_user' => wp_get_current_user(),
                'is_logged_in' => is_user_logged_in(),
            ]);
        });

        // ── شورت كود صفحة نجاح التسجيل ──
        add_shortcode('vmp_vendor_register_success', function (): string {
            return $this->renderTemplate('vendor-register-success.php', 'register');
        });

        // ── شورت كود عرض متجر البائع ──
        add_shortcode('vmp_vendor_store', function ($atts): string {
            $atts = shortcode_atts(['slug' => '', 'id' => 0], $atts);

            $vendor_repo = $this->container->make(VendorRepositoryInterface::class);

            // إذا لم يتم تمرير slug عبر الشورت كود، نأخذه من query_var
            if (empty($atts['slug'])) {
                $atts['slug'] = get_query_var('vendor_store', '');
            }

            // البحث عن البائع
            if (!empty($atts['slug'])) {
                $vendor = $vendor_repo->findBySlug(sanitize_text_field($atts['slug']));
            } elseif (!empty($atts['id'])) {
                $vendor = $vendor_repo->find((int) $atts['id']);
            } else {
                $vendor = null;
            }

            // التحقق من وجود البائع وحالته
            if (!$vendor || $vendor->status !== 'approved') {
                return '<p class="vmp-not-found">' . __('المتجر غير موجود.', 'vmp') . '</p>';
            }

            // ✅ تمرير متغير $vendor إلى القالب
            return $this->renderTemplate('vendor-store.php', 'store', ['vendor' => $vendor]);
        });
    }

    /**
     * تسجيل الهوكات التي تعتمد على WooCommerce
     *
     * @return void
     */
    private function registerWooCommerceHooks(): void
    {
        // منع الروابط المختصرة في صفحة المتجر
        add_filter('pre_get_shortlink', static function ($shortlink, $id, $context, $allow_slugs) {
            if ('query' === $context && get_query_var('vendor_store')) {
                return '';
            }
            return $shortlink;
        }, 10, 4);

        // يمكن إضافة هوكات WooCommerce إضافية هنا مستقبلاً
        // مثال: add_filter('woocommerce_product_data_store', ...);
    }

    /**
     * تحميل أصول الإضافة (CSS/JS) – النسخة النهائية المحسنة
     * ✅ تحميل wp_enqueue_media() شرطياً (فقط في صفحات رفع الملفات أو في أي صفحة VMP)
     * ✅ تحميل vendor-products.js شرطياً (فقط في صفحة المنتجات)
     * ✅ كائن JS واحد يحتوي كل شيء (vmp_public)
     * ✅ nonce واحد للتطبيق العامة (vmp_public.nonce)
     * ✅ nonce خاص للتسجيل (vmp_public.register_nonce) لحل مشكلة التسجيل
     * ✅ يدعم Page Builders عبر GLOBALS['vmp_is_active']
     * ✅ يدعم جميع أنواع الـ permalinks عبر GLOBALS['vmp_current_page']
     *
     * @return void
     */
    /**
     * بيانات JS لصفحة الملف الشخصي (تطابق ما كان يُحقن inline في القالب).
     *
     * @return array<string, mixed>
     */
    private function profileJsData(): array
    {
        $user_id = get_current_user_id();
        $store_base = get_option('vmp_store_base', 'store');
        $store_slug = '';
        $vendor = $this->vendorForCurrentUser();
        if ($vendor) {
            $store_slug = !empty($vendor->store_slug) ? $vendor->store_slug : sanitize_title($vendor->store_name);
            if (empty($store_slug)) {
                $store_slug = 'store-' . $vendor->id;
            }
        }
        $store_base_url = home_url('/' . $store_base . '/');

        return [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vmp_public_nonce'),
            'storeBase' => $store_base,
            'storeBaseUrl' => $store_base_url,
            'userId' => (int) $user_id,
            'i18n' => [
                'copied' => __('تم نسخ رابط المتجر!', 'vmp'),
                'slugAvailable' => __('الرابط متاح', 'vmp'),
                'slugTaken' => __('الرابط مستخدم مسبقاً', 'vmp'),
                'slugInvalid' => __('أحرف إنجليزية صغيرة، أرقام، وشرطات فقط.', 'vmp'),
                'slugCheckError' => __('تعذر التحقق', 'vmp'),
                'checking' => __('جاري التحقق...', 'vmp'),
                'saved' => __('تم حفظ التغييرات بنجاح.', 'vmp'),
                'saveError' => __('حدث خطأ أثناء الحفظ.', 'vmp'),
                'connectionError' => __('خطأ في الاتصال بالخادم.', 'vmp'),
                'saving' => __('جاري الحفظ...', 'vmp'),
                'saveBtn' => __('حفظ التعديلات', 'vmp'),
                'chooseImage' => __('اختر صورة', 'vmp'),
                'passwordMismatch' => __('كلمتا المرور غير متطابقتين.', 'vmp'),
                'passwordTooShort' => __('كلمة المرور يجب أن تكون 8 أحرف على الأقل.', 'vmp'),
            ],
        ];
    }

    /**
     * معدل العمولة للمستخدم الحالي (يطابق منطق vendor-edit-product.php).
     */
    private function currentUserCommissionRate(): float
    {
        $vendor = $this->vendorForCurrentUser();
        if (!$vendor) {
            return 10.0;
        }
        try {
            $sub_repo = $this->container->make(\VMP\Contracts\SubscriptionRepositoryInterface::class);
            $plan_repo = $this->container->make(\VMP\Contracts\SubscriptionPlanRepositoryInterface::class);
            $active_sub = $sub_repo->findActiveByVendor((int) $vendor->id);
            $plan = $active_sub ? $plan_repo->find((int) $active_sub->plan_id) : $plan_repo->findBySlug('free');
            return $plan ? (float) $plan->commission_rate : 10.0;
        } catch (\Throwable $e) {
            return 10.0;
        }
    }

    /**
     * البائع المسجل للمستخدم الحالي (إن وُجد).
     *
     * @return \VMP\Models\Vendor|null
     */
    private function vendorForCurrentUser()
    {
        try {
            $vendor_repo = $this->container->make(\VMP\Contracts\VendorRepositoryInterface::class);
            return $vendor_repo->findByUserId((int) get_current_user_id());
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function registerAssets(): void
    {
        add_action('wp_enqueue_scripts', function (): void {
            // ─── 1. التحقق من أننا في صفحة VMP ───
            $is_vmp_page = !empty($GLOBALS['vmp_is_active']);

            // ─── 2. احتياطي: التحقق من post_content (للمحتوى الثابت) ───
            if (!$is_vmp_page && !empty($GLOBALS['post'])) {
                $content = $GLOBALS['post']->post_content ?? '';
                $shortcodes = ['vmp_vendor_register', 'vmp_vendor_dashboard', 'vmp_vendor_store'];
                foreach ($shortcodes as $sc) {
                    if (has_shortcode($content, $sc)) {
                        $is_vmp_page = true;
                        break;
                    }
                }
            }

            // ─── 3. إذا لم تكن صفحة VMP، لا نحمّل أي أصول ───
            if (!$is_vmp_page) {
                return;
            }

            // ─── 4. الحصول على الصفحة الحالية (موثوق مع جميع أنواع الـ permalinks) ───
            // NOTE: $GLOBALS['vmp_current_page'] is set during shortcode execution, which happens
            // AFTER wp_enqueue_scripts. So we must also check post_content for the register shortcode.
            $current_page = $GLOBALS['vmp_current_page']
                ?? sanitize_key($_GET['vmp_page'] ?? 'dashboard');

            // Fallback: detect register page via shortcode in post_content (for page builders/static pages)
            if ($current_page === 'dashboard' && !empty($GLOBALS['post'])) {
                $content = $GLOBALS['post']->post_content ?? '';
                if (has_shortcode($content, 'vmp_vendor_register')) {
                    $current_page = 'register';
                }
            }

            // Additional fallback: Check if current page is the vendor-register page (cached)
            if ($current_page === 'dashboard') {
                $register_page_id = get_transient('vmp_register_page_id');
                if (false === $register_page_id) {
                    global $wpdb;
                    $register_page_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%vmp_vendor_register%' AND post_type = 'page' AND post_status = 'publish' LIMIT 1");
                    set_transient('vmp_register_page_id', $register_page_id ?: 0, DAY_IN_SECONDS);
                }
                if ($register_page_id) {
                    $current_page_id = get_queried_object_id();
                    if ($current_page_id == $register_page_id) {
                        $current_page = 'register';
                    }
                }
            }

            // URL-based fallback: check if current URL contains vendor-register
            if ($current_page === 'dashboard') {
                $request_uri = $_SERVER['REQUEST_URI'] ?? '';
                if (strpos($request_uri, 'vendor-register') !== false || strpos($request_uri, 'تسجيل-بائع') !== false) {
                    $current_page = 'register';
                }
            }

            // ─── 5. Force load wp_enqueue_media() for any VMP page to avoid missing media scripts
            // Some themes or page builders might not print required REST settings; we also provide a fallback below.
            wp_enqueue_media();

            // ─── 6. تحميل ملفات التصميم (CSS) ───
            wp_enqueue_style(
                'vmp-public',
                VMP_PLUGIN_URL . 'public/css/public.css',
                [],
                VMP_VERSION
            );

            // ─── 7. تحميل ملف JavaScript العام (يُحمّل في كل صفحات VMP) ───
            wp_enqueue_script(
                'vmp-public',
                VMP_PLUGIN_URL . 'public/js/public.js',
                ['jquery', 'media-editor'],
                VMP_VERSION,
                true
            );

            // ─── 8. تحميل JS المنتجات فقط في صفحة المنتجات ───
            if (in_array($current_page, ['products', 'add-product', 'edit-product'], true)) {
                wp_enqueue_script(
                    'vmp-products-js',
                    VMP_PLUGIN_URL . 'public/js/vendor-products.js',
                    ['jquery', 'vmp-public', 'media-editor'],
                    VMP_VERSION,
                    true
                );
            }

            if ($current_page === 'ai-create-product') {
                wp_enqueue_script(
                    'vmp-ai-product-js',
                    VMP_PLUGIN_URL . 'public/js/vendor-ai-product.js',
                    ['jquery', 'vmp-public'],
                    VMP_VERSION,
                    true
                );
            }

            // ─── 8b. تحميل ملفات التسجيل في صفحة التسجيل ───
            if ($current_page === 'register') {
                wp_enqueue_style(
                    'vmp-register-css',
                    VMP_PLUGIN_URL . 'public/css/vendor-register.css',
                    ['vmp-public'],
                    VMP_VERSION
                );

                wp_enqueue_script(
                    'vmp-register-js',
                    VMP_PLUGIN_URL . 'public/js/vendor-register.js',
                    ['jquery', 'vmp-public'],
                    VMP_VERSION,
                    true
                );

                // بيانات تسجيل البائع (تطابق ما كان يُحقن inline في القالب)
                $is_logged_in = is_user_logged_in();
                wp_localize_script('vmp-register-js', 'vmpRegisterData', [
                    'restGuestUrl' => esc_url_raw(rest_url('vmp/v1/vendor/register-guest')),
                    'restApplyUrl' => esc_url_raw(rest_url('vmp/v1/vendor/apply')),
                    'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
                    'isLoggedIn' => (bool) $is_logged_in,
                    'strings' => [
                        'submit' => $is_logged_in ? __('إرسال طلب الترقية', 'vmp') : __('تسجيل كبائع', 'vmp'),
                        'submitting' => __('جاري إرسال الطلب...', 'vmp'),
                        'error' => __('حدث خطأ أثناء معالجة الطلب', 'vmp'),
                        'passwordMismatch' => __('كلمتا المرور غير متطابقتين', 'vmp'),
                        'termsRequired' => __('يجب الموافقة على الشروط والأحكام', 'vmp'),
                    ],
                ]);
            }

            // ─── 8c. تحميل أصول مخصصة لكل صفحة (فصل inline CSS/JS إلى ملفات خارجية — task B) ───
            if ($current_page === 'dashboard') {
                wp_enqueue_style('vmp-dashboard-css', VMP_PLUGIN_URL . 'public/css/vendor-dashboard.css', ['vmp-public'], VMP_VERSION);
                wp_enqueue_script('vmp-dashboard-js', VMP_PLUGIN_URL . 'public/js/vendor-dashboard.js', ['jquery', 'vmp-public'], VMP_VERSION, true);
            }

            if ($current_page === 'store') {
                wp_enqueue_style('vmp-store-css', VMP_PLUGIN_URL . 'public/css/vendor-store.css', ['vmp-public'], VMP_VERSION);
            }

            if ($current_page === 'profile') {
                wp_enqueue_style('vmp-profile-css', VMP_PLUGIN_URL . 'public/css/vendor-profile.css', ['vmp-public'], VMP_VERSION);
                wp_enqueue_script('vmp-profile-js', VMP_PLUGIN_URL . 'public/js/vendor-profile.js', ['jquery', 'vmp-public', 'media-editor'], VMP_VERSION, true);
                wp_localize_script('vmp-profile-js', 'vmp_profile_data', $this->profileJsData());
            }

            if ($current_page === 'orders') {
                wp_enqueue_style('vmp-orders-css', VMP_PLUGIN_URL . 'public/css/vendor-orders.css', ['vmp-public'], VMP_VERSION);
                wp_enqueue_script('vmp-orders-js', VMP_PLUGIN_URL . 'public/js/vendor-orders.js', ['jquery', 'vmp-public'], VMP_VERSION, true);
                wp_localize_script('vmp-orders-js', 'vmp_orders_data', [
                    'i18n' => [
                        'loading' => __('جاري تحميل التفاصيل...', 'vmp'),
                        'orderNumber' => __('رقم الطلب:', 'vmp'),
                        'customer' => __('العميل:', 'vmp'),
                        'email' => __('البريد:', 'vmp'),
                        'date' => __('التاريخ:', 'vmp'),
                        'total' => __('الإجمالي:', 'vmp'),
                        'noDetails' => __('لا توجد تفاصيل إضافية.', 'vmp'),
                        'loadFailed' => __('تعذر تحميل التفاصيل.', 'vmp'),
                        'loadError' => __('حدث خطأ أثناء تحميل التفاصيل.', 'vmp'),
                    ],
                ]);
            }

            if ($current_page === 'subscriptions') {
                wp_enqueue_script('vmp-subscriptions-js', VMP_PLUGIN_URL . 'public/js/vendor-subscriptions.js', ['jquery', 'vmp-public'], VMP_VERSION, true);
                wp_localize_script('vmp-subscriptions-js', 'vmp_subs_data', [
                    'i18n' => [
                        'confirmChange' => __('هل أنت متأكد من طلب تغيير خطتك إلى', 'vmp'),
                        'willReview' => __('سيتم مراجعة الطلب من قبل المشرف.', 'vmp'),
                        'sending' => __('جاري الإرسال...', 'vmp'),
                        'requestChange' => __('طلب تغيير الخطة', 'vmp'),
                        'connError' => __('حدث خطأ في الاتصال.', 'vmp'),
                        'confirmCancel' => __('هل أنت متأكد من إلغاء طلب تغيير الخطة؟', 'vmp'),
                        'canceling' => __('جاري...', 'vmp'),
                        'cancelRequest' => __('إلغاء الطلب', 'vmp'),
                    ],
                ]);
            }

            if ($current_page === 'edit-product') {
                wp_enqueue_script('vmp-edit-product-js', VMP_PLUGIN_URL . 'public/js/vendor-edit-product.js', ['jquery', 'vmp-public'], VMP_VERSION, true);
                wp_localize_script('vmp-edit-product-js', 'vmp_edit_product_data', ['commissionRate' => $this->currentUserCommissionRate()]);
            }

            // ─── 9. كائن واحد يحتوي كل شيء (بدون تكرار) ───
            wp_localize_script('vmp-public', 'vmp_public', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vmp_public_nonce'), // ✅ nonce عام
                'register_nonce' => wp_create_nonce('vmp_vendor_register_nonce'), // ✅ nonce خاص بالتسجيل
                'page' => $current_page, // ✅ الصفحة الحالية (موثوقة)
                'plugin_url' => VMP_PLUGIN_URL,
                'dashboard_url' => home_url('/vendor-dashboard/'),
                'strings' => [
                    'loading' => __('جاري...', 'vmp'),
                    'delete' => __('حذف', 'vmp'),
                    'error' => __('حدث خطأ', 'vmp'),
                    'conn_error' => __('حدث خطأ في الاتصال', 'vmp'),
                    'confirm_delete' => __('هل أنت متأكد من حذف هذا المنتج؟', 'vmp'),
                    'next' => __('التالي', 'vmp'),
                    'prev' => __('السابق', 'vmp'),
                    'submit' => __('إرسال الطلب', 'vmp'),
                ],
            ]);

            // ─── 10. Ensure REST API settings are available for wp.media on frontend ───
            // Some themes/plugins may not print wpApiSettings in frontend; provide fallback
            wp_localize_script('vmp-public', 'wpApiSettings', [
                'root' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        });
    }

    /**
     * عرض اسم البائع في صفحات WooCommerce العامة
     * (صفحة أرشيف المنتجات + صفحة المنتج الفردي)
     * ✅ يستخدم الدوال المساعدة من helpers.php
     * ✅ يضيف رابطاً إلى صفحة متجر البائع
     *
     * @return void
     */
    private function registerVendorNameInWooCommerce(): void
    {
        // ── عرض اسم البائع تحت عنوان المنتج في صفحة المتجر (أرشيف المنتجات) ──
        add_action('woocommerce_after_shop_loop_item_title', function () {
            global $product;
            if (!$product) {
                return;
            }

            $vendor_id = vmp_get_product_vendor_id($product->get_id());
            if (!$vendor_id) {
                return;
            }

            $vendor = vmp_get_vendor($vendor_id);
            if (!$vendor || $vendor->status !== 'approved') {
                return;
            }

            echo '<p class="vmp-vendor-name"><a href="' . esc_url(home_url('/store/' . $vendor->slug . '/')) . '">' . esc_html($vendor->store_name) . '</a></p>';
        }, 6);

        // ── عرض اسم البائع في صفحة المنتج الفردي (تفاصيل المنتج) ──
        add_action('woocommerce_single_product_summary', function () {
            global $product;
            if (!$product) {
                return;
            }

            $vendor_id = vmp_get_product_vendor_id($product->get_id());
            if (!$vendor_id) {
                return;
            }

            $vendor = vmp_get_vendor($vendor_id);
            if (!$vendor || $vendor->status !== 'approved') {
                return;
            }

            echo '<p class="vmp-vendor-name"><a href="' . esc_url(home_url('/store/' . $vendor->slug . '/')) . '">' . esc_html($vendor->store_name) . '</a></p>';
        }, 6);
    }
}
