<?php
namespace VMP\Modules\VendorRegistration\Admin;

// Registers admin menu and page for Vendor Requests review
add_action('admin_menu', function() {
    add_menu_page(
        __('Vendor Marketplace', 'vmp'),
        __('Vendor Marketplace', 'vmp'),
        'manage_vmp_requests',
        'vmp_dashboard',
        '\VMP\Modules\VendorRegistration\Admin\render_requests_page',
        'dashicons-store',
        56
    );

    add_submenu_page('vmp_dashboard', __('Vendor Requests', 'vmp'), __('Vendor Requests', 'vmp'), 'manage_vmp_requests', 'vmp_requests', '\VMP\Modules\VendorRegistration\Admin\render_requests_page');
});

add_action('admin_enqueue_scripts', function($hook) {
    // only enqueue on our pages
    if (strpos($hook, 'vmp') === false) return;

    $base = plugin_dir_url(__FILE__) . '/../../../assets/admin/';
    wp_enqueue_style('vmp-admin-review', $base . 'css/review.css', [], '1.1');
    wp_enqueue_script('vmp-admin-review', $base . 'js/review.js', ['wp-api-fetch', 'jquery'], '1.1', true);

    // pass REST root and nonce
    wp_localize_script('vmp-admin-review', 'VMP_Admin_Settings', [
        'restRoot' => esc_url_raw(rest_url('vmp/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
});

function render_requests_page()
{
    ?>
    <div class="wrap vmp-admin-wrap">
      <h1><?php esc_html_e('Vendor Requests', 'vmp'); ?></h1>

      <div class="vmp-top-controls">
        <input id="vmp-search" placeholder="Search requests..." />

        <select id="vmp-filter-status"><option value="">All statuses</option><option value="pending">Pending</option><option value="activated">Activated</option><option value="rejected">Rejected</option></select>

        <button id="vmp-bulk-activate" class="vmp-btn">Bulk Activate</button>
        <button id="vmp-bulk-reject" class="vmp-btn danger">Bulk Reject</button>
      </div>

      <div id="vmp-request-list" class="vmp-grid">
        <div class="vmp-col vmp-col--sidebar">
          <div class="vmp-card" id="vmp-health-card">
            <!-- Health card loaded via JS -->
            <div class="vmp-card-title">Health</div>
            <div id="vmp-health-content">Loading...</div>
          </div>
        </div>

        <div class="vmp-col vmp-col--main">
          <div class="vmp-card">
            <div id="vmp-requests-table">Loading requests...</div>
          </div>

          <div class="vmp-card" id="vmp-activity-log">
            <h3>Activity Log</h3>
            <div id="vmp-activity-items">Loading activity...</div>
            <div id="vmp-activity-loadmore" style="text-align:center; margin-top:8px;"><button id="vmp-activity-loadmore-btn" class="vmp-btn">Load More</button></div>
          </div>
        </div>

        <div class="vmp-col vmp-col--details">
          <div class="vmp-card">
            <div id="vmp-request-preview">Select a request to preview details</div>
          </div>
        </div>
      </div>

      <div id="vmp-request-detail-modal" class="vmp-modal" aria-hidden="true"></div>
    </div>
    <?php
}
