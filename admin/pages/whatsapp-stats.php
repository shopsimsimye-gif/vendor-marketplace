<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من الصلاحية ──
if (!current_user_can('vmp_manage_reports')) {
    wp_die(__('ليس لديك صلاحية للوصول إلى هذه الصفحة.', 'vmp'));
}

// ── جلب جميع البائعين (للقائمة المنسدلة) ──
$vendor_repo = new \VMP\Repositories\VendorRepository();
$vendors = $vendor_repo->getAll(['status' => 'approved', 'limit' => 999]);
?>

<div class="wrap vmp-admin-wrap">
    <!-- ═══════ الرأس ═══════ -->
    <div class="vmp-admin-header">
        <div class="vmp-admin-header-left">
            <h1 class="vmp-admin-title">
                <span class="vmp-admin-title-icon">📊</span>
                <?php _e('إحصائيات واتساب', 'vmp'); ?>
            </h1>
            <p class="vmp-admin-subtitle"><?php _e('عرض وتحليل نقرات واتساب لجميع البائعين.', 'vmp'); ?></p>
        </div>
        <div class="vmp-admin-header-right">
            <span class="vmp-admin-date-badge">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php echo date_i18n('l, j F Y'); ?>
            </span>
        </div>
    </div>

    <!-- ═══════ أدوات التصفية ═══════ -->
    <div class="vmp-admin-card vmp-filter-card">
        <div class="vmp-admin-card-body">
            <form id="vmp-whatsapp-filter" class="vmp-filter-form">
                <div class="vmp-filter-group">
                    <label class="vmp-filter-label">
                        <span class="dashicons dashicons-store"></span>
                        <?php _e('البائع:', 'vmp'); ?>
                    </label>
                    <select name="vendor_id" id="vmp-whatsapp-vendor" class="vmp-filter-select">
                        <option value="0"><?php _e('جميع البائعين', 'vmp'); ?></option>
                        <?php foreach ($vendors as $vendor) : ?>
                            <option value="<?php echo (int) $vendor->id; ?>">
                                <?php echo esc_html($vendor->store_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="vmp-filter-group">
                    <label class="vmp-filter-label">
                        <span class="dashicons dashicons-clock"></span>
                        <?php _e('المدة:', 'vmp'); ?>
                    </label>
                    <select name="months" id="vmp-whatsapp-months" class="vmp-filter-select">
                        <option value="1"><?php _e('شهر واحد', 'vmp'); ?></option>
                        <option value="3"><?php _e('3 أشهر', 'vmp'); ?></option>
                        <option value="6" selected><?php _e('6 أشهر', 'vmp'); ?></option>
                        <option value="12"><?php _e('12 شهر', 'vmp'); ?></option>
                    </select>
                </div>

                <button type="button" id="vmp-whatsapp-refresh" class="vmp-btn vmp-btn-primary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('تحديث', 'vmp'); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- ═══════ البطاقات الإحصائية ═══════ -->
    <div id="vmp-whatsapp-summary">
        <div class="vmp-admin-stats-grid">
            <div class="vmp-stat-card vmp-stat-total">
                <div class="vmp-stat-icon">
                    <span class="dashicons dashicons-whatsapp"></span>
                </div>
                <div class="vmp-stat-content">
                    <span class="vmp-stat-label"><?php _e('إجمالي النقرات', 'vmp'); ?></span>
                    <span class="vmp-stat-value" id="vmp-total-clicks">0</span>
                </div>
                <div class="vmp-stat-progress">
                    <div class="vmp-stat-progress-bar" style="width:0%"></div>
                </div>
            </div>

            <div class="vmp-stat-card vmp-stat-today">
                <div class="vmp-stat-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="vmp-stat-content">
                    <span class="vmp-stat-label"><?php _e('نقرات اليوم', 'vmp'); ?></span>
                    <span class="vmp-stat-value" id="vmp-today-clicks">0</span>
                </div>
                <div class="vmp-stat-progress">
                    <div class="vmp-stat-progress-bar" style="width:0%"></div>
                </div>
            </div>

            <div class="vmp-stat-card vmp-stat-week">
                <div class="vmp-stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="vmp-stat-content">
                    <span class="vmp-stat-label"><?php _e('نقرات هذا الأسبوع', 'vmp'); ?></span>
                    <span class="vmp-stat-value" id="vmp-week-clicks">0</span>
                </div>
                <div class="vmp-stat-progress">
                    <div class="vmp-stat-progress-bar" style="width:0%"></div>
                </div>
            </div>

            <div class="vmp-stat-card vmp-stat-month">
                <div class="vmp-stat-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="vmp-stat-content">
                    <span class="vmp-stat-label"><?php _e('نقرات هذا الشهر', 'vmp'); ?></span>
                    <span class="vmp-stat-value" id="vmp-month-clicks">0</span>
                </div>
                <div class="vmp-stat-progress">
                    <div class="vmp-stat-progress-bar" style="width:0%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════ الرسم البياني ═══════ -->
    <div class="vmp-admin-card vmp-chart-card">
        <div class="vmp-admin-card-header">
            <h2 class="vmp-card-title">
                <span class="vmp-card-icon">📈</span>
                <?php _e('اتجاه النقرات', 'vmp'); ?>
            </h2>
            <span class="vmp-card-badge" id="vmp-chart-period"><?php _e('آخر 6 أشهر', 'vmp'); ?></span>
        </div>
        <div class="vmp-admin-card-body">
            <div class="vmp-chart-container">
                <canvas id="vmp-whatsapp-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- ═══════ جدول البائعين ═══════ -->
    <div class="vmp-admin-card vmp-table-card">
        <div class="vmp-admin-card-header">
            <h2 class="vmp-card-title">
                <span class="vmp-card-icon">📋</span>
                <?php _e('تفاصيل البائعين', 'vmp'); ?>
            </h2>
            <div class="vmp-card-actions">
                <button id="vmp-export-whatsapp-csv" class="vmp-btn vmp-btn-secondary">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('تصدير CSV', 'vmp'); ?>
                </button>
            </div>
        </div>
        <div class="vmp-admin-card-body vmp-table-wrapper">
            <div id="vmp-whatsapp-table-wrapper">
                <div class="vmp-loading-state">
                    <div class="vmp-loading-spinner"></div>
                    <span><?php _e('جاري تحميل البيانات...', 'vmp'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════ مودال تفاصيل البائع ═══════ -->
<div class="vmp-modal-overlay" id="vmp-vendor-detail-modal" style="display:none;">
    <div class="vmp-modal vmp-modal-large">
        <div class="vmp-modal-header">
            <h2 id="vmp-vendor-detail-title">
                <span class="dashicons dashicons-store"></span>
                <?php _e('تفاصيل البائع', 'vmp'); ?>
            </h2>
            <button class="vmp-modal-close" aria-label="إغلاق">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="vmp-modal-body">
            <div id="vmp-vendor-detail-content">
                <div class="vmp-loading-state">
                    <div class="vmp-loading-spinner"></div>
                    <span><?php _e('جاري تحميل البيانات...', 'vmp'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- الأنماط المخصصة                                             -->
<!-- ════════════════════════════════════════════════════════════ -->
<style>
/* ─── المتغيرات ─── */
:root {
    --vmp-primary: #6366f1;
    --vmp-primary-hover: #4f46e5;
    --vmp-primary-light: rgba(99, 102, 241, 0.12);
    --vmp-success: #10b981;
    --vmp-success-light: rgba(16, 185, 129, 0.12);
    --vmp-warning: #f59e0b;
    --vmp-warning-light: rgba(245, 158, 11, 0.12);
    --vmp-purple: #8b5cf6;
    --vmp-purple-light: rgba(139, 92, 246, 0.12);
    --vmp-red: #ef4444;
    --vmp-gray-50: #f8fafc;
    --vmp-gray-100: #f1f5f9;
    --vmp-gray-200: #e2e8f0;
    --vmp-gray-300: #cbd5e1;
    --vmp-gray-400: #94a3b8;
    --vmp-gray-500: #64748b;
    --vmp-gray-600: #475569;
    --vmp-gray-700: #334155;
    --vmp-gray-800: #1e293b;
    --vmp-gray-900: #0f172a;
    --vmp-radius: 12px;
    --vmp-radius-sm: 8px;
    --vmp-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --vmp-shadow-hover: 0 8px 30px rgba(0,0,0,0.08);
    --vmp-transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ─── الرأس ─── */
.vmp-admin-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--vmp-gray-200);
}
.vmp-admin-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 24px;
    font-weight: 700;
    color: var(--vmp-gray-900);
    margin: 0;
}
.vmp-admin-title-icon {
    font-size: 28px;
}
.vmp-admin-subtitle {
    color: var(--vmp-gray-500);
    font-size: 14px;
    margin: 4px 0 0 0;
}
.vmp-admin-date-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: var(--vmp-gray-100);
    border-radius: 9999px;
    font-size: 13px;
    color: var(--vmp-gray-600);
}
.vmp-admin-date-badge .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* ─── البطاقات ─── */
.vmp-admin-card {
    background: #ffffff;
    border-radius: var(--vmp-radius);
    box-shadow: var(--vmp-shadow);
    border: 1px solid var(--vmp-gray-200);
    margin-bottom: 24px;
    transition: var(--vmp-transition);
}
.vmp-admin-card:hover {
    box-shadow: var(--vmp-shadow-hover);
}
.vmp-admin-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid var(--vmp-gray-200);
    flex-wrap: wrap;
    gap: 12px;
}
.vmp-admin-card-header .vmp-card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 600;
    color: var(--vmp-gray-800);
    margin: 0;
}
.vmp-card-icon {
    font-size: 20px;
}
.vmp-card-badge {
    font-size: 12px;
    padding: 4px 12px;
    background: var(--vmp-gray-100);
    border-radius: 9999px;
    color: var(--vmp-gray-500);
}
.vmp-card-actions {
    display: flex;
    gap: 8px;
}
.vmp-admin-card-body {
    padding: 20px 24px;
}
.vmp-filter-card .vmp-admin-card-body {
    padding: 16px 24px;
}
.vmp-table-card .vmp-admin-card-body {
    padding: 0;
}

