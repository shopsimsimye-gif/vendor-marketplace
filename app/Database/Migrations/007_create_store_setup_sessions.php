<?php
// Migration: create store setup sessions table
global $wpdb;
$table = $wpdb->prefix . 'vmp_store_setup_sessions';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE IF NOT EXISTS {$table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  vendor_request_id BIGINT UNSIGNED NOT NULL,
  session_uuid VARCHAR(191) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'draft',
  current_step INT NOT NULL DEFAULT 0,
  last_saved_step INT NOT NULL DEFAULT 0,
  payload LONGTEXT NULL,
  completed_steps LONGTEXT NULL,
  started_at DATETIME NULL,
  last_activity_at DATETIME NULL,
  completed_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY (session_uuid),
  KEY user_idx (user_id),
  KEY request_idx (vendor_request_id)
) {$charset_collate};";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql);
