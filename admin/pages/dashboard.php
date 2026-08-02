<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من الصلاحية ──
if (!current_user_can('vmp_manage_vendors')) {
    wp_die(__('ليس لديك صلاحية للوصول إلى هذه الصفحة.', 'vmp'));
}

// ── استخدام الحاوية ──
$container = \VMP\Core\Container::getInstance();

// ── جلب المستودعات ──
$vendor_repo = $container->make(\VMP\Repositories\VendorRepository::class);
$order_repo = $container->make(\VMP\Repositories\OrderRepository::class);
$product_repo = $container->make(\VMP\Repositories\ProductRepository::class);
$commission_repo = $container->make(\VMP\Repositories\CommissionRepository::class);

// ── إحصائيات عامة (مع التخزين المؤقت) ──
$cache_key = 'vmp_admin_dashboard_stats';
$stats = get_transient($cache_key);

if (false === $stats) {
    global $wpdb;

    $total_vendors = $vendor_repo->getCount();
    $active_vendors = $vendor_repo->getCount('approved');
    $pending_vendors = $vendor_repo->getCount('pending');
    $total_sales = $order_repo->getTotalSalesForAllVendors();
    $total_commissions = $commission_repo->getTotalCommissions();
    $pending_commissions = $commission_repo->getTotalCommissions('pending');

    $latest_pending_vendors = $vendor_repo->getLatestPending(5);
    $latest_pending_products = $product_repo->getPending(5);

    $whatsapp_clicks = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}vmp_whatsapp_clicks"));

    $stats = [
        'total_vendors' => $total_vendors,
        'active_vendors' => $active_vendors,
        'pending_vendors' => $pending_vendors,
        'total_sales' => $total_sales,
        'total_commissions' => $total_commissions,
        'pending_commissions' => $pending_commissions,
        'latest_pending_vendors' => $latest_pending_vendors,
        'latest_pending_products' => $latest_pending_products,
        'whatsapp_clicks' => $whatsapp_clicks,
    ];

    set_transient($cache_key, $stats, 300);
} else {
    $total_vendors = $stats['total_vendors'];
    $active_vendors = $stats['active_vendors'];
    $pending_vendors = $stats['pending_vendors'];
    $total_sales = $stats['total_sales'];
    $total_commissions = $stats['total_commissions'];
    $pending_commissions = $stats['pending_commissions'];
    $latest_pending_vendors = $stats['latest_pending_vendors'];
    $latest_pending_products = $stats['latest_pending_products'];
    $whatsapp_clicks = $stats['whatsapp_clicks'];
}

// ── بيانات الرسم البياني ──
$chart_cache_key = 'vmp_admin_chart_data';
$chart_data = get_transient($chart_cache_key);

if (false === $chart_data) {
    global $wpdb;
    $chart_data = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                DATE_FORMAT(created_at, '%%Y-%%m') AS month,
                COALESCE(SUM(vendor_amount), 0) AS earnings,
                COALESCE(SUM(commission_amount), 0) AS commissions,
                COUNT(*) AS orders
             FROM {$wpdb->prefix}vmp_commissions
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
             ORDER BY month ASC"
        )
    );
    set_transient($chart_cache_key, $chart_data, 3600);
}

// ── ✅ إشعارات المشرف ──
$admin_notices = get_option('vmp_admin_notices', []);
$unread_notices = array_filter($admin_notices, function($n) {
    return empty($n['read']);
});
?>

