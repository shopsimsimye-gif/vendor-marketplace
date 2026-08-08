<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── البائع الحالي ──
$vendor_id = \VMP\Support\VendorHelper::get_current_vendor_id();
if ( ! $vendor_id ) {
    echo '<div class="vmp-notice vmp-notice-error">' . esc_html__( 'يجب تسجيل الدخول كبائع معتمد.', 'vmp' ) . '</div>';
    return;
}

$vendor_repo = \VMP\Core\Container::getInstance()->make( \VMP\Contracts\VendorRepositoryInterface::class );
$vendor      = $vendor_repo->find( $vendor_id );

if ( ! $vendor || 'approved' !== $vendor->status ) {
    echo '<div class="vmp-notice vmp-notice-error">' . esc_html__( 'يجب أن تكون بائعاً معتمداً للوصول إلى هذه الصفحة.', 'vmp' ) . '</div>';
    return;
}

/* ====== Repository Pattern ====== */
$product_repo = \VMP\Core\Container::getInstance()->make( \VMP\Contracts\ProductRepositoryInterface::class );
$plan_repo    = \VMP\Core\Container::getInstance()->make( \VMP\Contracts\SubscriptionPlanRepositoryInterface::class );
$sub_repo     = \VMP\Core\Container::getInstance()->make( \VMP\Contracts\SubscriptionRepositoryInterface::class );

/* ====== الخطة الحالية ====== */
$active_sub    = $sub_repo->findActiveByVendor( $vendor->id );
$plan          = $active_sub ? $plan_repo->find( $active_sub->plan_id ) : $plan_repo->findBySlug( 'free' );
$max_products  = $plan ? (int) $plan->max_products : 10;
$current_count = $product_repo->countByVendor( $vendor->id );
$can_add       = ( 0 === $max_products ) || ( $current_count < $max_products );

/* ====== جلب المنتجات عبر Repository ====== */
$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$limit  = 12;
$offset = ( $paged - 1 ) * $limit;

$products = $product_repo->getByVendor( $vendor->id, array(
    'limit'  => $limit,
    'offset' => $offset,
    'status' => '', // فارغ = كل الحالات (لا تحويل إلى status='all' لأنه فلتر لا يطابق)
) );

$total = $product_repo->countByVendor( $vendor->id );
$pages = (int) ceil( $total / $limit );

/* ====== حل N+1: جميع منتجات WooCommerce دفعة واحدة ====== */
$wc_products_by_id = array();
if ( ! empty( $products ) ) {
    $product_ids = array_filter( array_map( 'intval', wp_list_pluck( $products, 'product_id' ) ) );
    if ( ! empty( $product_ids ) ) {
        $wc_products = wc_get_products( array(
            'include' => $product_ids,
            'limit'   => -1,
        ) );
        foreach ( $wc_products as $wc_product ) {
            $wc_products_by_id[ $wc_product->get_id() ] = $wc_product;
        }
    }
}

/* ====== حالات المنتج ====== */
$status_labels = array(
    'pending'  => array( 'label' => __( 'قيد المراجعة', 'vmp' ), 'class' => 'vmp-status-pending' ),
    'approved' => array( 'label' => __( 'نشط', 'vmp' ), 'class' => 'vmp-status-approved' ),
    'rejected' => array( 'label' => __( 'مرفوض', 'vmp' ), 'class' => 'vmp-status-rejected' ),
);

