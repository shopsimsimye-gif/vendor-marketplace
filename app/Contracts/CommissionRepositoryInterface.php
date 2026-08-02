<?php
namespace VMP\Contracts;

defined('ABSPATH') || exit;

/**
 * واجهة مستودع العمولات
 */
interface CommissionRepositoryInterface
{
    /**
     * Create functionality helper.
     *
     * @param array $data Description index.
     * @return int|false Output payload.
     */
    public function create(array $data): int|false;

    /**
     * Find functionality helper.
     *
     * @param int $id Description index.
     * @return ?object Output payload.
     */
    public function find(int $id): ?object;

    /**
     * Update functionality helper.
     *
     * @param int $id Description index.
     * @param array $data Description index.
     * @return bool Output payload.
     */
    public function update(int $id, array $data): bool;

    /**
     * MarkAsPaid functionality helper.
     *
     * @param int $id Description index.
     * @return bool Output payload.
     */
    public function markAsPaid(int $id): bool;

    /**
     * MarkBulkAsPaid functionality helper.
     *
     * @param array $ids Description index.
     * @return int Output payload.
     */
    public function markBulkAsPaid(array $ids): int;

    /**
     * GetByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param array $args Description index.
     * @return array Output payload.
     */
    public function getByVendor(int $vendor_id, array $args = []): array;

    /**
     * GetAllPending functionality helper.
     *
     * @param int $limit Description index.
     * @return array Output payload.
     */
    public function getAllPending(int $limit = 100): array;

    /**
     * GetAdminStats functionality helper.
     *
     * @return array Output payload.
     */
    public function getAdminStats(): array;

    /**
     * GetTotalCommissions functionality helper.
     *
     * @param string $status Description index.
     * @return float Output payload.
     */
    public function getTotalCommissions(string $status = ''): float;

    /**
     * GetTotalByVendorAndPeriod functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param string $date_from Description index.
     * @param string $date_to Description index.
     * @return array Output payload.
     */
    public function getTotalByVendorAndPeriod(int $vendor_id, string $date_from, string $date_to): array;

    /**
     * GetMonthlyStats functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param int $months Description index.
     * @return array Output payload.
     */
    public function getMonthlyStats(int $vendor_id, int $months = 6): array;
}
