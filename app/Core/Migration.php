<?php
namespace VMP\Core;

defined('ABSPATH') || exit;

/**
 * Migration runner for vendor marketplace tables
 */
class Migration
{
    /**
     * Run all migrations
     */
    public static function run(): void
    {
        global $wpdb;
        
        $charsetCollate = $wpdb->get_charset_collate();
        $tablePrefix = $wpdb->prefix;
        
        // Vendor Requests Table
        $requestsTable = $tablePrefix . 'vmp_vendor_requests';
        $sql = "CREATE TABLE $requestsTable (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            store_name varchar(255) NOT NULL,
            store_slug varchar(255) NOT NULL,
            store_description longtext,
            store_address longtext NOT NULL,
            store_phone varchar(50) NOT NULL,
            store_email varchar(255) DEFAULT '',
            whatsapp_number varchar(50) DEFAULT '',
            store_logo bigint(20) unsigned DEFAULT 0,
            store_banner bigint(20) unsigned DEFAULT 0,
            license_file bigint(20) unsigned DEFAULT 0,
            plan_id bigint(20) unsigned DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            admin_notes longtext,
            terms_accepted tinyint(1) NOT NULL DEFAULT 0,
            terms_accepted_at datetime DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            reviewed_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY store_slug (store_slug),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charsetCollate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Add indexes if missing
        $wpdb->query("ALTER IGNORE TABLE $requestsTable ADD UNIQUE KEY store_slug (store_slug)");
        $wpdb->query("ALTER IGNORE TABLE $requestsTable ADD KEY user_id (user_id)");
        $wpdb->query("ALTER IGNORE TABLE $requestsTable ADD KEY status (status)");
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public static function drop(): void
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}vmp_vendor_requests");
    }
}