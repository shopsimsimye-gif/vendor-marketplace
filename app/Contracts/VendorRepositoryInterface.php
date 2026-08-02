<?php
namespace VMP\Contracts;

defined('ABSPATH') || exit;

/**
 * واجهة مستودع البائعين
 * تحدد جميع العمليات المتعلقة بالبائعين في النظام
 */
interface VendorRepositoryInterface
{
    /**
     * إنشاء بائع جديد
     *
     * @param array $data بيانات البائع (user_id, store_name, store_slug, ...)
     * @return int|false معرف البائع الجديد أو false في حالة الفشل
     */
    public function create(array $data): int|false;

    /**
     * الحصول على بائع بواسطة المعرف
     *
     * @param int $id معرف البائع
     * @return object|null كائن البائع أو null إذا لم يوجد
     */
    public function find(int $id): ?object;

    /**
     * الحصول على بائع بواسطة معرف المستخدم
     *
     * @param int $user_id معرف المستخدم في ووردبريس
     * @return object|null كائن البائع أو null إذا لم يوجد
     */
    public function findByUserId(int $user_id): ?object;

    /**
     * الحصول على بائع بواسطة الرابط المختصر (slug)
     *
     * @param string $slug الرابط المختصر للمتجر
     * @return object|null كائن البائع أو null إذا لم يوجد
     */
    public function findBySlug(string $slug): ?object;

    /**
     * تحديث بيانات بائع موجود
     *
     * @param int $id معرف البائع
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح أو فشل العملية
     */
    public function update(int $id, array $data): bool;

    /**
     * التحقق من وجود رابط مختصر مكرر
     *
     * @param string $slug الرابط المختصر
     * @return bool true إذا كان موجوداً، false إذا كان متاحاً
     */
    public function slugExists(string $slug): bool;

    /**
     * الموافقة على بائع (تغيير الحالة إلى 'approved')
     *
     * @param int $id معرف البائع
     * @return bool نجاح أو فشل العملية
     */
    public function approve(int $id): bool;

    /**
     * رفض بائع مع سبب
     *
     * @param int $id معرف البائع
     * @param string $reason سبب الرفض
     * @return bool نجاح أو فشل العملية
     */
    public function reject(int $id, string $reason = ''): bool;

    /**
     * الحصول على قائمة البائعين مع خيارات التصفية
     *
     * @param array $args معاملات البحث (status, limit, offset, order_by, ...)
     * @return array قائمة البائعين
     */
    public function getAll(array $args = []): array;

    /**
     * تحديث رصيد البائع (إضافة أو خصم)
     *
     * @param int $id معرف البائع
     * @param float $amount المبلغ (موجب للإضافة، سالب للخصم)
     * @return bool نجاح أو فشل العملية
     */
    public function updateBalance(int $id, float $amount): bool;

    /**
     * الحصول على عدد البائعين حسب الحالة
     *
     * @param string $status الحالة (pending, approved, rejected, ...)
     * @return int عدد البائعين
     */
    public function getCount(string $status = ''): int;

    /**
     * الحصول على أحدث البائعين المعلقين
     *
     * @param int $limit
     * @return array
     */
    public function getLatestPending(int $limit = 5): array;

    /**
     * الحصول على البائعين النشطين (المعتمدين)
     *
     * @param int $limit
     * @return array
     */
    public function getActiveVendors(int $limit = 50): array;

    /**
     * البحث عن بائعين بواسطة اسم المتجر
     *
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function search(string $query, int $limit = 20): array;

    /**
     * الحصول على إحصاءات سريعة عن البائعين (للوحة التحكم)
     *
     * @return array
     */
    public function getQuickStats(): array;

    /**
     * الحصول على إحصاءات تقييمات البائع (العدد + المتوسط)
     *
     * @param int $vendorId معرف البائع
     * @return array{count: int, avg_rating: float}
     */
    public function getReviewStats(int $vendorId): array;

    /**
     * حذف بائع نهائياً
     *
     * @param int $id معرف البائع
     * @return bool نجاح أو فشل العملية
     */
    public function delete(int $id): bool;
}