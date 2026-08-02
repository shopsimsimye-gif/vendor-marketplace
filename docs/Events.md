# Event System

Our Event system bridges the gap between WordPress legacy hooks (`do_action`) and modern OOP-based typed events.

## Typed Events
Typed events are classes that represent a specific action in the system (e.g., `VendorRegistered`, `OrderCompleted`). They implement `EventInterface` and optionally `StoppableEventInterface`.

They provide:
- Type-safety (properties are explicitly defined).
- IDE auto-completion.
- A central place to see what data is passed with an event.

## EventManager
The `EventManager` is responsible for dispatching events.

```php
// Dispatching a typed event
$event = new VendorApproved($vendorId, $userId, 'Store', 'email@test.com');
$this->eventManager->dispatch($event);
```

Behind the scenes, the `EventManager` uses `do_action()` to hook into WordPress. It automatically uses the `getEventName()` of the Typed Event class (e.g., `do_action('vmp.vendor.approved', $event)`).

## Backwards Compatibility
To ensure older plugins relying on `vmp_vendor_approved` do not break, Services still trigger legacy string-based hooks directly alongside Typed events.

```php
$this->eventManager->trigger('vmp_vendor_approved', $vendorId);
```
