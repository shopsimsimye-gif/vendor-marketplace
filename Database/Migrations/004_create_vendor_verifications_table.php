<?php
/**
 * Migrations: 004_create_vendor_verifications_table
 */
function vmp_migration_004() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vmp_vendor_verifications';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        vendor_id BIGINT UNSIGNED NOT NULL,
        type ENUM('email','phone','identity','license') NOT NULL,
        status ENUM('pending','verified','failed') DEFAULT 'pending',
        verified_at DATETIME,
        metadata LONGTEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_vendor (vendor_id),
        INDEX idx_type (type)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
vmp_migration_004();
