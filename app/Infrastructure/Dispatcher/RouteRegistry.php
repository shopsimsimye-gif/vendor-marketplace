<?php
namespace VMP\Infrastructure\Dispatcher;

defined('ABSPATH') || exit;

/**
 * Class RouteRegistry
 *
 * Description of administrative platform component RouteRegistry.
 *
 * @package vendor-marketplace
 */
class RouteRegistry
{
    /** @var array<string, array{controller: string, method: string, is_public: bool, nonce_action: string, nonce_field: string}> */
    protected array $ajaxRoutes = [];

    /**
     * تسجيل مسار AJAX
     *
     * @param string $action       اسم الهوك الخاص بـ AJAX (مثلاً: vmp_register_vendor)
     * @param string $controller   اسم كلاس الكنترولر (مثلاً: VendorController::class)
     * @param string $method       اسم الدالة (مثلاً: registerVendor)
     * @param bool   $isPublic     هل المسار متاح للزوار غير المسجلين؟
     * @param string $nonce_action اسم الـ nonce action للتحقق من CSRF (فارغ = تجاهل التحقق)
     * @param string $nonce_field  اسم حقل الـ nonce في بيانات POST
     */
    public function ajax(
        string $action,
        string $controller,
        string $method,
        bool   $isPublic     = false,
        string $nonce_action = '',
        string $nonce_field  = '_wpnonce'
    ): void {
        $this->ajaxRoutes[$action] = [
            'controller'   => $controller,
            'method'       => $method,
            'is_public'    => $isPublic,
            'nonce_action' => $nonce_action,
            'nonce_field'  => $nonce_field,
        ];
    }

    /**
     * الحصول على جميع مسارات AJAX المسجلة.
     *
     * @return array<string, array{controller: string, method: string, is_public: bool, nonce_action: string, nonce_field: string}>
     */
    public function getAjaxRoutes(): array
    {
        return $this->ajaxRoutes;
    }
}
