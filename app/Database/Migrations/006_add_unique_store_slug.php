<?php
// Migration: add UNIQUE index on store_slug to prevent duplicate store slugs
// Runable on activation/migrations runner
global $wpdb;
$table = $wpdb->prefix . 'vmp_vendor_stores';
$index = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", 'uniq_store_slug'));
if (empty($index)) {
    $sql = "ALTER TABLE {$table} ADD UNIQUE KEY uniq_store_slug (store_slug(191))"; // length for utf8mb4
    $wpdb->query($sql);
}
