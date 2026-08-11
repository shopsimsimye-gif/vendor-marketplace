<?php
/**
 * Migration: ensure unique store slug on vendor stores table.
 */
function vmp_migration_006() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'vmp_vendor_stores';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
        return;
    }

    $wpdb->query("ALTER TABLE `{$table_name}` ADD UNIQUE KEY `idx_unique_store_slug` (`store_slug`)");
}

vmp_migration_006();
