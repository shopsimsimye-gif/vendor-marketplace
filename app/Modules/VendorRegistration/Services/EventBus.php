<?php
namespace VMP\Modules\VendorRegistration\Services;

class EventBus
{
    /**
     * Dispatch an event to WP actions and return true if dispatched.
     * Uses specific action name based on event class short name: vmp_event_{snake}
     * Also fires a generic 'vmp_event' action.
     */
    public function dispatch(object $event): bool
    {
        $class = get_class($event);
        $parts = explode('\\', $class);
        $short = end($parts);
        // convert CamelCase to snake
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $short));
        $action = 'vmp_event_' . $snake;
        // Fire specific action
        do_action($action, $event);
        // Fire generic event bus
        do_action('vmp_event', $event);
        return true;
    }
}