/* ─── الفلتر ─── */
.vmp-filter-form {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: flex-end;
}
.vmp-filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 160px;
}
.vmp-filter-label {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    font-weight: 500;
    color: var(--vmp-gray-600);
}
.vmp-filter-label .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}
.vmp-filter-select {
    padding: 8px 12px;
    border: 1.5px solid var(--vmp-gray-200);
    border-radius: var(--vmp-radius-sm);
    background: #ffffff;
    font-size: 14px;
    color: var(--vmp-gray-800);
    transition: var(--vmp-transition);
    min-width: 160px;
    height: 42px;
}
.vmp-filter-select:focus {
    border-color: var(--vmp-primary);
    outline: none;
    box-shadow: 0 0 0 3px var(--vmp-primary-light);
}

/* ─── الأزرار ─── */
.vmp-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border: none;
    border-radius: var(--vmp-radius-sm);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--vmp-transition);
    text-decoration: none;
    height: 42px;
}
.vmp-btn-primary {
    background: var(--vmp-primary);
    color: #ffffff;
}
.vmp-btn-primary:hover {
    background: var(--vmp-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
}
.vmp-btn-secondary {
    background: var(--vmp-gray-100);
    color: var(--vmp-gray-700);
}
.vmp-btn-secondary:hover {
    background: var(--vmp-gray-200);
}
.vmp-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

/* ─── البطاقات الإحصائية ─── */
.vmp-admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.vmp-stat-card {
    background: #ffffff;
    border-radius: var(--vmp-radius);
    padding: 20px 24px;
    border: 1px solid var(--vmp-gray-200);
    box-shadow: var(--vmp-shadow);
    transition: var(--vmp-transition);
    position: relative;
    overflow: hidden;
}
.vmp-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--vmp-shadow-hover);
}
.vmp-stat-card .vmp-stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 10px;
    font-size: 20px;
    margin-bottom: 12px;
}
.vmp-stat-card .vmp-stat-icon .dashicons {
    font-size: 22px;
    width: 22px;
    height: 22px;
}
.vmp-stat-total .vmp-stat-icon {
    background: var(--vmp-primary-light);
    color: var(--vmp-primary);
}
.vmp-stat-today .vmp-stat-icon {
    background: var(--vmp-success-light);
    color: var(--vmp-success);
}
.vmp-stat-week .vmp-stat-icon {
    background: var(--vmp-warning-light);
    color: var(--vmp-warning);
}
.vmp-stat-month .vmp-stat-icon {
    background: var(--vmp-purple-light);
    color: var(--vmp-purple);
}
.vmp-stat-content {
    display: flex;
    flex-direction: column;
}
.vmp-stat-label {
    font-size: 13px;
    color: var(--vmp-gray-500);
    font-weight: 500;
}
.vmp-stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--vmp-gray-900);
    margin-top: 2px;
}
.vmp-stat-progress {
    margin-top: 12px;
    height: 3px;
    background: var(--vmp-gray-100);
    border-radius: 9999px;
    overflow: hidden;
}
.vmp-stat-progress-bar {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.8s ease;
}
.vmp-stat-total .vmp-stat-progress-bar {
    background: var(--vmp-primary);
}
.vmp-stat-today .vmp-stat-progress-bar {
    background: var(--vmp-success);
}
.vmp-stat-week .vmp-stat-progress-bar {
    background: var(--vmp-warning);
}
.vmp-stat-month .vmp-stat-progress-bar {
    background: var(--vmp-purple);
}

