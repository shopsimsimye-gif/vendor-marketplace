<?php

define('ABSPATH', dirname(__DIR__, 2) . '/');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/Support/VirtualStore.php';

use VMP\Support\VirtualStore;

$cases = [
    [
        'server' => ['REQUEST_URI' => '/store/shoeeeee'],
        'get' => [],
        'expected' => 'shoeeeee',
    ],
    [
        'server' => ['REQUEST_URI' => '/store/shoeeeee/'],
        'get' => [],
        'expected' => 'shoeeeee',
    ],
    [
        'server' => ['REQUEST_URI' => '/store/cat'],
        'get' => [],
        'expected' => 'cat',
    ],
    [
        'server' => ['REQUEST_URI' => '/store/shoeeeee/anything'],
        'get' => [],
        'expected' => 'shoeeeee',
    ],
    [
        'server' => ['REQUEST_URI' => '/products'],
        'get' => ['vendor_store' => 'fallback-slug'],
        'expected' => 'fallback-slug',
    ],
];

foreach ($cases as $index => $case) {
    $actual = VirtualStore::resolveVendorSlugFromRequest($case['server'], $case['get']);
    if ($actual !== $case['expected']) {
        fwrite(STDERR, "Routing case {$index} failed: expected {$case['expected']}, got {$actual}\n");
        exit(1);
    }
}

echo "Virtual store routing tests passed\n";
