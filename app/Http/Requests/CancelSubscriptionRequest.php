<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class CancelSubscriptionRequest
 *
 * Description of administrative platform component CancelSubscriptionRequest.
 *
 * @package vendor-marketplace
 */
class CancelSubscriptionRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [];
    }
}
