<?php
namespace VMP\Contracts;

defined('ABSPATH') || exit;

/**
 * واجهة مستودع طلبات السحب
 */
interface WithdrawalRepositoryInterface
{
    /**
     * Create functionality helper.
     *
     * @param array $data Description index.
     * @return int|false Output payload.
     */
    public function create(array $data): int|false|\WP_Error;

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
     * GetByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param array $args Description index.
     * @return array Output payload.
     */
    public function getByVendor(int $vendor_id, array $args = []): array;

    /**
     * CountByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return int Output payload.
     */
    public function countByVendor(int $vendor_id): int;

    /**
     * GetPending functionality helper.
     *
     * @param int $limit Description index.
     * @return array Output payload.
     */
    public function getPending(int $limit = 100): array;

    /**
     * Approve functionality helper.
     *
     * @param int $id Description index.
     * @param int $processed_by Description index.
     * @return bool Output payload.
     */
    public function approve(int $id, int $processed_by): bool|\WP_Error;

    /**
     * Reject functionality helper.
     *
     * @param int $id Description index.
     * @param int $processed_by Description index.
     * @param string $reason Description index.
     * @return bool Output payload.
     */
    public function reject(int $id, int $processed_by, string $reason = ''): bool;
}