/* ─── الرسم البياني ─── */
.vmp-chart-card .vmp-admin-card-body {
    padding: 16px 24px 24px;
}
.vmp-chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

/* ─── الجدول ─── */
.vmp-table-wrapper {
    overflow-x: auto;
}
.vmp-admin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.vmp-admin-table thead {
    background: var(--vmp-gray-50);
}
.vmp-admin-table th {
    padding: 12px 16px;
    text-align: right;
    font-weight: 600;
    color: var(--vmp-gray-600);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--vmp-gray-200);
}
.vmp-admin-table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--vmp-gray-100);
    color: var(--vmp-gray-700);
    vertical-align: middle;
}
.vmp-admin-table tbody tr:hover {
    background: var(--vmp-gray-50);
}
.vmp-admin-table tbody tr:last-child td {
    border-bottom: none;
}
.vmp-admin-table .vmp-bar-track {
    width: 80px;
    height: 4px;
    background: var(--vmp-gray-200);
    border-radius: 9999px;
    overflow: hidden;
    display: inline-block;
    vertical-align: middle;
}
.vmp-admin-table .vmp-bar-fill {
    height: 100%;
    border-radius: 9999px;
    background: var(--vmp-primary);
    transition: width 0.6s ease;
}
.vmp-admin-table .vmp-click-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}
.vmp-admin-table .vmp-click-cell strong {
    min-width: 28px;
}

