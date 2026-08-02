<?php
namespace VMP\Modules\VendorRegistration\Listeners;

use VMP\Modules\VendorRegistration\Services\NotificationServiceInterface;
use VMP\Modules\VendorRegistration\Services\ActivityLogService;
use VMP\Modules\VendorRegistration\Repositories\StoreSetupSessionRepository;

class VendorRejectedListeners
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
        $reason = $event->reason ?? '';

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
            'message' => $reason,
            'review_url' => admin_url('admin.php?page=vmp_requests&request=' . (int)$request->id),
            'wizard_url' => $session ? rest_url('vmp/v1/store/setup?session_uuid=' . $session->session_uuid) : '',
            'status_url' => rest_url('vmp/v1/requests/' . (int)$request->id . '/status'),
        ];

        $payload = [
            'template' => 'vendor-rejected.php',
            'to' => $to,
            'subject' => sprintf("Your vendor request #%d has been rejected", (int)$request->id),
            'data' => $data,
        ];

        $this->notifier->notify(['email'], $payload);
        $this->logger->log($adminId, 'VendorRejected: email_sent', ['request_id'=> (int)$request->id]);
    }

    public function createInAppNotification(object $event): void
    {
        $request = $event->request;
        $message = $event->reason ?? '';
        $vendorId = $request->user_id ?? null;
        $data = [
            'user_id' => $vendorId,
            'title' => 'Your vendor request was rejected',
            'body' => $message,
            'data' => ['request_id' => (int)$request->id],
        ];
        $this->notifier->notify(['in_app'], ['data' => $data]);
        $this->logger->log($event->adminId, 'VendorRejected: in_app_notification', ['request_id'=>(int)$request->id, 'user'=>$vendorId]);
    }

    public function dispatchWebhook(object $event): void
    {
        $request = $event->request;
        $payload = [
            'event_name' => 'vendor_rejected',
            'data' => [ 'request' => $request, 'admin_id' => $event->adminId, 'reason' => $event->reason, 'timestamp' => $event->timestamp ],
        ];
        $this->notifier->notify(['webhook'], $payload);
        $this->logger->log($event->adminId, 'VendorRejected: webhook_dispatched', ['request_id'=>(int)$request->id]);
    }

    public function auditTrail(object $event): void
    {
        $this->logger->log($event->adminId, 'VendorRejected:audit', ['request_id'=>(int)$event->request->id, 'reason'=>$event->reason]);
    }
}
