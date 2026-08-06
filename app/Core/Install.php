<?php
namespace VMP\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Install
 *
 * Handles plugin installation, activation, deactivation, uninstallation,
 * database migrations, and default data creation.
 *
 * @package vendor-marketplace
 */
class Install {

    /**
     * Activate functionality helper.
     *
     * Creates tables, runs migrations, sets up rewrite rules,
     * roles, cron jobs, default data, and default pages.
     *
     * @return void
     */
    public static function activate(): void {
        self::create_tables();
        self::migrate_existing_tables();
        update_option('vmp_db_version', VMP_VERSION);

        add_rewrite_rule('^store/([^/]+)/?$', 'index.php?vendor_store=$matches[1]', 'top');
        flush_rewrite_rules();

        self::setup_roles();
        self::create_cron_jobs();
        self::create_default_data();
        self::create_default_pages();
    }

    /**
     * Deactivate functionality helper.
     *
     * Clears all scheduled cron jobs.
     *
     * @return void
     */
    public static function deactivate(): void {
        wp_clear_scheduled_hook('vmp_check_expired_subscriptions');
        wp_clear_scheduled_hook('vmp_send_subscription_reminders');
        wp_clear_scheduled_hook('vmp_cleanup_logs');
        wp_clear_scheduled_hook('vmp_run_queue');
    }

    /**
     * Upgrade functionality helper.
     *
     * Runs on plugin update to create new tables and migrate existing ones.
     *
     * @return void
     */
    public static function upgrade(): void {
        $installed = get_option('vmp_db_version', '0.0.0');

        self::create_tables();
        self::migrate_existing_tables();

        if (version_compare($installed, VMP_VERSION, '<')) {
            self::setup_roles();
            self::create_cron_jobs();
        }

        update_option('vmp_db_version', VMP_VERSION);
    }

    /**
     * Uninstall functionality helper.
     *
     * Removes all plugin tables, options, and the vendor role.
     *
     * @return void
     */
    public static function uninstall(): void {
        global $wpdb;

        $tables = [
            'vmp_vendors',
            'vmp_vendor_products',
            'vmp_vendor_orders',
            'vmp_commissions',
            'vmp_withdrawals',
            'vmp_subscription_plans',
            'vmp_vendor_subscriptions',
            'vmp_logs',
            'vmp_whatsapp_clicks',
            'vmp_vendor_reviews',
            'vmp_jobs',
            'vmp_ai_jobs',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}{$table}`");
        }

        delete_option('vmp_db_version');
        delete_option('vmp_settings');
        delete_option('vmp_default_commission');
        delete_option('vmp_min_withdrawal');
        delete_option('vmp_notification_settings');

        remove_role('vmp_vendor');
    }

    /**
     * Create all plugin database tables.
     *
     * Uses dbDelta for safe table creation and updates.
     *
     * @return void
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $p = $wpdb->prefix . 'vmp_';

        $tables = [];

        // ── 1. جدول البائعين (vendors) ──
        $tables[] = "CREATE TABLE `{$p}vendors` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `store_name` VARCHAR(255) NOT NULL,
            `store_slug` VARCHAR(255) NOT NULL,
            `store_description` LONGTEXT,
            `store_address` LONGTEXT,
            `store_latitude` DECIMAL(10,8) NULL,
            `store_longitude` DECIMAL(11,8) NULL,
            `store_phone` VARCHAR(50),
            `store_email` VARCHAR(100),
            `store_logo` INT,
            `store_banner` INT,
            `store_video` VARCHAR(500),
            `whatsapp_number` VARCHAR(50),
            `whatsapp_message` LONGTEXT,
            `social_facebook` VARCHAR(255),
            `social_instagram` VARCHAR(255),
            `social_twitter` VARCHAR(255),
            `social_youtube` VARCHAR(255),
            `custom_css` LONGTEXT,
            `status` VARCHAR(20) DEFAULT 'pending',
            `is_trusted` TINYINT(1) DEFAULT 0,
            `rating` DECIMAL(2,1) DEFAULT 0.0,
            `review_count` INT DEFAULT 0,
            `total_products` INT DEFAULT 0,
            `total_orders` INT DEFAULT 0,
            `total_sales` DECIMAL(15,2) DEFAULT 0.00,
            `balance` DECIMAL(15,2) DEFAULT 0.00,
            `subscription_plan` VARCHAR(20) DEFAULT 'free',
            `subscription_status` VARCHAR(20) DEFAULT 'active',
            `subscription_start` DATETIME NULL,
            `subscription_expiry` DATETIME NULL,
            `admin_notes` LONGTEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `store_slug` (`store_slug`),
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_subscription_status` (`subscription_status`),
            INDEX `idx_subscription_plan` (`subscription_plan`),
            PRIMARY KEY (`id`)
        ) {$charset_collate};";

