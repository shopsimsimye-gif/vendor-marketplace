<?php
// Migration: create activity logs table
global $wpdb;
$table = $wpdb->prefix . 'vmp_activity_logs';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE IF NOT EXISTS {$table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(191) NOT NULL,
  meta LONGTEXT NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY user_idx (user_id)
) {$charset_collate};";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql);
