<?php
namespace VMP\Modules\VendorRegistration\Listeners;

use VMP\Modules\VendorRegistration\Services\NotificationService;
use VMP\Modules\VendorRegistration\Services\NotificationServiceInterface;
use VMP\Modules\VendorRegistration\Services\NotificationChannels\EmailChannel;
use VMP\Modules\VendorRegistration\Services\NotificationChannels\InAppChannel;
use VMP\Modules\VendorRegistration\Services\NotificationChannels\WebhookChannel;
use VMP\Modules\VendorRegistration\Services\ActivityLogService;
use VMP\Modules\VendorRegistration\Repositories\StoreSetupSessionRepository;

class VendorActivatedListeners
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

        $vendor = null;
        if (!empty($request->user_id)) $vendor = get_user_by('ID', $request->user_id);

        // try to find setup session
        $session = $this->sessionsRepo->findByRequest((int)$request->id);

        $reviewUrl = admin_url('admin.php?page=vmp_requests&request=' . (int)$request->id);
        $wizardUrl = $session ? rest_url('vmp/v1/store/setup?session_uuid=' . $session->session_uuid) : '';
        $statusUrl = rest_url('vmp/v1/requests/' . (int)$request->id . '/status');

        $to = $vendor ? $vendor->user_email : null;
        $data = [
            'request' => $request,
            'vendor' => $vendor,
            'session' => $session,
            'store' => null,
            'admin' => get_user_by('ID', $adminId),
            'message' => '',
            'review_url' => $reviewUrl,
            'wizard_url' => $wizardUrl,
            'status_url' => $statusUrl,
        ];

        $payload = [
            'template' => 'vendor-activated.php',
            'to' => $to,
            'subject' => sprintf("Your vendor request #%d has been activated", (int)$request->id),
            'data' => $data,
        ];

        // default: send email via EmailChannel
        $this->notifier->notify(['email'], $payload);

        // log
        $this->logger->log($adminId, 'VendorActivated: email_sent', ['request_id'=> (int)$request->id, 'to' => $to]);
    }

    public function createInAppNotification(object $event): void
    {
        $request = $event->request;
        $vendorId = $request->user_id ?? null;
        $data = [
            'user_id' => $vendorId,
            'title' => 'Your vendor request has been activated',
            'body' => 'Congratulations — your store is now active. Click to continue setup.',
            'data' => ['request_id' => (int)$request->id],
        ];
        $this->notifier->notify(['in_app'], ['data' => $data]);
        $this->logger->log($event->adminId, 'VendorActivated: in_app_notification', ['request_id'=>(int)$request->id, 'user'=>$vendorId]);
    }

    public function dispatchWebhook(object $event): void
    {
        $request = $event->request;
        $payload = [
            'event_name' => 'vendor_activated',
            'data' => [ 'request' => $request, 'admin_id' => $event->adminId, 'timestamp' => $event->timestamp ],
        ];
        $this->notifier->notify(['webhook'], $payload);
        $this->logger->log($event->adminId, 'VendorActivated: webhook_dispatched', ['request_id'=>(int)$request->id]);
    }

    public function auditTrail(object $event): void
    {
        $this->logger->log($event->adminId, 'VendorActivated:audit', ['request_id'=>(int)$event->request->id]);
    }
}
