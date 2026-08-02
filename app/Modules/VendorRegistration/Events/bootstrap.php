<?php
// bootstrap event listeners for VendorApproved
add_action('plugins_loaded', function() {
    // register listeners to the action 'vmp_event_vendorapproved' or 'vmp_event_vendorapproved' depending on short class name
    // Our EventBus uses lowercase short class name, so action is vmp_event_vendorapproved ? Wait: EventBus uses strtolower of short name which is VendorApproved => vendorapproved
    // But earlier EventBus builds 'vmp_event_' . strtolower($class) which yields vmp_event_vendorapproved
    add_action('vmp_event_vendorapproved', function($event) {
        try {
            $listener = new VMP\Modules\VendorRegistration\Listeners\SendApprovalEmailListener();
            ($listener)($event);
        } catch (\Throwable $e) { error_log('SendApprovalEmailListener error: '.$e->getMessage()); }
    });

    add_action('vmp_event_vendorapproved', function($event) {
        try {
            $listener = new VMP\Modules\VendorRegistration\Listeners\RedirectAfterLoginListener();
            ($listener)($event);
        } catch (\Throwable $e) { error_log('RedirectAfterLoginListener error: '.$e->getMessage()); }
    });

    add_action('vmp_event_vendorapproved', function($event) {
        try {
            $listener = new VMP\Modules\VendorRegistration\Listeners\CreateStoreSetupSessionListener();
            ($listener)($event);
        } catch (\Throwable $e) { error_log('CreateStoreSetupSessionListener error: '.$e->getMessage()); }
    });

    add_action('vmp_event_vendorapproved', function($event) {
        try {
            $listener = new VMP\Modules\VendorRegistration\Listeners\ActivityLogListener();
            ($listener)($event);
        } catch (\Throwable $e) { error_log('ActivityLogListener error: '.$e->getMessage()); }
    });

    add_action('vmp_event_vendorapproved', function($event) {
        try {
            $listener = new VMP\Modules\VendorRegistration\Listeners\NotificationListener();
            ($listener)($event);
        } catch (\Throwable $e) { error_log('NotificationListener error: '.$e->getMessage()); }
    });
});
