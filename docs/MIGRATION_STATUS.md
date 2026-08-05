# Vendor Marketplace — حالة الترحيل (MIGRATION STATUS)

> **الغرض**: توثيق ما نُقل / ما بقي / ما حُذف / ما عاد — حتى لا ننسى أي جزء.
> **آخر تحديث**: 2026-08-05 — جلسة Architecture Audit (تنظيف نهائي).

## الرموز
- ✔ = نُقل/تم بنجاح
- ⚠ = بقي (يعمل لكن يحتاج قرار/مراجعة)
- ❌ = حُذف (Dead Code) أو مُجدول للحذف
- 🔄 = قيد الترحيل
- 💀 = عاد للوجود رغم الحذف (يحتاج إعادة حذف)

---

## VendorRegistration (وحدة تسجيل البائع)

| الجزء | الحالة | ملاحظات |
|---|---|---|
| `Modules\VendorRegistration` (المجلد كاملاً) | ✔ حي وضروري | أُعيد (2026-08-03) بعد حذف خاطئ — **الموقع لا يعمل بدونه** |
| `Controllers\RegistrationController` (القديم) | ⚠ موجود | **0 مراجع** — routes كلها تشير إلى الجديد (جولة 7)؛ يمكن حذفه لاحقاً |
| `RestVendorRegistrationController` (الجديد) | ✔ مفعّل | registerGuest/apply منقولة إليه (جولة 7) — **أُصلح `<?php` المفقود** |
| `Request DTO` | ✔ موجود | — |
| `Services` (CapabilityManager, HealthService...) | ✔ موجود | — |
| `Repositories` (WpVendorStore/WpVendorRequest) | ⚠ غير مسجلة في Container | تُستدعى بـ `new` فقط (لا تغيير) |
| `Templates` (store-view/status-dashboard) | ⚠ قوالب قديمة | تحتاج مراجعة UI |
| `Legacy Views` | ⚠ موجودة | — |
| Multi-step AJAX (step1/step2/submit) | ❌ معطلة | (جولة 3) — comment-out في CoreServiceProvider |

---

## AI (وحدة الذكاء الاصطناعي)

| الجزء | الحالة | ملاحظات |
|---|---|---|
| `Providers` | ✔ موجود | AIServiceProvider |
| `Jobs` (vmp_ai_jobs) | ✔ موجود | جدول DB موجود |
| `ProviderHealth` / `ProviderHealthScore` | ✔ موجود | فحوصات صحية |
| `Legacy Helpers` | ⚠ تحتاج مراجعة | — |
| `AiSettingsController` | ✔ مسجل | (جولة 1) |
| AJAX: vmp_admin_save_ai_settings / vmp_ai_test_connection | ✔ مسجلة | — |

---

## Policies (نظام الصلاحيات القديم)

| الجزء | الحالة | ملاحظات |
|---|---|---|
| `app/Policies/` (9 ملفات) | ❌ **حُذف نهائياً (git rm)** | 2026-08-05 — مُسجَّل في git (لن يعود مع pull). نسخة: `.qa_backups/policies-final-removal-20260805/` |
| `PolicyResolver` | ❌ حُذف نهائياً | git rm 2026-08-05 |
| `Services/AuthorizationService.php` | ❌ حُذف نهائياً | git rm 2026-08-05 |
| `Contracts/AuthorizationServiceInterface.php` | ❌ حُذف نهائياً | git rm 2026-08-05 |
| `BaseController` | ✔ نظيف | لا يحوي authorization/Policies (في app/Controllers/) |
| `Exceptions/AuthorizationException.php` | ✔ يُحتفظ به | مستخدم في AbstractRequest (حماية حقيقية) |
| `PoliciesHealthCheck` (VendorRegistration) | ✔ **شيء مختلف** | فحص terms_accepted في طلب التسجيل — **ليس** نظام الصلاحيات. لا يُلمس |

### قرار (2026-08-04) → **نُفّذ (2026-08-05)**
- السبب: لا مراجع نشطة (0 استخدام) — dead code.
- **التنفيذ**: حُذفت عبر `git rm` (11 ملفاً) — مُسجّلة في index فلن تعود مع أي pull/sync.
- النسخ الاحتياطية: `.qa_backups/policies-final-removal-20260805/` (و`policies-round-5/`).

---

## Repositories & Contracts (طبقة البيانات)

