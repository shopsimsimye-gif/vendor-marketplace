<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class AdminWhatsappSettingsRequest
 *
 * Description of administrative platform component AdminWhatsappSettingsRequest.
 *
 * @package vendor-marketplace
 */
class AdminWhatsappSettingsRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'show_on_product' => ['boolean'],
            'default_message' => ['string'],
        ];
    }
}
