# خطة تطوير صفحة تسجيل البائعين متعددة الخطوات

## نظرة عامة
تطوير نظام تسجيل بائعين احترافي متعدد الخطوات (Multi-Step) يتكامل مع نظام مستخدمي ووردبريس وووكومرس، مع دعم ذكي للمستخدمين المسجلين مسبقاً، ونظام موافقة إدارية كامل.

---

## المراحل والمهام

### المرحلة 1: البنية التحتية وقاعدة البيانات
- [x] التحقق من وجود جدول `vmp_vendor_requests` أو إنشاء Migration جديد
- [x] التحقق من وجود model/repository للطلبات المعلقة
- [ ] إنشاء/تحديث Model `VendorRequest` مع الحقول المطلوبة
- [ ] إنشاء Repository `VendorRequestRepository` مع العمليات المطلوبة
- [ ] إضافة الأحداث (Events): `VendorRequestSubmitted`, `VendorRequestApproved`, `VendorRequestRejected`

### المرحلة 2: الإعدادات الإدارية (Admin Settings)
- [x] التحقق من وجود إعدادات التسجيل في `vmp_settings`
- [ ] إضافة إعدادات جديدة في تبويب "عام":
  - [ ] رسالة نجاح التسجيل (`register_success_message`)
  - [ ] رسالة انتظار الموافقة (`pending_approval_message`)
  - [ ] رسالة الموافقة (`approval_message`)
  - [ ] رسالة الرفض مع سبب (`rejection_message`)
  - [ ] تفعيل/تعطيل الموافقة اليدوية (`manual_approval`)
  - [ ] رابط صفحة الشروط والأحكام (`terms_page`)
- [ ] إضافة tab جديد "التسجيل" أو تحديث tab "عام"

### المرحلة 3: منطق التسجيل (Backend - PHP)
- [ ] إنشاء Controller `VendorRegistrationController` (PSR-4)
- [ ] تنفيذ AJAX handlers:
  - [ ] `vmp_vendor_register_step1` - التحقق من المستخدم/البيانات الأساسية
  - [ ] `vmp_vendor_register_step2` - التحقق من slug المتجر وحفظ بيانات المتجر مؤقتاً
  - [ ] `vmp_check_store_slug` - AJAX للتحقق الفوري من عدم تكرار الـ slug
  - [ ] `vmp_vendor_register_submit` - الإرسال النهائي وإنشاء طلب الانضمام
  - [ ] `vmp_upload_media` - رفع الوسائط (شعار، غلاف، رخصة)
- [ ] تنفيذ Hooks & Filters:
  - `vmp_before_vendor_request_create`
  - `vmp_after_vendor_request_create`
  - `vmp_vendor_request_status_changed`
  - `vmp_vendor_registration_fields`
  - `vmp_vendor_registration_validation`

### المرحلة 4: الواجهة الأمامية (Frontend)
- [ ] إنشاء/تحديث template `public/templates/vendor-register.php`
- [ ] إنشاء JS module `public/js/vendor-registration.js` (ES6 modules)
- [ ] إنشاء CSS `public/css/vendor-registration.css`
- [ ] تنفيذ Multi-step form UI:
  - الخطوة 1: البيانات الأساسية (مخفية للمستخدمين المسجلين)
  - الخطوة 2: بيانات المتجر + رفع الوسائط
  - الخطوة 3: الشروط والأحكام + الإرسال
- [ ] JavaScript validation (client-side)
- [ ] AJAX slug checking مع debounce
- [ ] Media uploader integration (WP Media Library)
- [ ] Progress indicator مع animations

### المرحلة 5: لوحة المشرف - إدارة طلبات الانضمام
- [ ] إضافة صفحة `admin/pages/vendor-requests.php`
- [ ] قائمة الطلبات مع فلاتر (pending, approved, rejected)
- [ ] عرض تفاصيل الطلب
- [ ] إجراءات: موافقة، رفض (مع سبب)، حذف
- [ ] إشروع

### المرحلة 6: الإشعارات والرسائل
- [ ] تنفيذ نظام الإشعارات للبائعين
- [ ] رسائل نجاح/انتظار/موافقة/رفض قابلة للتخصيص
- [ ] إرسال إيميل للمشرف عند طلب جديد
- [ ] إرسال إيميل للبائع عند تغيير الحالة