/* روابط لوحة التحكم (من الإعدادات، لا بناء يدوي لـ ?vmp_page=) */
$dashboard_base = vmp_dashboard_url( '' ); // global wrapper (helpers.php)
$ai_create_url  = add_query_arg( 'vmp_page', 'ai-create-product', $dashboard_base );
$add_product_url = add_query_arg( 'vmp_page', 'add-product', $dashboard_base );
?>
<div class="vmp-wrap">
    <!-- شريط التنقل (partial موحد) -->
    <?php if ( file_exists( VMP_PLUGIN_DIR . 'public/templates/partials/vendor-nav.php' ) ) : ?>
        <?php include VMP_PLUGIN_DIR . 'public/templates/partials/vendor-nav.php'; ?>
    <?php else : ?>
        <div class="vmp-notice vmp-notice-warning"><?php esc_html_e( 'ملف التنقل غير موجود.', 'vmp' ); ?></div>
    <?php endif; ?>

    <div class="vmp-card">
        <div class="vmp-card-header">
            <h2 class="vmp-card-title"><?php esc_html_e( 'منتجاتي', 'vmp' ); ?> (<?php echo (int) $total; ?>)</h2>

            <?php if ( $can_add ) : ?>
                <a href="<?php echo esc_url( $ai_create_url ); ?>" class="vmp-btn vmp-btn-secondary vmp-btn-sm">
                    <?php esc_html_e( 'إنشاء من صورة', 'vmp' ); ?>
                </a>
                <a href="<?php echo esc_url( $add_product_url ); ?>" class="vmp-btn vmp-btn-primary vmp-btn-sm">
                    + <?php esc_html_e( 'إضافة منتج', 'vmp' ); ?>
                </a>
            <?php else : ?>
                <span class="vmp-badge-status vmp-status-warning">
                    <?php esc_html_e( 'وصلت للحد الأقصى للمنتجات', 'vmp' ); ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="vmp-table-wrap">
            <table class="vmp-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'الصورة', 'vmp' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'اسم المنتج', 'vmp' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'السعر', 'vmp' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'المخزون', 'vmp' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'الحالة', 'vmp' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'إجراءات', 'vmp' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $products ) ) : ?>
                        <tr>
                            <td colspan="6">
                                <div class="vmp-empty">
                                    <div class="vmp-empty-icon">📦</div>
                                    <h3><?php esc_html_e( 'لا توجد منتجات', 'vmp' ); ?></h3>
                                    <p><?php esc_html_e( 'لم تقم بإضافة أي منتجات بعد.', 'vmp' ); ?></p>
                                    <?php if ( $can_add ) : ?>
                                        <a href="<?php echo esc_url( $add_product_url ); ?>" class="vmp-btn vmp-btn-primary">
                                            <?php esc_html_e( 'إضافة أول منتج', 'vmp' ); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $products as $p ) :
                            $wc_product = $wc_products_by_id[ (int) $p->product_id ] ?? null;
                            $badge      = $status_labels[ $p->status ] ?? array( 'label' => $p->status, 'class' => '' );
                        ?>
                            <tr>
                                <td class="vmp-product-thumb-cell">
                                    <?php if ( $wc_product ) : ?>
                                        <?php echo $wc_product->get_image( 'thumbnail', array( 'class' => 'vmp-product-thumb', 'alt' => $wc_product->get_name() ) ); ?>
                                    <?php else : ?>
                                        <div class="vmp-product-thumb-placeholder" aria-hidden="true"></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $wc_product ) : ?>
                                        <strong><?php echo esc_html( $wc_product->get_name() ); ?></strong>
                                    <?php else : ?>
                                        <span class="vmp-product-deleted"><?php esc_html_e( 'منتج محذوف', 'vmp' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $wc_product ) : ?>
                                        <?php echo $wc_product->get_price_html(); ?>
                                    <?php else : ?>
                                        <span class="vmp-product-deleted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $wc_product && $wc_product->managing_stock() ) : ?>
                                        <?php echo (int) $wc_product->get_stock_quantity(); ?>
                                    <?php else : ?>
                                        <span class="vmp-available-badge"><?php esc_html_e( 'متوفر', 'vmp' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="vmp-badge-status <?php echo esc_attr( $badge['class'] ); ?>"><?php echo esc_html( $badge['label'] ); ?></span>
                                </td>
                                <td>
                                    <?php if ( $wc_product ) : ?>
                                        <div class="vmp-actions">
                                            <a href="<?php echo esc_url( get_permalink( (int) $p->product_id ) ); ?>" target="_blank" rel="noopener noreferrer" class="vmp-btn vmp-btn-outline vmp-btn-sm">
                                                <?php esc_html_e( 'عرض', 'vmp' ); ?>
                                            </a>
                                            <a href="<?php echo esc_url( add_query_arg( array( 'vmp_page' => 'edit-product', 'id' => (int) $p->id ), $dashboard_base ) ); ?>" class="vmp-btn vmp-btn-secondary vmp-btn-sm">
                                                <?php esc_html_e( 'تعديل', 'vmp' ); ?>
                                            </a>
                                            <button
                                                type="button"
                                                class="vmp-btn vmp-btn-danger vmp-btn-sm vmp-delete-product"
                                                data-product-id="<?php echo (int) $p->id; ?>"
                                                aria-label="<?php echo esc_attr( sprintf( __( 'حذف المنتج: %s', 'vmp' ), $wc_product->get_name() ) ); ?>">
                                                <?php esc_html_e( 'حذف', 'vmp' ); ?>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ( $pages > 1 ) : ?>
            <div class="vmp-pagination">
                <?php echo paginate_links( array(
                    'base'       => add_query_arg( 'paged', '%#%' ),
                    'format'     => '',
                    'prev_text'  => '<span aria-hidden="true">&laquo;</span><span class="screen-reader-text">' . esc_html__( 'السابق', 'vmp' ) . '</span>',
                    'next_text'  => '<span aria-hidden="true">&raquo;</span><span class="screen-reader-text">' . esc_html__( 'التالي', 'vmp' ) . '</span>',
                    'total'      => $pages,
                    'current'    => $paged,
                    'aria_label' => esc_html__( 'ترقيم الصفحات', 'vmp' ),
                ) ); ?>
            </div>
        <?php endif; ?>
    </div>
</div>
