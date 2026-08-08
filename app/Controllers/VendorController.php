<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Services\VendorService;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Core\Container;
use VMP\Http\Requests\RegisterVendorRequest;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ErrorResponse;
use VMP\Http\Responses\ApiResponse;
use VMP\Exceptions\ServiceException;

/**
 * Class VendorController
 *
 * Description of administrative platform component VendorController.
 *
 * @package vendor-marketplace
 */
class VendorController extends BaseController
{
    public function __construct(
        private VendorService $vendorService
    ) {}

    /**
     * تسجيل بائع جديد
     */
    public function registerVendor(RegisterVendorRequest $request): ApiResponse
    {
        try {
            // 1. تحويل الـ Request إلى DTO
            $dto = $request->toDTO();

            // 2. معالجة العمليات عبر طبقة الخدمة
            $vendor = $this->vendorService->registerVendor($dto);

            // 3. إعداد بيانات الاستجابة
            $vendorArray = [];
            if ($vendor instanceof \VMP\DTO\VendorDTO) {
                $vendorArray = $vendor->toArray();
            } elseif (is_array($vendor)) {
                $vendorArray = $vendor;
            }

            // 4. رسالة قابلة للتعديل من إعدادات المشرف
            $settings = function_exists('get_option') ? get_option('vmp_settings', []) : [];
            $success_message = ($settings['messages']['register_success'] ?? null)
                ?: (function_exists('__') ? __('تم تقديم طلب التسجيل بنجاح، سيتم التواصل معكم في القروب لقبول الانضمام.', 'vmp') : 'تم تقديم طلب التسجيل بنجاح، سيتم التواصل معكم في القروب لقبول الانضمام.');

            // 5. إرجاع استجابة ناجحة مع redirect إلى لوحة البائع
            return new SuccessResponse(
                data: array_merge($vendorArray, ['redirect' => home_url('/vendor-dashboard/')]),
                message: $success_message
            );

        } catch (ServiceException $e) {
            $msg = $e->getMessage();

            // حالة: المستخدم لديه حساب بائع موجود مسبقاً
            if (mb_stripos($msg, 'حساب بائع') !== false || mb_stripos($msg, 'لديك حساب بائع') !== false) {
                $redirect = function_exists('home_url') ? home_url('/vendor-dashboard/') : '/vendor-dashboard/';
                return new SuccessResponse(
                    data: ['redirect' => $redirect],
                    message: (function_exists('__') ? __('لديك حساب بائع مسجّل مسبقاً — جاري تحويلك إلى لوحة البائع.', 'vmp') : 'لديك حساب بائع مسجّل مسبقاً — جاري تحويلك إلى لوحة البائع.')
                );
            }

            return new ErrorResponse(
                message: $msg,
                additionalData: ['message' => $msg],
                statusCode: 400
            );
        } catch (\Throwable $e) {
            // Unexpected errors
            error_log('[VMP][VendorController] Unexpected error during registerVendor: ' . $e->getMessage());
            return new ErrorResponse(
                message: (function_exists('__') ? __('حدث خطأ أثناء معالجة الطلب.', 'vmp') : 'حدث خطأ أثناء معالجة الطلب.'),
                additionalData: ['error' => $e->getMessage()],
                statusCode: 500
            );
        }
    }