<div class="wrap vmp-admin-wrap">
    <!-- رأس الصفحة -->
    <div class="vmp-admin-header">
        <div>
            <h1><?php _e('لوحة تحكم Vendor Marketplace', 'vmp'); ?></h1>
            <p class="vmp-admin-subtitle"><?php _e('نظرة عامة على أداء السوق وإحصائيات البائعين.', 'vmp'); ?></p>
        </div>
        <div class="vmp-admin-header-actions">
            <span class="vmp-admin-date"><?php echo date_i18n('l, j F Y'); ?></span>
        </div>
    </div>

    <!-- ✅ إشعارات المشرف (إذا وجدت) -->
    <?php if (!empty($unread_notices)) : ?>
        <div class="vmp-admin-card" style="border-color: #f59e0b; margin-bottom: 24px;">
            <div class="vmp-admin-card-header">
                <h2>🔔 <?php _e('إشعارات جديدة', 'vmp'); ?></h2>
                <span class="vmp-admin-badge" style="background:#f59e0b;"><?php echo count($unread_notices); ?></span>
            </div>
            <div class="vmp-admin-card-body" style="padding: 0;">
                <ul style="list-style:none; padding:0; margin:0;">
                    <?php foreach ($unread_notices as $notice) : ?>
                        <li style="display:flex; justify-content:space-between; align-items:center; padding:10px 16px; border-bottom:1px solid #f1f5f9;">
                            <span><?php echo esc_html($notice['message']); ?></span>
                            <a href="admin.php?page=vmp-subscriptions" class="button button-small">
                                <?php _e('مراجعة', 'vmp'); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- بطاقات الإحصائيات -->
    <div class="vmp-admin-stats">
        <div class="vmp-admin-stat-card">
            <div class="vmp-admin-stat-icon" style="background:rgba(99,102,241,0.12); color:#6366f1;">
                <span class="dashicons dashicons-store"></span>
            </div>
            <div class="vmp-admin-stat-content">
                <span class="vmp-admin-stat-label"><?php _e('البائعون (نشط/إجمالي)', 'vmp'); ?></span>
                <span class="vmp-admin-stat-value"><?php echo (int) $active_vendors . ' / ' . (int) $total_vendors; ?></span>
            </div>
        </div>
        <div class="vmp-admin-stat-card">
            <div class="vmp-admin-stat-icon" style="background:rgba(245,158,11,0.12); color:#f59e0b;">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="vmp-admin-stat-content">
                <span class="vmp-admin-stat-label"><?php _e('طلبات انضمام معلقة', 'vmp'); ?></span>
                <span class="vmp-admin-stat-value"><?php echo (int) $pending_vendors; ?></span>
            </div>
        </div>
        <div class="vmp-admin-stat-card">
            <div class="vmp-admin-stat-icon" style="background:rgba(16,185,129,0.12); color:#10b981;">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="vmp-admin-stat-content">
                <span class="vmp-admin-stat-label"><?php _e('إجمالي المبيعات', 'vmp'); ?></span>
                <span class="vmp-admin-stat-value"><?php echo wc_price($total_sales); ?></span>
            </div>
        </div>
        <div class="vmp-admin-stat-card">
            <div class="vmp-admin-stat-icon" style="background:rgba(139,92,246,0.12); color:#8b5cf6;">
                <span class="dashicons dashicons-chart-pie"></span>
            </div>
            <div class="vmp-admin-stat-content">
                <span class="vmp-admin-stat-label"><?php _e('إجمالي العمولات', 'vmp'); ?></span>
                <span class="vmp-admin-stat-value"><?php echo wc_price($total_commissions); ?></span>
            </div>
        </div>
        <div class="vmp-admin-stat-card">
            <div class="vmp-admin-stat-icon" style="background:rgba(37,211,102,0.12); color:#25D366;">
                <span class="dashicons dashicons-whatsapp"></span>
            </div>
            <div class="vmp-admin-stat-content">
                <span class="vmp-admin-stat-label"><?php _e('نقرات واتساب', 'vmp'); ?></span>
                <span class="vmp-admin-stat-value"><?php echo (int) $whatsapp_clicks; ?></span>
            </div>
        </div>
        <div class="vmp-admin-stat-card">
            <div class="vmp-admin-stat-icon" style="background:rgba(239,68,68,0.12); color:#ef4444;">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="vmp-admin-stat-content">
                <span class="vmp-admin-stat-label"><?php _e('عمولات معلقة', 'vmp'); ?></span>
                <span class="vmp-admin-stat-value"><?php echo wc_price($pending_commissions); ?></span>
            </div>
        </div>
    </div>

    <!-- الشبكة الرئيسية (رسم بياني + طلبات المراجعة) -->
    <div class="vmp-admin-grid">
        <!-- الرسم البياني -->
        <div class="vmp-admin-card">
            <div class="vmp-admin-card-header">
                <h2>📈 <?php _e('أداء السوق (آخر 6 أشهر)', 'vmp'); ?></h2>
            </div>
            <div class="vmp-admin-card-body">
                <canvas id="vmp-admin-chart" height="280"></canvas>
            </div>
        </div>

        <!-- طلبات المراجعة -->
        <div class="vmp-admin-card">
            <div class="vmp-admin-card-header">
                <h2>⏳ <?php _e('طلبات تحتاج مراجعة', 'vmp'); ?></h2>
                <span class="vmp-admin-badge"><?php echo count($latest_pending_vendors) + count($latest_pending_products); ?></span>
            </div>
            <div class="vmp-admin-card-body" style="padding: 0;">
                <?php if (empty($latest_pending_vendors) && empty($latest_pending_products)) : ?>
                    <div class="vmp-admin-empty-state">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <p><?php _e('كل شيء على ما يرام، لا توجد طلبات معلقة.', 'vmp'); ?></p>
                    </div>
                <?php else : ?>
                    <table class="vmp-admin-table">
                        <thead>
                            <tr>
                                <th><?php _e('النوع', 'vmp'); ?></th>
                                <th><?php _e('الاسم', 'vmp'); ?></th>
                                <th><?php _e('إجراء', 'vmp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latest_pending_vendors as $v) : ?>
                                <tr>
                                    <td><span class="vmp-status vmp-status-pending"><?php _e('بائع', 'vmp'); ?></span></td>
                                    <td><?php echo esc_html($v->store_name); ?></td>
                                    <td><a href="admin.php?page=vmp-vendors" class="button button-small"><?php _e('مراجعة', 'vmp'); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($latest_pending_products as $p) : ?>
                                <tr>
                                    <td><span class="vmp-status vmp-status-pending" style="background:#e0e7ff;color:#4f46e5;"><?php _e('منتج', 'vmp'); ?></span></td>
                                    <td><?php 
                                        $title = get_the_title($p->product_id);
                                        echo $title ? esc_html($title) : __('منتج محذوف', 'vmp');
                                    ?></td>
                                    <td><a href="admin.php?page=vmp-products" class="button button-small"><?php _e('مراجعة', 'vmp'); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- روابط سريعة -->
    <div class="vmp-admin-quick-links">
        <a href="admin.php?page=vmp-vendors" class="vmp-quick-link">
            <span class="dashicons dashicons-store"></span>
            <?php _e('إدارة البائعين', 'vmp'); ?>
        </a>
        <a href="admin.php?page=vmp-products" class="vmp-quick-link">
            <span class="dashicons dashicons-products"></span>
            <?php _e('إدارة المنتجات', 'vmp'); ?>
        </a>
        <a href="admin.php?page=vmp-orders" class="vmp-quick-link">
            <span class="dashicons dashicons-cart"></span>
            <?php _e('الطلبات', 'vmp'); ?>
        </a>
        <a href="admin.php?page=vmp-commissions" class="vmp-quick-link">
            <span class="dashicons dashicons-chart-pie"></span>
            <?php _e('العمولات', 'vmp'); ?>
        </a>
        <a href="admin.php?page=vmp-withdrawals" class="vmp-quick-link">
            <span class="dashicons dashicons-money-alt"></span>
            <?php _e('السحوبات', 'vmp'); ?>
        </a>
        <a href="admin.php?page=vmp-settings" class="vmp-quick-link">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('الإعدادات', 'vmp'); ?>
        </a>
        <a href="admin.php?page=vmp-subscriptions" class="vmp-quick-link">
            <span class="dashicons dashicons-tag"></span>
            <?php _e('خطط الاشتراك', 'vmp'); ?>
        </a>
    </div>
</div>

<!-- JavaScript للرسم البياني -->
<script>
jQuery(document).ready(function($) {
    var ctx = document.getElementById('vmp-admin-chart');
    if (ctx && typeof Chart !== 'undefined') {
        var chartData = <?php 
            $labels = [];
            $earnings = [];
            $commissions = [];
            $orders = [];
            foreach ($chart_data as $row) {
                $months_ar = ['01' => 'يناير', '02' => 'فبراير', '03' => 'مارس', '04' => 'أبريل', '05' => 'مايو', '06' => 'يونيو', '07' => 'يوليو', '08' => 'أغسطس', '09' => 'سبتمبر', '10' => 'أكتوبر', '11' => 'نوفمبر', '12' => 'ديسمبر'];
                list($year, $month) = explode('-', $row->month);
                $labels[] = ($months_ar[$month] ?? $month) . ' ' . $year;
                $earnings[] = (float) $row->earnings;
                $commissions[] = (float) $row->commissions;
                $orders[] = (int) $row->orders;
            }
            echo json_encode([
                'labels' => $labels,
                'earnings' => $earnings,
                'commissions' => $commissions,
                'orders' => $orders,
            ]);
        ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: '<?php _e('أرباح البائعين', 'vmp'); ?>',
                    data: chartData.earnings,
                    backgroundColor: 'rgba(99,102,241,0.65)',
                    borderColor: '#6366f1',
                    borderWidth: 2,
                    borderRadius: 6
                }, {
                    label: '<?php _e('عمولات الموقع', 'vmp'); ?>',
                    data: chartData.commissions,
                    backgroundColor: 'rgba(16,185,129,0.65)',
                    borderColor: '#10b981',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: 'Cairo', size: 12 },
                            usePointStyle: true,
                            boxWidth: 6
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: { size: 11 },
                            callback: function(value) {
                                return value.toFixed(0);
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: { size: 11 }
                        }
                    }
                }
            }
        });
    }
});
</script>

