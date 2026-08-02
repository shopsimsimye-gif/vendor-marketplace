<?php
namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Exceptions\AuthorizationException;
use VMP\Contracts\AuthorizationServiceInterface;
use VMP\Policies\PolicyResolver;

/**
 * Class AuthorizationService
 *
 * Description of administrative platform component AuthorizationService.
 *
 * @package vendor-marketplace
 */
class AuthorizationService implements AuthorizationServiceInterface
{
    public function __construct(
        protected PolicyResolver $resolver
    ) {}

    /**
     * التحقق من الصلاحية ويرمي Exception إذا فشل.
     *
     * @param string $action اسم الإجراء (مثل: createProduct, updateVendor)
     * @param mixed $model الكائن المراد التحقق ضده (اختياري)
     * @return true
     * @throws AuthorizationException
     */
    public function authorize(string $action, mixed $model = null): bool
    {
        if (!$this->check($action, $model)) {
            throw new AuthorizationException(sprintf(__('غير مصرح لك بتنفيذ الإجراء: %s', 'vmp'), $action));
        }

        return true;
    }

    /**
     * التحقق من الصلاحية ويعيد قيمة منطقية.
     *
     * @param string $action
     * @param mixed $model
     * @return bool
     */
    public function check(string $action, mixed $model = null): bool
    {
        $policy = $this->resolver->resolve($action);

        if (!$policy) {
            // إذا لم يتم العثور على Policy محدد، نمنع الوصول افتراضياً.
            return false;
        }

        if (!method_exists($policy, $action)) {
            return false;
        }

        return $policy->$action($model);
    }
}
