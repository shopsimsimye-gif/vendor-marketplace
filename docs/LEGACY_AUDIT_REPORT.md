# Legacy Audit Report — vendor-marketplace

> ملاحظة: هذا تقرير قرارٍ (Decision Document) لا تقرير تغييرات. يُحدَّث مع كل مرحلة تنظيف.
> آخر تحديث: 2026-08-05 (المرحلة 1A — بعد إزالة 46 ajax_* + حذف RestAPI.php + وسم Vendor.php بـ @deprecated)
> طريقة الإثبات: قراءة فعلية لملفات `app/Modules/*.php` + قائمة Kernel + RouteRegistry + فحص المراجع الكامل (`grep`) — لا استنتاج.

## التصنيف العام (Status Matrix)

| الملف | التصنيف | المنطق الحي الفعلي | الكود الميت | سبب الإبقاء | القرار (مرحلي) |
|---|---|---|---|---|---|---|
| `Notification.php` | **Active** | Event/Eventمعرف (Event listener × 7 عبر EventBus) | لا شيء | قناة Events خالصة | لا شيء |
| `Order.php` | Active | WooCommerce hooks (split/ completion) | 3× ajax | hooks حيٌاء | حذف ajax_* |
| `Commission.php` | Active | business Logic (calculate) | 5× ajax_* | business | حذف ajax_* |
| `Subscription.php` | Active | business + hooks | 13× ajax_* | business | حذف ajax_* |
| `Template.php` | Active | getters + CSS inject + enqueue | 3× ajax_* | Controller يستخدمه | حذف ajax_* |
| `Whatsapp.php` | Active | front hooks (render) | 7× ajax_* | front hooks | حذف ajax_* |
| `Settings.php` | Active | getSetting/setSetting + notices (Notification uses) | 0 | medium | لا شيء |
| `Product.php` | 🔍 Transitional | ??? (كلاهما ajax) | 5× ajax_* | —تدقيق ثم إعادة التقييم | حذف ajax_* أولًا ثم إعادة التقييم |
| `Withdrawal.php` | Transitional⸺? | كلها ajax | 3× ajax_* | — | حذف ajax_* ثم إعادة التقييم |
| `Report.php` | Legacy (بقايا) | كلها ajax | 6× ajax_* | — | حذف ajax_* ثم تؤكد على حذف |
| `RestAPI.php` | Dead | — | الكل | لا مراجع بلا Internal | — |
| `Vendor.php` | Legacy/BC | alias | — | BC توافق | `@deprecated` (لا حذف الآن) |
| `Vendor/` (folder) | Active | VendorModule + Hooks | — | shortcodes/redirect | مُستبعد من هذه المرحلة |
| `AI/` | Active | orchestrator | — | AI Core | مراجعة Controllers فقط |

## 2) الحالة التفصيلية للملفات المتأثرة بالمرحلة 1A

### `RestAPI.php` — **DEAD** ✅
- ليس في مصفوفة Kernel (12 وحدة: vendor..ai). 
- لا `add_action(rest_api_init)` reachable (لم يُحمّل أبداً)؟ لا — `init()` يضيف `rest_api_init` لكن لا يوجد من يحمّله؛ كل REST في `ApiServiceProvider` (10 مسارات). 
- `grep -rn "RestAPI"` خارج الملف: **0 مرجع غير معلّق**.
- القرار: **حذف** (مؤكد بعد الفحص النهائي).

### `Product.php` — **Transitional** (قيد تدقيق)
- الطرق: `__construct`, `init()`, 5× `ajax_*`, `fixVendorCapabilities()` (خاصة). لا يوجد WooCommerce hook أصلي، لا helper مكشوف.
- `fixVendorCapabilities` غير مستدعى (خط comment + تعريف).
- القرار الآن: **حذف 5 ajax_* فقط**، ثم إعادة تقيم الملف في المرحلة 1B (قد يصبح مرشح حذف).

### `Withdrawal.php` — **Transitional/Legacy**
- كلها `ajax_*` (3) + constructor. لا منطق حي مكشوف.
- القرار: حذف 3 ajax_* ثم إعادة تقيم (المرحلة 1B).