/* ─── الحالة الفارغة ─── */
.vmp-empty-state {
    text-align: center;
    padding: 48px 24px;
    color: var(--vmp-gray-400);
}
.vmp-empty-state .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: var(--vmp-gray-300);
    margin-bottom: 12px;
}
.vmp-empty-state p {
    margin: 0;
    font-size: 15px;
}

/* ─── حالة التحميل ─── */
.vmp-loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    gap: 12px;
    color: var(--vmp-gray-400);
}
.vmp-loading-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid var(--vmp-gray-200);
    border-top-color: var(--vmp-primary);
    border-radius: 50%;
    animation: vmp-spin 0.8s linear infinite;
}
@keyframes vmp-spin {
    to { transform: rotate(360deg); }
}

/* ─── المودال ─── */
.vmp-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: vmpFadeIn 0.25s ease;
}
@keyframes vmpFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.vmp-modal {
    background: #ffffff;
    border-radius: var(--vmp-radius);
    max-width: 680px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 24px 64px rgba(0,0,0,0.2);
    animation: vmpSlideIn 0.3s ease;
}
.vmp-modal-large {
    max-width: 800px;
}
@keyframes vmpSlideIn {
    from { opacity: 0; transform: scale(0.96) translateY(12px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.vmp-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    border-bottom: 1px solid var(--vmp-gray-200);
}
.vmp-modal-header h2 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--vmp-gray-900);
}
.vmp-modal-header h2 .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}
.vmp-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    color: var(--vmp-gray-400);
    transition: var(--vmp-transition);
}
.vmp-modal-close:hover {
    color: var(--vmp-gray-900);
}
.vmp-modal-close .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}
.vmp-modal-body {
    padding: 24px;
}

