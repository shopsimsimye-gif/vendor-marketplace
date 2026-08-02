<?php
namespace VMP\Modules\VendorRegistration\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use VMP\Modules\VendorRegistration\Repositories\WpVendorRequestRepository;
use VMP\Modules\VendorRegistration\Services\ActivityLogService;
use VMP\Modules\VendorRegistration\Services\Health\HealthService;

class AdminRequestsController
{
    private WpVendorRequestRepository $repo;
    private ActivityLogService $activityService;

    public function __construct()
    {
        $this->repo = new WpVendorRequestRepository();
        $this->activityService = new ActivityLogService();
    }

    public function listRequests(WP_REST_Request $request): WP_REST_Response
    {
        $page = max(1, (int) $request->get_param('page') ?: 1);
        $perPage = min(50, (int) $request->get_param('per_page') ?: 20);
        $q = $request->get_param('q') ?: '';
        $status = $request->get_param('status') ?: '';

        $offset = ($page - 1) * $perPage;
        $items = method_exists($this->repo, 'search') ? $this->repo->search($q, $status, $perPage, $offset) : [];

        // map to simpler structure
        $data = array_map(function($r){
            return [
                'id' => (int)$r->id,
                'vendor_id' => $r->user_id ?? null,
                'vendor_name' => $r->vendor_name ?? '',
                'store_name' => $r->store_name ?? '',
                'status' => $r->status ?? '',
                'submitted_at' => $r->created_at ?? '',
                'last_activity' => $r->updated_at ?? '',
            ];
        }, $items ?: []);

        return new WP_REST_Response(['data' => $data, 'page' => $page, 'per_page' => $perPage], 200);
    }

    public function getRequest(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $r = $this->repo->find($id);
        if (!$r) return new WP_REST_Response(['error' => 'not_found'], 404);

        $detail = [
            'id' => (int)$r->id,
            'vendor_id' => $r->user_id ?? null,
            'vendor_name' => $r->vendor_name ?? '',
            'store_name' => $r->store_name ?? '',
            'status' => $r->status ?? '',
            'submitted_at' => $r->created_at ?? '',
            'last_activity' => $r->updated_at ?? '',
            'payload' => $r,
        ];

        return new WP_REST_Response(['data' => $detail], 200);
    }

    public function healthSummary(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        $healthService = new HealthService($this->repo);
        $report = $healthService->getReport($id);

        if ($report->percent_complete === 0 && in_array('not_found', $report->warnings, true)) {
            return new WP_REST_Response(['error' => 'not_found'], 404);
        }

        return new WP_REST_Response(['data' => $report->toArray()], 200);
    }

    public function getActivity(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $page = max(1, (int)$request->get_param('page') ?: 1);
        $perPage = min(50, (int)$request->get_param('per_page') ?: 20);

        $offset = ($page - 1) * $perPage;
        $items = $this->activityService->findByRequest($id, $perPage, $offset);

        return new WP_REST_Response(['data' => $items, 'page' => $page, 'per_page' => $perPage], 200);
    }

    public function bulkAction(WP_REST_Request $request): WP_REST_Response
    {
        $body = json_decode($request->get_body() ?: '{}', true);
        $action = $body['action'] ?? '';
        $ids = $body['ids'] ?? [];

        if (!in_array($action, ['activate','reject','request_changes'])) {
            return new WP_REST_Response(['error'=>'invalid_action'], 400);
        }

        $results = [];
        foreach ($ids as $id) {
            try {
                $svc = new \VMP\Modules\VendorRegistration\Services\AdminReviewService();
                if ($action === 'activate') $svc->activate((int)$id, get_current_user_id());
                if ($action === 'reject') $svc->reject((int)$id, get_current_user_id(), 'Bulk reject');
                if ($action === 'request_changes') $svc->requestChanges((int)$id, get_current_user_id(), 'Bulk request');
                $results[$id] = 'ok';
            } catch (\Throwable $e) {
                $results[$id] = 'error';
            }
        }

        return new WP_REST_Response(['data' => $results], 200);
    }
}
