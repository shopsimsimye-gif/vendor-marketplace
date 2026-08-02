<?php
namespace VMP\Policies;

defined('ABSPATH') || exit;

/**
 * Class PolicyResolver
 *
 * Description of administrative platform component PolicyResolver.
 *
 * @package vendor-marketplace
 */
class PolicyResolver
{
    /** @var array<string, object> */
    protected array $policies = [];

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    public function __construct()
    {
        $this->registerDefaultPolicies();
    }

    /**
     * RegisterDefaultPolicies functionality helper.
     *
     * @return void Output payload.
     */
    protected function registerDefaultPolicies(): void
    {
        $this->policies = [
            'vendor'       => new VendorPolicy(),
            'product'      => new ProductPolicy(),
            'order'        => new OrderPolicy(),
            'commission'   => new CommissionPolicy(),
            'withdrawal'   => new WithdrawalPolicy(),
            'subscription' => new SubscriptionPolicy(),
            'dashboard'    => new DashboardPolicy(),
        ];
    }

    /**
     * الحصول على الـ Policy المناسب بناءً على اسم الإجراء.
     *
     * @param string $action
     * @return object|null
     */
    public function resolve(string $action): ?object
    {
        $policyName = $this->guessPolicyName($action);
        return $this->policies[$policyName] ?? null;
    }

    /**
     * محاولة استنتاج اسم الـ Policy من الـ Action.
     */
    protected function guessPolicyName(string $action): string
    {
        $action = strtolower($action);

        $keys = array_keys($this->policies);
        foreach ($keys as $key) {
            if (str_contains($action, $key)) {
                return $key;
            }
        }

        return '';
    }
}
