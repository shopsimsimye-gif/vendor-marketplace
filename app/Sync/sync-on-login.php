<?php
// Sync vendor meta on login to keep user_meta in sync with vmp_vendors table
add_action('wp_login', function($user_login, $user) {
    if (!function_exists('update_user_meta')) return;
    $user_id = $user->ID ?? 0;
    if (!$user_id) return;

    global $wpdb;
    try {
        $vendor_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}vmp_vendors WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        if ($vendor_id > 0) {
            update_user_meta($user_id, 'vmp_vendor_id', $vendor_id);
            $status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}vmp_vendors WHERE id = %d LIMIT 1",
                $vendor_id
            ));
            update_user_meta($user_id, 'vmp_vendor_status', $status ?: '');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[VMP][AUTH] wp_login sync: user=' . $user_id . ' vendor_id=' . $vendor_id . ' status=' . ($status ?? ''));
            }
        }
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[VMP][AUTH] wp_login sync failed: ' . $e->getMessage());
        }
    }
}, 10, 2);
