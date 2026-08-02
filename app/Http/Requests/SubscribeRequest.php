<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class SubscribeRequest
 *
 * Description of administrative platform component SubscribeRequest.
 *
 * @package vendor-marketplace
 */
class SubscribeRequest extends AbstractRequest
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
        return [
            'plan_id' => ['required', 'integer'],
        ];
    }

    /**
     * Attributes functionality helper.
     *
     * @return array Output payload.
     */
    protected function attributes(): array
    {
        return [
            'plan_id' => __('معرف الخطة', 'vmp'),
        ];
    }
}
