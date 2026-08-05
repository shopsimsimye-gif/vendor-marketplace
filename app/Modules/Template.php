<?php
namespace VMP\Modules;

use VMP\Core\Container;
use VMP\Repositories\VendorRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Template
 *
 * Description of administrative platform component Template.
 *
 * @package vendor-marketplace
 */
class Template extends AbstractModule
{
    private VendorRepository $vendorRepository;

    private array $available_templates = [
        'classic' => [
            'name' => 'Classic',
            'name_ar' => 'الكلاسيكي',
            'description' => 'تصميم أنيق وكلاسيكي مناسب لجميع المتاجر',
            'preview' => 'classic-preview.jpg',
        ],
        'modern' => [
            'name' => 'Modern',
            'name_ar' => 'العصري',
            'description' => 'تصميم حديث وجذاب مع تأثيرات بصرية مميزة',
            'preview' => 'modern-preview.jpg',
        ],
        'minimal' => [
            'name' => 'Minimal',
            'name_ar' => 'البسيط',
            'description' => 'تصميم بسيط ونظيف يركز على المنتجات',
            'preview' => 'minimal-preview.jpg',
        ],
    ];

    private array $available_fonts = [
        'Cairo' => 'Cairo',
        'Tajawal' => 'Tajawal',
        'Almarai' => 'Almarai',
        'Changa' => 'Changa',
        'Amiri' => 'Amiri',
    ];

    /**
     *   Construct functionality helper.
     *
     * @param Container $container Description index.
     * @return void Output payload.
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->vendorRepository = $this->make(VendorRepository::class);
    }

    /**
     * Init functionality helper.
     *
     * @return void Output payload.
     */
    public function init(): void
    {
        // [QA 2026-08-02] نُقل تسجيل AJAX إلى RouteRegistry في CoreServiceProvider
        // (TemplateController.saveTemplate / getTemplateSettings / getTemplatesList)
        // add_action('wp_ajax_vmp_save_template', [$this, 'ajax_save_template']);
        // add_action('wp_ajax_vmp_get_template_settings', [$this, 'ajax_get_template_settings']);
        // add_action('wp_ajax_vmp_get_templates_list', [$this, 'ajax_get_templates_list']);
        add_action('wp_head', [$this, 'inject_vendor_css_vars']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_vendor_font']);
    }

    /**
     * [QA 2026-08-02] Getters عامة للقوائم — يستخدمها TemplateController
     *
     * @return array
     */
    public function get_available_templates(): array
    {
        return $this->available_templates;
    }

    /**
     * [QA 2026-08-02] Getters عامة للخطوط — يستخدمها TemplateController
     *
     * @return array
     */
    public function get_available_fonts(): array
    {
        return $this->available_fonts;
    }

    /**
     * Get Vendor Template Settings functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return array Output payload.
     */
    public function get_vendor_template_settings(int $vendor_id): array
    {
        $defaults = [
            'template' => 'classic',
            'primary_color' => '#6366f1',
            'secondary_color' => '#a5b4fc',
            'bg_color' => '#ffffff',
            'text_color' => '#1e1b4b',
            'font_family' => 'Cairo',
            'button_radius' => '8',
            'show_banner' => true,
            'show_rating' => true,
            'products_per_row' => 3,
        ];

        $saved = get_user_meta($this->get_user_id_by_vendor($vendor_id), 'vmp_template_settings', true);
        if (!is_array($saved)) {
            return $defaults;
        }

        return wp_parse_args($saved, $defaults);
    }

    /**
     * Get User Id By Vendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return int Output payload.
     */
    private function get_user_id_by_vendor(int $vendor_id): int
    {
        $vendor = $this->vendorRepository->find($vendor_id);
        return $vendor ? (int) $vendor->user_id : 0;
    }

    /**
     * Inject Vendor Css Vars functionality helper.
     *
     * @return void Output payload.
     */
    public function inject_vendor_css_vars(): void
    {
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'vmp_vendor_store')) {
            return;
        }

