# Architecture & Design Patterns

Vendor Marketplace uses a modern, SOLID-based PHP architecture inspired by frameworks like Laravel, adapted for WordPress plugins.

## 1. Container (DI & IoC)
All dependencies are injected via the Dependency Injection Container (`VMP\Core\Container`). The `CoreServiceProvider` is responsible for binding interfaces to concrete implementations (like binding `VendorRepositoryInterface` to `CachedVendorRepository`).

## 2. Repositories Pattern
Database logic is isolated in Repositories. We use the Decorator pattern to inject caching automatically (e.g., `CachedVendorRepository` decorates `VendorRepository`). The rest of the application depends entirely on `Interface` bindings, so caching works transparently.

## 3. Services Layer
Business logic lives in Services (e.g., `VendorService`). Services orchestrate actions between Repositories, validate complex business rules, and trigger Events. Controllers rely exclusively on Services.

## 4. DTOs (Data Transfer Objects)
Data passed into Services is encapsulated in DTOs. This provides type-safety and guarantees that incoming data structures are clean and strictly validated before touching the business logic.

## 5. Request Validation
We use a robust Request validation layer (`VMP\Http\Requests\AbstractRequest`). It handles sanitization, validation rules (`required`, `email`, etc.), and automatic WP Nonce validation out of the box, before the controller is even called.

## 6. Action Dispatcher
Our custom `ActionDispatcher` maps WordPress AJAX hooks dynamically to controller methods using `RouteRegistry`. It resolves the appropriate `Request` class, handles the full lifecycle, and centralizes Exception handling via `ExceptionHandler`.
