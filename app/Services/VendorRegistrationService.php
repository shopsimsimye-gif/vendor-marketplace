<?php
namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;
use VMP\Contracts\VendorRequestRepositoryInterface;
use VMP\Repositories\VendorRepository;
use VMP\Repositories\VendorRequestRepository;
use VMP\DTO\RegisterVendorDTO;

/**
 * خدمة تسجيل البائعين متعددة الخطوات
 * تدير عملية التسجيل من البداية للنهاية مع التحقق الأمني
 */
class VendorRegistrationService
{
    private VendorRepositoryInterface $vendorRepository;
    private VendorRequestRepositoryInterface $requestRepository;

    public function __construct(
        VendorRepositoryInterface $vendorRepository = null,
        VendorRequestRepositoryInterface $requestRepository = null
    ) {
        // استخدام مستودعات حقيقية إذا لم يتم حقنها (للتوافق مع السياق القديم)
        $this->vendorRepository = $vendorRepository ?? new VendorRepository();
        $this->requestRepository = $requestRepository ?? new VendorRequestRepository();
    }

    /**
     * الحصول على الإعدادات
     */
    public function getSettings(): array
    {
        $defaults = [
            'registration_page_id'        => 0,
            'register_success_message'    => __('تم تقديم طلب انضمامك بنجاح! وهو الآن قيد المراجعة من قبل الإدارة.', 'vmp'),
            'pending_approval_message'    => __('طلبك قيد المراجعة. سيتم إعلامك بالبريد الإلكتروني عند اتخاذ قرار.', 'vmp'),
            'approval_message'            => __('تهانينا! تم قبول طلبك وأصبحت بائعاً. يمكنك الآن البدء في إضافة منتجاتك.', 'vmp'),
            'rejection_message'           => __('نعتذر، تم رفض طلبك. السبب: {reason}', 'vmp'),
            'manual_approval_enabled'     => true,
            'terms_page_url'              => '',
            'redirect_after_submit'       => '',
            'require_terms_acceptance'    => true,
            'auto_login_after_register'   => true,
            'allowed_roles_to_register'   => ['customer', 'subscriber', 'contributor', 'author', 'editor'],
        ];

        // موقع إعدادات موحّد
        $all_settings = get_option('vmp_settings', []);
        $settings = is_array($all_settings['registration'] ?? null) ? $all_settings['registration'] : [];
        return array_merge($defaults, $settings);
    }

    /**
     * الحصول على إعداد واحد
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->getSettings();
        return $settings[$key] ?? $default;
    }

    /**
     * الحصول على رابط صفحة الشروط والأحكام
     */
    public function getTermsPageUrl(): string
    {
        $url = $this->getSetting('terms_page_url', '');
        if ($url) {
            return $url;
        }
        // fallback للصفحة الافتراضية
        $pageId = $this->getSetting('registration_page_id', 0);
        return $pageId ? get_permalink($pageId) : home_url('/terms/');
    }

    /**
     * التحقق مما إذا كانت الموافقة اليدوية مفعلة
     */
    public function isManualApprovalEnabled(): bool
    {
        return (bool) $this->getSetting('manual_approval_enabled', true);
    }

