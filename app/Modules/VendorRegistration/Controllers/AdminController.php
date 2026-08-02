<?php
namespace VMP\Modules\VendorRegistration\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use VMP\Modules\VendorRegistration\Repositories\WpVendorRequestRepository;
use VMP\Modules\VendorRegistration\Services\ApprovalService;
use VMP\Modules\VendorRegistration\Services\StateMachine;
use VMP\Modules\VendorRegistration\Services\EventBus;

class AdminController
{
    private ApprovalService $service;

    public function __construct()
    {
        $repo = new WpVendorRequestRepository();
        $sm   = new StateMachine();
        $bus  = new EventBus();
        $this->service = new ApprovalService($repo, $sm, $bus);
    }

    public function approve(WP_REST_Request $request): WP_REST_Response
    {
        if (!current_user_can('manage_vmp_requests')) {
            return new WP_REST_Response(['error' => 'forbidden'], 403);
        }
        $id = (int) $request->get_param('id');
        try {
            $this->service->approve($id, get_current_user_id());
            return new WP_REST_Response(['success' => true]);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function reject(WP_REST_Request $request): WP_REST_Response
    {
        if (!current_user_can('manage_vmp_requests')) {
            return new WP_REST_Response(['error' => 'forbidden'], 403);
        }
        $id = (int) $request->get_param('id');
        $body = $request->get_json_params();
        $reason = $body['reason'] ?? '';
        if (empty($reason)) {
            return new WP_REST_Response(['error' => 'Rejection reason required'], 400);
        }
        try {
            $this->service->reject($id, get_current_user_id(), sanitize_text_field($reason));
            return new WP_REST_Response(['success' => true]);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}
