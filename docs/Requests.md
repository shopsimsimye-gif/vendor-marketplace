# Request Validation System

Instead of validating data directly inside the controller or using global `$_POST` access, we use dedicated `Request` classes that extend `AbstractRequest`.

## Features
- **Centralized Rules**: Define validation rules using a syntax similar to Laravel (`required|string|min:3|email`).
- **Automatic Nonce Validation**: `AbstractRequest::fromPost()` automatically checks the WP Nonce to prevent CSRF before the request is even fully built.
- **Custom Error Messages**: Override `messages()` and `attributes()` to translate and customize validation errors.
- **Custom Validation Logic**: Override `validate()` for complex checks (e.g., checking database uniqueness).

## Workflow (ActionDispatcher integration)
1. The `ActionDispatcher` intercepts the AJAX call.
2. It resolves the specific `Request` class needed by the Controller using Reflection (`ControllerMethodResolver`).
3. It calls `$requestClass::fromPost($nonce_action, $nonce_field)`.
4. It calls `$request->validate()`.
5. If validation fails, `ValidationException` is thrown and handled globally by `ExceptionHandler`.
6. If successful, the `Request` is passed to the Controller.

## How to create a Request
1. Create a class inside `app/Http/Requests` extending `AbstractRequest`.
2. Implement the `rules()` method.
3. Use it as a type-hint in your Controller method.