    /**
     * التحقق مما إذا كان الدور مسموحاً له بالتسجيل
     */
    public function isRoleAllowedToRegister(\WP_User $user): bool
    {
        $allowedRoles = $this->getSetting('allowed_roles_to_register', ['customer', 'subscriber', 'contributor', 'author', 'editor']);
        foreach ($user->roles as $role) {
            if (in_array($role, $allowedRoles, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * التحقق من وجود طلب معلق للمستخدم
     */
    public function getPendingRequestByUser(int $userId): ?object
    {
        $request = $this->requestRepository->findByUserId($userId);
        return ($request && $request->status === 'pending') ? $request : null;
    }

    /**
     * الحصول على بائع موجود للمستخدم
     */
    public function getVendorByUser(int $userId): ?object
    {
        return $this->vendorRepository->findByUserId($userId);
    }

    /**
     * تحديث الاسم الكامل للمستخدم
     */
    public function updateUserFullName(int $userId, string $fullName): bool
    {
        $parts = explode(' ', trim($fullName), 2);
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';

        $result = wp_update_user([
            'ID'           => $userId,
            'first_name'   => sanitize_text_field($firstName),
            'last_name'    => sanitize_text_field($lastName),
            'display_name' => sanitize_text_field($fullName),
        ]);

        return !is_wp_error($result);
    }

    /**
     * التحقق من توفر slug
     */
    public function slugExists(string $slug, int $excludeUserId = 0): bool
    {
        // التحقق في جدول الطلبات
        if ($this->requestRepository->slugExists($slug)) {
            return true;
        }

        // التحقق في جدول البائعين المعتمدين
        if ($this->vendorRepository->slugExists($slug)) {
            return true;
        }

        // التحقق من معرف المستخدم المستثنى
        if ($excludeUserId > 0) {
            $existing = $this->requestRepository->findByUserId($excludeUserId);
            if ($existing && $existing->store_slug === $slug) {
                return false; // نفس المستخدم، لا يعتبر تكراراً
            }
            
            $vendor = $this->vendorRepository->findByUserId($excludeUserId);
            if ($vendor && $vendor->store_slug === $slug) {
                return false;
            }
        }

        return false;
    }

    /**
     * التحقق من توفر البريد الإلكتروني
     */
    public function emailExists(string $email, int $excludeUserId = 0): bool
    {
        // التحقق في جدول الطلبات
        if ($this->requestRepository->emailExists($email)) {
            return true;
        }

        // التحقق في جدول البائعين المعتمدين
        if ($this->vendorRepository->emailExists($email)) {
            return true;
        }

        // التحقق من معرف المستخدم المستثنى
        if ($excludeUserId > 0) {
            $existing = $this->requestRepository->findByUserId($excludeUserId);
            if ($existing && $existing->store_email === $email) {
                return false; // نفس المستخدم، لا يعتبر تكراراً
            }
            
            $vendor = $this->vendorRepository->findByUserId($excludeUserId);
            if ($vendor && $vendor->store_email === $email) {
                return false;
            }
        }

        return false;
    }

    /**
     * إنشاء طلب انضمام جديد من DTO
     */
    public function createRequestFromDTO(RegisterVendorDTO $dto): int|false
    {
        $data = $dto->getRequestData();
        return $this->createRequest($data);
    }

    /**
     * إنشاء طلب انضمام جديد
     */
    public function createRequest(array $data): int|false
    {
        // تطبيق الفلاتر
        $data = apply_filters('vmp_vendor_request_data', $data, $data['user_id'] ?? 0);

        // تعيين الحالة الأولية
        $data['status'] = $this->isManualApprovalEnabled() ? 'pending' : 'pending'; // دائماً pending في البداية

        // إنشاء الطلب
        return $this->requestRepository->create($data);
    }

    /**
     * رفع ملف وسائط (شعار، غلاف، رخصة)
     */
    public function handleMediaUpload(array $file, string $type): array|\WP_Error
    {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $allowedTypes = [
            'logo'    => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
            'banner'  => ['image/jpeg', 'image/png', 'image/webp'],
            'license' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        ];

        if (!isset($allowedTypes[$type])) {
            return new \WP_Error('invalid_type', __('نوع ملف غير مدعوم', 'vmp'));
        }

        // التحقق من نوع الملف
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes[$type], true)) {
            return new \WP_Error('invalid_mime', __('نوع الملف غير مسموح', 'vmp'));
        }

        // حدود الحجم (5 ميجابايت للصور، 10 ميجابايت للمستندات)
        $maxSize = ($type === 'license') ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return new \WP_Error('file_too_large', sprintf(__('حجم الملف يتجاوز الحد المسموح (%s)', 'vmp'), size_format($maxSize)));
        }

        // إعدادات الرفع
        $uploadOverrides = [
            'test_form' => false,
            'mimes'     => $allowedTypes[$type],
        ];

        // إضافة مرشحات للتحقق من MIME type
        add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes) use ($allowedTypes, $type) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $mime = $data['type'] ?? '';
            
            if (in_array($mime, $allowedTypes[$type], true)) {
                return [
                    'ext'             => $ext,
                    'type'            => $mime,
                    'proper_filename' => $data['proper_filename'],
                ];
            }
            return $data;
        }, 10, 4);

        $movefile = wp_handle_upload($file, $uploadOverrides);

        remove_filter('wp_check_filetype_and_ext', 'wp_check_filetype_and_ext', 10);

        if (isset($movefile['error'])) {
            return new \WP_Error('upload_failed', $movefile['error']);
        }

        // إدراج في مكتبة الوسائط
        $attachment = [
            'post_mime_type' => $movefile['type'],
            'post_title'     => sanitize_file_name(pathinfo($movefile['file'], PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'guid'           => $movefile['url'],
        ];

        $attachId = wp_insert_attachment($attachment, $movefile['file']);
        
        if (is_wp_error($attachId)) {
            return $attachId;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachData = wp_generate_attachment_metadata($attachId, $movefile['file']);
        wp_update_attachment_metadata($attachId, $attachData);

        return [
            'id'   => $attachId,
            'url'  => $movefile['url'],
            'type' => $movefile['type'],
        ];
    }

    /**
     * التحقق من صحة بيانات الخطوة 1
     */
    public function validateStep1Data(array $data): array
    {
        $errors = [];

        if (empty($data['user_email'])) {
            $errors['user_email'] = __('البريد الإلكتروني مطلوب', 'vmp');
        } elseif (!is_email($data['user_email'])) {
            $errors['user_email'] = __('بريد إلكتروني غير صالح', 'vmp');
        } elseif (email_exists($data['user_email'])) {
            $errors['user_email'] = __('هذا البريد الإلكتروني مسجّل مسبقاً', 'vmp');
        }

        if (!is_user_logged_in() && empty($data['user_pass'])) {
            $errors['user_pass'] = __('كلمة المرور مطلوبة', 'vmp');
        } elseif (!is_user_logged_in() && strlen($data['user_pass']) < 6) {
            $errors['user_pass'] = __('كلمة المرور يجب أن تكون 6 أحرف على الأقل', 'vmp');
        }

        return $errors;
    }

    /**
     * التحقق من صحة بيانات الخطوة 2
     */
    public function validateStep2Data(array $data): array
    {
        $errors = [];

        // الحقول الإلزامية
        $required = [
            'store_name'     => __('اسم المتجر مطلوب', 'vmp'),
            'store_slug'     => __('رابط المتجر مطلوب', 'vmp'),
            'store_address'  => __('عنوان المتجر مطلوب', 'vmp'),
            'store_phone'    => __('رقم الجوال مطلوب', 'vmp'),
        ];

        foreach ($required as $field => $message) {
            if (empty($data[$field])) {
                $errors[$field] = $message;
            }
        }

        // التحقق من slug
        if (!empty($data['store_slug'])) {
            $slug = sanitize_title($data['store_slug']);
            if ($slug !== $data['store_slug']) {
                $errors['store_slug'] = __('رابط المتجر يجب أن يحتوي على حروف وأرقام وشرطات فقط', 'vmp');
            }
        }

        // التحقق من الإيميل (اختياري)
        if (!empty($data['store_email']) && !is_email($data['store_email'])) {
            $errors['store_email'] = __('بريد إلكتروني للمتجر غير صالح', 'vmp');
        }

        // التحقق من الواتساب (اختياري)
        if (!empty($data['whatsapp_number']) && !preg_match('/^[\d\s\+\-\(\)]{8,}$/', $data['whatsapp_number'])) {
            $errors['whatsapp_number'] = __('رقم واتساب غير صالح', 'vmp');
        }

        return $errors;
    }

    /**
     * التحقق من صحة بيانات الإرسال النهائي
     */
    public function validateSubmitData(array $data): array
    {
        $errors = [];

        if (empty($data['terms_accepted'])) {
            $errors['terms_accepted'] = __('يجب الموافقة على الشروط والأحكام', 'vmp');
        }

        return $errors;
    }

    /**
     * تنظيف بيانات الخطوة 1
     */
    public function sanitizeStep1Data(array $data): array
    {
        return [
            'user_email'    => sanitize_email($data['user_email'] ?? ''),
            'user_pass'     => $data['user_pass'] ?? '', // لا ننظف كلمة المرور
            'first_name'    => sanitize_text_field($data['first_name'] ?? ''),
            'last_name'     => sanitize_text_field($data['last_name'] ?? ''),
            'full_name'     => sanitize_text_field($data['full_name'] ?? ''),
        ];
    }

    /**
     * تنظيف بيانات الخطوة 2
     */
    public function sanitizeStep2Data(array $data): array
    {
        return [
            'store_name'        => sanitize_text_field($data['store_name'] ?? ''),
            'store_slug'        => sanitize_title($data['store_slug'] ?? ''),
            'store_description' => sanitize_textarea_field($data['store_description'] ?? ''),
            'store_address'     => sanitize_textarea_field($data['store_address'] ?? ''),
            'store_phone'       => sanitize_text_field($data['store_phone'] ?? ''),
            'store_email'       => sanitize_email($data['store_email'] ?? ''),
            'whatsapp_number'   => sanitize_text_field($data['whatsapp_number'] ?? ''),
            'store_logo'        => absint($data['store_logo'] ?? 0),
            'store_banner'      => absint($data['store_banner'] ?? 0),
            'license_file'      => absint($data['license_file'] ?? 0),
            'plan_id'           => absint($data['plan_id'] ?? 0),
        ];
    }

    /**
     * تنظيف بيانات الإرسال
     */
    public function sanitizeSubmitData(array $data): array
    {
        return [
            'terms_accepted'    => !empty($data['terms_accepted']),
        ];
    }

    /**
     * عرض حقول الإعدادات في لوحة التحكم
     */
    public function renderSettingsFields(): void
    {
        $settings = $this->getSettings();
        ?>
        <div class="vmp-settings-section">
            <h2><?php _e('إعدادات صفحة تسجيل البائعين', 'vmp'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="vmp_registration_page_id"><?php _e('صفحة التسجيل', 'vmp'); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_dropdown_pages([
                            'name'             => 'vmp_settings[registration][registration_page_id]',
                            'selected'         => $settings['registration_page_id'],
                            'show_option_none' => __('— اختر صفحة —', 'vmp'),
                            'option_none_value'=> '0',
                        ]);
                        ?>
                        <p class="description"><?php _e('الصفحة التي تحتوي على الشورتكود [vmp_vendor_registration]', 'vmp'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="vmp_terms_page_url"><?php _e('صفحة الشروط والأحكام', 'vmp'); ?></label>
                    </th>
                    <td>
                        <input type="url" 
                               name="vmp_settings[registration][terms_page_url]" 
                               id="vmp_terms_page_url"
                               value="<?php echo esc_attr($settings['terms_page_url']); ?>" 
                               class="regular-text" />
                        <p class="description"><?php _e('رابط صفحة الشروط والأحكام (اختياري، سيتم استخدام الصفحة الافتراضية إذا ترك فارغاً)', 'vmp'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('تفعيل الموافقة اليدوية', 'vmp'); ?></th>
                    <td>
                        <input type="checkbox" 
                               name="vmp_settings[registration][manual_approval_enabled]" 
                               id="vmp_manual_approval_enabled"
                               value="1" 
                               <?php checked($settings['manual_approval_enabled'], true); ?> />
                        <label for="vmp_manual_approval_enabled"><?php _e('تفعيل المراجعة اليدوية لطلبات الانضمام', 'vmp'); ?></label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('رسالة نجاح التسجيل', 'vmp'); ?></th>
                    <td>
                        <textarea name="vmp_settings[registration][register_success_message]" 
                                  class="large-text" rows="3"><?php echo esc_textarea($settings['register_success_message']); ?></textarea>
                        <p class="description"><?php _e('الرسالة التي تظهر بعد إرسال طلب التسجيل بنجاح', 'vmp'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('رسالة قيد المراجعة', 'vmp'); ?></th>
                    <td>
                        <textarea name="vmp_settings[registration][pending_approval_message]" 
                                  class="large-text" rows="3"><?php echo esc_textarea($settings['pending_approval_message']); ?></textarea>
                        <p class="description"><?php _e('الرسالة التي تظهر للمستخدمين عند وجود طلب قيد المراجعة', 'vmp'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('رسالة القبول', 'vmp'); ?></th>
                    <td>
                        <textarea name="vmp_settings[registration][approval_message]" 
                                  class="large-text" rows="3"><?php echo esc_textarea($settings['approval_message']); ?></textarea>
                        <p class="description"><?php _e('الرسالة التي تظهر عند قبول الطلب', 'vmp'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('رسالة الرفض', 'vmp'); ?></th>
                    <td>
                        <textarea name="vmp_settings[registration][rejection_message]" 
                                  class="large-text" rows="3"><?php echo esc_textarea($settings['rejection_message']); ?></textarea>
                        <p class="description"><?php _e('الرسالة التي تظهر عند رفض الطلب. استخدم {reason} لعرض سبب الرفض', 'vmp'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('إرسال إشعار للمشرف', 'vmp'); ?></th>
                    <td>
                        <input type="checkbox" 
                               name="vmp_settings[registration][send_admin_notification]" 
                               id="vmp_send_admin_notification"
                               value="1" 
                               <?php checked($settings['send_admin_notification'] ?? true, true); ?> />
                        <label for="vmp_send_admin_notification"><?php _e('إرسال بريد إلكتروني للمشرف عند تسجيل بائع جديد', 'vmp'); ?></label>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}
