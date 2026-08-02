<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * تهيئة دعم الجلسات للـ multi-step registration
 *
 * @package VMP\Core
 * @since 2.0.0
 */

// ─── Session Initialization ────────────────────────────────────────────────
add_action('init', function (): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => 86400, // 24 ساعة
            'cookie_secure'   => is_ssl(),
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }
}, 1);

/**
 * تنسيق السعر للعملة المحلية
 */
if (!function_exists('vmp_format_price')) {
    function vmp_format_price(float $amount): string
    {
        return \VMP\Support\price($amount);
    }
}

/**
 * التحقق من توفر رابط متجر (slug)
 */
if (!function_exists('vmp_check_store_slug')) {
    function vmp_check_store_slug(string $slug): bool
    {
        global $wpdb;
        $slug = sanitize_title($slug);
        if (!$slug) {
            return false;
        }
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->prefix}vmp_vendors WHERE slug = %s LIMIT 1",
            $slug
        ));
    }
}

/**
 * حفظ بيانات نموذج التسجيل في الجلسة
 */
if (!function_exists('vmp_save_register_data')) {
    function vmp_save_register_data(array $data, int $step): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['vmp_vendor_register_data'] = $data;
        $_SESSION['vmp_vendor_register_step'] = $step;
    }
}

/**
 * استرجاع بيانات نموذج التسجيل من الجلسة
 */
if (!function_exists('vmp_get_register_data')) {
    function vmp_get_register_data(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['vmp_vendor_register_data'] ?? [];
    }
}

/**
 * استرجاع الخطوة الحالية من الجلسة
 */
if (!function_exists('vmp_get_register_step')) {
    function vmp_get_register_step(): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return (int) ($_SESSION['vmp_vendor_register_step'] ?? 1);
    }
}

/**
 * مسح بيانات التسجيل من الجلسة
 */
if (!function_exists('vmp_clear_register_data')) {
    function vmp_clear_register_data(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['vmp_vendor_register_data']);
        unset($_SESSION['vmp_vendor_register_step']);
        unset($_SESSION['vmp_vendor_register_errors']);
    }
}

/**
 * إرسال بريد إلكتروني ترحيبي للبائع الجديد
 */
if (!function_exists('vmp_send_welcome_email')) {
    function vmp_send_welcome_email(int $vendor_id, array $data): void
    {
        $settings = get_option('vmp_settings', []);
        $template = $settings['messages']['welcome_email'] ?? '';
        
        if (!$template) {
            return;
        }

        $vendor = \VMP\Core\Container::getInstance()
            ->make(\VMP\Contracts\VendorRepositoryInterface::class)
            ->find($vendor_id);

        if (!$vendor) {
            return;
        }

        $replacements = [
            '{first_name}' => $vendor->first_name ?? '',
            '{store_name}' => $vendor->store_name ?? '',
            '{store_slug}' => $vendor->store_slug ?? '',
            '{login_url}'  => wp_login_url(),
            '{dashboard_url}' => home_url('/vendor-dashboard/'),
        ];

        $subject = sprintf(__('مرحباً بك في %s!', 'vmp'), get_bloginfo('name'));
        $message = strtr($template, $replacements);

        // تحويل للنص HTML
        $message = wpautop($message);

        wp_mail($vendor->user_email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }
}

/**
 * إرسال إشعار للمشرف عند تسجيل بائع جديد
 */
if (!function_exists('vmp_send_admin_notification')) {
    function vmp_send_admin_notification(int $vendor_id): void
    {
        $settings = get_option('vmp_settings', []);
        
        if (empty($settings['registration']['send_admin_notification'])) {
            return;
        }

        $vendor = \VMP\Core\Container::getInstance()
            ->make(\VMP\Contracts\VendorRepositoryInterface::class)
            ->find($vendor_id);

        if (!$vendor) {
            return;
        }

        $admin_email = get_option('admin_email');
        $subject = sprintf(__('[%s] طلب تسجيل بائع جديد', 'vmp'), get_bloginfo('name'));
        
        $message = sprintf(__('تم استلام طلب تسجيل بائع جديد:', 'vmp') . "\n\n", get_bloginfo('name'));
        $message .= sprintf(__('الاسم: %s', 'vmp'), $vendor->first_name . ' ' . $vendor->last_name) . "\n";
        $message .= sprintf(__('البريد الإلكتروني: %s', 'vmp'), $vendor->user_email) . "\n";
        $message .= sprintf(__('اسم المتجر: %s', 'vmp'), $vendor->store_name) . "\n";
        $message .= sprintf(__('رابط المتجر: %s', 'vmp'), home_url('/store/' . $vendor->store_slug)) . "\n";
        $message .= sprintf(__('الهاتف: %s', 'vmp'), $vendor->phone) . "\n";
        $message .= sprintf(__('الدولة: %s', 'vmp'), $vendor->country) . "\n";
        $message .= sprintf(__('المدينة: %s', 'vmp'), $vendor->city) . "\n";
        $message .= "\n" . __('يمكنك مراجعة الطلب من لوحة التحكم.', 'vmp') . "\n";
        $message .= admin_url('admin.php?page=vmp-vendors&vendor=' . $vendor_id);

        wp_mail($admin_email, $subject, $message);
    }
}

/**
 * تسجيل محاولات التسجيل (للأمان والمراجعة)
 */
if (!function_exists('vmp_log_registration_attempt')) {
    function vmp_log_registration_attempt(array $data, bool $success, string $message = ''): void
    {
        $settings = get_option('vmp_settings', []);
        
        if (empty($settings['registration']['log_registration_attempts'])) {
            return;
        }

        $log = sprintf(
            "[%s] Registration attempt: %s | Email: %s | Store: %s | Success: %s | IP: %s\n",
            current_time('mysql'),
            $message ?: ($success ? 'SUCCESS' : 'FAILED'),
            $data['user_email'] ?? 'N/A',
            $data['store_name'] ?? 'N/A',
            $success ? 'YES' : 'NO',
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        );

        error_log($log);
    }
}