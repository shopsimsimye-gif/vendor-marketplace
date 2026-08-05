<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\DTO\RegisterVendorDTO;
use VMP\Core\Container;
use VMP\Contracts\VendorRepositoryInterface;

/**
 * طلب تسجيل البائع - التحقق من صحة البيانات
 */
class RegisterVendorRequest extends AbstractRequest
{
    /**
     * تحويل بيانات الطلب إلى DTO
     */
    public function toDTO(): RegisterVendorDTO
    {
        return RegisterVendorDTO::fromArray($this->validated());
    }

    /**
     * تعريف القواعد الأساسية
     */
    protected function rules(): array
    {
        return [
            // الخطوة 1: بيانات الحساب
            'user_email'      => ['required', 'email'],
            'user_pass'       => ['required', 'string', 'min:8'],
            'first_name'      => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}\s\-]+$/u'],
            'last_name'       => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}\s\-]+$/u'],
            'full_name'       => ['required', 'string', 'min:3', 'max:100'],
            
            // الخطوة 2: بيانات المتجر
            'store_name'      => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\p{L}\s\-0-9]+$/u'],
            'store_slug'      => ['nullable', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9\-]+$/'],
            'store_description' => ['nullable', 'string', 'max:500'],
            'store_address'   => ['required', 'string', 'min:5', 'max:300'],
            'store_phone'     => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'store_email'     => ['nullable', 'email'],
            'whatsapp_number' => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'store_logo'      => ['nullable', 'integer', 'min:1'],
            'store_banner'    => ['nullable', 'integer', 'min:1'],
            'license_file'    => ['nullable', 'integer', 'min:1'],
            'plan_id'         => ['nullable', 'integer', 'min:1'],
            
            // الخطوة 3: الشروط
            'terms_accepted'  => ['required', 'boolean', 'accepted'],
        ];
    }

    /**
     * أسماء الحقول المخصصة لرسائل الخطأ
     */
    protected function attributes(): array
    {
        return [
            'user_email'        => __('البريد الإلكتروني', 'vmp'),
            'user_pass'         => __('كلمة المرور', 'vmp'),
            'first_name'        => __('الاسم الأول', 'vmp'),
            'last_name'         => __('الاسم الأخير', 'vmp'),
            'full_name'         => __('الاسم الكامل', 'vmp'),
            'store_name'        => __('اسم المتجر', 'vmp'),
            'store_slug'        => __('رابط المتجر', 'vmp'),
            'store_description' => __('وصف المتجر', 'vmp'),
            'store_address'     => __('عنوان المتجر', 'vmp'),
            'store_phone'       => __('رقم الهاتف', 'vmp'),
            'store_email'       => __('بريد المتجر', 'vmp'),
            'whatsapp_number'   => __('رقم واتساب', 'vmp'),
            'terms_accepted'    => __('الموافقة على الأحكام', 'vmp'),
        ];
    }

    /**
     * رسائل مخصصة
     */
    protected function messages(): array
    {
        return [
            'store_name.required'      => __('اسم المتجر مطلوب', 'vmp'),
            'store_name.regex'         => __('اسم المتجر يحتوي على أحرف غير مسموحة.', 'vmp'),
            'store_slug.regex'         => __('رابط المتجر يجب أن يحتوي على حروف إنجليزية صغيرة وأرقام وشرطات فقط', 'vmp'),
            'store_address.required'   => __('عنوان المتجر مطلوب', 'vmp'),
            'store_phone.required'     => __('رقم الهاتف مطلوب', 'vmp'),
            'store_phone.regex'        => __('رقم الهاتف غير صالح', 'vmp'),
            'terms_accepted.required'  => __('يجب الموافقة على الشروط والأحكام', 'vmp'),
            'terms_accepted.accepted'  => __('يجب الموافقة على الشروط والأحكام', 'vmp'),
            'user_email.required'      => __('البريد الإلكتروني مطلوب', 'vmp'),
            'user_email.email'         => __('البريد الإلكتروني غير صالح', 'vmp'),
            'user_pass.required'       => __('كلمة المرور مطلوبة', 'vmp'),
            'user_pass.min'            => __('كلمة المرور يجب أن تكون 8 أحرف على الأقل', 'vmp'),
            'first_name.regex'         => __('الاسم الأول يجب أن يحتوي على أحرف فقط.', 'vmp'),
            'last_name.regex'          => __('الاسم الأخير يجب أن يحتوي على أحرف فقط.', 'vmp'),
        ];
    }

    /**
     * تنفيذ التحققات الإضافية المعقدة (DB checks)
     */
    public function validate(): bool
    {
        if (!parent::validate()) {
            return false;
        }

        $errors = [];

        // 1. التحقق من عدم تكرار البريد الإلكتروني
        $email = $this->input('user_email');
        if ($email && email_exists($email)) {
            $errors[] = __('هذا البريد الإلكتروني مسجّل مسبقاً.', 'vmp');
        }

        // 2. توليد slug تلقائياً إذا لم يُرسل
        $slug = $this->input('store_slug');
        if (empty($slug)) {
            $slug = sanitize_title($this->input('store_name', ''));
        }

        // 3. التحقق من عدم تكرار الـ slug
        if ($slug) {
            /** @var VendorRepositoryInterface $vendorRepo */
            $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
            if ($vendorRepo && $vendorRepo->slugExists($slug)) {
                $errors[] = __('الرابط المختصر مستخدم مسبقاً.', 'vmp');
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addError($error);
            }
            return false;
        }

        return true;
    }

    /**
     * Validated functionality helper.
     *
     * @return array Output payload.
     */
    public function validated(): array
    {
        $data = parent::validated();

        if (empty($data['store_slug']) && !empty($data['store_name'])) {
            $data['store_slug'] = sanitize_title($data['store_name']);
        }

        return $data;
    }
}
