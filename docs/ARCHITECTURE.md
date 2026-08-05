# Vendor Marketplace — Architecture (المرجع المعماري الرسمي)

> **الغرض**: المرجع الرسمي لبنية الإضافة (VMP). كل طبقة موثقة مع مسؤوليتها وملفاتها.
> **آخر تحديث**: 2026-08-04 — جلسة Architecture Audit.
> **القاعدة**: أي تغيير معماري يجب أن يحدّث هذا الملف أولاً.

---

## 1. نظرة عامة — سلسلة الإقلاع (Bootstrap Chain)

```
vendor-marketplace.php  (نقطة الدخول — الملف الرئيسي)
        │
        ▼
Application             (VMP\Core\Application — ينشئ Container و Kernel)
        │
        ▼
Kernel                  (VMP\Core\Kernel — يسجّل ويُقلع Service Providers)
        │
        ▼
Service Providers       (app/Providers/* — 9 مزودات)
        │
        ▼
Container               (VMP\Core\Container — DI / IoC / Singleton)
        │
        ▼
Modules                 (app/Modules/* — وحدات الأعمال)
        │
        ▼
REST ── AJAX ── Frontend ── Admin
```

---

## 2. الطبقات بالتفصيل

### 2.1 نقطة الدخول — `vendor-marketplace.php`
- **المسؤولية**: تعريف الإضافة، الثوابت، تفعيل/تعطيل، تحميل autoload، إقلاع `Application`.
- **الأحداث المسجلة**:
  - `plugins_loaded` → `new Application(__FILE__)` + `$plugin->boot()`
  - `parse_request` → حل Slug متجر البائع (VirtualStore) — يعيد كتابة query vars
  - `template_redirect` → `setup_virtual_store_page()`
  - `register_activation_hook` → Capabilities + Migration + flush rewrite
  - `register_deactivation_hook` → flush rewrite
- **ملاحظات**: الملف أصلي (لم يُعدّل في جولات الإصلاح). نسخة احتياطية في `.qa_backups/vendor-mainfile-20260803/`.

### 2.2 `Application` — `app/Core/Application.php`
- **المسؤولية**: تنسيق الإقلاع الكامل.
- **المكوّنات المسجلة في `register()`**:
  - `app` (نفسها)
  - `Container::class` (نفس الحاوية)
  - `config` (VMP\Support\Config)
  - `logger` (singleton)
  - `event_manager` (singleton)
  - `migration` (singleton)
  - `module_manager` (singleton)
  - `Kernel` → `register()`
- **ملاحظة**: ليس Singleton — يُنشأ بـ `new Application()` في vendor-marketplace.php (لا `getInstance()`).

### 2.3 `Kernel` — `app/Core/Kernel.php`
- **المسؤولية**: تسجيل وإقلاع Service Providers + تحميل الوحدات + نصوص الترجمة.
- **ترتيب Providers في `register()`**:
  1. `InstallServiceProvider`
  2. `CoreServiceProvider`
  3. `EventServiceProvider`
  4. `WooCommerceServiceProvider`
  5. `AdminServiceProvider`
  6. `VendorServiceProvider`
  7. `ApiServiceProvider`
  8. `CronServiceProvider`
- **ترتيب `boot()`**: Install → WooCommerce → Vendor (سكوت كودات) → البقية → Modules → TextDomain.
- **الوحدات المحمّلة** (`registerModules()`): vendor, product, order, commission, withdrawal, subscription, whatsapp, template, report, notification, settings, ai.

### 2.4 `Container` — `app/Core/Container.php`
- **المسؤولية**: حاوية حقن التبعيات (DI/IoC).
- **الواجهة**: `bind()`, `singleton()`, `instance()`, `make()`, `has()`, `get()`, `forget()`.
- **سلوك `make()`**:
  - instance موجودة → إرجاعها
  - binding موجود → resolve (مع singleton cache)
  - **غير مسجّل**: إذا كان class موجود → `build()` تلقائي؛ **إذا كان interface → إرجاع `null`** (⚠️ خطر صامت)
- **`build()`**: انعكاس على constructor، حقن تلقائي للأنواع غير المدمجة، `Container::class` يُحقن ذاتيًا.

