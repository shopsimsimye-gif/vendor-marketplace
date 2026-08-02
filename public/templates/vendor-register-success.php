<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * قالب نجاح التسجيل
 *
 * @package VMP\Templates
 */

$request_id = isset($_GET['request_id']) ? absint($_GET['request_id']) : 0;
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pending';

$messages = [
    'pending' => [
        'title' => __('طلبك للانضمام إلينا كبائع قيد المراجعة', 'vmp'),
        'icon'  => 'dashicons-clock',
        'color' => '#f59e0b',
        'text'  => __('تم استلام طلب تسجيلك بنجاح، وهو الآن قيد المراجعة من قبل المشرف. سنقوم بإرسال بريد إلكتروني يفيدك بقرار القرار قريباً.', 'vmp'),
    ],
    'approved' => [
        'title' => __('تهانينا! تم قبول طلبك كبائع', 'vmp'),
        'icon'  => 'dashicons-yes-alt',
        'color' => '#10b981',
        'text'  => __('حسابك جاهز الآن. يمكنك الدخول إلى لوحة التحكم لبدء إعداد متجرك واختيار خطة الاشتراك.', 'vmp'),
    ],
    'rejected' => [
        'title' => __('عذراً، تم رفض طلب الانضمام', 'vmp'),
        'icon'  => 'dashicons-dismiss',
        'color' => '#ef4444',
        'text'  => __('يمكنك مراجعة أسباب الرفض عبر البريد الإلكتروني الذي أرسلناه لك أو التواصل مع الدعم الفني.', 'vmp'),
    ],
];

$msg = $messages[$status] ?? $messages['pending'];
?>

<div class="vmp-wrap vmp-register-success-wrap">
    <div class="vmp-container" style="max-width: 600px; margin: 40px auto; text-align: center;">
        <div class="vmp-card" style="padding: 48px 32px; background: #ffffff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
            <div class="vmp-success-icon" style="margin: 0 auto 24px;">
                <span class="dashicons <?php echo esc_attr($msg['icon']); ?>" style="font-size: 72px; width: 72px; height: 72px; color: <?php echo esc_attr($msg['color']); ?>;"></span>
            </div>
            <h1 style="font-size: 24px; color: #0f172a; margin-bottom: 16px;"><?php echo esc_html($msg['title']); ?></h1>
            <p style="font-size: 16px; color: #64748b; margin-bottom: 32px; line-height: 1.6;"><?php echo esc_html($msg['text']); ?></p>

            <?php if ($status === 'approved') : ?>
                <?php
                $settings = get_option('vmp_settings', []);
                $dashboard_page_id = !empty($settings['display']['dashboard_page']) ? (int) $settings['display']['dashboard_page'] : 0;
                $dashboard_url = $dashboard_page_id && get_post($dashboard_page_id) ? get_permalink($dashboard_page_id) : home_url('/vendor-dashboard/');
                ?>
                <a href="<?php echo esc_url($dashboard_url); ?>" class="vmp-btn vmp-btn-primary" style="display: inline-block; padding: 12px 32px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    <span class="dashicons dashicons-admin-generic" style="margin-left: 8px; vertical-align: middle;"></span>
                    <?php esc_html_e('الذهاب إلى لوحة البائع', 'vmp'); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="vmp-btn vmp-btn-outline" style="display: inline-block; padding: 10px 24px; border: 1px solid #cbd5e1; border-radius: 8px; color: #334155; text-decoration: none; font-weight: 500;">
                    <?php esc_html_e('العودة للرئيسية', 'vmp'); ?>
                </a>
            <?php endif; ?>

            <?php if ($request_id) : ?>
                <p style="margin-top: 24px; font-size: 13px; color: #94a3b8;">
                    <?php printf(esc_html__('رقم الطلب الخاص بك: #%d', 'vmp'), $request_id); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>