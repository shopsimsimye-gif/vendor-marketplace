<?php
namespace VMP\Jobs;

defined('ABSPATH') || exit;

use VMP\Core\Queue\JobInterface;
use VMP\Core\Logger;
use VMP\Core\Container;

/**
 * Class CleanupLogsJob
 *
 * يحذف السجلات القديمة من قاعدة البيانات للحفاظ على الأداء
 */
class CleanupLogsJob implements JobInterface
{
    public function __construct(
        private int $olderThanDays = 30
    ) {}

    /**
     * Handle functionality helper.
     *
     * @return void Output payload.
     */
    public function handle(): void
    {
        global $wpdb;

        /** @var Logger $logger */
        $logger = Container::getInstance()->make(Logger::class);

        $table    = $wpdb->prefix . 'vmp_logs';
        $cutoff   = date('Y-m-d H:i:s', strtotime("-{$this->olderThanDays} days"));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff
            )
        );

        $logger->info("تنظيف السجلات: حُذف {$deleted} سجل أقدم من {$this->olderThanDays} يوم.");
    }

    /**
     * GetPayload functionality helper.
     *
     * @return array Output payload.
     */
    public function getPayload(): array
    {
        return [
            'older_than_days' => $this->olderThanDays,
        ];
    }

    /**
     * FromPayload functionality helper.
     *
     * @param array $payload Description index.
     * @return self Output payload.
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            olderThanDays: (int) ($payload['older_than_days'] ?? 30)
        );
    }
}
