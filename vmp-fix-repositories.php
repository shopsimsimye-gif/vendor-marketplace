<?php
/**
 * VMP Repository Fixes - Run this on the server
 * Fixes: ProductRepository, CommissionRepository, WithdrawalRepository
 */

$base = '/opt/1panel/apps/wordpress/wordpress/data/wp-content/plugins/vendor-marketplace/';
$fixed = 0;

// --- Fix 1: ProductRepository::create() ---
$f = $base . 'app/Repositories/ProductRepository.php';
if (file_exists($f)) {
    $c = file_get_contents($f);
    $old = "public function create(int \$vendor_id, int \$product_id, array \$data): int|false\n    {\n        global \$wpdb;\n\n        \$result = \$wpdb->insert("";
    $new = "public function create(int \$vendor_id, int \$product_id, array \$data): int|false|\\WP_Error\n    {\n        global \$wpdb;\n\n        \$subscription = \\VMP\\Modules\\Subscription::get_vendor_subscription(\$vendor_id);\n        \$plan = \$subscription ? \\VMP\\Modules\\Subscription::get_plan(\$subscription->plan_id) : null;\n        \$limit = \$plan ? (int)\$plan->product_limit : 10;\n        if (\$limit > 0) {\n            \$current = \$wpdb->get_var(\$wpdb->prepare(\"SELECT COUNT(*) FROM {\$this->table} WHERE vendor_id = %d AND status != 'deleted'\", \$vendor_id));\n            if ((int)\$current >= \$limit) return new \\WP_Error('product_limit_reached', __('Product limit reached.', 'vmp'));\n        }\n\n        \$result = \$wpdb->insert("";
    if (strpos($c, $old) !== false) {
        $c = str_replace($old, $new, $c);
        file_put_contents($f, $c);
        echo "[OK] ProductRepository::create() fixed\n";
        $fixed++;
    } else {
        echo "[SKIP] ProductRepository pattern not found (already fixed?)\n";
    }
} else {
    echo "[ERR] ProductRepository.php not found\n";
}

// --- Fix 2: CommissionRepository::markAsPaid() ---
$f = $base . 'app/Repositories/CommissionRepository.php';
if (file_exists($f)) {
    $c = file_get_contents($f);
    $old = "public function markAsPaid(int \$id): bool\n    {\n        global \$wpdb;\n\n        \$result = \$wpdb->update("";
    $new = "public function markAsPaid(int \$id): bool|\\WP_Error\n    {\n        global \$wpdb;\n\n        \$commission = \$this->find(\$id);\n        if (!\$commission) return new \\WP_Error('commission_not_found', __('Commission not found.', 'vmp'));\n        if (\$commission->status === 'paid') return new \\WP_Error('already_paid', __('Commission already paid.', 'vmp'));\n        \$vendor_repo = new \\VMP\\Repositories\\VendorRepository();\n        if (\$vendor_repo->getBalance(\$commission->vendor_id) < \$commission->amount) return new \\WP_Error('insufficient_balance', __('Insufficient vendor balance.', 'vmp'));\n\n        \$result = \$wpdb->update("";
    if (strpos($c, $old) !== false) {
        $c = str_replace($old, $new, $c);
        file_put_contents($f, $c);
        echo "[OK] CommissionRepository::markAsPaid() fixed\n";
        $fixed++;
    } else {
        echo "[SKIP] CommissionRepository pattern not found (already fixed?)\n";
    }
} else {
    echo "[ERR] CommissionRepository.php not found\n";
}

// --- Fix 3: WithdrawalRepository::create() ---
$f = $base . 'app/Repositories/WithdrawalRepository.php';
if (file_exists($f)) {
    $c = file_get_contents($f);
    $old = "public function create(int \$vendor_id, float \$amount, array \$data): int|false\n    {\n        global \$wpdb;\n\n        \$result = \$wpdb->insert("";
    $new = "public function create(int \$vendor_id, float \$amount, array \$data): int|false|\\WP_Error\n    {\n        global \$wpdb;\n\n        if (\$amount <= 0) return new \\WP_Error('invalid_amount', __('Withdrawal amount must be greater than zero.', 'vmp'));\n        \$vendor_repo = new \\VMP\\Repositories\\VendorRepository();\n        if (\$vendor_repo->getBalance(\$vendor_id) < \$amount) return new \\WP_Error('insufficient_balance', __('Insufficient balance.', 'vmp'));\n\n        \$result = \$wpdb->insert("";
    if (strpos($c, $old) !== false) {
        $c = str_replace($old, $new, $c);
        file_put_contents($f, $c);
        echo "[OK] WithdrawalRepository::create() fixed\n";
        $fixed++;
    } else {
        echo "[SKIP] WithdrawalRepository pattern not found (already fixed?)\n";
    }
} else {
    echo "[ERR] WithdrawalRepository.php not found\n";
}

echo "\n=== Done: $fixed file(s) fixed ===\n";
