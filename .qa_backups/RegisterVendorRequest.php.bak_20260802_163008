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
            'user_email'     => ['required', 'email'],
            'user_pass'      => ['required', 'string', 'min:8'],
            'first_name'     => ['required', 'string', 'min:2', 'max:50'],
            'last_name'      => ['required', 'string', 'min:2', 'max:50'],
            'full_name'      => ['required', 'string', 'min:3', 'max:100'],
            
            // الخطوة 2: بيانات المتجر
            'store_name'        => ['required', 'string', 'min:3', 'max:100'],
            'store_slug'        => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9\-]+$/'],
            'store_description' => ['string', 'max:500'],
            'store_address'     => ['required', 'string', 'min:5', 'max:300'],
            'store_phone'       => ['required', 'phone'],
            'store_email'       => ['email'],
            'whatsapp_number'   => ['phone'],
            'store_logo'        => ['integer', 'min:0'],
            'store_banner'      => ['integer', 'min:0'],
            'license_file'      => ['integer', 'min:0'],
            'plan_id'           => ['integer', 'min:0'],
            
            // الخطوة 3: الشروط
            'terms_accepted'    => ['required', 'boolean'],
        ];
    }

    /**
     * أسماء الحقول المخصصة لرسائل الخطأ
     */
    protected function attributes(): array
    {
        return [
            'user_email'      => __('البريد الإلكتروني', 'vmp'),
            'user_pass'       => __('كلمة المرور', 'vmp'),
            'first_name'      => __('الاسم الأول', 'vmp'),
            'last_name'       => __('الاسم الأخير', 'vmp'),
            'full_name'       => __('الاسم الكامل', 'vmp'),
            'store_name'      => __('اسم المتجر', 'vmp'),
            'store_slug'      => __('رابط المتجر', 'vmp'),
            'store_description' => __('وصف المتجر', 'vmp'),
            'store_address'   => __('عنوان المتجر', 'vmp'),
            'store_phone'     => __('رقم الهاتف', 'vmp'),
            'store_email'     => __('بريد المتجر', 'vmp'),
            'whatsapp_number' => __('رقم واتساب', 'vmp'),
            'terms_accepted'  => __('الموافقة على الأحكام', 'vmp'),
        ];
    }

    /**
     * رسائل مخصصة
     */
    protected function messages(): array
    {
        return [
            'store_name.required'      => __('اسم المتجر مطلوب', 'vmp'),
            'store_slug.required'      => __('رابط المتجر مطلوب', 'vmp'),
            'store_slug.regex'         => __('رابط المتجر يجب أن يحتوي على حروف إنجليزية صغيرة وأرقام وشرطات فقط', 'vmp'),
            'store_address.required'   => __('عنوان المتجر مطلوب', 'vmp'),
            'store_phone.required'     => __('رقم الهاتف مطلوب', 'vmp'),
            'store_phone.phone'        => __('رقم الهاتف غير صالح', 'vmp'),
            'terms_accepted.required'  => __('يجب الموافقة على الشروط والأحكام', 'vmp'),
            'terms_accepted.boolean'   => __('يجب الموافقة على الشروط والأحكام', 'vmp'),
            'user_email.required'      => __('البريد الإلكتروني مطلوب', 'vmp'),
            'user_email.email'         => __('البريد الإلكتروني غير صالح', 'vmp'),
            'user_pass.required'       => __('كلمة المرور مطلوبة', 'vmp'),
            'user_pass.min'            => __('كلمة المرور يجب أن تكون 8 أحرف على الأقل', 'vmp'),
        ];
    }

    /**
     * تنفيذ التحققات الإضافية المعقدة (مثل فحص قاعدة البيانات)
     */
    public function validate(): bool
    {
        // 1. التحقق الأساسي بناءً على rules()
        if (!parent::validate()) {
            return false;
        }

        $errors = [];

        // 2. تحقق من أن اسم المتجر لا يحتوي على رموز غير مسموحة
        $storeName = $this->string('store_name');
        if (!preg_match('/^[\p{L}\s\-0-9]+$/u', $storeName)) {
            $errors[] = __('اسم المتجر يحتوي على أحرف غير مسموحة.', 'vmp');
        }

        // 3. تحقق من أن البريد الإلكتروني إذا كان مسجلاً مسبقاً
        $email = $this->string('user_email');
        if (email_exists($email)) {
            $errors[] = __('هذا البريد الإلكتروني مسجّل مسبقاً.', 'vmp');
        }

        // 4. التحقق من صحة الهاتف
        $phone = $this->string('store_phone');
        if (empty($phone) || !preg_match('/^\+?[0-9]{7,15}$/', preg_replace('/\s/', '', $phone))) {
            $errors[] = __('رقم الهاتف غير صالح أو مفقود.', 'vmp');
        }

        // 5. التحقق من أن الـ slug لا يحتوي إلا على أحرف مناسبة
        $slug = $this->string('store_slug') ?: sanitize_title($storeName);
        if ($slug && !preg_match('/^[a-z0-9\-]+$/', $slug)) {
            $errors[] = $this->messages()['store_slug.regex'];
        }

        // 6. التحقق من عدم تكرار الـ slug باستخدام VendorRepositoryInterface
        if ($slug) {
            /** @var VendorRepositoryInterface $vendorRepo */
            $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
            if ($vendorRepo && $vendorRepo->slugExists($slug)) {
                $errors[] = __('الرابط المختصر مستخدم مسبقاً.', 'vmp');
            }
        }

        // 7. التحقق من الأسماء (الأول والأخير)
        $firstName = $this->string('first_name');
        if ($firstName && !preg_match('/^[\p{L}\s\-]+$/u', $firstName)) {
            $errors[] = __('الاسم الأول يجب أن يحتوي على أحرف فقط.', 'vmp');
        }

        $lastName = $this->string('last_name');
        if ($lastName && !preg_match('/^[\p{L}\s\-]+$/u', $lastName)) {
            $errors[] = __('الاسم الأخير يجب أن يحتوي على أحرف فقط.', 'vmp');
        }

        // 8. تحقق من قبول الأحكام
        if (!$this->bool('terms_accepted')) {
            $errors[] = __('يجب الموافقة على الأحكام والشروط.', 'vmp');
        }

        if (!empty($errors)) {
            // إضافة الأخطاء المخصصة إلى مصفوفة الأخطاء
            $reflection = new \ReflectionClass(parent::class);
            $property = $reflection->getProperty('errors');
            $property->setAccessible(true);
            $existingErrors = $property->getValue($this);
            $property->setValue($this, array_merge($existingErrors, $errors));
            
            return false;
        }

        return true;
    }
}