<style>
/* ── رأس الصفحة ── */
.vmp-admin-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e2e8f0;
}
.vmp-admin-header h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
}
.vmp-admin-subtitle {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 14px;
}
.vmp-admin-date {
    background: #f1f5f9;
    padding: 6px 16px;
    border-radius: 9999px;
    font-size: 13px;
    color: #475569;
}

/* ── بطاقات الإحصائيات ── */
.vmp-admin-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}
.vmp-admin-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.2s;
}
.vmp-admin-stat-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.vmp-admin-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.vmp-admin-stat-icon .dashicons {
    font-size: 28px;
    width: 28px;
    height: 28px;
}
.vmp-admin-stat-content {
    display: flex;
    flex-direction: column;
}
.vmp-admin-stat-label {
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
}
.vmp-admin-stat-value {
    font-size: 26px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}
.vmp-admin-stat-change {
    font-size: 12px;
    font-weight: 500;
    margin-top: 2px;
}
.vmp-stat-change-success { color: #10b981; }
.vmp-stat-change-warning { color: #f59e0b; }
.vmp-stat-change-info { color: #6366f1; }
.vmp-stat-change-danger { color: #ef4444; }

/* ── الشبكة الرئيسية ── */
.vmp-admin-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    margin-bottom: 28px;
}
.vmp-admin-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.vmp-admin-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.vmp-admin-card-header h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
}
.vmp-admin-badge {
    background: #6366f1;
    color: #fff;
    padding: 2px 12px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
}
.vmp-admin-card-body {
    padding: 20px;
}

/* ── حالة فارغة ── */
.vmp-admin-empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
}
.vmp-admin-empty-state .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #cbd5e1;
    display: block;
    margin: 0 auto 12px;
}
.vmp-admin-empty-state p {
    margin: 0;
    font-size: 14px;
}

/* ── جدول المراجعة ── */
.vmp-admin-table {
    width: 100%;
    border-collapse: collapse;
}
.vmp-admin-table thead th {
    background: #f8fafc;
    padding: 10px 16px;
    text-align: right;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}
.vmp-admin-table tbody td {
    padding: 10px 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    vertical-align: middle;
}
.vmp-admin-table tbody tr:last-child td {
    border-bottom: none;
}
.vmp-admin-table .button-small {
    font-size: 11px;
    padding: 2px 12px;
    min-height: 28px;
}

/* ── روابط سريعة ── */
.vmp-admin-quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 16px 0 4px;
    border-top: 1px solid #e2e8f0;
}
.vmp-quick-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: #475569;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}
.vmp-quick-link:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #0f172a;
}
.vmp-quick-link .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* ── استجابة ── */
@media (max-width: 1200px) {
    .vmp-admin-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    .vmp-admin-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    .vmp-admin-stats {
        grid-template-columns: 1fr;
    }
    .vmp-admin-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    .vmp-admin-quick-links {
        flex-direction: column;
        align-items: stretch;
    }
    .vmp-quick-link {
        justify-content: center;
    }
}
</style>