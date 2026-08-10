# Vendor Marketplace — قائمة مراجعة الإضافة (REVIEW CHECKLIST)

> **الهدف**: توثيق منهجي لكل جزء من الإضافة وفق دورة العمل: قراءة → تحليل → تحديد مشاكل → اقتراح إصلاح → تنفيذ → اختبار → توثيق → إغلاق.
> **القاعدة الحاكمة (2026-08-04)**: لا تُغلق أي بند إلا بدليلين: (1) فحص container/PHP، (2) فحص HTTP/سلوك حقيقي.
> **النسخ الاحتياطية**: قبل أي تعديل → `.qa_backups/<اسم-الجولة>-<تاريخ>/`.

## الحالات
- ☐ معلق (لم تبدأ)
- 🔄 جارٍ (قيد المراجعة)
- ⚠️ ملاحظات (فيها مشاكل غير محلولة)
- ✅ مكتمل (قراءة + تحليل + إصلاح + اختبار + توثيق)

## دورة عمل كل عنصر
```
قراءة → تحليل → تحديد المشاكل → اقتراح الإصلاح → تنفيذ الإصلاح → اختبار → توثيق → إغلاق
```
لا يُعتبر أي عنصر منتهيًا إلا بعد إتمام جميع الخطوات الثماني.

---

## المرحلة 1: البنية الأساسية (Core Stabilization) — Bootstrap

### 1.1 Bootstrap — نقطة دخول واحدة
| البند | الحالة | تاريخ المراجعة | عدد المشاكل | عدد الإصلاحات | حالة الاختبارات |
|---|---|---|---|---|---|
| `vendor-marketplace.php` (الملف الرئيسي) | ☐ | — | — | — | — |
| `Application` (app/Core/Application.php) | ✅ | 2026-08-04 | 0 | 0 | PHP container: LOADED OK |
| `Kernel` (app/Core/Kernel.php) | ✅ | 2026-08-04 | 1 (كود ميت `$woocommerceActive`) | 1 (حذفه من boot()) | PHP container: LOADED OK + php -l سليم |
| `Container` (app/Core/Container.php) — null-safety لـ make() | ✅ | 2026-08-04 | 0 | 0 | PHP container: LOADED OK |
| `ModuleManager` (app/Core/ModuleManager.php) | ✅ | 2026-08-04 | 0 | 0 | PHP container: LOADED OK |
| توثيق: كل `make()` عبر القوالب تستخدم interfaces **مسجلة** فقط | ☐ | — | — | — | — |

### 1.2 Service Providers (لا تسجيل مكرر، لا Hooks في Constructors، Register ثم Boot)
| البند | الحالة | تاريخ المراجعة | عدد المشاكل | عدد الإصلاحات | حالة الاختبارات |
|---|---|---|---|---|---|
| `CoreServiceProvider` — أُعيدت كتابته (جولة 1) | ☐ | — | — | — | — |
| `AdminServiceProvider` — أُزيل self-heal الصلاحيات (2026-08-04) | ✅ | 2026-08-04 | 1 (ازدواج caps في كل request) | 1 (حذف self-heal + نقل إلى upgrade) | PHP container: admin/shop 9/9 caps، editor 0 |
| `ApiServiceProvider` — أُزيل require لـ Rest/routes.php القديمة (2026-08-05) | ✅ | 2026-08-05 | 1 (ازدواج تسجيل + نقاط ميتة) | 1 | PHP container: REST 10 مساراً |
| `CronServiceProvider` — أُصلح (جولة 1) | ☐ | — | — | — | — |
| `VendorServiceProvider` — routes → RestVendorRegistrationController (جولة 7) | ☐ | — | — | — | — |
| `EventServiceProvider` | ☐ | — | — | — | — |
| `WooCommerceServiceProvider` | ☐ | — | — | — | — |
| `InstallServiceProvider` + `Install::upgrade()` — `setup_roles()`+`create_cron_jobs()` عند ترقية فقط | ✅ | 2026-08-04 | 1 (upgrade لم يستدعِ setup_roles) | 1 (version guard + setup_roles/cron في upgrade) | محاكاة upgrade: caps تُستعاد 9/9 |

### 1.3 Modules (Services / Controllers / Repositories / DTO / Events / Validation)
| البند | الحالة | تاريخ المراجعة | عدد المشاكل | عدد الإصلاحات | حالة الاختبارات |
|---|---|---|---|---|---|
| `AI` (Modules/AI — أمن + صلاحيات + Rate Limit) | ☐ | — | — | — | — |
| `Vendor` (Modules/Vendor — VendorServiceProvider + VendorHooks) | ☐ | — | — | — | — |
| `VendorRegistration` ⚠️ **حيّ وضروري** — لا تُحذف | ☐ | — | — | — | — |
| `Policies/` — **حُذف نهائياً عبر git rm** (2026-08-05) — 9 ملفات | ✅ | 2026-08-05 | 1 (عاد بعد جولة 5) | 1 (git rm) | PHP container: classes gone, لا مراجع |
| `AuthorizationService` + `AuthorizationServiceInterface` — **حُذفا عبر git rm** (2026-08-05) | ✅ | 2026-08-05 | 1 (عادا بعد جولة 5) | 1 (git rm) | PHP container: gone, لا مراجع |
| وحدات Kernel: product/order/commission/withdrawal/subscription/whatsapp/template/report/notification/settings | ☐ | — | — | — | — |

