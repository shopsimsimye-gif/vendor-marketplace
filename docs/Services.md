# Services Layer

The Services layer is the heart of our application's business logic. It sits between the Controllers and the Repositories.

## Responsibilities
- **Business Logic execution**: Ensures data correctness and relationships between domains.
- **Repository Orchestration**: A Service might need to call multiple Repositories (e.g., `OrderService` uses `OrderRepository`, `CommissionRepository`, and `VendorRepository`).
- **Event Dispatching**: Services are responsible for firing domain events (`EventManager->dispatch()`) after successful state changes (e.g., `VendorApproved` event).
- **Error Handling**: Throws `ServiceException` for business rule violations, which the `ExceptionHandler` catches and converts into standard API error responses.

## Guidelines for writing Services
- **No Global Variables**: Never use `$wpdb` or `$_POST` inside a Service. All data must be passed into the service via DTOs or arguments.
- **Dependency Injection**: Always declare Repositories and other Services in the constructor. The Container will resolve them.
- **Do not return Responses**: Services should return primitive types, arrays, or DTOs. It is the Controller's job to format the output as an HTTP Response.
