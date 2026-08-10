<?php
namespace VMP\Modules\AI\Repositories;

defined('ABSPATH') || exit;

/**
 * Class AIUsageLedgerRepository
 *
 * Persistent usage ledger for AI requests per vendor.
 *
 * @package vendor-marketplace
 */
class AIUsageLedgerRepository
{
    private \wpdb $wpdb;
    private string $table;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'vmp_ai_usage_ledger';
    }

    /**
     * Record an AI usage entry.
     *
     * @param array{
     *     vendor_id: int,
     *     job_id?: string,
     *     provider: string,
     *     capability: string,
     *     input_tokens: int,
     *     output_tokens: int,
     *     images: int,
     *     searches: int,
     *     cost: float,
     *     latency_ms: int,
     *     metadata?: array
     * } $data
     * @return int Insert ID
     */
    public function record(array $data): int
    {
        $vendorId = absint($data['vendor_id'] ?? 0);
        if ($vendorId <= 0) {
            return 0;
        }

        $insertData = [
            'vendor_id'      => $vendorId,
            'job_id'         => $data['job_id'] ?? '',
            'provider'       => $data['provider'] ?? '',
            'capability'     => $data['capability'] ?? '',
            'input_tokens'   => max(0, (int) ($data['input_tokens'] ?? 0)),
            'output_tokens'  => max(0, (int) ($data['output_tokens'] ?? 0)),
            'total_tokens'   => max(0, (int) (($data['input_tokens'] ?? 0) + ($data['output_tokens'] ?? 0))),
            'images'         => max(0, (int) ($data['images'] ?? 0)),
            'searches'       => max(0, (int) ($data['searches'] ?? 0)),
            'cost'           => max(0.0, (float) ($data['cost'] ?? 0.0)),
            'latency_ms'     => max(0, (int) ($data['latency_ms'] ?? 0)),
            'metadata'       => isset($data['metadata']) ? json_encode($data['metadata'], JSON_THROW_ON_ERROR) : null,
        ];

        $format = ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%f', '%d', '%s'];
        $this->wpdb->insert($this->table, $insertData, $format);
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Get total cost for a vendor in the current month.
     */
    public function getMonthlyCost(int $vendorId): float
    {
        $startOfMonth = date('Y-m-01 00:00:00');
        $endOfMonth   = date('Y-m-t 23:59:59');

        $sum = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(cost) FROM {$this->table} WHERE vendor_id = %d AND created_at BETWEEN %s AND %s",
            $vendorId, $startOfMonth, $endOfMonth
        ));

        return (float) ($sum ?? 0.0);
    }

    /**
     * Get total request count for a vendor in the current month.
     */
    public function getMonthlyRequestCount(int $vendorId): int
    {
        $startOfMonth = date('Y-m-01 00:00:00');
        $endOfMonth   = date('Y-m-t 23:59:59');

        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE vendor_id = %d AND created_at BETWEEN %s AND %s",
            $vendorId, $startOfMonth, $endOfMonth
        ));

        return (int) ($count ?? 0);
    }

    /**
     * Get usage summary for a vendor in a date range.
     */
    public function getUsageSummary(int $vendorId, string $startDate, string $endDate): array
    {
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT 
                SUM(cost) as total_cost,
                COUNT(*) as total_requests,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(total_tokens) as total_tokens,
                SUM(images) as total_images,
                SUM(searches) as total_searches,
                AVG(latency_ms) as avg_latency_ms
            FROM {$this->table}
            WHERE vendor_id = %d AND created_at BETWEEN %s AND %s",
            $vendorId, $startDate, $endDate
        ), ARRAY_A);

        return $results[0] ?? [
            'total_cost' => 0.0,
            'total_requests' => 0,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'total_tokens' => 0,
            'total_images' => 0,
            'total_searches' => 0,
            'avg_latency_ms' => 0,
        ];
    }
}
