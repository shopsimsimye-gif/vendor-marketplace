<?php
namespace VMP\DTO;

defined('ABSPATH') || exit;

use VMP\DTO\BaseDTO;

/**
 * DTO لبيانات تسجيل البائع
 * يحتوي على جميع البيانات المجمعة من الخطوات الثلاث
 */
class RegisterVendorDTO extends BaseDTO
{
    // بيانات الخطوة 1: إنشاء الحساب
    public string $user_email = '';
    public string $user_pass = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $full_name = '';

    // بيانات الخطوة 2: معلومات المتجر
    public string $store_name = '';
    public string $store_slug = '';
    public string $store_description = '';
    public string $store_address = '';
    public string $store_phone = '';
    public string $store_email = '';
    public string $whatsapp_number = '';
    public int $store_logo = 0;
    public int $store_banner = 0;
    public int $license_file = 0;
    public int $plan_id = 0;

    // بيانات الخطوة 3: الشروط والأحكام
    public bool $terms_accepted = false;

    // بيانات إضافية
    public int $user_id = 0;
    public int $request_id = 0;
    public string $status = 'draft';

    /**
     * بناء DTO من مصفوفة
     */
    public static function fromArray(array $data): static
    {
        $dto = new static();
        $dto->user_email        = (string) ($data['user_email'] ?? '');
        $dto->user_pass         = (string) ($data['user_pass'] ?? '');
        $dto->first_name        = (string) ($data['first_name'] ?? '');
        $dto->last_name         = (string) ($data['last_name'] ?? '');
        $dto->full_name         = (string) ($data['full_name'] ?? '');
        $dto->store_name        = (string) ($data['store_name'] ?? '');
        $dto->store_slug        = (string) ($data['store_slug'] ?? '');
        $dto->store_description = (string) ($data['store_description'] ?? '');
        $dto->store_address     = (string) ($data['store_address'] ?? '');
        $dto->store_phone       = (string) ($data['store_phone'] ?? '');
        $dto->store_email       = (string) ($data['store_email'] ?? '');
        $dto->whatsapp_number   = (string) ($data['whatsapp_number'] ?? '');
        $dto->store_logo        = (int)    ($data['store_logo'] ?? 0);
        $dto->store_banner      = (int)    ($data['store_banner'] ?? 0);
        $dto->license_file      = (int)    ($data['license_file'] ?? 0);
        $dto->plan_id           = (int)    ($data['plan_id'] ?? 0);
        $dto->terms_accepted    = !empty($data['terms_accepted']);
        $dto->user_id           = (int)    ($data['user_id'] ?? 0);
        $dto->request_id        = (int)    ($data['request_id'] ?? 0);
        $dto->status            = (string) ($data['status'] ?? 'draft');
        return $dto;
    }

    /**
     * ملء الكائن من مصفوفة (للخطوة 1)
     */
    public function fillFromStep1(array $data): self
    {
        $this->user_email    = $data['user_email'] ?? '';
        $this->user_pass     = $data['user_pass'] ?? '';
        $this->first_name    = $data['first_name'] ?? '';
        $this->last_name     = $data['last_name'] ?? '';
        $this->full_name     = $data['full_name'] ?? '';
        return $this;
    }

    /**
     * ملء الكائن من مصفوفة (للخطوة 2)
     */
    public function fillFromStep2(array $data): self
    {
        $this->store_name        = $data['store_name'] ?? '';
        $this->store_slug        = $data['store_slug'] ?? '';
        $this->store_description = $data['store_description'] ?? '';
        $this->store_address     = $data['store_address'] ?? '';
        $this->store_phone       = $data['store_phone'] ?? '';
        $this->store_email       = $data['store_email'] ?? '';
        $this->whatsapp_number   = $data['whatsapp_number'] ?? '';
        $this->store_logo        = absint($data['store_logo'] ?? 0);
        $this->store_banner      = absint($data['store_banner'] ?? 0);
        $this->license_file      = absint($data['license_file'] ?? 0);
        $this->plan_id           = absint($data['plan_id'] ?? 0);
        return $this;
    }

    /**
     * ملء الكائن من مصفوفة (للخطوة 3)
     */
    public function fillFromStep3(array $data): self
    {
        $this->terms_accepted = !empty($data['terms_accepted']);
        return $this;
    }

    /**
     * تحويل إلى مصفوفة للحفظ في الجلسة
     */
    public function toArray(): array
    {
        return [
            // الخطوة 1
            'user_email'    => $this->user_email,
            'user_pass'     => $this->user_pass,
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'full_name'     => $this->full_name,
            
            // الخطوة 2
            'store_name'        => $this->store_name,
            'store_slug'        => $this->store_slug,
            'store_description' => $this->store_description,
            'store_address'     => $this->store_address,
            'store_phone'       => $this->store_phone,
            'store_email'       => $this->store_email,
            'whatsapp_number'   => $this->whatsapp_number,
            'store_logo'        => $this->store_logo,
            'store_banner'      => $this->store_banner,
            'license_file'      => $this->license_file,
            'plan_id'           => $this->plan_id,
            
            // الخطوة 3
            'terms_accepted'    => $this->terms_accepted,
            
            // بيانات إضافية
            'user_id'    => $this->user_id,
            'request_id' => $this->request_id,
            'status'     => $this->status,
        ];
    }

    /**
     * الحصول على البيانات للإرسال لقاعدة البيانات (إنشاء طلب)
     */
    public function getRequestData(): array
    {
        return [
            'user_id'           => $this->user_id,
            'store_name'        => $this->store_name,
            'store_slug'        => $this->store_slug,
            'store_description' => $this->store_description,
            'store_address'     => $this->store_address,
            'store_phone'       => $this->store_phone,
            'store_email'       => $this->store_email,
            'whatsapp_number'   => $this->whatsapp_number,
            'store_logo'        => $this->store_logo,
            'store_banner'      => $this->store_banner,
            'license_file'      => $this->license_file,
            'plan_id'           => $this->plan_id,
            'status'            => $this->status,
        ];
    }

    /**
     * التحقق من اكتمال البيانات للخطوة 1
     */
    public function isStep1Complete(): bool
    {
        return !empty($this->user_email) && 
               (is_user_logged_in() || !empty($this->user_pass)) &&
               !empty($this->full_name);
    }

    /**
     * التحقق من اكتمال البيانات للخطوة 2
     */
    public function isStep2Complete(): bool
    {
        return !empty($this->store_name) &&
               !empty($this->store_slug) &&
               !empty($this->store_address) &&
               !empty($this->store_phone);
    }

    /**
     * التحقق من اكتمال البيانات للخطوة 3
     */
    public function isStep3Complete(): bool
    {
        return $this->terms_accepted === true;
    }

    /**
     * التحقق من اكتمال جميع البيانات
     */
    public function isComplete(): bool
    {
        return $this->isStep1Complete() && $this->isStep2Complete() && $this->isStep3Complete();
    }
}
