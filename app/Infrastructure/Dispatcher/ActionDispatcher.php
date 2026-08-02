<?php
namespace VMP\Infrastructure\Dispatcher;

defined('ABSPATH') || exit;

use VMP\Http\Responses\ApiResponse;
use VMP\Http\Requests\AbstractRequest;
use VMP\Exceptions\ValidationException;
use VMP\Exceptions\AuthorizationException;
use Throwable;

/**
 * Class ActionDispatcher
 *
 * Description of administrative platform component ActionDispatcher.
 *
 * @package vendor-marketplace
 */
class ActionDispatcher
{
    public function __construct(
        protected \VMP\Core\Container $container,
        protected RouteRegistry $routeRegistry,
        protected ExceptionHandler $exceptionHandler,
        protected ControllerMethodResolver $methodResolver
    ) {}

    /**
     * تسجيل مسارات AJAX في ووردبريس.
     */
    public function registerAjaxHooks(): void
    {
        $routes = $this->routeRegistry->getAjaxRoutes();

        foreach ($routes as $action => $routeData) {
            $controller   = $routeData['controller'];
            $method       = $routeData['method'];
            $isPublic     = $routeData['is_public'];
            $nonce_action = $routeData['nonce_action'] ?? '';
            $nonce_field  = $routeData['nonce_field']  ?? '_wpnonce';

            $callback = function () use ($controller, $method, $nonce_action, $nonce_field) {
                $this->dispatch($controller, $method, $nonce_action, $nonce_field);
            };

            add_action("wp_ajax_{$action}", $callback);

            if ($isPublic) {
                add_action("wp_ajax_nopriv_{$action}", $callback);
            }
        }
    }

    /**
     * تشغيل دورة حياة الطلب.
     *
     * @param string $controller
     * @param string $method
     * @param string $nonce_action اسم الـ nonce action (فارغ = تجاهل التحقق)
     * @param string $nonce_field  اسم حقل الـ nonce في POST
     */
    protected function dispatch(
        string $controller,
        string $method,
        string $nonce_action = '',
        string $nonce_field  = '_wpnonce'
    ): void {
        try {
            // 1. تحديد نوع الـ Request المطلوب
            $requestClass = $this->methodResolver->resolveRequestClass($controller, $method);
            $request = null;

            // 2. إنشاء الـ Request من POST مع تمرير معاملات الـ nonce للتحقق من CSRF
            if ($requestClass && is_subclass_of($requestClass, AbstractRequest::class)) {
                $request = call_user_func([$requestClass, 'fromPost'], $nonce_action, $nonce_field);

                // هذه الدالة بداخلها تقوم بـ authorize() ثم rules validation
                // ترمي الاستثناءات ValidationException و AuthorizationException
                $request->validate();
            }

            // 3. استدعاء الـ Controller عبر الـ Container (لحل الـ Dependencies)
            $controllerInstance = $this->container->make($controller);

            // 4. تمرير الـ Request (إذا كان مطلوباً)
            if ($request) {
                $response = $controllerInstance->$method($request);
            } else {
                $response = $controllerInstance->$method();
            }

            // 5. إرسال الرد
            if ($response instanceof ApiResponse) {
                $response->send();
            }

        } catch (Throwable $e) {
            // 6. التقاط جميع الاستثناءات وتوجيهها للمكان الموحد
            $this->exceptionHandler->handle($e);
        }
    }
}
