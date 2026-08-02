<?php
namespace VMP\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نظام التسجيل - يدعم WC_Logger إذا كان WooCommerce موجوداً
 */
class Logger
{
    private string $table;
    private \wpdb $db;

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'vmp_logs';
    }

    /**
     * تسجيل رسالة
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // ✅ استخدام WC_Logger إذا كان WooCommerce نشطاً
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message, [
                'source'  => 'vmp',
                'context' => $context,
            ]);
            return;
        }

        // Fallback: جدول vmp_logs
        global $wpdb;
        $wpdb->insert($this->table, [
            'level'    => sanitize_text_field($level),
            'message'  => sanitize_text_field($message),
            'context'  => !empty($context) ? wp_json_encode($context) : null,
            'user_id'  => get_current_user_id() ?: null,
            'ip_address' => $this->get_anonymized_ip(),
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Error functionality helper.
     *
     * @param string $message Description index.
     * @param array $context Description index.
     * @return void Output payload.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Warning functionality helper.
     *
     * @param string $message Description index.
     * @param array $context Description index.
     * @return void Output payload.
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Info functionality helper.
     *
     * @param string $message Description index.
     * @param array $context Description index.
     * @return void Output payload.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Debug functionality helper.
     *
     * @param string $message Description index.
     * @param array $context Description index.
     * @return void Output payload.
     */
    public function debug(string $message, array $context = []): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log('debug', $message, $context);
        }
    }

    /**
     * Get Logs functionality helper.
     *
     * @param array $args Description index.
     * @return array Output payload.
     */
    public function get_logs(array $args = []): array
    {
        global $wpdb;

        $defaults = ['level' => '', 'limit' => 100, 'offset' => 0];
        $args = wp_parse_args($args, $defaults);

        $where = [];
        $params = [];

        if (!empty($args['level'])) {
            $where[] = 'level = %s';
            $params[] = $args['level'];
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM {$this->table} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Clear Old functionality helper.
     *
     * @param int $days Description index.
     * @return int Output payload.
     */
    public function clear_old(int $days = 30): int
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }

    /**
     * Get Anonymized Ip functionality helper.
     *
     * @return ?string Output payload.
     */
    private function get_anonymized_ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!$ip) {
            return null;
        }
        return preg_replace('/\d+$/', '0', $ip);
    }
}