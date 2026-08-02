<?php
/**
 * Store view template
 * Expects query var vmp_store_slug to be present
 */
use VMP\Modules\VendorRegistration\Repositories\WpVendorStoreRepository;

$slug = '';
if (function_exists('get_query_var')) {
    $slug = (string) get_query_var('vmp_store_slug');
}
if (empty($slug) && !empty($_GET['vmp_store_slug'])) {
    $slug = sanitize_text_field((string) $_GET['vmp_store_slug']);
}
if (empty($slug) && !empty($_SERVER['REQUEST_URI'])) {
    $uri = trim((string) $_SERVER['REQUEST_URI']);
    $uri = wp_parse_url($uri, PHP_URL_PATH) ?: $uri;
    $uri = trim((string) $uri, '/');
    if (preg_match('#^vendor-store/([^/]+)(?:/.*)?$#', $uri, $matches)) {
        $slug = sanitize_text_field($matches[1]);
    }
}

if (empty($slug)) {
    if (function_exists('status_header')) {
        status_header(404);
    }
    echo '<h1>Store not found</h1>';
    return;
}

$repo = new \VMP\Modules\VendorRegistration\Repositories\WpVendorStoreRepository();
$store = $repo->findBySlug($slug);
if (!$store) {
    if (function_exists('status_header')) {
        status_header(404);
    }
    echo '<h1>Store not found</h1>';
    return;
}

// render basic store page
?><div class="vmp-store-page">
  <h1 class="vmp-store-title"><?php echo esc_html($store->store_name ?? $store->store_slug); ?></h1>
  <?php if (!empty($store->logo)): ?>
    <div class="vmp-store-logo"><img src="<?php echo esc_url($store->logo); ?>" alt="<?php echo esc_attr($store->store_name ?? ''); ?>" /></div>
  <?php endif; ?>
  <?php if (!empty($store->description)): ?>
    <div class="vmp-store-description"><?php echo wp_kses_post(wpautop($store->description)); ?></div>
  <?php endif; ?>

  <div class="vmp-store-meta">
    <strong><?php echo esc_html(function_exists('__') ? __('البائع', 'vmp') : 'البائع'); ?>:</strong> <?php echo esc_html((string) ($store->vendor_id ?? '')); ?><br />
    <strong><?php echo esc_html(function_exists('__') ? __('الحالة', 'vmp') : 'الحالة'); ?>:</strong> <?php echo esc_html($store->is_active ? (function_exists('__') ? __('Active','vmp') : 'Active') : (function_exists('__') ? __('Inactive','vmp') : 'Inactive')); ?>

  <div class="vmp-store-actions">
    <?php if (function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('current_user_can') && current_user_can('manage_vmp_requests')): ?>
      <a class="button" href="<?php echo esc_url(function_exists('admin_url') ? admin_url('admin.php?page=vmp-vendor-requests') : '#'); ?>"><?php echo esc_html(function_exists('__') ? __('Manage stores', 'vmp') : 'Manage stores'); ?></a>
    <?php endif; ?>
  </div>

  <div class="vmp-store-products">
    <!-- Placeholder for products listing. Integrate with WooCommerce or product repository later. -->
    <p><?php echo esc_html(function_exists('__') ? __('قسم المنتجات سيُعرض هنا لاحقًا.', 'vmp') : 'قسم المنتجات سيُعرض هنا لاحقًا.'); ?></p>
  </div>
</div>
