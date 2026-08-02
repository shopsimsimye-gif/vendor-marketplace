<?php
namespace VMP\Infrastructure\Dispatcher;

defined('ABSPATH') || exit;

use ReflectionMethod;
use ReflectionNamedType;
use VMP\Http\Requests\AbstractRequest;
use Exception;

/**
 * Class ControllerMethodResolver
 *
 * Description of administrative platform component ControllerMethodResolver.
 *
 * @package vendor-marketplace
 */
class ControllerMethodResolver
{
    /**
     * @param string $controller
     * @param string $method
     * @return string|null اسم كلاس الـ Request المطلوب، أو null إذا لم يكن هناك Request مخصص.
     */
    public function resolveRequestClass(string $controller, string $method): ?string
    {
        $cacheKey = 'vmp_resolver_' . md5($controller . '::' . $method);
        
        $cached = wp_cache_get($cacheKey, 'vmp_method_resolver');
        if ($cached !== false) {
            return $cached === 'none' ? null : $cached;
        }

        try {
            $reflection = new ReflectionMethod($controller, $method);
            $parameters = $reflection->getParameters();

            foreach ($parameters as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType) {
                    $typeName = $type->getName();
                    if (is_subclass_of($typeName, AbstractRequest::class)) {
                        wp_cache_set($cacheKey, $typeName, 'vmp_method_resolver', HOUR_IN_SECONDS);
                        return $typeName;
                    }
                }
            }
        } catch (Exception $e) {
            // تجاهل أخطاء Reflection
        }

        wp_cache_set($cacheKey, 'none', 'vmp_method_resolver', HOUR_IN_SECONDS);
        return null;
    }
}
