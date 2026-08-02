<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class AdminGetWithdrawalsRequest
 *
 * Description of administrative platform component AdminGetWithdrawalsRequest.
 *
 * @package vendor-marketplace
 */
class AdminGetWithdrawalsRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_manage_withdrawals');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'limit' => ['integer'],
        ];
    }
}
