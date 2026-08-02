<?php
namespace VMP\Database\Migrations;

class CreateVendorTables {
    public static function up(): void {
        $base = __DIR__;

        $files = [
            $base . '/001_create_vendor_requests_table.php',
            $base . '/002_create_vendor_request_logs_table.php',
            $base . '/003_create_vendor_stores_table.php',
            $base . '/004_create_vendor_verifications_table.php',
            $base . '/005_create_vendor_settings_table.php',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                include_once $file;
                // each migration file defines and calls its migration function on include
            }
        }
    }
}
