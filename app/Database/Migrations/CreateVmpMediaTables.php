<?php

declare(strict_types=1);

namespace VMP\Database\Migrations;

defined('ABSPATH') || exit;

use VMP\Core\Migration;

class CreateVmpMediaTables extends Migration
{
    public static function up(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Media folders table
        $folders_table = $wpdb->prefix . 'vmp_media_folders';
        $sql_folders = "CREATE TABLE $folders_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT UNSIGNED NOT NULL,
            parent_id BIGINT UNSIGNED DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_vendor_parent (vendor_id, parent_id),
            KEY idx_vendor_path (vendor_id, path(255)),
            KEY idx_slug (slug)
        ) $charset_collate;";

        // Media storage configurations table
        $storage_table = $wpdb->prefix . 'vmp_media_storage';
        $sql_storage = "CREATE TABLE $storage_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            config LONGTEXT DEFAULT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_vendor_type (vendor_id, type),
            KEY idx_vendor_default (vendor_id, is_default)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_folders);
        dbDelta($sql_storage);
    }

    public static function down(): void
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}vmp_media_folders");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}vmp_media_storage");
    }
}
