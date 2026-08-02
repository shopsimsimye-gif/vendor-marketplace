<?php
namespace VMP\Modules\VendorRegistration\Listeners;

use VMP\Modules\VendorRegistration\Events\VendorApproved;

class CreateStoreOnApproval {
    public function handle(VendorApproved $event): void {
        // Minimal skeleton: create store record in wp_vmp_vendor_stores
        global $wpdb;
        $table = $wpdb->prefix . 'vmp_vendor_stores';
        $vendorId = $event->request->user_id ?? 0;
        $slug = sanitize_title($event->request->username ?? 'vendor-' . $vendorId);
        // ensure unique slug (append id if exists)
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE store_slug = %s", $slug));
        if ($exists) {
            $slug .= '-' . $vendorId;
        }
        $wpdb->insert($table, [
            'vendor_id' => $vendorId,
            'store_name' => $event->request->username ?? 'Store',
            'store_slug' => $slug,
            'store_url' => home_url('/vendor-store/' . $slug),
            'is_active' => 0,
        ]);
    }
}

// Register listener to the WP action fired on approval
add_action('vmp_vendor_approved', function($event) {
    try {
        $listener = new CreateStoreOnApproval();
        if ($event instanceof VendorApproved) {
            $listener->handle($event);
        }
    } catch (\Throwable $e) {
        error_log('CreateStoreOnApproval failed: ' . $e->getMessage());
    }
});