/* ─── المودال: الإحصائيات الفرعية ─── */
.vmp-modal .vmp-admin-stats-grid {
    grid-template-columns: repeat(4, 1fr);
    margin-bottom: 20px;
}
.vmp-modal .vmp-stat-card {
    padding: 14px 18px;
}
.vmp-modal .vmp-stat-value {
    font-size: 20px;
}
.vmp-modal .vmp-stat-icon {
    width: 36px;
    height: 36px;
    font-size: 16px;
    margin-bottom: 8px;
}
.vmp-modal .vmp-stat-icon .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

/* ─── مودال: جدول المنتجات ─── */
.vmp-modal .vmp-admin-table {
    font-size: 13px;
}
.vmp-modal .vmp-admin-table th,
.vmp-modal .vmp-admin-table td {
    padding: 8px 12px;
}

/* ─── الاستجابة ─── */
@media (max-width: 782px) {
    .vmp-admin-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .vmp-admin-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .vmp-filter-group {
        min-width: 100%;
    }
    .vmp-filter-form .vmp-btn {
        width: 100%;
        justify-content: center;
    }
    .vmp-modal {
        margin: 12px;
        max-width: 100%;
    }
    .vmp-modal .vmp-admin-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .vmp-chart-container {
        height: 200px;
    }
    .vmp-admin-card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .vmp-card-actions {
        width: 100%;
    }
    .vmp-card-actions .vmp-btn {
        flex: 1;
        justify-content: center;
    }
}
@media (max-width: 480px) {
    .vmp-admin-stats-grid {
        grid-template-columns: 1fr;
    }
    .vmp-modal .vmp-admin-stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    .vmp-stat-value {
        font-size: 22px;
    }
    .vmp-admin-table th,
    .vmp-admin-table td {
        padding: 8px 10px;
        font-size: 12px;
    }
}
</style>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- JavaScript                                                 -->
<!-- ════════════════════════════════════════════════════════════ -->
<script>
jQuery(document).ready(function($) {
    'use strict';

    // ── تحميل البيانات ──
    /**
     * LoadStats functionality helper.
     *
     * @return void Output payload.
     */
    function loadStats(vendorId, months) {
        var $wrapper = $('#vmp-whatsapp-table-wrapper');
        $wrapper.html('<div class="vmp-loading-state"><div class="vmp-loading-spinner"></div><span><?php _e('جاري تحميل البيانات...', 'vmp'); ?></span></div>');

        $.post(ajaxurl, {
            action: 'vmp_admin_get_whatsapp_stats',
            nonce: vmp_admin.nonce
        }, function(response) {
            if (response.success) {
                renderTable(response.data.vendors);
                renderSummary(response.data.vendors);
                renderChart(vendorId || 0, months || 6);
            } else {
                $wrapper.html('<div class="vmp-empty-state"><span class="dashicons dashicons-warning"></span><p>' + response.data.message + '</p></div>');
            }
        }).fail(function() {
            $wrapper.html('<div class="vmp-empty-state"><span class="dashicons dashicons-warning"></span><p><?php _e('حدث خطأ في الاتصال.', 'vmp'); ?></p></div>');
        });
    }

    // ── عرض الجدول ──
    /**
     * RenderTable functionality helper.
     *
     * @return mixed Output payload.
     */
    function renderTable(vendors) {
        var $wrapper = $('#vmp-whatsapp-table-wrapper');

        if (!vendors || vendors.length === 0) {
            $wrapper.html('<div class="vmp-empty-state"><span class="dashicons dashicons-chart-area"></span><p><?php _e('لا توجد بيانات لعرضها.', 'vmp'); ?></p></div>');
            return;
        }

        var html = '<table class="vmp-admin-table">' +
            '<thead><tr>' +
            '<th><?php _e('المتجر', 'vmp'); ?></th>' +
            '<th><?php _e('إجمالي النقرات', 'vmp'); ?></th>' +
            '<th><?php _e('اليوم', 'vmp'); ?></th>' +
            '<th><?php _e('السبوع', 'vmp'); ?></th>' +
            '<th><?php _e('الشهر', 'vmp'); ?></th>' +
            '<th><?php _e('نقرات المنتجات', 'vmp'); ?></th>' +
            '<th><?php _e('نقرات المتجر', 'vmp'); ?></th>' +
            '<th><?php _e('الإجراءات', 'vmp'); ?></th>' +
            '</tr></thead><tbody>';

        var maxClicks = Math.max(...vendors.map(v => parseInt(v.total_clicks) || 0), 1);

        $.each(vendors, function(i, v) {
            var progress = maxClicks > 0 ? Math.min((parseInt(v.total_clicks) / maxClicks) * 100, 100) : 0;
            html += '<tr>' +
                '<td><strong>' + v.store_name + '</strong></td>' +
                '<td>' +
                '<div class="vmp-click-cell">' +
                '<strong>' + v.total_clicks + '</strong>' +
                '<span class="vmp-bar-track"><span class="vmp-bar-fill" style="width:' + progress + '%"></span></span>' +
                '</div>' +
                '</td>' +
                '<td>' + v.today_clicks + '</td>' +
                '<td>' + v.week_clicks + '</td>' +
                '<td>' + v.month_clicks + '</td>' +
                '<td>' + v.product_clicks + '</td>' +
                '<td>' + v.store_clicks + '</td>' +
                '<td>' +
                '<button class="button button-small vmp-view-vendor" data-id="' + v.id + '" data-name="' + v.store_name + '">' +
                '<?php _e('تفاصيل', 'vmp'); ?>' +
                '</button>' +
                '</td>' +
                '</tr>';
        });

        html += '</tbody></table>';
        $wrapper.html(html);
    }

    // ── عرض الإحصائيات الإجمالية ──
    /**
     * RenderSummary functionality helper.
     *
     * @return void Output payload.
     */
    function renderSummary(vendors) {
        var total = 0, today = 0, week = 0, month = 0;
        $.each(vendors, function(i, v) {
            total += parseInt(v.total_clicks || 0);
            today += parseInt(v.today_clicks || 0);
            week += parseInt(v.week_clicks || 0);
            month += parseInt(v.month_clicks || 0);
        });

        var maxVal = Math.max(total, today, week, month, 1);
        $('#vmp-total-clicks').text(total);
        $('#vmp-total-clicks').closest('.vmp-stat-card').find('.vmp-stat-progress-bar').css('width', (total / maxVal * 100) + '%');
        $('#vmp-today-clicks').text(today);
        $('#vmp-today-clicks').closest('.vmp-stat-card').find('.vmp-stat-progress-bar').css('width', (today / maxVal * 100) + '%');
        $('#vmp-week-clicks').text(week);
        $('#vmp-week-clicks').closest('.vmp-stat-card').find('.vmp-stat-progress-bar').css('width', (week / maxVal * 100) + '%');
        $('#vmp-month-clicks').text(month);
        $('#vmp-month-clicks').closest('.vmp-stat-card').find('.vmp-stat-progress-bar').css('width', (month / maxVal * 100) + '%');
    }

    // ── عرض الرسم البياني ──
    /**
     * RenderChart functionality helper.
     *
     * @return void Output payload.
     */
    function renderChart(vendorId, months) {
        var periodText = months === 1 ? '<?php _e('آخر شهر', 'vmp'); ?>' :
                         months === 3 ? '<?php _e('آخر 3 أشهر', 'vmp'); ?>' :
                         months === 6 ? '<?php _e('آخر 6 أشهر', 'vmp'); ?>' :
                         '<?php _e('آخر 12 شهراً', 'vmp'); ?>';
        $('#vmp-chart-period').text(periodText);

        $.post(ajaxurl, {
            action: 'vmp_admin_get_whatsapp_chart',
            nonce: vmp_admin.nonce,
            vendor_id: vendorId || 0,
            months: months || 6
        }, function(response) {
            if (response.success && response.data.data) {
                var chartData = response.data.data;
                var labels = [];
                var clicks = [];

                $.each(chartData, function(i, row) {
                    labels.push(row.date);
                    clicks.push(parseInt(row.clicks));
                });

                var ctx = document.getElementById('vmp-whatsapp-chart');
                if (ctx) {
                    if (window.vmpWhatsappChart) {
                        window.vmpWhatsappChart.destroy();
                    }

                    window.vmpWhatsappChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: '<?php _e('النقرات', 'vmp'); ?>',
                                data: clicks,
                                borderColor: '#6366f1',
                                backgroundColor: 'rgba(99,102,241,0.08)',
                                borderWidth: 2.5,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#6366f1',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(15,23,42,0.9)',
                                    titleFont: { family: 'Cairo', size: 13 },
                                    bodyFont: { family: 'Cairo', size: 12 },
                                    cornerRadius: 8,
                                    padding: 10
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0,0,0,0.04)',
                                        drawBorder: false
                                    },
                                    ticks: {
                                        font: { size: 11 },
                                        stepSize: 1,
                                        color: '#94a3b8'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        font: { size: 10 },
                                        maxTicksLimit: 15,
                                        color: '#94a3b8'
                                    }
                                }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            }
                        }
                    });
                }
            }
        });
    }

    // ── عرض تفاصيل البائع ──
    $(document).on('click', '.vmp-view-vendor', function(e) {
        e.preventDefault();
        var vendorId = $(this).data('id');
        var vendorName = $(this).data('name');

        $('#vmp-vendor-detail-title').html('<span class="dashicons dashicons-store"></span> ' + vendorName);
        $('#vmp-vendor-detail-content').html('<div class="vmp-loading-state"><div class="vmp-loading-spinner"></div><span><?php _e('جاري تحميل البيانات...', 'vmp'); ?></span></div>');
        $('#vmp-vendor-detail-modal').show();

        $.post(ajaxurl, {
            action: 'vmp_admin_get_vendor_whatsapp_stats',
            nonce: vmp_admin.nonce,
            vendor_id: vendorId
        }, function(response) {
            if (response.success) {
                var s = response.data.stats;
                var html = '';

                html += '<div class="vmp-admin-stats-grid">';
                html += '<div class="vmp-stat-card vmp-stat-total"><div class="vmp-stat-icon"><span class="dashicons dashicons-whatsapp"></span></div><div class="vmp-stat-content"><span class="vmp-stat-label"><?php _e('الإجمالي', 'vmp'); ?></span><span class="vmp-stat-value">' + s.total_clicks + '</span></div></div>';
                html += '<div class="vmp-stat-card vmp-stat-today"><div class="vmp-stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div><div class="vmp-stat-content"><span class="vmp-stat-label"><?php _e('اليوم', 'vmp'); ?></span><span class="vmp-stat-value">' + s.today_clicks + '</span></div></div>';
                html += '<div class="vmp-stat-card vmp-stat-week"><div class="vmp-stat-icon"><span class="dashicons dashicons-clock"></span></div><div class="vmp-stat-content"><span class="vmp-stat-label"><?php _e('هذا الأسبوع', 'vmp'); ?></span><span class="vmp-stat-value">' + s.week_clicks + '</span></div></div>';
                html += '<div class="vmp-stat-card vmp-stat-month"><div class="vmp-stat-icon"><span class="dashicons dashicons-chart-line"></span></div><div class="vmp-stat-content"><span class="vmp-stat-label"><?php _e('هذا الشهر', 'vmp'); ?></span><span class="vmp-stat-value">' + s.month_clicks + '</span></div></div>';
                html += '</div>';

                // المنتجات الأكثر استفساراً
                if (response.data.top_products && response.data.top_products.length > 0) {
                    html += '<h3 style="margin:20px 0 12px; font-size:15px; font-weight:600; color:var(--vmp-gray-800);"><?php _e('المنتجات الأكثر استفساراً', 'vmp'); ?></h3>';
                    html += '<table class="vmp-admin-table"><thead><tr><th><?php _e('المنتج', 'vmp'); ?></th><th style="text-align:center;"><?php _e('عدد الاستفسارات', 'vmp'); ?></th></tr></thead><tbody>';
                    var maxProd = Math.max(...response.data.top_products.map(p => parseInt(p.clicks)), 1);
                    $.each(response.data.top_products, function(i, p) {
                        var prodProgress = (parseInt(p.clicks) / maxProd * 100);
                        html += '<tr>' +
                            '<td>' + (p.product_name || '#' + p.product_id) + '</td>' +
                            '<td style="text-align:center;">' +
                            '<div class="vmp-click-cell" style="justify-content:center;">' +
                            '<strong>' + p.clicks + '</strong>' +
                            '<span class="vmp-bar-track"><span class="vmp-bar-fill" style="width:' + prodProgress + '%; background:#8b5cf6;"></span></span>' +
                            '</div>' +
                            '</td>' +
                            '</tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<p style="color: var(--vmp-gray-400); text-align:center; padding:12px;"><?php _e('لا توجد استفسارات عن منتجات محددة.', 'vmp'); ?></p>';
                }

                $('#vmp-vendor-detail-content').html(html);
            } else {
                $('#vmp-vendor-detail-content').html('<div class="vmp-empty-state"><span class="dashicons dashicons-warning"></span><p>' + response.data.message + '</p></div>');
            }
        }).fail(function() {
            $('#vmp-vendor-detail-content').html('<div class="vmp-empty-state"><span class="dashicons dashicons-warning"></span><p><?php _e('حدث خطأ في الاتصال.', 'vmp'); ?></p></div>');
        });
    });

    // ── إغلاق المودال ──
    $(document).on('click', '.vmp-modal-close', function() {
        $('#vmp-vendor-detail-modal').hide();
    });
    $(document).on('click', '#vmp-vendor-detail-modal', function(e) {
        if ($(e.target).is(this)) {
            $(this).hide();
        }
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#vmp-vendor-detail-modal').hide();
        }
    });

    // ── تحديث البيانات ──
    $('#vmp-whatsapp-refresh').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $btn.find('.dashicons').addClass('dashicons-update-spin');

        var vendorId = $('#vmp-whatsapp-vendor').val();
        var months = $('#vmp-whatsapp-months').val();
        loadStats(vendorId, months);

        setTimeout(function() {
            $btn.prop('disabled', false);
            $btn.find('.dashicons').removeClass('dashicons-update-spin');
        }, 500);
    });

    // ── تصدير CSV ──
    $('#vmp-export-whatsapp-csv').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-update-spin"></span> <?php _e('جاري...', 'vmp'); ?>');

        var csv = '<?php _e('المتجر,الإجمالي,اليوم,الاسبوع,الشهر,نقرات المنتجات,نقرات المتجر', 'vmp'); ?>\n';
        $('.vmp-admin-table tbody tr').each(function() {
            var cols = $(this).find('td');
            var row = [];
            cols.each(function(i, col) {
                if (i !== cols.length - 1) {
                    row.push($(col).text().trim());
                }
            });
            csv += row.join(',') + '\n';
        });

        var blob = new Blob(['\uFEFF' + csv], {type: 'text/csv;charset=utf-8;'});
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'vmp_whatsapp_stats_' + new Date().toISOString().slice(0,10) + '.csv';
        link.click();
        URL.revokeObjectURL(link.href);

        $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> <?php _e('تصدير CSV', 'vmp'); ?>');
    });

    // ── تحميل البيانات عند فتح الصفحة ──
    loadStats(0, 6);
});
</script>