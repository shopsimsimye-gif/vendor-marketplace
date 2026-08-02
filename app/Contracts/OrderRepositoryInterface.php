<?php
namespace VMP\Contracts;

defined('ABSPATH') || exit;

/**
 * واجهة مستودع الطلبات
 */
interface OrderRepositoryInterface
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
     * FindByOrderId functionality helper.
     *
     * @param int $order_id Description index.
     * @param int $vendor_id Description index.
     * @return ?object Output payload.
     */
    public function findByOrderId(int $order_id, int $vendor_id): ?object;

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
     * GetByParentOrder functionality helper.
     *
     * @param int $parent_order_id Description index.
     * @return array Output payload.
     */
    public function getByParentOrder(int $parent_order_id): array;

    /**
     * CountByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param string $status Description index.
     * @return int Output payload.
     */
    public function countByVendor(int $vendor_id, string $status = ''): int;

    /**
     * GetTotalSales functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return float Output payload.
     */
    public function getTotalSales(int $vendor_id): float;

    /**
     * GetTotalEarnings functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return float Output payload.
     */
    public function getTotalEarnings(int $vendor_id): float;

    /**
     * GetTotalSalesForAllVendors functionality helper.
     *
     * @return float Output payload.
     */
    public function getTotalSalesForAllVendors(): float;

    /**
     * GetTotalEarningsForAllVendors functionality helper.
     *
     * @return float Output payload.
     */
    public function getTotalEarningsForAllVendors(): float;

    /**
     * GetTotalEarningsByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return float Output payload.
     */
    public function getTotalEarningsByVendor(int $vendor_id): float;
}