### المرحلة 7: الأمان والتحقق
- [ ] Nonce verification في جميع نقاط AJAX
- [ ] Sanitization كاملة: `sanitize_text_field`, `sanitize_email`, `esc_url_raw`, `sanitize_textarea_field`, `wp_kses_post`
- [ ] Validation للخانات الإجبارية مع رسائل خطأ واضحة
- [ ] Escaping لجميع المخرجات: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`
- [ ] حماية من إنشاء حسابات مكررة (`email_exists()`)
- [ ] التحقق من الصلاحيات (`current_user_can`)

### المرحلة 8: التوافق والاختبار
- [ ] اختبار التوافق مع WooCommerce
- [ ] اختبار مع مستخدمين مسجلين وغير مسجلين
- [ ] اختبار Hooks & Filters
- [ ] الالتزام بـ WordPress Coding Standards (WPCS)
- [ ] الالتزام بـ PSR-4 مع Autoloading
- [ ] توثيق الكود (PHPDoc)

---

## الهيكلية المقترحة للملفات الجديدة/المحدثة

### PHP (Backend)
```
app/
├── Contracts/
│   └── VendorRequestRepositoryInterface.php
├── Models/
│   └── VendorRequest.php
├── Repositories/
│   └── VendorRequestRepository.php
├── Controllers/
│   └── VendorRegistrationController.php
├── Events/
│   ├── Vendor/
│   │   ├── VendorRequestSubmitted.php
│   │   ├── VendorRequestApproved.php
│   │   └── VendorRequestRejected.php
├── Migrations/
│   └── create_vendor_requests_table.php
└── Providers/
    └── VendorRegistrationServiceProvider.php (or extend VendorServiceProvider)
```

### Admin
```
admin/
├── pages/
│   └── vendor-requests.php
└── assets/
    ├── css/
    │   └── vendor-requests.css
    └── js/
        └── vendor-requests.js
```

### Public (Frontend)
```
public/
├── templates/
│   └── vendor-register.php
├── css/
│   └── vendor-registration.css
└── js/
    └── vendor-registration.js
```

### Database
```
Table: wp_vmp_vendor_requests
- id (BIGINT, PK, AI)
- user_id (BIGINT, FK to wp_users)
- store_name (VARCHAR)
- store_slug (VARCHAR, UNIQUE)
- store_description (TEXT)
- store_address (TEXT)
- store_phone (VARCHAR)
- store_email (VARCHAR)
- whatsapp_number (VARCHAR)
- store_logo_id (BIGINT)
- store_banner_id (BIGINT)
- license_file_id (BIGINT)
- status (ENUM: pending, approved, rejected)
- admin_notes (TEXT)
- plan_id (BIGINT)
- created_at (DATETIME)
- updated_at (DATETIME)
- reviewed_at (DATETIME)
- reviewed_by (BIGINT)
```

---

## Hooks & Filters المقترحة

### Actions
```php
// قبل إنشاء طلب الانضمام
do_action('vmp_before_vendor_request_create', array $request_data, int $user_id);

// بعد إنشاء طلب الانضمام
do_action('vmp_after_vendor_request_create', int $request_id, array $request_data, int $user_id);

// عند تغيير حالة الطلب
do_action('vmp_vendor_request_status_changed', int $request_id, string $old_status, string $new_status, int $admin_id);

// عند الموافقة
do_action('vmp_vendor_request_approved', int $request_id, int $vendor_id);

// عند الرفض
do_action('vmp_vendor_request_rejected', int $request_id, string $reason);
```

### Filters
```php
// تخصيص حقول التسجيل
apply_filters('vmp_vendor_registration_fields', array $fields, int $step);

// تخصيص التحقق
apply_filters('vmp_vendor_registration_validation', array $errors, array $data, int $step);

// تخصيص رسائل النجاح/الخطأ
apply_filters('vmp_vendor_register_success_message', string $message, int $request_id);
apply_filters('vmp_vendor_register_error_message', string $message, array $errors);

// تخصيص بيانات الطلب قبل الحفظ
apply_filters('vmp_vendor_request_data', array $data, int $user_id);
```

---

## معايير الجودة
- ✅ WordPress Coding Standards (WPCS)
- ✅ PSR-4 Autoloading
- ✅ PHP 7.4+ compatibility
- ✅ WooCommerce 6.0+ compatibility
- ✅ أمان: Nonce, Sanitization, Validation, Escaping
- ✅ Hooks & Filters للتخصيص الكامل
- ✅ AJAX-driven UX
- ✅ RTL support
- ✅ Translation ready (text domain: vmp)