        // ── 2. جدول طلبات انضمام البائعين (vendor_requests) ──
        $tables[] = "CREATE TABLE `{$p}vendor_requests` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `store_name` VARCHAR(255) NOT NULL,
            `store_slug` VARCHAR(255) NOT NULL,
            `store_description` LONGTEXT,
            `store_address` LONGTEXT NOT NULL,
            `store_phone` VARCHAR(50) NOT NULL,
            `store_email` VARCHAR(255) DEFAULT '',
            `whatsapp_number` VARCHAR(50) DEFAULT '',
            `store_logo` BIGINT UNSIGNED DEFAULT 0,
            `store_banner` BIGINT UNSIGNED DEFAULT 0,
            `license_file` BIGINT UNSIGNED DEFAULT 0,
            `plan_id` BIGINT UNSIGNED DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `admin_notes` LONGTEXT,
            `terms_accepted` TINYINT(1) NOT NULL DEFAULT 0,
            `terms_accepted_at` DATETIME DEFAULT NULL,
            `reviewed_at` DATETIME DEFAULT NULL,
            `reviewed_by` BIGINT UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `store_slug` (`store_slug`),
            KEY `user_id` (`user_id`),
            KEY `status` (`status`),
            KEY `created_at` (`created_at`)
        ) {$charset_collate};";

        // ── 3. جدول منتجات البائعين (vendor_products) ──
        $tables[] = "CREATE TABLE `{$p}vendor_products` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `vendor_id` BIGINT UNSIGNED NOT NULL,
            `product_id` BIGINT UNSIGNED NOT NULL,
            `status` VARCHAR(20) DEFAULT 'pending',
            `is_featured` TINYINT(1) DEFAULT 0,
            `admin_notes` LONGTEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_vendor_id` (`vendor_id`),
            INDEX `idx_product_id` (`product_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_vendor_status` (`vendor_id`, `status`),
            INDEX `idx_vendor_created` (`vendor_id`, `created_at`),
            PRIMARY KEY (`id`)
        ) {$charset_collate};";

        // ── 3. جدول طلبات البائعين (vendor_orders) ──
        $tables[] = "CREATE TABLE `{$p}vendor_orders` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `vendor_id` BIGINT UNSIGNED NOT NULL,
            `order_id` BIGINT UNSIGNED NOT NULL,
            `parent_order_id` BIGINT UNSIGNED NOT NULL,
            `status` VARCHAR(20) DEFAULT 'pending',
            `total` DECIMAL(15,2) DEFAULT 0.00,
            `commission` DECIMAL(15,2) DEFAULT 0.00,
            `vendor_earnings` DECIMAL(15,2) DEFAULT 0.00,
            `shipping_cost` DECIMAL(15,2) DEFAULT 0.00,
            `tax` DECIMAL(15,2) DEFAULT 0.00,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_vendor_id` (`vendor_id`),
            INDEX `idx_order_id` (`order_id`),
            INDEX `idx_parent_order` (`parent_order_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_order_vendor` (`order_id`, `vendor_id`),
            INDEX `idx_vendor_status` (`vendor_id`, `status`),
            PRIMARY KEY (`id`)
        ) {$charset_collate};";

        // ── 4. جدول العمولات (commissions) ──
        $tables[] = "CREATE TABLE `{$p}commissions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `vendor_id` BIGINT UNSIGNED NOT NULL,
            `order_id` BIGINT UNSIGNED NOT NULL,
            `vendor_order_id` BIGINT UNSIGNED,
            `product_id` BIGINT UNSIGNED NOT NULL,
            `amount` DECIMAL(15,2) DEFAULT 0.00,
            `commission_rate` DECIMAL(5,2) DEFAULT 10.00,
            `commission_amount` DECIMAL(15,2) DEFAULT 0.00,
            `vendor_amount` DECIMAL(15,2) DEFAULT 0.00,
            `status` VARCHAR(20) DEFAULT 'pending',
            `paid_at` DATETIME NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_vendor_id` (`vendor_id`),
            INDEX `idx_order_id` (`order_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_vendor_order` (`vendor_id`, `order_id`),
            PRIMARY KEY (`id`)
        ) {$charset_collate};";

        // ── 5. جدول السحوبات (withdrawals) ──
        $tables[] = "CREATE TABLE `{$p}withdrawals` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `vendor_id` BIGINT UNSIGNED NOT NULL,
            `amount` DECIMAL(15,2) DEFAULT 0.00,
            `status` VARCHAR(20) DEFAULT 'pending',
            `method` VARCHAR(50) DEFAULT 'bank_transfer',
            `method_details` LONGTEXT,
            `notes` LONGTEXT,
            `processed_by` BIGINT UNSIGNED,
            `processed_at` DATETIME NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_vendor_id` (`vendor_id`),
            INDEX `idx_status` (`status`),
            PRIMARY KEY (`id`)
        ) {$charset_collate};";

        // ── 6. جدول خطط الاشتراك (subscription_plans) ──
        $tables[] = "CREATE TABLE `{$p}subscription_plans` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(100) NOT NULL,
            `description` LONGTEXT,
            `price` DECIMAL(15,2) DEFAULT 0.00,
            `billing_period` VARCHAR(20) DEFAULT 'month',
            `billing_interval` INT DEFAULT 1,
            `max_products` INT DEFAULT 0,
            `commission_rate` DECIMAL(5,2) DEFAULT 10.00,
            `features` LONGTEXT,
            `is_active` TINYINT(1) DEFAULT 1,
            `sort_order` INT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `slug` (`slug`),
            INDEX `idx_active` (`is_active`),
            PRIMARY KEY (`id`)
        ) {$charset_collate};";

        // ── 7. جدول اشتراكات البائعين (vendor_subscriptions) ──
        $tables[] = "CREATE TABLE `{$p}vendor_subscriptions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `vendor_id` BIGINT UNSIGNED NOT NULL,
            `plan_id` BIGINT UNSIGNED NOT NULL,
            `status` VARCHAR(20) DEFAULT 'active',
            `amount` DECIMAL(15,2) DEFAULT 0.00,
            `billing_period` VARCHAR(20) DEFAULT 'month',
            `billing_interval` INT DEFAULT 1,
            `start_date` DATETIME,
            `end_date` DATETIME,
            `trial_end_date` DATETIME,
            `payment_method` VARCHAR(50),
            `payment_details` LONGTEXT,
            `cancelled_at` DATETIME,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_vendor_id` (`vendor_id`),
            INDEX `idx_plan_id` (`plan_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_end_date` (`end_date`),
            INDEX `idx_vendor_status` (`vendor_id`, `status`),
            PRIMARY KEY (`id`)
        ) {$charset_collate};";

        // ── 8. جدول السجلات (logs) ──
        $tables[] = "CREATE TABLE `{$p}logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `level` VARCHAR(20) DEFAULT 'info',
            `message` LONGTEXT,
            `context` LONGTEXT,
            `user_id` BIGINT UNSIGNED,
            `ip_address` VARCHAR(45),
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_level` (`level`),
            INDEX `idx_created` (`created_at`),
            PRIMARY KEY (`id`)
        ) {$charset_collate};";

        // ── 9. جدول نقرات الواتساب (whatsapp_clicks) ──
        $tables[] = "CREATE TABLE `{$p}whatsapp_clicks` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `vendor_id` BIGINT UNSIGNED NOT NULL,
            `product_id` BIGINT UNSIGNED,
            `page_url` VARCHAR(500),
            `click_type` VARCHAR(50) DEFAULT 'button',
            `user_agent` VARCHAR(255),
            `referrer` VARCHAR(500),
            `clicked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_vendor_id` (`vendor_id`),
            INDEX `idx_product_id` (`product_id`),
            INDEX `idx_clicked` (`clicked_at`),
            PRIMARY KEY (`id`)
        ) {$charset_collate};";

        // ── 10. جدول تقييمات البائعين (vendor_reviews) ──
        $tables[] = "CREATE TABLE `{$p}vendor_reviews` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `vendor_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `order_id` BIGINT UNSIGNED,
            `rating` INT DEFAULT 5,
            `title` VARCHAR(255),
            `comment` LONGTEXT,
            `status` VARCHAR(20) DEFAULT 'approved',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_vendor_id` (`vendor_id`),
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_status` (`status`),
            PRIMARY KEY (`id`)
        ) {$charset_collate};";

        // ── 11. جدول مهام الخلفية (jobs) ──
        $tables[] = "CREATE TABLE `{$p}jobs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `job_class` VARCHAR(255) NOT NULL,
            `payload` LONGTEXT NOT NULL,
            `status` VARCHAR(20) DEFAULT 'pending',
            `attempts` INT DEFAULT 0,
            `error_message` LONGTEXT,
            `locked_at` DATETIME NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_status` (`status`),
            INDEX `idx_locked_status` (`status`, `locked_at`),
            PRIMARY KEY (`id`)
        ) {$charset_collate};";

        // ── 12. جدول مهام إنشاء المنتجات بالذكاء الاصطناعي (ai_jobs) ──
        $tables[] = "CREATE TABLE `{$p}ai_jobs` (
            `id` VARCHAR(80) NOT NULL,
            `vendor_id` BIGINT UNSIGNED NOT NULL,
            `attachment_id` BIGINT UNSIGNED DEFAULT 0,
            `workflow` VARCHAR(100) DEFAULT 'product-image-v1',
            `provider` VARCHAR(100) DEFAULT '',
            `capability` VARCHAR(100) DEFAULT 'product_generation',
            `status` VARCHAR(40) DEFAULT 'QUEUED',
            `progress` INT DEFAULT 0,
            `current_step` VARCHAR(60) DEFAULT 'QUEUED',
            `result` LONGTEXT,
            `cost` DECIMAL(15,6) DEFAULT 0.000000,
            `tokens` LONGTEXT,
            `latency` INT DEFAULT 0,
            `retries` INT DEFAULT 0,
            `error` LONGTEXT,
            `logs` LONGTEXT,
            `product_id` BIGINT UNSIGNED DEFAULT 0,
            `vendor_product_id` BIGINT UNSIGNED DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_vendor_id` (`vendor_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_vendor_status` (`vendor_id`, `status`),
            INDEX `idx_created_at` (`created_at`),
            PRIMARY KEY (`id`)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE `{$p}ai_job_locks` (
            `lock_id` VARCHAR(191) NOT NULL,
            `acquired_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at` DATETIME NOT NULL,
            PRIMARY KEY (`lock_id`)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE `{$p}media` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `vendor_id` bigint(20) unsigned NOT NULL,
            `attachment_id` bigint(20) unsigned NOT NULL,
            `folder_id` bigint(20) unsigned NULL,
            `type` varchar(50) NOT NULL DEFAULT 'image',
            `mime_type` varchar(100) NOT NULL,
            `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
            `width` int(10) unsigned NULL,
            `height` int(10) unsigned NULL,
            `metadata` longtext NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_vendor` (`vendor_id`),
            KEY `idx_attachment` (`attachment_id`),
            KEY `idx_folder` (`folder_id`),
            KEY `idx_type` (`type`)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    /**
     * Migrate existing database tables.
     *
     * Safely adds new columns and indexes to existing tables without data loss.
     *
     * @return void
     */
    public static function migrate_existing_tables(): void {
        global $wpdb;

        $p = $wpdb->prefix . 'vmp_';

        // ── 1. ترحيل جدول البائعين (vendors) ──
        self::migrate_table_columns($p . 'vendors', [
            'store_address'     => "ALTER TABLE `%s` ADD `store_address` LONGTEXT NULL",
            'store_latitude'    => "ALTER TABLE `%s` ADD `store_latitude` DECIMAL(10,8) NULL",
            'store_longitude'   => "ALTER TABLE `%s` ADD `store_longitude` DECIMAL(11,8) NULL",
            'store_phone'       => "ALTER TABLE `%s` ADD `store_phone` VARCHAR(50) NULL",
            'store_email'       => "ALTER TABLE `%s` ADD `store_email` VARCHAR(100) NULL",
            'store_logo'        => "ALTER TABLE `%s` ADD `store_logo` INT NULL",
            'store_banner'      => "ALTER TABLE `%s` ADD `store_banner` INT NULL",
            'store_video'       => "ALTER TABLE `%s` ADD `store_video` VARCHAR(500) NULL",
            'whatsapp_number'   => "ALTER TABLE `%s` ADD `whatsapp_number` VARCHAR(50) NULL",
            'whatsapp_message'  => "ALTER TABLE `%s` ADD `whatsapp_message` LONGTEXT NULL",
            'social_facebook'   => "ALTER TABLE `%s` ADD `social_facebook` VARCHAR(255) NULL",
            'social_instagram'  => "ALTER TABLE `%s` ADD `social_instagram` VARCHAR(255) NULL",
            'social_twitter'    => "ALTER TABLE `%s` ADD `social_twitter` VARCHAR(255) NULL",
            'social_youtube'    => "ALTER TABLE `%s` ADD `social_youtube` VARCHAR(255) NULL",
            'custom_css'        => "ALTER TABLE `%s` ADD `custom_css` LONGTEXT NULL",
            'subscription_plan' => "ALTER TABLE `%s` ADD `subscription_plan` VARCHAR(20) DEFAULT 'free'",
            'subscription_status' => "ALTER TABLE `%s` ADD `subscription_status` VARCHAR(20) DEFAULT 'active'",
            'balance'           => "ALTER TABLE `%s` ADD `balance` DECIMAL(15,2) DEFAULT 0.00",
        ]);

        // ── 2. ترحيل جدول اشتراكات البائعين (vendor_subscriptions) ──
        self::migrate_table_columns($p . 'vendor_subscriptions', [
            'amount'            => "ALTER TABLE `%s` ADD `amount` DECIMAL(15,2) DEFAULT 0.00",
            'billing_period'    => "ALTER TABLE `%s` ADD `billing_period` VARCHAR(20) DEFAULT 'month'",
            'billing_interval'  => "ALTER TABLE `%s` ADD `billing_interval` INT DEFAULT 1",
            'trial_end_date'    => "ALTER TABLE `%s` ADD `trial_end_date` DATETIME NULL",
            'payment_method'    => "ALTER TABLE `%s` ADD `payment_method` VARCHAR(50) NULL",
            'payment_details'   => "ALTER TABLE `%s` ADD `payment_details` LONGTEXT NULL",
            'cancelled_at'      => "ALTER TABLE `%s` ADD `cancelled_at` DATETIME NULL",
        ]);

        // ── 3. ترحيل جدول خطط الاشتراك (subscription_plans) ──
        self::migrate_table_columns($p . 'subscription_plans', [
            'features'          => "ALTER TABLE `%s` ADD `features` LONGTEXT NULL",
            'max_products'        => "ALTER TABLE `%s` ADD `max_products` INT DEFAULT 0",
            'commission_rate'     => "ALTER TABLE `%s` ADD `commission_rate` DECIMAL(5,2) DEFAULT 10.00",
            'sort_order'          => "ALTER TABLE `%s` ADD `sort_order` INT DEFAULT 0",
            'is_active'           => "ALTER TABLE `%s` ADD `is_active` TINYINT(1) DEFAULT 1",
            'billing_interval'    => "ALTER TABLE `%s` ADD `billing_interval` INT DEFAULT 1",
        ]);

        // ── 4. ترحيل جدول السحوبات (withdrawals) ──
        self::migrate_table_columns($p . 'withdrawals', [
            'method_details'      => "ALTER TABLE `%s` ADD `method_details` LONGTEXT NULL",
            'notes'               => "ALTER TABLE `%s` ADD `notes` LONGTEXT NULL",
            'processed_by'        => "ALTER TABLE `%s` ADD `processed_by` BIGINT UNSIGNED NULL",
            'processed_at'        => "ALTER TABLE `%s` ADD `processed_at` DATETIME NULL",
        ]);

        // ── 5. إضافة فهارس جديدة ──
        $indexes = [
            $p . 'vendor_products' => [
                'idx_vendor_status'  => '(`vendor_id`, `status`)',
                'idx_vendor_created' => '(`vendor_id`, `created_at`)',
            ],
            $p . 'vendor_orders' => [
                'idx_order_vendor' => '(`order_id`, `vendor_id`)',
                'idx_vendor_status'  => '(`vendor_id`, `status`)',
            ],
            $p . 'commissions' => [
                'idx_vendor_order' => '(`vendor_id`, `order_id`)',
            ],
        ];

        foreach ($indexes as $table => $index_map) {
            self::migrate_table_indexes($table, $index_map);
        }

        // ── 6. ترحيل جدول طلبات البائعين (vendor_requests) ──
        // جعل store_address و store_phone يقبلان قيمة فارغة (NOT NULL مع DEFAULT '')
        $req_table = $p . 'vendor_requests';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $req_table))) {
            $cols = $wpdb->get_results("SHOW COLUMNS FROM `{$req_table}`", ARRAY_A);
            $col_map = array_column($cols, 'Type', 'Field');

            // Fix store_address to allow empty string
            if (isset($col_map['store_address']) && stripos((string)($col_map['store_address'] ?? ''), 'NOT NULL') !== false) {
                $wpdb->query("ALTER TABLE `{$req_table}` MODIFY `store_address` LONGTEXT NOT NULL DEFAULT ''");
            }
            // Fix store_phone to allow empty string
            if (isset($col_map['store_phone'])) {
                $wpdb->query("ALTER TABLE `{$req_table}` MODIFY `store_phone` VARCHAR(50) NOT NULL DEFAULT ''");
            }

            // Add whatsapp_number if missing
            if (!array_key_exists('whatsapp_number', $col_map)) {
                $wpdb->query("ALTER TABLE `{$req_table}` ADD `whatsapp_number` VARCHAR(50) NOT NULL DEFAULT ''");
            }

            // Add store_description if missing
            if (!array_key_exists('store_description', $col_map)) {
                $wpdb->query("ALTER TABLE `{$req_table}` ADD `store_description` LONGTEXT NULL");
            }

            // Ensure store_email allows empty
            if (isset($col_map['store_email'])) {
                $wpdb->query("ALTER TABLE `{$req_table}` MODIFY `store_email` VARCHAR(255) NOT NULL DEFAULT ''");
            }
        }

        update_option('vmp_db_version', VMP_VERSION);
    }

    /**
     * Migrate table columns safely.
     *
     * @param string $table Full table name with prefix.
     * @param array  $columns Map of column name => ALTER TABLE SQL pattern.
     * @return void
     */
    private static function migrate_table_columns(string $table, array $columns): void {
        global $wpdb;

        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
            return;
        }

        $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0);

        if (empty($existing_columns)) {
            return;
        }

        foreach ($columns as $column => $sql_pattern) {
            if (!in_array($column, $existing_columns, true)) {
                $sql = sprintf($sql_pattern, $table);
                $result = $wpdb->query($sql);

                if ($result === false) {
                    self::log_migration_error($table, $wpdb->last_error);
                }
            }
        }
    }

    /**
     * Migrate table indexes safely.
     *
     * @param string $table Full table name with prefix.
     * @param array  $indexes Map of index name => column definition.
     * @return void
     */
    private static function migrate_table_indexes(string $table, array $indexes): void {
        global $wpdb;

        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
            return;
        }

        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
        $existing_names = array_unique(array_column($existing_indexes, 'Key_name'));

        foreach ($indexes as $index_name => $column_def) {
            if (!in_array($index_name, $existing_names, true)) {
                $sql = "ALTER TABLE `{$table}` ADD INDEX `{$index_name}` {$column_def}";
                $result = $wpdb->query($sql);

                if ($result === false) {
                    self::log_migration_error($table, $wpdb->last_error);
                }
            }
        }
    }

    /**
     * Log migration error.
     *
     * @param string $table Table name where error occurred.
     * @param string $error Error message.
     * @return void
     */
    private static function log_migration_error(string $table, string $error): void {
        $message = sprintf('[VMP] Migration Error [%s]: %s', $table, $error);

        if (class_exists('\\VMP\\Core\\Logger')) {
            \VMP\Core\Logger::error($message);
        } else {
            error_log($message);
        }
    }

    /**
     * Setup user roles and capabilities.
     *
     * Creates the vendor role and adds admin capabilities.
     *
     * @return void
     */
    private static function setup_roles(): void {
        // ── دور البائع ──
        $vendor_role = get_role('vmp_vendor');

        if (!$vendor_role) {
            add_role('vmp_vendor', __('بائع', 'vmp'), [
                'read'                      => true,
                'upload_files'              => true,
                'vmp_vendor'                => true,
                'vmp_vendor_products'       => true,
                'vmp_vendor_orders'         => true,
                'vmp_vendor_withdrawals'    => true,
                'vmp_vendor_reports'        => true,
                'vmp_vendor_subscription'   => true,
            ]);
        } else {
            $vendor_role->add_cap('upload_files');
        }

        // ── دور المشرف (أدمن الموقع) ──
        $admin_caps = [
            'vmp_manage_vendors',
            'vmp_manage_products',
            'vmp_manage_orders',
            'vmp_manage_commissions',
            'vmp_manage_withdrawals',
            'vmp_manage_reports',
            'vmp_manage_settings',
            'vmp_manage_subscriptions',
            'manage_vmp_requests',
        ];

        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($admin_caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }

        // ── دور مدير المتجر (Shop Manager) — يحصل على نفس صلاحيات الإدارة ──
        $shop_manager_role = get_role('shop_manager');
        if ($shop_manager_role) {
            foreach ($admin_caps as $cap) {
                $shop_manager_role->add_cap($cap);
            }
        }
    }

    /**
     * [QA 2026-08-06] Self-heal: إعادة منح صلاحيات VMP الإدارية لمستخدمي دور Shop Manager.
     *
     * كانت الصلاحيات تُمنح فقط عند تفعيل/ترقية الإضافة (setup_roles)، فيفقدها
     * shop_manager بعد أي sync/نشر متأخر. هذه الدالة تُستدعى من init وتعيد المنح
     * مرة واحدة فقط عند الفقدان (has_cap guard) فلا تسبب كتابة DB متكررة.
     *
     * @return void
     */
    public static function ensure_shop_manager_caps(): void {
        $role = get_role('shop_manager');
        if (!$role) {
            return;
        }

        $caps = [
            'vmp_manage_vendors',
            'vmp_manage_products',
            'vmp_manage_orders',
            'vmp_manage_commissions',
            'vmp_manage_withdrawals',
            'vmp_manage_reports',
            'vmp_manage_settings',
            'vmp_manage_subscriptions',
            'manage_vmp_requests',
        ];

        foreach ($caps as $cap) {
            if (!$role->has_cap($cap)) {
                $role->add_cap($cap, true);
            }
        }
    }

    /**
     * Create cron jobs.
     *
     * Schedules daily and weekly maintenance tasks.
     *
     * @return void
     */
    private static function create_cron_jobs(): void {
        if (!wp_next_scheduled('vmp_check_expired_subscriptions')) {
            wp_schedule_event(time(), 'daily', 'vmp_check_expired_subscriptions');
        }

        if (!wp_next_scheduled('vmp_send_subscription_reminders')) {
            wp_schedule_event(time(), 'daily', 'vmp_send_subscription_reminders');
        }

        if (!wp_next_scheduled('vmp_cleanup_logs')) {
            wp_schedule_event(time(), 'weekly', 'vmp_cleanup_logs');
        }

        if (!wp_next_scheduled('vmp_run_queue')) {
            wp_schedule_event(time(), 'every_minute', 'vmp_run_queue');
        }
    }

    /**
     * Create default subscription plans and settings.
     *
     * @return void
     */
    private static function create_default_data(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'vmp_subscription_plans';

        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
            return;
        }

        // ── الخطة المجانية (Free) ──
        $free_exists = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE slug = %s", 'free')
        );

        if ($free_exists === 0) {
            $wpdb->insert($table, [
                'name'              => 'مجاني',
                'slug'              => 'free',
                'description'       => 'الخطة المجانية الأساسية',
                'price'             => 0.00,
                'billing_period'    => 'month',
                'billing_interval'  => 1,
                'max_products'      => 10,
                'commission_rate'   => 15.00,
                'features'          => wp_json_encode([
                    'whatsapp_button'       => true,
                    'store_address'         => false,
                    'social_links'          => false,
                    'product_video'         => false,
                    'unlimited_products'    => false,
                    'custom_domain'         => false,
                    'advanced_analytics'    => false,
                    'trusted_badge'         => false,
                    'coupons'               => false,
                ]),
                'sort_order'        => 0,
            ]);
        }

        // ── الخطة المميزة (Premium) ──
        $premium_exists = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE slug = %s", 'premium')
        );

        if ($premium_exists === 0) {
            $wpdb->insert($table, [
                'name'              => 'مميزة',
                'slug'              => 'premium',
                'description'       => 'خطة متقدمة مع جميع الميزات',
                'price'             => 49.99,
                'billing_period'    => 'month',
                'billing_interval'  => 1,
                'max_products'      => -1,
                'commission_rate'   => 5.00,
                'features'          => wp_json_encode([
                    'whatsapp_button'       => true,
                    'store_address'         => true,
                    'social_links'          => true,
                    'product_video'         => true,
                    'unlimited_products'    => true,
                    'custom_domain'         => true,
                    'advanced_analytics'    => true,
                    'trusted_badge'         => true,
                    'coupons'               => true,
                ]),
                'sort_order'        => 1,
            ]);
        }

        // ── الإعدادات الافتراضية ──
        if (!get_option('vmp_default_commission')) {
            update_option('vmp_default_commission', 10);
        }

        if (!get_option('vmp_min_withdrawal')) {
            update_option('vmp_min_withdrawal', 50);
        }

        if (!get_option('vmp_notification_settings')) {
            update_option('vmp_notification_settings', [
                'email_vendor_registered'   => true,
                'email_vendor_approved'     => true,
                'email_vendor_rejected'     => true,
                'email_product_approved'    => true,
                'email_order_placed'        => true,
                'admin_email'               => get_option('admin_email'),
            ]);
        }
    }

    /**
     * Create default plugin pages.
     *
     * Creates registration and dashboard pages with shortcodes.
     *
     * @return void
     */
    private static function create_default_pages(): void {
        $settings = get_option('vmp_settings', []);

        if (!is_array($settings)) {
            $settings = [];
        }

        if (!isset($settings['display']) || !is_array($settings['display'])) {
            $settings['display'] = [];
        }

        // ── صفحة تسجيل البائع ──
        $register_page = get_page_by_path('vendor-register');

        if (!$register_page) {
            $register_id = wp_insert_post([
                'post_title'     => 'تسجيل بائع',
                'post_name'      => 'vendor-register',
                'post_content'   => '[vmp_vendor_register]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
            ]);

            if ($register_id && !is_wp_error($register_id)) {
                $settings['display']['register_page'] = $register_id;
            }
        } else {
            $settings['display']['register_page'] = $register_page->ID;
        }

        // ── صفحة لوحة تحكم البائع ──
        $dashboard_page = get_page_by_path('vendor-dashboard');

        if (!$dashboard_page) {
            $dashboard_id = wp_insert_post([
                'post_title'     => 'لوحة تحكم البائع',
                'post_name'      => 'vendor-dashboard',
                'post_content'   => '[vmp_vendor_dashboard]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
            ]);

            if ($dashboard_id && !is_wp_error($dashboard_id)) {
                $settings['display']['dashboard_page'] = $dashboard_id;
            }
        } else {
            $settings['display']['dashboard_page'] = $dashboard_page->ID;
        }

        update_option('vmp_settings', $settings);
    }
}
