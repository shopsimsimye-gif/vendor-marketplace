<?php
namespace VMP\Modules\VendorRegistration\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use VMP\Modules\VendorRegistration\Services\AdminReviewService;

class AdminReviewController
{
    private AdminReviewService $service;

    public function __construct(AdminReviewService $service)
    {
        $this->service = $service;
    }

    public function activate(WP_REST_Request $request): WP_REST_Response
    {
        $requestId = (int) $request->get_param('id');
        $adminId = get_current_user_id();
        try {
            $ok = $this->service->activate($requestId, $adminId);
            if ($ok) return new WP_REST_Response(['success' => true], 200);
            return new WP_REST_Response(['success' => false], 500);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['success'=>false,'error'=>$e->getMessage()], 404);
        } catch (\RuntimeException $e) {
            return new WP_REST_Response(['success'=>false,'error'=>$e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['success'=>false,'error'=>'server_error'], 500);
        }
    }

    public function requestChanges(WP_REST_Request $request): WP_REST_Response
    {
        $requestId = (int) $request->get_param('id');
        $adminId = get_current_user_id();
        $body = $request->get_json_params();
        $message = isset($body['message']) ? (string) $body['message'] : '';
        if (trim($message) === '') return new WP_REST_Response(['success'=>false,'error'=>'message_required'], 422);
        try {
            $ok = $this->service->requestChanges($requestId, $adminId, $message);
            if ($ok) return new WP_REST_Response(['success' => true], 200);
            return new WP_REST_Response(['success' => false], 500);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['success'=>false,'error'=>$e->getMessage()], 404);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['success'=>false,'error'=>'server_error'], 500);
        }
    }

    public function reject(WP_REST_Request $request): WP_REST_Response
    {
        $requestId = (int) $request->get_param('id');
        $adminId = get_current_user_id();
        $body = $request->get_json_params();
        $reason = isset($body['reason']) ? (string) $body['reason'] : '';
        if (trim($reason) === '') return new WP_REST_Response(['success'=>false,'error'=>'reason_required'], 422);
        try {
            $ok = $this->service->reject($requestId, $adminId, $reason);
            if ($ok) return new WP_REST_Response(['success' => true], 200);
            return new WP_REST_Response(['success' => false], 500);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['success'=>false,'error'=>$e->getMessage()], 404);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['success'=>false,'error'=>'server_error'], 500);
        }
    }
}
