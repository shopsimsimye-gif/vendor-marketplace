<?php
namespace VMP\Core;

defined('ABSPATH') || exit;

/**
 * Logger — تسجيل الأخطاء والأحداث
 *
 * @package VMP\Core
 */
class Logger
{
    protected $db;
    protected string $table;

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
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message, [
                'source' => 'vmp',
                'context' => $context,
            ]);
            return;
        }

        global $wpdb;
        $wpdb->insert($this->table, [
            'level' => sanitize_text_field($level),
            'message' => $message,
            'context' => !empty($context) ? wp_json_encode($context) : null,
            'user_id' => get_current_user_id() ?: null,
            'ip_address' => $this->get_anonymized_ip(),
            'created_at' => current_time('mysql'),
        ]);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log('debug', $message, $context);
        }
    }

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

    public function clear_old(int $days = 30): int
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }

    private function get_anonymized_ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!$ip) {
            return null;
        }
        return preg_replace('/\d+$/', '0', $ip);
    }
}
