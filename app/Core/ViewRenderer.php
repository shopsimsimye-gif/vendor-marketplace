<?php
namespace VMP\Core;

defined('ABSPATH') || exit;

/**
 * فئات أساسية للـ Views
 */
class ViewRenderer
{
    private string $templatePath;

    public function __construct()
    {
        $this->templatePath = VMP_PLUGIN_DIR . 'public/templates/';
    }

    /**
     * Render functionality helper.
     *
     * @param string $template Description index.
     * @param array $data Description index.
     * @return void Output payload.
     */
    public function render(string $template, array $data = []): void
    {
        // إضافة البيانات المتاحة في جميع القوالب
        $data = array_merge([
            'plugin_url'   => VMP_PLUGIN_URL,
            'plugin_dir'   => VMP_PLUGIN_DIR,
            'ajax_url'     => admin_url('admin-ajax.php'),
            'rest_url'     => rest_url('vmp/v1/'),
            'nonce'        => wp_create_nonce('vmp_nonce'),
            'is_rtl'       => is_rtl(),
            'current_user' => wp_get_current_user(),
        ], $data);

        // استخراج المتغيرات للوصول المباشر في القالب
        extract($data);

        $templateFile = $this->templatePath . $template . '.php';

        if (!file_exists($templateFile)) {
            $templateFile = VMP_PLUGIN_DIR . 'templates/' . $template . '.php';
        }

        if (file_exists($templateFile)) {
            include $templateFile;
        } else {
            error_log("[VMP] Template not found: {$template}");
            // fallback
            echo '<div class="vmp-error"><p>' . esc_html__('Template not found', 'vmp') . ': ' . esc_html($template) . '</p></div>';
        }
    }
}