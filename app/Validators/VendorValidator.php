<?php
namespace VMP\Validators;

defined('ABSPATH') || exit;

/**
 * Class VendorValidator
 *
 * Description of administrative platform component VendorValidator.
 *
 * @package vendor-marketplace
 */
class VendorValidator
{
    private \wpdb $db;

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * التحقق من بيانات البائع
     *
     * @param array $data البيانات المراد التحقق منها
     * @param int $exclude_user_id معرف المستخدم المستثنى من التحقق (للتعديل)
     * @return array مصفوفة الأخطاء (فارغة إذا كانت البيانات صحيحة)
     */
    public function validate(array $data, int $exclude_user_id = 0): array
    {
        $errors = [];

        // ── 1. اسم المتجر ──
        $store_name = trim($data['store_name'] ?? '');
        if (empty($store_name)) {
            $errors[] = __('اسم المتجر مطلوب.', 'vmp');
        } elseif (strlen($store_name) < 3) {
            $errors[] = __('اسم المتجر يجب أن يكون 3 أحرف على الأقل.', 'vmp');
        } elseif (strlen($store_name) > 100) {
            $errors[] = __('اسم المتجر لا يمكن أن يتجاوز 100 حرف.', 'vmp');
        } elseif (!preg_match('/^[\p{L}\s\-0-9]+$/u', $store_name)) {
            $errors[] = __('اسم المتجر يحتوي على أحرف غير مسموحة.', 'vmp');
        }

        // ── 2. البريد الإلكتروني ──
        $email = trim($data['user_email'] ?? '');
        if (empty($email)) {
            $errors[] = __('البريد الإلكتروني مطلوب.', 'vmp');
        } elseif (!is_email($email)) {
            $errors[] = __('البريد الإلكتروني غير صالح.', 'vmp');
        } elseif ($exclude_user_id === 0 && email_exists($email)) {
            $errors[] = __('هذا البريد الإلكتروني مسجّل مسبقاً.', 'vmp');
        } elseif ($exclude_user_id > 0) {
            $existing = get_user_by('email', $email);
            if ($existing && (int) $existing->ID !== $exclude_user_id) {
                $errors[] = __('هذا البريد الإلكتروني مستخدم من قبل حساب آخر.', 'vmp');
            }
        }

        // ── 3. الـ Slug ──
        $slug = sanitize_title($data['store_slug'] ?? $data['store_name'] ?? '');
        if (!empty($slug)) {
            if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
                $errors[] = __('الرابط المختصر (slug) يجب أن يحتوي على أحرف وأرقام وشرطات فقط.', 'vmp');
            }
            // التحقق من عدم وجود slug مكرر
            $vendor_id = (int) ($data['vendor_id'] ?? 0);
            $existing = $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) FROM {$this->db->prefix}vmp_vendors WHERE store_slug = %s AND id != %d",
                $slug,
                $vendor_id
            ));
            if ($existing > 0) {
                $errors[] = __('الرابط المختصر مستخدم مسبقاً.', 'vmp');
            }
        }

        // ── 4. رقم الهاتف ──
        $phone = preg_replace('/\s/', '', $data['phone'] ?? '');
        if (!empty($phone) && !preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
            $errors[] = __('رقم الهاتف غير صالح. يجب أن يكون 7-15 رقماً، ويمكن أن يبدأ بـ +.', 'vmp');
        }

        // ── 5. الاسم الأول ──
        $first_name = trim($data['first_name'] ?? '');
        if (!empty($first_name) && !preg_match('/^[\p{L}\s\-]+$/u', $first_name)) {
            $errors[] = __('الاسم الأول يجب أن يحتوي على أحرف فقط.', 'vmp');
        }

        // ── 6. الاسم الأخير ──
        $last_name = trim($data['last_name'] ?? '');
        if (!empty($last_name) && !preg_match('/^[\p{L}\s\-]+$/u', $last_name)) {
            $errors[] = __('الاسم الأخير يجب أن يحتوي على أحرف فقط.', 'vmp');
        }

        // ── 7. كلمة المرور (إذا كانت موجودة) ──
        if (!empty($data['user_pass']) && strlen($data['user_pass']) < 6) {
            $errors[] = __('كلمة المرور يجب أن تكون 6 أحرف على الأقل.', 'vmp');
        }

        return $errors;
    }

    /**
     * التحقق من صحة رقم الهاتف (دالة مساعدة)
     */
    public function validatePhone(string $phone): bool
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return (bool) preg_match('/^\+?[0-9]{7,15}$/', $phone);
    }

    /**
     * التحقق من صحة الاسم (أحرف ومسافات وشرطات فقط)
     */
    public function validateName(string $name): bool
    {
        return (bool) preg_match('/^[\p{L}\s\-]+$/u', $name);
    }
}