<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class AdminGetCommissionsRequest
 *
 * Description of administrative platform component AdminGetCommissionsRequest.
 *
 * @package vendor-marketplace
 */
class AdminGetCommissionsRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('vmp_manage_commissions');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'status' => ['string'],
            'limit'  => ['integer'],
            'offset' => ['integer'],
        ];
    }
}