### `Report.php` — **Legacy**
- كلها `ajax_*` (6) + helper خاصان (يستخدمهما الـ ajax فقط).
- القرار: حذف 6 ajax_* ثم إعادة تقيم (المرحلة 1B). ملاحظة: Report الجديد (ReportController + Service) موجود، فالوحدة أصبحت دورها إسقاطًا (Backstop) لا تتنفيذ.

### `Vendor.php` — **Legacy/BC**
- `class Vendor extends Vendor\VendorModule` — alias للتقاف الخلفي.
- القرار: وضع علامة `@deprecated` + إبقاء مؤقت (لا حذف).

## 3) منهجية التحقق متطلبة بعد المرحلة 1A
- `php -l` لكل ملف تعديل.
- wp-load بلا fatal (كرnomal).
- Architecture Guard: `scripts/architecture-test.php`.
- REST `/vmp/v1` = 10.
- AJAX check: كل actions الميتة لا تُسجَّل؛ الـ live (Cron + VendorRequestsAdminPage) تبقى.
- WooCommerce flow smoke (لا fatal).

## 4. خارطة الطريق النهائية (قرار المستخدم 2026-08-05)
1. ✅ Legacy Audit (هذا الملف).
2. المرحلة 1A: حذف RestAPI + إزالة ajax_* + `@deprecated` Vendor → اختبارات.
3. المرحلة 1B: إعادة تدقيق Modules بعد التنظيف؛ عندها فقط قرار حذف أي Module فارغ (Product/Withdrawal/Report).
4. المرحلة 2: توحيد Controllers locations (AI) + مراجعة تكرار Providers.
5. المرحلة 3 (بعد إصدار مستقر): حذف Vendor.php إن لم يُستَدعى.

_لا يُنفّذ حذف أي Module كامل في نفس مرحلة إزالة ajax_*._

---

## حالة ما بعد المرحلة 1A (2026-08-05 — التحقق من الملفات الفعلية، لا استنتاج)

تم تنفيذ المرحلة 1A بنجاح:
- حذف app/Modules/RestAPI.php (نقل إلى .qa_backups/legacy-cleanup-1a-20260805-202411/RestAPI.php.deleted).
- إزالة 46 طريقة ajax_* من 8 وحدات (Product 5, Order 3, Commission 6, Withdrawal 3, Report 6, Subscription 13, Template 3, Whatsapp 7) — لم يعد أي function ajax_* في app/Modules/.
- Vendor.php موسوم بـ @deprecated.

### جدول التصنيف النهائي (بناءً على الفحص الفعلي 2026-08-05)

| الملف | التصنيف | الإثبات الفعلي (function list) | القرار |
|---|---|---|---|
| Notification.php (334 سطر) | **Active** | 7 دوال on_* (أحداث) | إبقاء |
| Order.php (260) | **Active** | split_order/on_order_completed/on_order_cancelled + 3 add_action WooCommerce | إبقاء |
| Commission.php (132) | **Active** | calculate_rate/calculate_amount | إبقاء |
| Subscription.php (~500) | **Active** | 15 دالة (on_vendor_approved, can_add_product, get_commission_rate...) | إبقاء |
| Template.php (286) | **Active** | get_available_templates/get_available_fonts/get_vendor_template_settings + wp_head/wp_enqueue | إبقاء |
| Whatsapp.php (271) | **Active** | render_product_button (woo hook) + render_store_button | إبقاء |
| Settings.php (573) | **Active** | getSetting/setSetting/save_settings | إبقاء |
| Product.php (106) | **Transitional → مرشح حذف** | constructor + init() فارغ + fixVendorCapabilities (خاصة غير مستدعاة) — لا منطق حي متبقي | 🔍 إعادة تقييم في 1B (لا حذف الآن) |
| Withdrawal.php (67) | **Legacy → فارغ** | constructor + init() فقط | 🔍 إعادة تقييم في 1B (لا حذف الآن) |
| Report.php (147) | **Legacy → فارغ فعليًا** | constructor + init() + helperان خاصان | 🔍 إعادة تقييم في 1B (لا حذف الآن) |
| Vendor.php (13) | Legacy/BC | alias + @deprecated | إبقاء مؤقتًا |
| RestAPI.php | **Dead — حُذف** | لا مراجع | تم الحذف |
| Vendor/VendorHooks.php | Active | login_redirect/wp_login/woo_login_redirect/admin_init/admin_bar_menu | إبقاء؛ 3 طرق ajax_* ميتة (معلّقة) تُراجع في 1B |
| AI/ | Active | orchestrator + Controllers | مراجعة Controllers في مرحلة مستقلة |