    public function updateProfile(): ApiResponse
    {
        try {
            $nonce = function_exists('sanitize_text_field') && function_exists('wp_unslash')
                ? sanitize_text_field(wp_unslash($_POST['nonce'] ?? $_POST['_wpnonce'] ?? ''))
                : ((string) ($_POST['nonce'] ?? $_POST['_wpnonce'] ?? ''));
            $nonceValid = false;
            if (function_exists('wp_verify_nonce')) {
                $nonceValid = wp_verify_nonce($nonce, 'vmp_update_profile') || wp_verify_nonce($nonce, 'vmp_public_nonce');
            }

            if ($nonce === '' || !$nonceValid) {
                return new ErrorResponse(
                    message: __('طلب غير صالح، يرجى تحديث الصفحة.', 'vmp'),
                    additionalData: ['message' => __('طلب غير صالح، يرجى تحديث الصفحة.', 'vmp')]
                );
            }

            $userId = function_exists('get_current_user_id') ? get_current_user_id() : 0;
            if (!$userId) {
                return new ErrorResponse(
                    message: __('يجب تسجيل الدخول أولاً.', 'vmp'),
                    additionalData: ['message' => __('يجب تسجيل الدخول أولاً.', 'vmp')],
                    statusCode: 401
                );
            }

            $repository = $this->vendorRepository();
            $vendor = $repository->findByUserId($userId);
            if (!$vendor || $vendor->status !== 'approved') {
                return new ErrorResponse(
                    message: __('يجب أن تكون بائعاً معتمداً لتعديل إعدادات المتجر.', 'vmp'),
                    additionalData: ['message' => __('يجب أن تكون بائعاً معتمداً لتعديل إعدادات المتجر.', 'vmp')],
                    statusCode: 403
                );
            }

            $storeName = function_exists('sanitize_text_field') && function_exists('wp_unslash')
                ? sanitize_text_field(wp_unslash($_POST['store_name'] ?? ''))
                : ((string) ($_POST['store_name'] ?? ''));
            if ($storeName === '') {
                return new ErrorResponse(
                    message: __('اسم المتجر مطلوب.', 'vmp'),
                    additionalData: ['message' => __('اسم المتجر مطلوب.', 'vmp')]
                );
            }

            $storeSlug = function_exists('sanitize_title') && function_exists('wp_unslash')
                ? sanitize_title(wp_unslash($_POST['store_slug'] ?? $storeName))
                : sanitize_title((string) ($_POST['store_slug'] ?? $storeName));
            if ($storeSlug === '') {
                return new ErrorResponse(
                    message: __('رابط المتجر غير صالح.', 'vmp'),
                    additionalData: ['message' => __('رابط المتجر غير صالح.', 'vmp')]
                );
            }

            $existingVendor = $repository->findBySlug($storeSlug);
            if ($existingVendor && (int) $existingVendor->id !== (int) $vendor->id) {
                return new ErrorResponse(
                    message: __('رابط المتجر مستخدم مسبقاً.', 'vmp'),
                    additionalData: ['message' => __('رابط المتجر مستخدم مسبقاً.', 'vmp')]
                );
            }

            $email = function_exists('sanitize_email') && function_exists('wp_unslash')
                ? sanitize_email(wp_unslash($_POST['store_email'] ?? ''))
                : ((string) ($_POST['store_email'] ?? ''));
            if ($email === '' || (function_exists('is_email') && !is_email($email))) {
                return new ErrorResponse(
                    message: __('البريد الإلكتروني غير صالح.', 'vmp'),
                    additionalData: ['message' => __('البريد الإلكتروني غير صالح.', 'vmp')]
                );
            }

            $userData = [
                'ID' => $userId,
                'first_name' => function_exists('sanitize_text_field') && function_exists('wp_unslash')
                    ? sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''))
                    : ((string) ($_POST['first_name'] ?? '')),
                'last_name' => function_exists('sanitize_text_field') && function_exists('wp_unslash')
                    ? sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''))
                    : ((string) ($_POST['last_name'] ?? '')),
                'user_email' => $email,
            ];

            $password = function_exists('wp_unslash') ? (string) wp_unslash($_POST['password'] ?? '') : (string) ($_POST['password'] ?? '');
            if ($password !== '') {
                if (strlen($password) < 6) {
                    return new ErrorResponse(
                        message: __('كلمة المرور يجب أن تكون 6 أحرف على الأقل.', 'vmp'),
                        additionalData: ['message' => __('كلمة المرور يجب أن تكون 6 أحرف على الأقل.', 'vmp')]
                    );
                }
                $userData['user_pass'] = $password;
            }

            $updatedUser = function_exists('wp_update_user') ? wp_update_user($userData) : false;
            if ($updatedUser !== false && function_exists('is_wp_error') && is_wp_error($updatedUser)) {
                return new ErrorResponse(
                    message: $updatedUser->get_error_message(),
                    additionalData: ['message' => $updatedUser->get_error_message()]
                );
            }

            $latitude = $this->nullableDecimal($_POST['store_latitude'] ?? null);
            $longitude = $this->nullableDecimal($_POST['store_longitude'] ?? null);

            $vendorData = [
                'store_name' => $storeName,
                'store_slug' => $storeSlug,
                'store_description' => function_exists('sanitize_textarea_field') && function_exists('wp_unslash')
                    ? sanitize_textarea_field(wp_unslash($_POST['store_description'] ?? ''))
                    : ((string) ($_POST['store_description'] ?? '')),
                'store_phone' => function_exists('sanitize_text_field') && function_exists('wp_unslash')
                    ? sanitize_text_field(wp_unslash($_POST['store_phone'] ?? ''))
                    : ((string) ($_POST['store_phone'] ?? '')),
                'store_email' => $email,
                'store_address' => function_exists('sanitize_textarea_field') && function_exists('wp_unslash')
                    ? sanitize_textarea_field(wp_unslash($_POST['store_address'] ?? ''))
                    : ((string) ($_POST['store_address'] ?? '')),
                'store_latitude' => $latitude,
                'store_longitude' => $longitude,
                'social_facebook' => function_exists('esc_url_raw') && function_exists('wp_unslash')
                    ? esc_url_raw(wp_unslash($_POST['social_facebook'] ?? ''))
                    : ((string) ($_POST['social_facebook'] ?? '')),
                'social_instagram' => function_exists('esc_url_raw') && function_exists('wp_unslash')
                    ? esc_url_raw(wp_unslash($_POST['social_instagram'] ?? ''))
                    : ((string) ($_POST['social_instagram'] ?? '')),
                'social_twitter' => function_exists('esc_url_raw') && function_exists('wp_unslash')
                    ? esc_url_raw(wp_unslash($_POST['social_twitter'] ?? ''))
                    : ((string) ($_POST['social_twitter'] ?? '')),
                'social_youtube' => function_exists('esc_url_raw') && function_exists('wp_unslash')
                    ? esc_url_raw(wp_unslash($_POST['social_youtube'] ?? ''))
                    : ((string) ($_POST['social_youtube'] ?? '')),
                'store_video' => function_exists('esc_url_raw') && function_exists('wp_unslash')
                    ? esc_url_raw(wp_unslash($_POST['store_video'] ?? ''))
                    : ((string) ($_POST['store_video'] ?? '')),
                'whatsapp_number' => function_exists('sanitize_text_field') && function_exists('wp_unslash')
                    ? sanitize_text_field(wp_unslash($_POST['whatsapp_number'] ?? ''))
                    : ((string) ($_POST['whatsapp_number'] ?? '')),
                'store_logo' => $this->ownedAttachmentId($_POST['store_logo'] ?? 0, $userId),
                'store_banner' => $this->ownedAttachmentId($_POST['store_banner'] ?? 0, $userId),
            ];

            $updatedVendor = $this->vendorService->updateProfile((int) $vendor->id, $vendorData);
            $message = __('تم حفظ التعديلات بنجاح.', 'vmp');

            return new SuccessResponse(
                data: [
                    'message' => $message,
                    'store_slug' => $storeSlug,
                    'vendor' => $updatedVendor->toArray(),
                ],
                message: $message
            );
        } catch (\Throwable $e) {
            error_log('[VMP][VendorController] Profile update error: ' . $e->getMessage());

            return new ErrorResponse(
                message: __('حدث خطأ أثناء حفظ إعدادات المتجر.', 'vmp'),
                additionalData: ['message' => __('حدث خطأ أثناء حفظ إعدادات المتجر.', 'vmp')],
                statusCode: 500
            );
        }
    }

    /**
     * يتحقق من أن معرّف المرفق مملوك للمستخدم الحالي (نفس منطق Phase 3 للمنتجات).
     * - 0 تعني إزالة الصورة (مسموح دائماً).
     * - أي معرّف غير مملوك يُرفض ويُعاد 0 بدلاً من حفظ مرفق بائع آخر.
     */
    private function ownedAttachmentId(mixed $raw, int $userId): int
    {
        $attachmentId = (int) $raw;
        if ($attachmentId <= 0) {
            return 0;
        }
        if (!function_exists('get_post')) {
            return 0;
        }
        $attachment = get_post($attachmentId);
        $isOwned = $attachment && (int) $attachment->post_author === $userId;
        if ($isOwned) {
            return $attachmentId;
        }
        error_log('[VMP][VendorController] Rejected non-owned attachment ' . $attachmentId . ' for user ' . $userId);
        return 0;
    }

    private function vendorRepository(): VendorRepositoryInterface
    {
        return Container::getInstance()->make(VendorRepositoryInterface::class);
    }

    private function nullableDecimal(mixed $value): ?string
    {
        $value = trim((string) wp_unslash($value ?? ''));

        if ($value === '') {
            return null;
        }

        return is_numeric($value) ? $value : null;
    }
    /**
     * التحقق من توفر slug المتجر (AJAX) — يستخدمه vendor-profile.js
     * الواجهة ترسل: action=vmp_check_store_slug & nonce=vmp_public_nonce & slug & exclude_user_id
     */
    public function checkStoreSlug(): ApiResponse
    {
        $slug = sanitize_title($_POST['slug'] ?? '');
        $excludeUserId = absint($_POST['exclude_user_id'] ?? 0);

        if ($slug === '') {
            return new SuccessResponse(
                data: ['available' => true, 'slug' => $slug, 'message' => ''],
                message: ''
            );
        }

        $exists = $this->vendorService->slugExistsForCheck($slug, $excludeUserId);

        return new SuccessResponse(
            data: [
                'available' => !$exists,
                'slug'      => $slug,
                'message'   => $exists
                    ? __('الرابط مستخدم مسبقاً', 'vmp')
                    : __('الرابط متاح', 'vmp'),
            ],
            message: $exists ? __('الرابط مستخدم مسبقاً', 'vmp') : __('الرابط متاح', 'vmp')
        );
    }

    /**
     * منح البائع (AJAX admin — vmp_admin_approve_vendor).
     *
     * [QA 2026-08-07] أُضيفت لإصلاح المسار المعلَّق الذي كان يشير إلى method غائبة.
     * - فحص الصلاحية: vmp_manage_vendors (بائع معتمد / مدير).
     * - فحص nonce: يقبل vmp_admin_nonce أو vmp_vendor_action_{id} (كما يرسله admin/pages/vendors.php).
     *   vendor_id: من POST. إن لم يُبعث يستخدم GET vendor_id (المسار القديم).
     */
    public function adminApprove(): ApiResponse
    {
        if (!current_user_can('vmp_manage_vendors')) {
            return new ErrorResponse(message: __('غير مصرح لك بهذا الإجراء.', 'vmp'), statusCode: 403);
        }

        $vendor_id = absint($_POST['vendor_id'] ?? $_GET['vendor_id'] ?? 0);
        if (!$vendor_id) {
            return new ErrorResponse(message: __('معرّف بائع غير صالح.', 'vmp'), statusCode: 400);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        $nonceValid = wp_verify_nonce($nonce, 'vmp_admin_nonce') !== false
            || wp_verify_nonce($nonce, 'vmp_vendor_action_' . $vendor_id) !== false;
        if (!$nonceValid) {
            return new ErrorResponse(message: __('رموز أمان غير صالح.', 'vmp'), statusCode: 403);
        }

        try {
            $this->vendorService->approveVendor($vendor_id);
        } catch (ServiceException $e) {
            return new ErrorResponse(message: $e->getMessage(), statusCode: 400);
        } catch (\Exception $e) {
            return new ErrorResponse(message: $e->getMessage(), statusCode: 500);
        }

        return new SuccessResponse(
            data: ['vendor_id' => $vendor_id],
            message: __('تمت الموافقة على البائع.', 'vmp')
        );
    }

    /**
     * رفض بائع (AJAX) — vmp_admin_reject_vendor.
     *
     * [QA 2026-08-07] أُضيفت لإصلاح الـ method الغائبة التي يُخبر إليها المودال.
     * يعتمد نفس حماية adminApprove.
     */
    public function adminReject(): ApiResponse
    {
        if (!current_user_can('vmp_manage_vendors')) {
            return new ErrorResponse(message: __('غير مسموح بهذا الإجراء.', 'vmp'), statusCode: 403);
        }

        $vendor_id = absint($_POST['vendor_id'] ?? $_GET['vendor_id'] ?? 0);
        if (!$vendor_id) {
            return new ErrorResponse(message: __('معرّف بائع غير صالح.', 'vmp'), statusCode: 400);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        $nonceValid = wp_verify_nonce($nonce, 'vmp_admin_nonce') !== false
            || wp_verify_nonce($nonce, 'vmp_vendor_action_' . $vendor_id) !== false;
        if (!$nonceValid) {
            return new ErrorResponse(message: __('رمز أمان غير صالح.', 'vmp'), statusCode: 403);
        }

        $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

        try {
            $this->vendorService->rejectVendor($vendor_id, $reason);
        } catch (ServiceException $e) {
            return new ErrorResponse(message: $e->getMessage(), statusCode: 400);
        } catch (\Exception $e) {
            return new ErrorResponse(message: $e->getMessage(), statusCode: 500);
        }

        return new SuccessResponse(
            data: ['vendor_id' => $vendor_id],
            message: __('تم رفض البائع.', 'vmp')
        );
    }

}
