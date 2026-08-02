<?php
namespace VMP\Modules\VendorRegistration\Services\NotificationChannels;

class WebhookChannel
{
    /**
     * $payload keys: event_name, endpoint(s) optional, data
     */
    public function __invoke(array $payload): bool
    {
        // Dispatch a WP action dedicated to webhooks for the event so integrators can hook in
        $event = $payload['event_name'] ?? 'vmp_event_generic';
        $data = $payload['data'] ?? [];
        do_action('vmp_webhook_' . $event, $data);
        return true;
    }
}