### 1.4 Routing (REST / AJAX / Rewrite / Dashboard Nav / Frontend)
| البند | الحالة | تاريخ المراجعة | عدد المشاكل | عدد الإصلاحات | حالة الاختبارات |
|---|---|---|---|---|---|
| REST `/vmp/v1` — **10 مسارات** (register-guest/apply + vendors×5 + products×2 + root) | ✅ | 2026-08-05 | 7 نقاط ميتة أُزيلت | 1 (ApiServiceProvider) | PHP container: register-guest/apply حيّان |
| AJAX actions — لا تسجيلات قديمة | ☐ | — | — | — | — |
| مشكلة «انتقال صفحات البائع إلى الصفحة الرئيسية» — سيناريو مستخدم | ☐ | — | — | — | — |
| قوالب لوحة البائع 12 — التنقل موحّد عبر partials | ☐ | — | — | — | — |

### 1.5 Security
| البند | الحالة | تاريخ المراجعة | عدد المشاكل | عدد الإصلاحات | حالة الاختبارات |
|---|---|---|---|---|---|
| PermissionService / CapabilityManager — لا add_cap/add_role في الطلبات | ☐ | — | — | — | — |
| EncryptionService | ☐ | — | — | — | — |
| Nonce (vmp_public_nonce في كل النماذج + AJAX) | ☐ | — | — | — | — |
| Rate Limiter — IP موثوق فقط (جولة 4) | ☐ | — | — | — | — |
| Validation — sanitize في كل المدخلات | ☐ | — | — | — | — |
| Upload Security — أنواع/حجم/مسار | ☐ | — | — | — | — |

---

## المرحلة 2: مراجعة صفحات البائع (سيناريوهات استخدام، ليست ملفات فقط)
لكل صفحة: Navigation / Permissions / REST Requests / الأداء / الأخطاء / تجربة المستخدم

| الصفحة | الحالة | تاريخ المراجعة | عدد المشاكل | عدد الإصلاحات | حالة الاختبارات |
|---|---|---|---|---|---|
| Dashboard (vendor-dashboard.php) | ☐ | — | — | — | — |
| Store (vendor-store.php) | ☐ | — | — | — | — |
| Products (vendor-products.php / add / edit) | ☐ | — | — | — | — |
| Orders (vendor-orders.php — Modal + getOrderDetails) | ☐ | — | — | — | — |
| Customers | ☐ | — | — | — | — |
| Coupons | ☐ | — | — | — | — |
| Withdrawals (vendor-withdrawals.php) | ☐ | — | — | — | — |
| Analytics | ☐ | — | — | — | — |
| Subscription (vendor-subscriptions.php — ملاحظة الخطة المجانية) | ☐ | — | — | — | — |
| Settings / Profile (vendor-profile.php) | ☐ | — | — | — | — |
| Employees | ☐ | — | — | — | — |
| AI (vendor-ai-create-product.php) | ☐ | — | — | — | — |

---

## المرحلة 3: الاختبارات (بعد استقرار كل شيء)
- ☐ Unit Tests
- ☐ Integration Tests
- ☐ REST Tests
- ☐ Playwright E2E
- ☐ Accessibility
- ☐ Performance
- ☐ Security Testing

---

## المرحلة 4: الميزات الجديدة (بعد نسخة v1.0 مستقرة — القاعدة الصارمة)
- ☐ Vendor Media Library (مؤجلة — قرار 2026-08-04) → **v1.1**
- ☐ AI Studio → v1.2
- ☐ Cloud Storage → v1.1+
- ☐ Workflow Builder → v1.2+
- ☐ Notification Center → v1.1+
- ☐ Reports → v1.1+
- ☐ Marketplace Analytics → v1.2+

---

## سجل التعديلات
| التاريخ | البند | التعديل | الاختبار | النسخة الاحتياطية |
|---|---|---|---|---|
| 2026-08-04 | قرار | تأجيل Vendor Media Library | — | — |
| 2026-08-04 | وثيقة | إعادة بناء القائمة وفق خطة Architecture Audit | — | `.qa_backups/docs-audit-20260804/` |
| 2026-08-05 | تنظيف | حذف `Policies/` + `AuthorizationService` نهائياً عبر **git rm** (لم يعد يعود مع pull) | PHP container: gone + REST سليم | `.qa_backups/policies-final-removal-20260805/` |
| 2026-08-05 | تنظيف | `ApiServiceProvider` — إزالة require لـ Rest/routes.php (نقاط ميتة) | PHP container: REST 10 مساراً، لا فادح | `.qa_backups/dead-routes-remove-20260805/` |
| 2026-08-10 | مراجعة | **المرحلة C**: PSR-4 audit (323 نوعًا، 0 انتهاك) + توثيق توحيد Controllers (الانقسام مقصود) | PHP container: PSR-4 OK + REST 10 مسارًا + WP-load OK | `.qa_backups/phaseC-docs-20260810/` |
