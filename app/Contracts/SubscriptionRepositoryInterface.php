<?php
namespace VMP\Contracts;

defined('ABSPATH') || exit;

/**
 * واجهة مستودع الاشتراكات
 */
interface SubscriptionRepositoryInterface
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
     * FindActiveByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return ?object Output payload.
     */
    public function findActiveByVendor(int $vendor_id): ?object;

    /**
     * Update functionality helper.
     *
     * @param int $id Description index.
     * @param array $data Description index.
     * @return bool Output payload.
     */
    public function update(int $id, array $data): bool;

    /**
     * Cancel functionality helper.
     *
     * @param int $id Description index.
     * @return bool Output payload.
     */
    public function cancel(int $id): bool;

    /**
     * GetByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param int $limit Description index.
     * @return array Output payload.
     */
    public function getByVendor(int $vendor_id, int $limit = 10): array;

    /**
     * GetExpired functionality helper.
     *
     * @return array Output payload.
     */
    public function getExpired(): array;

    /**
     * GetExpiringSoon functionality helper.
     *
     * @param int $days Description index.
     * @return array Output payload.
     */
    public function getExpiringSoon(int $days = 7): array;

    /**
     * RequestPlanChange functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param int $new_plan_id Description index.
     * @return int|false Output payload.
     */
    public function requestPlanChange(int $vendor_id, int $new_plan_id): int|false;

    /**
     * ApprovePlanChange functionality helper.
     *
     * @param int $subscription_id Description index.
     * @return bool Output payload.
     */
    public function approvePlanChange(int $subscription_id): bool;

    /**
     * RejectPlanChange functionality helper.
     *
     * @param int $subscription_id Description index.
     * @param string $reason Description index.
     * @return bool Output payload.
     */
    public function rejectPlanChange(int $subscription_id, string $reason = ''): bool;

    /**
     * GetPendingPlanChanges functionality helper.
     *
     * @param int $limit Description index.
     * @return array Output payload.
     */
    public function getPendingPlanChanges(int $limit = 50): array;

    /**
     * GetPendingPlanChangeByVendor functionality helper.
     *
     * @param int $vendor_id Description index.
     * @return ?object Output payload.
     */
    public function getPendingPlanChangeByVendor(int $vendor_id): ?object;

    /**
     * ForceDelete functionality helper.
     *
     * @param int $id Description index.
     * @return bool Output payload.
     */
    public function forceDelete(int $id): bool;

    /**
     * CanAddProduct functionality helper.
     *
     * @param int $vendor_id Description index.
     * @param int $current_count Description index.
     * @return bool Output payload.
     */
    public function canAddProduct(int $vendor_id, int $current_count = 0): bool;
}
