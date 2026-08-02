<?php
/**
 * VMP Emergency Fix Script
 * Place this file temporarily in /wp-content/plugins/vendor-marketplace/
 * Access it via: https://xxx.local/wp-content/plugins/vendor-marketplace/vmp-fix.php
 * DELETE after use!
 */

// Bootstrap WordPress
$wp_root = dirname(__FILE__, 4); // go up to wp-content/../
if (!file_exists($wp_root . '/wp-load.php')) {
    $wp_root = dirname(__FILE__, 5);
}
require_once $wp_root . '/wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('Not authorized');
}

global $wpdb;
$table = $wpdb->prefix . 'vmp_vendor_requests';
$messages = [];

echo '<!DOCTYPE html><html dir="rtl"><head><meta charset="utf-8"><title>VMP Fix</title>';
echo '<style>body{font-family:Arial;padding:20px;direction:rtl} .ok{color:green} .err{color:red} .info{color:#555} pre{background:#f5f5f5;padding:10px;border-radius:4px}</style></head><body>';
echo '<h1>🔧 VMP إصلاح طارئ</h1>';

// ── 1. Show table structure ──
echo '<h2>1. هيكل جدول vmp_vendor_requests</h2>';
$cols = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);
if (empty($cols)) {
    echo '<p class="err">❌ الجدول غير موجود!</p>';
} else {
    echo '<pre>';
    foreach ($cols as $c) {
        echo sprintf("%-25s | %-20s | Null=%-3s | Default=%s\n", $c['Field'], $c['Type'], $c['Null'], $c['Default'] ?? 'NULL');
    }
    echo '</pre>';
}

// ── 2. Fix store_address (NOT NULL without DEFAULT) ──
echo '<h2>2. إصلاح حقول الجدول</h2>';
$fixed = [];

$result = $wpdb->query("ALTER TABLE `{$table}` MODIFY `store_address` LONGTEXT NOT NULL DEFAULT ''");
if ($result !== false) {
    $fixed[] = '<span class="ok">✅ store_address → NOT NULL DEFAULT \'\'</span>';
} else {
    $fixed[] = '<span class="info">ℹ️ store_address: ' . $wpdb->last_error . '</span>';
}

$result = $wpdb->query("ALTER TABLE `{$table}` MODIFY `store_phone` VARCHAR(50) NOT NULL DEFAULT ''");
if ($result !== false) {
    $fixed[] = '<span class="ok">✅ store_phone → NOT NULL DEFAULT \'\'</span>';
} else {
    $fixed[] = '<span class="info">ℹ️ store_phone: ' . $wpdb->last_error . '</span>';
}

$result = $wpdb->query("ALTER TABLE `{$table}` MODIFY `store_email` VARCHAR(255) NOT NULL DEFAULT ''");
if ($result !== false) {
    $fixed[] = '<span class="ok">✅ store_email → NOT NULL DEFAULT \'\'</span>';
} else {
    $fixed[] = '<span class="info">ℹ️ store_email: ' . $wpdb->last_error . '</span>';
}

// Add whatsapp_number if missing
$col_names = array_column($cols, 'Field');
if (!in_array('whatsapp_number', $col_names)) {
    $wpdb->query("ALTER TABLE `{$table}` ADD `whatsapp_number` VARCHAR(50) NOT NULL DEFAULT ''");
    $fixed[] = '<span class="ok">✅ أُضيف عمود whatsapp_number</span>';
}
if (!in_array('store_description', $col_names)) {
    $wpdb->query("ALTER TABLE `{$table}` ADD `store_description` LONGTEXT NULL AFTER `store_slug`");
    $fixed[] = '<span class="ok">✅ أُضيف عمود store_description</span>';
}

echo '<ul>' . implode('', array_map(fn($m) => "<li>$m</li>", $fixed)) . '</ul>';

// ── 3. Show current rows ──
echo '<h2>3. الطلبات الموجودة في الجدول</h2>';
$count = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
echo "<p>إجمالي السجلات: <strong>{$count}</strong></p>";

$rows = $wpdb->get_results("SELECT id, user_id, store_name, store_slug, store_phone, store_address, status, created_at FROM `{$table}` ORDER BY id DESC LIMIT 10");
if ($rows) {
    echo '<table border="1" cellpadding="5" style="border-collapse:collapse;width:100%">';
    echo '<tr><th>ID</th><th>user_id</th><th>store_name</th><th>store_slug</th><th>phone</th><th>status</th><th>created_at</th></tr>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td>' . esc_html($r->id) . '</td>';
        echo '<td>' . esc_html($r->user_id) . '</td>';
        echo '<td>' . esc_html($r->store_name) . '</td>';
        echo '<td>' . esc_html($r->store_slug) . '</td>';
        echo '<td>' . esc_html($r->store_phone) . '</td>';
        echo '<td><strong>' . esc_html($r->status) . '</strong></td>';
        echo '<td>' . esc_html($r->created_at) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p class="err">❌ لا توجد سجلات في الجدول. المشكلة في INSERT.</p>';
}

// ── 4. Fix Admin Capabilities ──
echo '<h2>4. إصلاح صلاحيات مدير الموقع</h2>';
$admin_role = get_role('administrator');
$vmp_caps = [
    'vmp_manage_vendors', 'vmp_manage_products', 'vmp_manage_orders',
    'vmp_manage_commissions', 'vmp_manage_withdrawals', 'vmp_manage_reports',
    'vmp_manage_settings', 'vmp_manage_subscriptions', 'manage_vmp_requests',
];
foreach ($vmp_caps as $cap) {
    $admin_role->add_cap($cap);
    echo '<span class="ok">✅ ' . $cap . '</span><br>';
}

// ── 5. Test INSERT ──
echo '<h2>5. اختبار INSERT جديد</h2>';
$test_slug = 'test-fix-' . time();
$test_result = $wpdb->insert($table, [
    'user_id'       => 1,
    'store_name'    => 'اختبار الإصلاح',
    'store_slug'    => $test_slug,
    'store_description' => '',
    'store_address' => 'اختبار',
    'store_phone'   => '0500000000',
    'store_email'   => 'test@test.com',
    'whatsapp_number' => '',
    'store_logo'    => 0,
    'store_banner'  => 0,
    'license_file'  => 0,
    'plan_id'       => 0,
    'status'        => 'test',
    'admin_notes'   => '',
    'terms_accepted'=> 1,
    'terms_accepted_at' => current_time('mysql'),
    'created_at'    => current_time('mysql'),
    'updated_at'    => current_time('mysql'),
]);

if ($test_result !== false) {
    $test_id = $wpdb->insert_id;
    echo '<p class="ok">✅ INSERT نجح! ID = ' . $test_id . '</p>';
    // Delete test row
    $wpdb->delete($table, ['id' => $test_id]);
    echo '<p class="info">تم حذف سجل الاختبار.</p>';
} else {
    echo '<p class="err">❌ INSERT فشل: ' . $wpdb->last_error . '</p>';
}

// ── 6. Reset DB version to force migration ──
update_option('vmp_db_version', '0.0.0');
echo '<h2>6. إعادة تعيين إصدار قاعدة البيانات لتشغيل الترحيل تلقائياً</h2>';
echo '<p class="ok">✅ تم إعادة تعيين vmp_db_version → سيتم تشغيل الترحيل عند دخولك للوحة الإدارة.</p>';

echo '<hr><p class="err"><strong>⚠️ احذف هذا الملف فوراً بعد الانتهاء!</strong></p>';
echo '</body></html>';
