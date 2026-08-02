<?php
/**
 * Migrations: 003_create_vendor_stores_table
 */
function vmp_migration_003() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vmp_vendor_stores';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        vendor_id BIGINT UNSIGNED NOT NULL,
        store_name VARCHAR(255),
        store_slug VARCHAR(255),
        description LONGTEXT,
        logo VARCHAR(255),
        banner VARCHAR(255),
        theme_settings LONGTEXT,
        store_url VARCHAR(255),
        address TEXT,
        policies LONGTEXT,
        shipping_config LONGTEXT,
        payment_config LONGTEXT,
        social_links LONGTEXT,
        setup_completed TINYINT DEFAULT 0,
        is_active TINYINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_slug (store_slug),
        INDEX idx_vendor (vendor_id)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
vmp_migration_003();
