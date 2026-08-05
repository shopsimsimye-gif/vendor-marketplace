<?php
/**
 * Quick Functional Test for VMP
 * Run: cd /var/www/html && wp eval-file wp-content/plugins/vendor-marketplace/tests/quick-functional-test.php
 */
require_once __DIR__ . "/../vendor-marketplace.php";

echo "=== VMP Quick Test ===\n";

// Test 1: Vendor Registration
echo "\n[1] Testing vendor registration...\n";
$vendor_repo = new \VMP\Repositories\VendorRepository();
$suffix = time(); // unique slug per run -> idempotent under store_slug UNIQUE
$test_email = "test_vendor_" . $suffix . "@example.com";
$result = $vendor_repo->create([
    "user_id" => 1,
    "store_name" => "Test Store " . $suffix,
    "email" => $test_email,
    "status" => "pending"
]);
echo $result ? "[PASS] Vendor created: ID $result\n" : "[FAIL] Vendor creation failed\n";

// Test 2: Product Creation (with limit)
echo "\n[2] Testing product creation...\n";
$product_repo = new \VMP\Repositories\ProductRepository();
if ($result) {
    $prod = $product_repo->create($result, 0, ["name" => "Test Product", "price" => 100]);
    if ($prod instanceof \WP_Error) {
        echo "[INFO] Product limit: " . $prod->get_error_message() . "\n";
    } else {
        echo $prod ? "[PASS] Product created: ID $prod\n" : "[FAIL] Product creation failed\n";
    }
}

// Test 3: Balance check
echo "\n[3] Testing balance operations...\n";
if ($result) {
    // updateBalance لا يرمي TypeError على بائع pending (عقد bool بدل WP_Error)
    $pending_ok = ($vendor_repo->updateBalance($result, 100.00) === false);
    // بعد الموافقة يصبح تحديث الرصيد ناجحاً
    $vendor_repo->approve($result);
    $bal_ok = $vendor_repo->updateBalance($result, 100.00);
    $v = $vendor_repo->find($result);
    $balance = $v ? (float) ($v->balance ?? 0) : 0;
    echo $pending_ok ? "[PASS] updateBalance returns false (not WP_Error) on pending vendor\n"
                     : "[WARN] updateBalance did not return false on pending vendor\n";
    echo ($bal_ok && $balance >= 100) ? "[PASS] Balance updated on approved vendor: $balance\n"
                                      : "[FAIL] Balance check failed: $balance\n";
}

echo "\n=== Test Complete ===\n";
