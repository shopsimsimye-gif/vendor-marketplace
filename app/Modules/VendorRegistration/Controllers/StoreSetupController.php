<?php
namespace VMP\Modules\VendorRegistration\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use VMP\Modules\VendorRegistration\Repositories\StoreSetupSessionRepositoryInterface;
use VMP\Modules\VendorRegistration\Services\StoreSetupServiceInterface;
use VMP\Modules\VendorRegistration\Services\IdempotencyService;
use VMP\Modules\VendorRegistration\Validators\StoreStep1Validator;
use VMP\Modules\VendorRegistration\Validators\StoreStep2Validator;
use VMP\Modules\VendorRegistration\Validators\StoreStep3Validator;
use VMP\Modules\VendorRegistration\Validators\StoreStep4Validator;
use VMP\Modules\VendorRegistration\Validators\StoreStep5Validator;

class StoreSetupController
{
    private StoreSetupSessionRepositoryInterface $repo;
    private StoreSetupServiceInterface $service;
    private IdempotencyService $idemp;
    private int $currentUser;

    public function __construct(StoreSetupSessionRepositoryInterface $repo, StoreSetupServiceInterface $service, IdempotencyService $idemp)
    {
        $this->repo = $repo;
        $this->service = $service;
        $this->idemp = $idemp;
        $this->currentUser = get_current_user_id();
    }

    public function start(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();
        $vendorRequestId = isset($body['vendor_request_id']) ? (int)$body['vendor_request_id'] : 0;
        $session = $this->service->startSession($this->currentUser, $vendorRequestId);
        return new WP_REST_Response(['success' => true, 'session' => $session], 201);
    }

    public function state(WP_REST_Request $request): WP_REST_Response
    {
        $uuid = $request->get_param('session_uuid') ?: $request->get_header('X-Session-UUID');
        if (!$uuid) return new WP_REST_Response(['success' => false, 'error' => 'session_uuid required'], 400);
        $session = $this->service->getSessionByUuid($uuid);
        if (!$session) return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        if ($session->user_id != $this->currentUser && !current_user_can('manage_vmp_requests')) {
            return new WP_REST_Response(['error' => 'forbidden'], 403);
        }
        return new WP_REST_Response(['success' => true, 'session' => $session]);
    }

    public function step(WP_REST_Request $request): WP_REST_Response
    {
        $step = (int)$request->get_param('step');
        $uuid = $request->get_param('session_uuid') ?: $request->get_header('X-Session-UUID');
        $idempotency = $request->get_header('X-Idempotency-Key') ?: null;
        $data = $request->get_json_params() ?: [];

        if (!$uuid) return new WP_REST_Response(['error' => 'session_uuid required'], 400);
        $session = $this->repo->findByUuid($uuid);
        if (!$session) return new WP_REST_Response(['error' => 'session_not_found'], 404);
        if ($session->user_id != $this->currentUser && !current_user_can('manage_vmp_requests')) {
            return new WP_REST_Response(['error' => 'forbidden'], 403);
        }

        // Step validators
        $validators = [
            1 => StoreStep1Validator::class,
            2 => StoreStep2Validator::class,
            3 => StoreStep3Validator::class,
            4 => StoreStep4Validator::class,
            5 => StoreStep5Validator::class,
        ];

        if (isset($validators[$step])) {
            $errors = $validators[$step]::validate($data);
            if (!empty($errors)) return new WP_REST_Response(['success' => false, 'errors' => $errors], 400);
        }

        // Idempotency check
        if ($idempotency) {
            $key = $idempotency . '|' . $uuid . '|' . $step;
            if ($this->idemp->exists($key)) {
                return new WP_REST_Response(['success' => true, 'session' => $this->repo->findById($session->id), 'idempotency' => ['key' => $idempotency, 'reused' => true]]);
            }
        }

        // Map step to allowed keys
        $allowed = [
            1 => ['store'],
            2 => ['branding'],
            3 => ['contact'],
            4 => ['policies'],
            5 => ['social'],
        ];
        $payloadPart = [];
        foreach ($allowed[$step] ?? [] as $k) {
            if (isset($data[$k])) $payloadPart[$k] = $data[$k];
        }
        if (empty($payloadPart)) return new WP_REST_Response(['error' => 'invalid_payload_for_step'], 400);

        $ok = $this->service->saveStep((int)$session->id, $step, $payloadPart);
        if (!$ok) return new WP_REST_Response(['error' => 'save_failed'], 500);

        if ($idempotency) $this->idemp->mark($key);

        $session = $this->repo->findById((int)$session->id);
        return new WP_REST_Response(['success' => true, 'session' => $session, 'idempotency' => ['key' => $idempotency, 'reused' => false]]);
    }

    public function finish(WP_REST_Request $request): WP_REST_Response
    {
        $uuid = $request->get_param('session_uuid') ?: $request->get_header('X-Session-UUID');
        $idempotency = $request->get_header('X-Idempotency-Key') ?: null;
        if (!$uuid) return new WP_REST_Response(['error' => 'session_uuid required'], 400);
        $session = $this->repo->findByUuid($uuid);
        if (!$session) return new WP_REST_Response(['error' => 'session_not_found'], 404);
        if ($session->user_id != $this->currentUser && !current_user_can('manage_vmp_requests')) {
            return new WP_REST_Response(['error' => 'forbidden'], 403);
        }

        try {
            $ok = $this->service->finishSession((int)$session->id, $idempotency);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }

        $session = $this->repo->findById((int)$session->id);
        return new WP_REST_Response(['success' => true, 'session' => $session, 'message' => 'Store setup completed, pending admin review']);
    }
}