| الجزء | الحالة | ملاحظات |
|---|---|---|
| جميع Contracts Interfaces (Vendor/Product/Order/Commission/Withdrawal/Subscription/Plan/VendorRequest) | ✔ مسجلة في Container | مع Cached decorators |
| `CachedVendorRepository` / `CachedProductRepository` | ✔ مسجلة | — |
| `WithdrawalRepository::countByVendor()` | ✔ مضاف | (جولة 2) |
| `CacheManager::deleteByPrefix()` | ✔ مضاف | (جولة 4) |
| `VendorRequestRepository::approve()` — إنشاء اشتراك مجاني | ✔ مضاف | (جولة 8) |

---

## Routing (REST / AJAX)

| الجزء | الحالة | ملاحظات |
|---|---|---|
| REST `/vmp/v1` | ✔ **10 مسارات** | 2026-08-05 — register-guest/apply (VendorServiceProvider) + vendors×5 + products×2 + root. أُزيلت نقاط `/register`, `/draft`, `/store/*`, `/admin/vendor/*` الميتة |
| AJAX actions القديمة (8) | ❌ غير مسجلة | (جولة 3) |
| 3 AJAX قوالب (TemplateController) | ✔ مسجلة | (جولة 6) |
| `AjaxController` (قديم) | ⚠ @deprecated لكن **مُبقى** | لا مراجع له — إبقاؤه قرار (جولة 4) |
| `ApiServiceProvider::boot` | ✔ مُنظّف | 2026-08-05 — أُزيل `require` لـ Rest/routes.php (الوحدة)؛ أُبقي تسجيل VendorApi/ProductApi |

---

## HTTP Layer

| الجزء | الحالة | ملاحظات |
|---|---|---|
| `JsonResponse` | ❌ محذوف | (جولة 4) — 5 طرق never |
| `AbstractRequest::addError()` | ✔ مضاف | (جولة 4) |
| `RateLimitMiddleware::getClientIp()` | ✔ IP موثوق فقط | Cloudflare CIDR (جولة 4) |
| `VendorMiddleware::checkVendorStatus()` | ✔ مستخرج | (جولة 4) |
| `CreateProductRequest` / `UpdateProductRequest` | ✔ بلا add_role/add_cap | (جولة 4/6) |
| `VendorResource::custom_css` | ✔ wp_strip_all_tags | (جولة 4) |
| `GetOrderDetailsRequest::authorize()` | ✔ ملكية + إدارة | (جولة 2) |
| `OrderService::getOrderDetails()` | ✔ فحص ملكية | (جولة 2) |

---

## القوالب الأمامية (12 قالباً)

| الجزء | الحالة | ملاحظات |
|---|---|---|
| جميع القوالب | ✔ بلا SQL مباشر | (جولة 2) — تستخدم Contracts Interfaces |
| التنقل (partials/vendor-nav.php) | ✔ file_exists guard | (جولة 2) |
| vendor-orders.php | ✔ Modal بدل alert | (جولة 2) |
| vendor-store.php | ✔ wc_setup_product_data | (جولة 2) |

---

## قضايا مفتوحة (لم تُحل بعد — لا تُغلق بدون قرار)

| القضية | الحالة | التفاصيل |
|---|---|---|
| `SubscriptionPlanRepository::findBySlug('free')` | ⚠ مكسور | slug في DB هو `%d9%85%d8%ac%d8%a7%d9%86%d9%8a` (=مجاني) — لا توجد خطة slug='free'. قرار: إنشاء slug='free' أو تغيير الاستعلام إلى price=0 |
| `tests/test-plugin-logic.php` | ⚠ خطأ نحوي مسبق | `test_exceptions_message()` تنقص `}` — غير مُصلح (يحتاج قرار) |
| `vmp_vendor` role في DB | ⚠ غير موجودة كـ role | المستخدمون يملكونها لكن `get_role('vmp_vendor')` يعيد null |
| مستخدم arfat1850 | ⚠ بلا سجل vmp_vendors | TemplateController يرد «البائع غير موجود» |
| `add_cap` في AdminServiceProvider | ⚠ يكتب DB كل طلب | مقترح: نقله إلى InstallServiceProvider |
| RestVendorRegistrationController (قديم مزدوج) | ⚠ 0 مراجع | step flow غير مفعّل — يُبقى للتوثيق |

---

## سجل التعديلات
| التاريخ | البند | الحالة |
|---|---|---|
| 2026-08-04 | إنشاء الوثيقة (جلسة Architecture Audit) | — |
| 2026-08-05 | حذف Policies/AuthorizationService نهائياً (git rm) | `.qa_backups/policies-final-removal-20260805/` |
| 2026-08-05 | إزالة نقاط REST الميتة (ApiServiceProvider) — REST=10 مسارات | `.qa_backups/dead-routes-remove-20260805/` |
