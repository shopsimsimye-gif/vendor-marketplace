<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Core\Logger;
use VMP\Modules\AI\Security\SecretManager;
use VMP\Http\Responses\ApiResponse;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ErrorResponse;

/**
 * Class AiSettingsController
 *
 * مسؤول عن إعدادات الذكاء الاصطناعي (الحفظ + اختبار الاتصال).
 * نُقلت معالجات AJAX هنا من CoreServiceProvider لفصل المسؤوليات.
 *
 * @package vendor-marketplace
 */
class AiSettingsController extends BaseController
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * حفظ إعدادات الذكاء الاصطناعي (للمشرف).
     */
    public function save(): ApiResponse
    {
        // التحقق من nonce
        if (!check_ajax_referer('vmp_admin_nonce', 'nonce', false)) {
            return new ErrorResponse(message: __('رمز الأمان غير صحيح.', 'vmp'));
        }

        // التحقق من صلاحية المشرف
        if (!current_user_can('vmp_manage_settings')) {
            return new ErrorResponse(message: __('غير مصرح لك.', 'vmp'));
        }

        // استقبال البيانات
        $settings = isset($_POST['vmp_ai_settings']) ? $_POST['vmp_ai_settings'] : [];
        if (empty($settings) || !is_array($settings)) {
            return new ErrorResponse(message: __('لم يتم إرسال أي إعدادات.', 'vmp'));
        }

        // تنظيف البيانات حسب نوع كل حقل
        $old_settings = get_option('vmp_ai_settings', []);
        $sanitized = [];

        foreach ($settings as $key => $value) {
            switch ($key) {
                case 'cache_enabled':
                case 'require_human_review':
                    $sanitized[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'cache_ttl':
                case 'monthly_vendor_request_limit':
                    $sanitized[$key] = absint($value);
                    break;
                case 'monthly_vendor_cost_limit':
                    $sanitized[$key] = (float) $value;
                    break;
                case 'openai_api_key':
                    // 🔐 تشفير المفتاح قبل الحفظ
                    if (empty($value) && isset($old_settings[$key])) {
                        $sanitized[$key] = $old_settings[$key]; // Keep existing (already encrypted)
                        $sanitized[$key . '_encrypted'] = $old_settings[$key . '_encrypted'] ?? false;
                    } elseif (!empty($value)) {
                        $cleanValue = sanitize_text_field($value);
                        try {
                            /** @var SecretManager $secretManager */
                            $secretManager = \VMP\Core\Container::getInstance()->make(SecretManager::class);
                            $encrypted = $secretManager->encryptSecret($cleanValue);
                            $sanitized[$key] = base64_encode(json_encode($encrypted, JSON_THROW_ON_ERROR));
                            $sanitized[$key . '_encrypted'] = true;
                        } catch (\Throwable $e) {
                            $this->logger->error('فشل تشفير مفتاح OpenAI API: ' . $e->getMessage());
                            return new ErrorResponse(message: __('فشل تشفير مفتاح API. يرجى التحقق من إعدادات التشفير (VMP_ENCRYPTION_KEY في wp-config.php).', 'vmp'));
                        }
                    }
                    break;
                case 'openai_organization':
                case 'openai_model':
                case 'openai_vision_model':
                case 'openai_image_model':
                case 'default_provider':
                case 'vision_provider':
                case 'llm_provider':
                case 'search_provider':
                case 'image_generation_provider':
                case 'default_status':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }

        // دمج الإعدادات القديمة مع الجديدة للحفاظ على القيم غير المرسلة
        $merged = array_merge($old_settings, $sanitized);

        // حفظ الإعدادات
        update_option('vmp_ai_settings', $merged);

        // تسجيل الحدث (للتتبع)
        $this->logger->info(
            'تم حفظ إعدادات الذكاء الاصطناعي.',
            ['user_id' => get_current_user_id()]
        );

        return new SuccessResponse(message: __('تم حفظ إعدادات الذكاء الاصطناعي بنجاح.', 'vmp'));
    }

    /**
     * اختبار الاتصال بـ OpenAI (للمشرف).
     */
    public function testConnection(): ApiResponse
    {
        // التحقق من nonce
        if (!check_ajax_referer('vmp_admin_nonce', 'nonce', false)) {
            return new ErrorResponse(message: __('رمز الأمان غير صحيح.', 'vmp'));
        }

        if (!current_user_can('vmp_manage_settings')) {
            return new ErrorResponse(message: __('غير مصرح لك.', 'vmp'));
        }

        $settings = get_option('vmp_ai_settings', []);
        $api_key_raw = $settings['openai_api_key'] ?? '';

        // 🔐 فك تشفير المفتاح إذا كان مشفراً
        $api_key = $api_key_raw;
        if (!empty($api_key_raw) && !empty($settings['openai_api_key_encrypted'])) {
            try {
                /** @var SecretManager $secretManager */
                $secretManager = \VMP\Core\Container::getInstance()->make(SecretManager::class);
                $payload = json_decode(base64_decode($api_key_raw), true);
                if (is_array($payload) && isset($payload['ciphertext'], $payload['iv'], $payload['tag'])) {
                    $api_key = $secretManager->decryptSecret(
                        $payload['ciphertext'],
                        $payload['iv'],
                        $payload['tag']
                    );
                }
            } catch (\Throwable $e) {
                $this->logger->error('فشل فك تشفير مفتاح OpenAI API: ' . $e->getMessage());
            }
        }

        if (empty($api_key)) {
            return new ErrorResponse(message: __('مفتاح OpenAI API غير موجود.', 'vmp'));
        }

        // اختبار الاتصال بـ OpenAI
        $response = wp_remote_post('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return new ErrorResponse(message: __('فشل الاتصال: ', 'vmp') . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            return new SuccessResponse(message: __('✅ الاتصال بـ OpenAI يعمل بنجاح.', 'vmp'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $error_msg = $data['error']['message'] ?? __('خطأ غير معروف.', 'vmp');
        return new ErrorResponse(message: __('❌ فشل الاتصال: ', 'vmp') . $error_msg);
    }
}
