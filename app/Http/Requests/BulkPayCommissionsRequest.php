<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class BulkPayCommissionsRequest
 *
 * Description of administrative platform component BulkPayCommissionsRequest.
 *
 * @package vendor-marketplace
 */
class BulkPayCommissionsRequest extends AbstractRequest
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
            'ids' => ['required', 'array'],
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
            'ids' => __('معرفات العمولات', 'vmp'),
        ];
    }

    /**
     * Validated functionality helper.
     *
     * @return array Output payload.
     */
    public function validated(): array
    {
        $data = parent::validated();
        if (isset($data['ids'])) {
            $data['ids'] = array_map('intval', (array) $data['ids']);
        }
        return $data;
    }
}