        $vendor_id = $this->get_current_store_vendor_id();
        if (!$vendor_id) {
            return;
        }

        $settings = $this->get_vendor_template_settings($vendor_id);
        $this->output_css_vars($settings, $vendor_id);
    }

    /**
     * Inject Css For Vendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return void Output payload.
     */
    public function inject_css_for_vendor(int $vendor_id): void
    {
        $settings = $this->get_vendor_template_settings($vendor_id);
        $this->output_css_vars($settings, $vendor_id);
    }

    /**
     * Output Css Vars functionality helper.
     *
     * @param array $settings Description index.
     * @param int $vendor_id Description index.
     * @return void Output payload.
     */
    private function output_css_vars(array $settings, int $vendor_id): void
    {
        $template = sanitize_text_field($settings['template']);
        $prefix = "vmp-store-{$vendor_id}";
        ?>
        <style id="vmp-template-vars-<?php echo $vendor_id; ?>">
        .<?php echo esc_attr($prefix); ?>,
        .vmp-store-wrap[data-vendor-id="<?php echo $vendor_id; ?>"] {
            --vmp-primary: <?php echo esc_attr($settings['primary_color']); ?>;
            --vmp-secondary: <?php echo esc_attr($settings['secondary_color']); ?>;
            --vmp-bg: <?php echo esc_attr($settings['bg_color']); ?>;
            --vmp-text: <?php echo esc_attr($settings['text_color']); ?>;
            --vmp-font: '<?php echo esc_attr($settings['font_family']); ?>', sans-serif;
            --vmp-radius: <?php echo (int) $settings['button_radius']; ?>px;
            --vmp-cols: <?php echo (int) $settings['products_per_row']; ?>;
        }
        </style>
        <script>
        document.documentElement.setAttribute('data-vmp-template', '<?php echo esc_js($template); ?>');
        </script>
        <?php
    }

    /**
     * Enqueue Vendor Font functionality helper.
     *
     * @return void Output payload.
     */
    public function enqueue_vendor_font(): void
    {
        global $post;
        if (!$post) {
            return;
        }

        $vendor_id = $this->get_current_store_vendor_id();
        if (!$vendor_id) {
            return;
        }

        $settings = $this->get_vendor_template_settings($vendor_id);
        $font_family = sanitize_text_field($settings['font_family']);

        if (!isset($this->available_fonts[$font_family])) {
            $font_family = 'Cairo';
        }

        $font_url = 'https://fonts.googleapis.com/css2?family=' . urlencode($font_family) . ':wght@300;400;500;600;700&display=swap';
        wp_enqueue_style("vmp-font-{$vendor_id}", $font_url, [], null);
    }

    /**
     * Get Current Store Vendor Id functionality helper.
     *
     * @return int Output payload.
     */
    private function get_current_store_vendor_id(): int
    {
        $slug = get_query_var('vendor_store', '');
        if ($slug) {
            $vendor = $this->vendorRepository->findBySlug($slug);
            return $vendor ? (int) $vendor->id : 0;
        }

        if (!empty($_GET['vendor_slug'])) {
            $vendor = $this->vendorRepository->findBySlug(sanitize_text_field($_GET['vendor_slug']));
            return $vendor ? (int) $vendor->id : 0;
        }

        return 0;
    }

    /**
     * Get Template Class functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return string Output payload.
     */
    public function get_template_class(int $vendor_id): string
    {
        $settings = $this->get_vendor_template_settings($vendor_id);
        $template = sanitize_text_field($settings['template']);
        if (!isset($this->available_templates[$template])) {
            $template = 'classic';
        }

        return "vmp-template-{$template} vmp-store-{$vendor_id}";
    }

    /**
     * Ajax Save Template functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Get Template Settings functionality helper.
     *
     * @return void Output payload.
     */

    /**
     * Ajax Get Templates List functionality helper.
     *
     * @return void Output payload.
     */
}
