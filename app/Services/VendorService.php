<?php
namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\VendorRequestRepositoryInterface;
use VMP\Contracts\ProductRepositoryInterface;
use VMP\Contracts\OrderRepositoryInterface;
use VMP\DTO\VendorDTO;
use VMP\DTO\RegisterVendorDTO;
use VMP\Exceptions\ServiceException;
use VMP\Core\EventManager;
use VMP\Core\Logger;
use VMP\Events\Vendor\VendorRegistered;
use VMP\Events\Vendor\VendorApproved;
use VMP\Events\Vendor\VendorRejected;
use Exception;
use VMP\Support\Cache\Manager as CacheManager;

/**
 * Class VendorService
 *
 * Description of administrative platform component VendorService.
 *
 * @package vendor-marketplace
 */
class VendorService
{
    public function __construct(
        private VendorRepositoryInterface $vendorRepository,
        private VendorRequestRepositoryInterface $requestRepository,
        private ProductRepositoryInterface $productRepository,
        private OrderRepositoryInterface $orderRepository,
        private EventManager $eventManager,
        private Logger $logger
    ) {}

    /**
     * تسجيل بائع جديد
     * 
     * @param RegisterVendorDTO $dto بيانات التسجيل
     * @return VendorDTO
     * @throws ServiceException إذا فشل التسجيل
     */
    public function registerVendor(RegisterVendorDTO $dto): VendorDTO
    {
        $userId = 0;
        
        if (!is_user_logged_in()) {
            if (email_exists($dto->user_email)) {
                throw new ServiceException(__('هذا البريد الإلكتروني مسجّل بالفعل، يرجى تسجيل الدخول أولاً', 'vmp'));
            }
            
            $username = sanitize_user(explode('@', $dto->user_email)[0], true);
            if (empty($username)) {
                $username = 'vendor_' . wp_generate_password(6, false);
            }
            if (username_exists($username)) {
                $username .= '_' . wp_generate_password(4, false);
            }
            
            $userId = wp_create_user($username, $dto->user_pass, $dto->user_email);
            
            if (is_wp_error($userId)) {
                throw new ServiceException(__('فشل إنشاء الحساب: ', 'vmp') . $userId->get_error_message());
            }
            
            wp_update_user([
                'ID'           => $userId,
                'first_name'   => $dto->first_name,
                'last_name'    => $dto->last_name,
                'display_name' => trim("{$dto->first_name} {$dto->last_name}") ?: $dto->store_name,
            ]);
            
            wp_set_current_user($userId);
            wp_set_auth_cookie($userId, true);
        } else {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            throw new ServiceException(__('حدث خطأ في تحديد المستخدم.', 'vmp'));
        }

        // التحقق مما إذا كان المستخدم بائعاً بالفعل
        $existingVendor = $this->vendorRepository->findByUserId($userId);
        if ($existingVendor) {
            throw new ServiceException(__('لديك حساب بائع مسجّل مسبقاً', 'vmp'));
        }

        $storeSlug = sanitize_title($dto->store_slug ?: $dto->store_name);

        // التأكد من عدم تكرار الـ slug
        if ($this->vendorRepository->slugExists($storeSlug)) {
            $storeSlug = $storeSlug . '-' . substr(uniqid(), -4);
        }

        $vendorData = [
            'user_id'     => $userId,
            'store_name'  => $dto->store_name,
            'store_slug'  => $storeSlug,
            'store_phone' => $dto->store_phone,
            'store_email' => $dto->user_email,
            'status'      => 'pending',
        ];

        $vendorId = $this->vendorRepository->create($vendorData);

        // أيضًا إنشاء طلب انضمام في جدول الطلبات (للظهور في لوحة الإدارة)
        $requestData = [
            'user_id'            => $userId,
            'store_name'         => $dto->store_name,
            'store_slug'         => $storeSlug,
            'store_description'  => $dto->store_description ?? '',
            'store_address'      => $dto->store_address ?? '',
            'store_phone'        => $dto->store_phone,
            'store_email'        => $dto->user_email,
            'whatsapp_number'    => $dto->whatsapp_number ?? '',
            'store_logo'         => 0,
            'store_banner'       => 0,
            'license_file'       => 0,
            'plan_id'            => 0,
            'status'             => 'pending',
            'terms_accepted'     => true,
            'terms_accepted_at'  => current_time('mysql'),
        ];
        $this->requestRepository->create($requestData);
        if (!$vendorId) {
            $this->logger->error('فشل إنشاء البائع', ['user_id' => $userId]);
            throw new ServiceException(__('حدث خطأ أثناء التسجيل، يرجى المحاولة مرة أخرى.', 'vmp'));
        }

        // تحديث بيانات المستخدم في ووردبريس
        update_user_meta($userId, 'vmp_vendor_id', $vendorId);
        update_user_meta($userId, 'vmp_vendor_status', 'pending');

        $vendorRow = $this->vendorRepository->find($vendorId);

        // إطلاق حدث تسجيل بائع (Typed Event)
        try {
            $this->eventManager->dispatch(new VendorRegistered(
                $vendorId,
                $userId,
                $vendorRow->store_name ?? $dto->store_name,
                $vendorRow->store_email ?? $dto->user_email
            ));
            // التوافق مع الإصدارات السابقة
            $this->eventManager->trigger('vmp_vendor_registered', $vendorId, $userId);
        } catch (Exception $e) {
            $this->logger->error('فشل إطلاق حدث التسجيل: ' . $e->getMessage());
        }

        return VendorDTO::fromObject($vendorRow);
    }

