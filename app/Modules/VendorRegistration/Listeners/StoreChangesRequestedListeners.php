<?php
namespace VMP\Modules\VendorRegistration\Listeners;

use VMP\Modules\VendorRegistration\Services\NotificationServiceInterface;
use VMP\Modules\VendorRegistration\Services\ActivityLogService;
use VMP\Modules\VendorRegistration\Repositories\StoreSetupSessionRepository;

class StoreChangesRequestedListeners
{
    private NotificationServiceInterface $notifier;
    private ActivityLogService $logger;
    private StoreSetupSessionRepository $sessionsRepo;

    public function __construct(NotificationServiceInterface $notifier, ActivityLogService $logger, StoreSetupSessionRepository $sessionsRepo)
    {
        $this->notifier = $notifier;
        $this->logger = $logger;
        $this->sessionsRepo = $sessionsRepo;
    }

    public function sendEmail(object $event): void
    {
        $request = $event->request;
        $adminId = $event->adminId;
        $message = $event->message ?? '';

        $vendor = null;
        if (!empty($request->user_id)) $vendor = get_user_by('ID', $request->user_id);
        $session = $this->sessionsRepo->findByRequest((int)$request->id);

        $to = $vendor ? $vendor->user_email : null;
        $data = [
            'request' => $request,
            'vendor' => $vendor,
            'session' => $session,
            'store' => null,
            'admin' => get_user_by('ID', $adminId),
            'message' => $message,
            'review_url' => admin_url('admin.php?page=vmp_requests&request=' . (int)$request->id),
            'wizard_url' => $session ? rest_url('vmp/v1/store/setup?session_uuid=' . $session->session_uuid) : '',
            'status_url' => rest_url('vmp/v1/requests/' . (int)$request->id . '/status'),
        ];

        $payload = [
            'template' => 'vendor-request-changes.php',
            'to' => $to,
            'subject' => sprintf("Action required on your vendor request #%d", (int)$request->id),
            'data' => $data,
        ];

        $this->notifier->notify(['email'], $payload);
        $this->logger->log($adminId, 'StoreChangesRequested: email_sent', ['request_id'=> (int)$request->id]);
    }

    public function createInAppNotification(object $event): void
    {
        $request = $event->request;
        $message = $event->message ?? '';
        $vendorId = $request->user_id ?? null;
        $data = [
            'user_id' => $vendorId,
            'title' => 'Changes requested for your vendor request',
            'body' => $message,
            'data' => ['request_id' => (int)$request->id],
        ];
        $this->notifier->notify(['in_app'], ['data' => $data]);
        $this->logger->log($event->adminId, 'StoreChangesRequested: in_app_notification', ['request_id'=>(int)$request->id, 'user'=>$vendorId]);
    }

    public function dispatchWebhook(object $event): void
    {
        $request = $event->request;
        $payload = [
            'event_name' => 'store_changes_requested',
            'data' => [ 'request' => $request, 'admin_id' => $event->adminId, 'message' => $event->message, 'timestamp' => $event->timestamp ],
        ];
        $this->notifier->notify(['webhook'], $payload);
        $this->logger->log($event->adminId, 'StoreChangesRequested: webhook_dispatched', ['request_id'=>(int)$request->id]);
    }

    public function auditTrail(object $event): void
    {
        $this->logger->log($event->adminId, 'StoreChangesRequested:audit', ['request_id'=>(int)$event->request->id, 'message'=>$event->message]);
    }
}
