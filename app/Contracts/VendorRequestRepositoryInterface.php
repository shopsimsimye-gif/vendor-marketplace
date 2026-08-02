<?php
namespace VMP\Contracts;

defined('ABSPATH') || exit;

/**
 * واجهة مستودع طلبات انضمام البائعين
 * تحدد جميع العمليات المتعلقة بطلبات التسجيل المعلقة
 */
interface VendorRequestRepositoryInterface
{
    /**
     * إنشاء طلب انضمام جديد
     *
     * @param array $data بيانات الطلب (user_id, store_name, store_slug, ...)
     * @return int|false معرف الطلب الجديد أو false في حالة الفشل
     */
    public function create(array $data): int|false;

    /**
     * الحصول على طلب بواسطة المعرف
     *
     * @param int $id معرف الطلب
     * @return object|null كائن الطلب أو null إذا لم يوجد
     */
    public function find(int $id): ?object;

    /**
     * الحصول على طلب بواسطة معرف المستخدم
     *
     * @param int $user_id معرف المستخدم في ووردبريس
     * @return object|null كائن الطلب أو null إذا لم يوجد
     */
    public function findByUserId(int $user_id): ?object;

    /**
     * الحصول على طلب بواسطة الرابط المختصر (slug)
     *
     * @param string $slug الرابط المختصر للمتجر
     * @return object|null كائن الطلب أو null إذا لم يوجد
     */
    public function findBySlug(string $slug): ?object;

    /**
     * تحديث بيانات طلب موجود
     *
     * @param int $id معرف الطلب
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
     * الموافقة على طلب (تغيير الحالة إلى 'approved' وإنشاء بائع)
     *
     * @param int $id معرف الطلب
     * @param int $admin_id معرف المشرف الذي وافق
     * @return int|false معرف البائع الجديد أو false في حالة الفشل
     */
    public function approve(int $id, int $admin_id): int|false;

    /**
     * رفض طلب مع سبب
     *
     * @param int $id معرف الطلب
     * @param string $reason سبب الرفض
     * @param int $admin_id معرف المشرف الذي رفض
     * @return bool نجاح أو فشل العملية
     */
    public function reject(int $id, string $reason, int $admin_id): bool;

    /**
     * الحصول على قائمة الطلبات مع خيارات التصفية
     *
     * @param array $args معاملات البحث (status, limit, offset, order_by, ...)
     * @return array قائمة الطلبات
     */
    public function getAll(array $args = []): array;

    /**
     * الحصول على عدد الطلبات حسب الحالة
     *
     * @param string $status الحالة (pending, approved, rejected, ...)
     * @return int عدد الطلبات
     */
    public function getCount(string $status = ''): int;

    /**
     * الحصول على أحدث الطلبات المعلقة
     *
     * @param int $limit
     * @return array
     */
    public function getLatestPending(int $limit = 5): array;

    /**
     * حذف طلب نهائياً
     *
     * @param int $id معرف الطلب
     * @return bool نجاح أو فشل العملية
     */
    public function delete(int $id): bool;

    /**
     * البحث عن طلبات بواسطة اسم المتجر
     *
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function search(string $query, int $limit = 20): array;

    /**
     * الحصول على إحصاءات سريعة عن الطلبات (لللوحة التحكم)
     *
     * @return array
     */
    public function getQuickStats(): array;
}