    /**
     * تحديث الملف الشخصي للبائع
     * 
     * @param int $vendorId معرف البائع
     * @param array $data البيانات المراد تحديثها
     * @return VendorDTO
     * @throws Exception
     */
    public function updateProfile(int $vendorId, array $data): VendorDTO
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) {
            throw new Exception(__('البائع غير موجود', 'vmp'));
        }

        $updated = $this->vendorRepository->update($vendorId, $data);
        if (!$updated) {
            if (empty($data)) {
                return VendorDTO::fromObject($vendor);
            }

            throw new Exception(__('تعذر حفظ بيانات المتجر في قاعدة البيانات.', 'vmp'));
        }

        $freshVendor = $this->vendorRepository->find($vendorId);
        if (!$freshVendor) {
            return VendorDTO::fromObject($vendor);
        }

        // [QA 2026-08-02] مسح كاش API الخاص بهذا البائع وكل قوائم البائعين
        // حتى لا تبقى البيانات القديمة في الاستجابات بعد تحديث الملف الشخصي.
        CacheManager::deleteByPrefix('api_vendor_' . $vendorId . '_');
        CacheManager::deleteByPrefix('api_vendor_' . $vendorId);
        CacheManager::deleteByPrefix('api_vendors_');

        try {
            $this->eventManager->trigger('vmp_vendor_profile_updated', $vendorId, $vendor->user_id);
        } catch (Exception $e) {
            $this->logger->error('فشل إطلاق حدث تحديث الملف الشخصي: ' . $e->getMessage());
        }

        return VendorDTO::fromObject($freshVendor);
    }

    /**
     * الموافقة على بائع
     * 
     * @param int $vendorId معرف البائع
     * @return void
     * @throws Exception
     */
    public function approveVendor(int $vendorId): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) {
            throw new Exception(__('البائع غير موجود', 'vmp'));
        }

        if ($vendor->status === 'approved') {
            throw new Exception(__('تمت الموافقة على هذا البائع مسبقاً', 'vmp'));
        }

        if (!$this->vendorRepository->approve($vendorId)) {
            throw new Exception(__('فشل في عملية الموافقة', 'vmp'));
        }

        // إضافة صلاحية البائع للمستخدم
        $user = get_userdata($vendor->user_id);
        if ($user) {
            $user->add_role('vmp_vendor');
        }

        update_user_meta($vendor->user_id, 'vmp_vendor_status', 'approved');
        \VMP\Support\Security::auditLog('vendor_approved', [
            'vendor_id' => $vendorId,
            'user_id'   => $vendor->user_id,
        ]);

        // إطلاق حدث الموافقة على البائع (Typed + Legacy)
        $user = get_userdata($vendor->user_id);
        $this->eventManager->dispatch(new VendorApproved(
            $vendorId,
            (int) $vendor->user_id,
            $vendor->store_name,
            $vendor->store_email ?? ($user ? $user->user_email : '')
        ));
        $this->eventManager->trigger('vmp_vendor_approved', $vendorId);
    }

    /**
     * رفض بائع
     * 
     * @param int $vendorId معرف البائع
     * @param string $reason سبب الرفض
     * @return void
     * @throws Exception
     */
    public function rejectVendor(int $vendorId, string $reason = ''): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) {
            throw new Exception(__('البائع غير موجود', 'vmp'));
        }

        if ($vendor->status === 'rejected') {
            throw new Exception(__('تم رفض هذا البائع مسبقاً', 'vmp'));
        }

        if (!$this->vendorRepository->reject($vendorId, $reason)) {
            throw new Exception(__('فشل في عملية الرفض', 'vmp'));
        }
        // إزالة صلاحية البائع
        $user = get_userdata($vendor->user_id);
        if ($user) {
            $user->remove_role('vmp_vendor');
        }

        update_user_meta($vendor->user_id, 'vmp_vendor_status', 'rejected');

        \VMP\Support\Security::auditLog('vendor_rejected', [
            'vendor_id' => $vendorId,
            'reason'    => $reason,
        ]);

        // إطلاق حدث رفض البائع (Typed + Legacy)
        $user = get_userdata($vendor->user_id);
        $this->eventManager->dispatch(new VendorRejected(
            $vendorId,
            (int) $vendor->user_id,
            $vendor->store_name,
            $vendor->store_email ?? ($user ? $user->user_email : ''),
            $reason
        ));
        $this->eventManager->trigger('vmp_vendor_rejected', $vendorId, $reason);
    }

    /**
     * التحقق من توفر slug (مع استبعاد بائع المستخدم الحالي عند التعديل)
     */
    public function slugExistsForCheck(string $slug, int $excludeUserId = 0): bool
    {
        if ($excludeUserId > 0) {
            $existing = $this->vendorRepository->findByUserId($excludeUserId);
            if ($existing && ($existing->slug ?? '') === $slug) {
                return false; // نفس slug البائع الحالي — متاح
            }
        }
        return $this->vendorRepository->slugExists($slug);
    }

    /**
     * تحديث إحصائيات البائع
     * 
     * @param int $vendorId معرف البائع
     * @return void
     */
    public function updateStats(int $vendorId): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) {
            return;
        }

        $totalProducts = $this->productRepository->countByVendor($vendorId, 'approved');
        $totalOrders   = $this->orderRepository->countByVendor($vendorId, 'completed');
        $totalSales    = $this->orderRepository->getTotalSales($vendorId);

        $ratingData = $this->vendorRepository->getReviewStats($vendorId);

        $this->vendorRepository->update($vendorId, [
            'total_products' => $totalProducts,
            'total_orders'   => $totalOrders,
            'total_sales'    => $totalSales,
            'review_count'   => $ratingData['count'],
            'rating'         => $ratingData['avg_rating'],
        ]);
    }
}
