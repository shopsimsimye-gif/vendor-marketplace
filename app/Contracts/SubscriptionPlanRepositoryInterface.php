<?php
namespace VMP\Contracts;

defined('ABSPATH') || exit;

/**
 * واجهة مستودع خطط الاشتراك
 */
interface SubscriptionPlanRepositoryInterface
{
    /**
     * Find functionality helper.
     *
     * @param int $id Description index.
     * @return ?object Output payload.
     */
    public function find(int $id): ?object;

    /**
     * FindBySlug functionality helper.
     *
     * @param string $slug Description index.
     * @return ?object Output payload.
     */
    public function findBySlug(string $slug): ?object;

    /**
     * GetAll functionality helper.
     *
     * @param bool $active_only Description index.
     * @return array Output payload.
     */
    public function getAll(bool $active_only = true): array;

    /**
     * Create functionality helper.
     *
     * @param array $data Description index.
     * @return int|false Output payload.
     */
    public function create(array $data): int|false;

    /**
     * Update functionality helper.
     *
     * @param int $id Description index.
     * @param array $data Description index.
     * @return bool Output payload.
     */
    public function update(int $id, array $data): bool;

    /**
     * حذف ناعم (تعطيل الخطة)
     */
    public function delete(int $id): bool;

    /**
     * حذف نهائي من قاعدة البيانات
     */
    public function forceDelete(int $id): bool;

    /**
     * GetFeatures functionality helper.
     *
     * @param int $id Description index.
     * @return array Output payload.
     */
    public function getFeatures(int $id): array;

    /**
     * HasFeature functionality helper.
     *
     * @param int $plan_id Description index.
     * @param string $feature_key Description index.
     * @return bool Output payload.
     */
    public function hasFeature(int $plan_id, string $feature_key): bool;

    /**
     * CanAddProduct functionality helper.
     *
     * @param int $plan_id Description index.
     * @param int $current_count Description index.
     * @return bool Output payload.
     */
    public function canAddProduct(int $plan_id, int $current_count): bool;

    /**
     * GetCount functionality helper.
     *
     * @return int Output payload.
     */
    public function getCount(): int;
}
