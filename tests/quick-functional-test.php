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
$test_email = "test_vendor_" . time() . "@example.com";
$result = $vendor_repo->create([
    "user_id" => 1,
    "store_name" => "Test Store",
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
    $balance = $vendor_repo->getBalance($result);
    echo "[INFO] Balance: $balance\n";
}

echo "\n=== Test Complete ===\n";