### 2.5 `ModuleManager` — `app/Core/ModuleManager.php`
- **المسؤولية**: تحميل الوحدات (`load_module` / `get_module`).
- **المنطق**: يبحث عن `VMP\Modules\{X}\{X}Module` أو `VMP\Modules\{X}`، ويُشغّل `{X}ServiceProvider` (register+boot) إن وُجد، ثم `init()`.
- **الملاحظة**: `load_module` يمنع التحميل المكرر (cache في `$loaded`).

---

## 3. Service Providers (9)

| Provider | المسؤولية | ملاحظات |
|---|---|---|
| `InstallServiceProvider` | التثبيت/الترقيات | — |
| `CoreServiceProvider` | Config, Helpers, Utilities, Repositories, Services, Controllers, Routes (AJAX) | أُعيدت كتابته (جولة 1) |
| `EventServiceProvider` | ربط Events بالListeners + NotificationService | — |
| `WooCommerceServiceProvider` | تكامل WooCommerce | — |
| `AdminServiceProvider` | صلاحيات admin+shop_manager (9 vmp_* caps) | جولة 9 |
| `VendorServiceProvider` | سكوت كودات البائع + REST register-guest/apply | جولة 7 |
| `ApiServiceProvider` | REST routes (Vendor/Product API + VendorRegistration Rest) | — |
| `CronServiceProvider` | مهام cron | أُصلح (جولة 1) |
| `ServiceProvider` | الفئة الأساسية | — |

---

## 4. طبقة البيانات (Repositories & Contracts)

- **Contracts** (`app/Contracts/`): واجهات لكل مخزن — كلها مسجلة في Container مع Decorators Cached.
- **Repositories** (`app/Repositories/`): تنفيذات مباشرة (SQL عبر `$wpdb`).
- **Cached** (`app/Repositories/Cached/`): Decorator يضيف cache تلقائيًا.
- **⚠️ استثناء**: مخازن `VendorRegistration` (`WpVendorStoreRepository` / `WpVendorRequestRepository`) **غير مسجلة** في Container — تُستدعى بـ `new` مباشرة.

---

## 5. طبقة الأعمال (Services & Modules)

- **Services** (`app/Services/`): منطق الأعمال (Vendor, Product, Order, Commission, Subscription, Withdrawal, Whatsapp, Notification, VendorRegistration).
- **Modules** (`app/Modules/`): وحدات مستقلة (Vendor, VendorRegistration, AI, + وحدات Kernel).

---

## 6. طبقة HTTP (Controllers, Requests, Middleware, Responses)

- **Controllers**: `app/Controllers/` (AJAX) + `app/Http/Controllers/Api/` (REST).
- **Requests** (`app/Http/Requests/`): `AbstractRequest` — sanitize + validation + nonce (`_wpnonce` أو `nonce`).
- **Middleware** (`app/Http/Middleware/`): RateLimit (IP موثوق فقط — جولة 4), Vendor, Authentication.
- **Responses** (`app/Http/Responses/`): Success/Error/Paginated/Validation — `JsonResponse` محذوف (جولة 4).

---

## 7. الطبقة الأمامية (Frontend / Admin)

- **قوالب لوحة البائع**: `public/templates/` (12 قالبًا) — لا SQL مباشر (جولة 2).
- **Admin**: `admin/` + `app/Admin/`.
- **الأصول**: `assets/`.

---

## 8. قرارات معمارية موثقة

| التاريخ | القرار |
|---|---|
| 2026-08-02 | إزالة نظام Policies (الجولة 5) — **⚠️ عاد للوجود عبر git** (انظر MIGRATION_STATUS) |
| 2026-08-02 | تعطيل AJAX القديمة متعددة الخطوات (الجولة 3) |
| 2026-08-03 | إبقاء VendorRegistration (لا حذف) — الجولة 7: registerGuest/apply في RestVendorRegistrationController |
| 2026-08-04 | تأجيل Vendor Media Library حتى v1.1 |
| 2026-08-04 | **القاعدة الصارمة**: لا ميزات جديدة قبل اكتمال مراجعة البنية الأساسية 100% |

---

## 9. روابط ذات صلة

- `docs/REVIEW_CHECKLIST.md` — سجل تنفيذ المراجعات
- `docs/MIGRATION_STATUS.md` — ما نُقل / ما بقي / ما حُذف
- `docs/Modules.md`, `docs/Events.md`, `docs/Services.md`, `docs/Requests.md`, `docs/Testing.md`, `docs/Roadmap.md`
