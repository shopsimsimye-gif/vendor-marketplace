<?php
// Register listeners for vendor events and perform one-time capabilities registration.
use VMP\Modules\VendorRegistration\Services\CapabilityManager;
use VMP\Modules\VendorRegistration\Config\Capabilities;

add_action('plugins_loaded', function() {
    // Ensure capabilities are provisioned when upgrading from older versions.
    // We check the stored capabilities version and only run registration when it differs.
    if (function_exists('get_option') && function_exists('update_option')) {
        $stored = get_option('vmp_capabilities_version');
        if ($stored !== Capabilities::CAPABILITIES_VERSION) {
            if (class_exists(CapabilityManager::class)) {
                CapabilityManager::register();
                update_option('vmp_capabilities_version', Capabilities::CAPABILITIES_VERSION);
            }
        }
    }

    // services
    $templatesDir = plugin_dir_path(__FILE__) . '/../../../templates/emails';
    $emailChannel = new \VMP\Modules\VendorRegistration\Services\NotificationChannels\EmailChannel($templatesDir);
    $inAppChannel = new \VMP\Modules\VendorRegistration\Services\NotificationChannels\InAppChannel();
    $webhookChannel = new \VMP\Modules\VendorRegistration\Services\NotificationChannels\WebhookChannel();

    $notificationService = new \VMP\Modules\VendorRegistration\Services\NotificationService([
        'email' => $emailChannel,
        'in_app' => $inAppChannel,
        'webhook' => $webhookChannel,
    ]);

    $logger = new \VMP\Modules\VendorRegistration\Services\ActivityLogService();
    $sessionsRepo = new \VMP\Modules\VendorRegistration\Repositories\StoreSetupRepository();

    $activatedListeners = new \VMP\Modules\VendorRegistration\Listeners\VendorActivatedListeners($notificationService, $logger, $sessionsRepo);
    $changesListeners = new \VMP\Modules\VendorRegistration\Listeners\StoreChangesRequestedListeners($notificationService, $logger, $sessionsRepo);
    $rejectedListeners = new \VMP\Modules\VendorRegistration\Listeners\VendorRejectedListeners($notificationService, $logger, $sessionsRepo);

    // Vendor activated
    add_action('vmp_event_vendor_activated', [$activatedListeners, 'sendEmail']);
    add_action('vmp_event_vendor_activated', [$activatedListeners, 'createInAppNotification']);
    add_action('vmp_event_vendor_activated', [$activatedListeners, 'dispatchWebhook']);
    add_action('vmp_event_vendor_activated', [$activatedListeners, 'auditTrail']);

    // Store changes requested
    add_action('vmp_event_store_changes_requested', [$changesListeners, 'sendEmail']);
    add_action('vmp_event_store_changes_requested', [$changesListeners, 'createInAppNotification']);
    add_action('vmp_event_store_changes_requested', [$changesListeners, 'dispatchWebhook']);
    add_action('vmp_event_store_changes_requested', [$changesListeners, 'auditTrail']);

    // Vendor rejected
    add_action('vmp_event_vendor_rejected', [$rejectedListeners, 'sendEmail']);
    add_action('vmp_event_vendor_rejected', [$rejectedListeners, 'createInAppNotification']);
    add_action('vmp_event_vendor_rejected', [$rejectedListeners, 'dispatchWebhook']);
    add_action('vmp_event_vendor_rejected', [$rejectedListeners, 'auditTrail']);

    // Expose a generic hook for after event processing
    add_action('vmp_event', function($event){
        do_action('vmp_after_event_processed', $event);
    });
});
