<?php
/**
 * Admin page for managing vendor requests
 */

namespace VMP\Admin;

defined('ABSPATH') || exit;

use VMP\Services\VendorRegistrationService;
use VMP\Repositories\VendorRequestRepository;
use VMP\Core\ViewRenderer;

/**
 * Class VendorRequestsAdminPage
 */
class VendorRequestsAdminPage
{
    private VendorRequestRepository $requestRepository;
    private VendorRegistrationService $registrationService;
    private ViewRenderer $viewRenderer;

    public function __construct(
        VendorRequestRepository $requestRepository,
        VendorRegistrationService $registrationService,
        ViewRenderer $viewRenderer
    ) {
        $this->requestRepository = $requestRepository;
        $this->registrationService = $registrationService;
        $this->viewRenderer = $viewRenderer;

        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_vmp_vendor_requests_action', [$this, 'handleAjaxAction']);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAssets(string $hook): void
    {
        // hook ID: toplevel_page_ or parent_slug_page_ depending on WP version
        $valid_hooks = [
            'vmp-dashboard_page_vmp-vendor-requests',
            'vendor-marketplace_page_vmp-vendor-requests',
        ];
        if (!in_array($hook, $valid_hooks, true) && strpos($hook, 'vmp-vendor-requests') === false) {
            return;
        }

        wp_enqueue_style(
            'vmp-vendor-requests-admin',
            VMP_PLUGIN_URL . 'admin/assets/css/vendor-requests.css',
            [],
            VMP_VERSION
        );

        wp_enqueue_script(
            'vmp-vendor-requests-admin',
            VMP_PLUGIN_URL . 'admin/assets/js/vendor-requests.js',
            ['jquery', 'wp-util'],
            VMP_VERSION,
            true
        );

        wp_localize_script('vmp-vendor-requests-admin', 'vmpVendorRequests', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('vmp_vendor_requests_nonce'),
            'i18n'    => [
                'confirmApprove' => __('هل أنت متأكد من الموافقة على هذا الطلب؟', 'vmp'),
                'confirmReject'  => __('هل أنت متأكد من رفض هذا الطلب؟', 'vmp'),
                'confirmDelete'  => __('هل أنت متأكد من حذف هذا الطلب نهائياً؟', 'vmp'),
                'enterReason'    => __('يرجى إدخال سبب الرفض', 'vmp'),
                'actionSuccess'  => __('تم تنفيذ العملية بنجاح', 'vmp'),
                'actionError'    => __('حدث خطأ، يرجى المحاولة مرة أخرى', 'vmp'),
            ],
        ]);
    }

    /**
     * Render admin page
     */
    public function renderPage(): void
    {
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $perPage = 20;

        $args = [
            'status' => $status !== 'all' ? $status : '',
            'limit'  => $perPage,
            'offset' => ($paged - 1) * $perPage,
            'order_by' => 'created_at',
            'order'    => 'DESC',
        ];

        if (!empty($_GET['s'])) {
            $args['search'] = sanitize_text_field($_GET['s']);
        }

        $requests = $this->requestRepository->getAll($args);
        $totalCount = $this->requestRepository->getCount($status !== 'all' ? $status : '');
        $totalPages = ceil($totalCount / $perPage);

        // Stats
        $stats = $this->requestRepository->getQuickStats();

        include VMP_PLUGIN_DIR . 'admin/templates/vendor-requests.php';
    }

    /**
     * Handle AJAX actions
     */
    public function handleAjaxAction(): void
    {
        check_ajax_referer('vmp_vendor_requests_nonce', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('vmp_manage_vendors') && !current_user_can('manage_vmp_requests')) {
            wp_send_json_error(['message' => __('غير مصرح', 'vmp')], 403);
        }

        $action = sanitize_text_field($_POST['action_type'] ?? '');
        $requestId = absint($_POST['request_id'] ?? 0);

        if (!$requestId) {
            wp_send_json_error(['message' => __('معرف طلب غير صحيح', 'vmp')]);
        }

        $request = $this->requestRepository->find($requestId);
        if (!$request) {
            wp_send_json_error(['message' => __('الطلب غير موجود', 'vmp')]);
        }

        switch ($action) {
            case 'approve':
                $this->handleApprove($request);
                break;

            case 'reject':
                $reason = sanitize_textarea_field($_POST['reason'] ?? '');
                if (empty($reason)) {
                    wp_send_json_error(['message' => __('سبب الرفض مطلوب', 'vmp')]);
                }
                $this->handleReject($request, $reason);
                break;

            case 'delete':
                $this->handleDelete($request);
                break;

            case 'view':
                $this->handleView($request);
                break;

            default:
                wp_send_json_error(['message' => __('إجراء غير معروف', 'vmp')]);
        }
    }

    /**
     * Handle approve action
     */
    private function handleApprove(object $request): void
    {
        $vendorId = $this->requestRepository->approve($request->id, get_current_user_id());

        if ($vendorId) {
            wp_send_json_success([
                'message' => __('تمت الموافقة على الطلب بنجاح', 'vmp'),
                'vendor_id' => $vendorId,
            ]);
        } else {
            wp_send_json_error(['message' => __('فشل في الموافقة على الطلب', 'vmp')]);
        }
    }

    /**
     * Handle reject action
     */
    private function handleReject(object $request, string $reason): void
    {
        $result = $this->requestRepository->reject($request->id, $reason, get_current_user_id());

        if ($result) {
            wp_send_json_success(['message' => __('تم رفض الطلب', 'vmp')]);
        } else {
            wp_send_json_error(['message' => __('فشل في رفض الطلب', 'vmp')]);
        }
    }

    /**
     * Handle delete action
     */
    private function handleDelete(object $request): void
    {
        $result = $this->requestRepository->delete($request->id);

        if ($result) {
            wp_send_json_success(['message' => __('تم حذف الطلب نهائياً', 'vmp')]);
        } else {
            wp_send_json_error(['message' => __('فشل في حذف الطلب', 'vmp')]);
        }
    }

    /**
     * Handle view action - return request details for modal
     */
    private function handleView(object $request): void
    {
        $data = [
            'id'                => $request->id,
            'store_name'        => $request->store_name,
            'store_slug'        => home_url('/store/' . $request->store_slug),
            'store_description' => $request->store_description,
            'store_address'     => $request->store_address,
            'store_phone'       => $request->store_phone,
            'store_email'       => $request->store_email,
            'whatsapp_number'   => $request->whatsapp_number,
            'status'            => $request->status,
            'created_at'        => $request->created_at,
            'admin_notes'       => $request->admin_notes,
        ];

        // Add images if present
        if (!empty($request->store_logo)) {
            $logo = wp_get_attachment_image_src((int) $request->store_logo, 'medium');
            if ($logo) {
                $data['store_logo'] = ['url' => $logo[0]];
            }
        }
        if (!empty($request->store_banner)) {
            $banner = wp_get_attachment_image_src((int) $request->store_banner, 'large');
            if ($banner) {
                $data['store_banner'] = ['url' => $banner[0]];
            }
        }
        if (!empty($request->license_file)) {
            $license = wp_get_attachment_url((int) $request->license_file);
            if ($license) {
                $data['license_file'] = ['url' => $license];
            }
        }

        wp_send_json_success($data);
    }
}