### قرار المستخدم (محفوظ)
- لا يُحذف أي Module كامل في مرحلة إزالة ajax_* — حتى لو أصبح Product/Withdrawal/Report فارغة، تُعاد تقييمها في 1B بعد اختبارات خضراء.
- القرار النهائي بحذف RestAPI.php من git (git rm) يُترك لتأكيد المستخدم بعد استقرار.

---

## Phase 1B Execution Log (2026-08-06)

### Actions Taken
| File | Action | Reason |
|------|--------|--------|
| RestAPI.php | `git rm` (1A→1B) | Dead — zero references, backed up |
| Product.php | `git rm` | Transitional→Dead — empty shell, no live refs |
| Withdrawal.php | `git rm` | Dead — empty shell, no live refs |
| Report.php | Kept | Legacy — live class_exists guard in vendor-dashboard.php:136 |

### Kernel Registry Changes
```diff
  $modules = [
      'vendor',
-     'product',
      'order',
      'commission',
-     'withdrawal',
      'subscription',
      'whatsapp',
      'template',
      'report',
      'notification',
      'settings',
      'ai',
  ];
```
> Note: `ModuleManager::resolveModuleClass()` is null-safe (returns null if class missing), so removed registry entries are harmless. `'report'` stays registered; `'vendor'` stays (alias).

### Verification Checklist (authoritative: docker `1Panel-wordpress-t5ET`, WP 7.0.2)
- [x] php -l: all Modules + Kernel.php — PASS (0 errors)
- [x] Architecture Guard: PASS — **0 Failures / 12 Warnings** (all in commented dead code, unchanged)
- [x] wp-load: PASS (no fatal) — plugin ACTIVE
- [x] REST `/vmp/v1`: **10 routes** (stable, unchanged)
- [x] class_exists after deletion: Product/Withdrawal/RestAPI → **GONE**; Report → **EXISTS**
- [x] Kernel module list: `'product'` and `'withdrawal'` removed; Report/Vendor kept
- [x] remaining `function ajax_` in `app/Modules/`: **0** (the 3 in Vendor/VendorHooks.php are the documented 1A exception — dead, registrations commented out)
- [x] grep safety gate: 0 live references to Product/Withdrawal module classes

### Classification Update (Post-1B)
| Module | Classification |
|--------|----------------|
| Notification | Active |
| Commission | Active |
| Template | Active |
| Subscription | Active |
| Order | Active |
| Vendor | Transitional (@deprecated) |
| Report | Legacy (pending decision on template guard) |
| Product | Dead (removed in 1B) |
| Withdrawal | Dead (removed in 1B) |
| RestAPI | Dead (removed in 1A→1B staged) |

### Pending Decisions (Phase 1C)
1. **Report.php**: `public/templates/vendor-dashboard.php:136` uses `class_exists('VMP\Modules\Report')`.
   - Option (a): delete Report.php + remove guard → `<canvas id="vmp-vendor-chart">` renders always
   - Option (b): keep Report.php as Legacy shell → guard stays functional
2. Vendor.php: remove `@deprecated` and delete file when migration complete.
3. AI Controllers: unify `app/Controllers/` vs `app/Modules/AI/Controllers/`.
4. The 3 dead `ajax_*` in `app/Modules/Vendor/VendorHooks.php` (commented registrations) — can be stripped in a later pass.
