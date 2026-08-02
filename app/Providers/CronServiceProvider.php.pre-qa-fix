<?php
namespace VMP\Providers;

defined('ABSPATH') || exit;

use VMP\Core\Queue\QueueManager;
use VMP\Jobs\CheckExpiredSubscriptionsJob;
use VMP\Jobs\CleanupLogsJob;
use VMP\Jobs\GenerateStatisticsJob;

/**
 * CronServiceProvider — يسجّل جدول المهام الدورية (WP-Cron)
 *
 * الجداول المسجّلة:
 * - vmp_run_queue            : كل دقيقة — تشغيل طابور المهام
 * - vmp_check_subscriptions  : يومياً   — فحص الاشتراكات المنتهية
 * - vmp_cleanup_logs         : يومياً   — تنظيف السجلات القديمة
 */
class CronServiceProvider extends ServiceProvider
{
    /**
     * Boot functionality helper.
     *
     * @return void Output payload.
     */
    public function boot(): void
    {
        // ─── جدول مخصص كل دقيقة ─────────────────────────────────────────────
        add_filter('cron_schedules', function (array $schedules): array {
            $schedules['every_minute'] = [
                'interval' => 60,
                'display'  => __('كل دقيقة', 'vmp'),
            ];
            $schedules['every_five_minutes'] = [
                'interval' => 300,
                'display'  => __('كل 5 دقائق', 'vmp'),
            ];
            return $schedules;
        });

        // ─── تشغيل طابور المهام (كل دقيقة) ──────────────────────────────────
        add_action('vmp_run_queue', function (): void {
            /** @var QueueManager $queue */
            $queue = $this->container->make(QueueManager::class);
            $queue->run(10); // معالجة 10 وظائف في الدفعة الواحدة
        });

        // ─── فحص الاشتراكات المنتهية (يومياً) ───────────────────────────────
        add_action('vmp_check_expired_subscriptions', function (): void {
            /** @var QueueManager $queue */
            $queue = $this->container->make(QueueManager::class);
            $queue->push(CheckExpiredSubscriptionsJob::class, ['batch_size' => 100]);
        });

        // ─── تنظيف السجلات القديمة (يومياً) ─────────────────────────────────
        add_action('vmp_cleanup_logs', function (): void {
            /** @var QueueManager $queue */
            $queue = $this->container->make(QueueManager::class);
            $queue->push(CleanupLogsJob::class, ['older_than_days' => 30]);
        });

        // ─── إرسال تذكيرات الاشتراك (أسبوعياً) ──────────────────────────────
        add_action('vmp_send_subscription_reminders', function (): void {
            /** @var QueueManager $queue */
            $queue = $this->container->make(QueueManager::class);
            $queue->push(CheckExpiredSubscriptionsJob::class, ['batch_size' => 50]);
        });

        add_action('wp_ajax_vmp_run_queue', [$this, 'handleRunQueueAjax']);
        add_action('wp_ajax_nopriv_vmp_run_queue', [$this, 'handleRunQueueAjax']);

        // ─── جدولة المهام الدورية (إذا لم تكن مجدولة بالفعل) ─────────────────
        $this->scheduleCronJobs();
    }

    /**
     * تسجيل مهام Cron إذا لم تكن مجدولة بالفعل
     */
    private function scheduleCronJobs(): void
    {
        // Schedule cron events during 'init' so they are registered in all request contexts (admin-ajax, REST, CLI)
        add_action('init', function (): void {
            if (!wp_next_scheduled('vmp_run_queue')) {
                wp_schedule_event(time(), 'every_minute', 'vmp_run_queue');
            }
            if (!wp_next_scheduled('vmp_check_expired_subscriptions')) {
                wp_schedule_event(time(), 'daily', 'vmp_check_expired_subscriptions');
            }
            if (!wp_next_scheduled('vmp_cleanup_logs')) {
                wp_schedule_event(time(), 'daily', 'vmp_cleanup_logs');
            }
            if (!wp_next_scheduled('vmp_send_subscription_reminders')) {
                wp_schedule_event(time(), 'weekly', 'vmp_send_subscription_reminders');
            }
        });

        // Admin notice: warn when no background processor is available (Action Scheduler missing and WP-Cron is disabled)
        add_action('admin_notices', function (): void {
            // If Action Scheduler is available we prefer it; otherwise ensure WP-Cron is enabled.
            if (!function_exists('as_enqueue_async_action')) {
                if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON === true) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('VMP: Neither Action Scheduler is available nor WP-Cron is enabled. Background AI jobs will not be processed. Install Action Scheduler or enable WP-Cron (set DISABLE_WP_CRON to false) or configure a system cron to run wp-cron.php.', 'vmp') . '</p></div>';
                }
            }
        });
    }

    /**
     * Handle an internal AJAX trigger for running the VMP queue.
     */
    public function handleRunQueueAjax(): void
    {
        if (!check_ajax_referer('vmp_run_queue', 'nonce', false)) {
            wp_send_json_error(['message' => __('غير مصرح به.', 'vmp')], 403);
        }

        try {
            /** @var QueueManager $queue */
            $queue = $this->container->make(QueueManager::class);
            $processed = $queue->run(10);
            wp_send_json_success(['processed' => $processed]);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[VMP-AI] handleRunQueueAjax exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }
}
