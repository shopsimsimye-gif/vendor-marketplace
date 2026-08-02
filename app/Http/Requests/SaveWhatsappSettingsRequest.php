<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class SaveWhatsappSettingsRequest
 *
 * Description of administrative platform component SaveWhatsappSettingsRequest.
 *
 * @package vendor-marketplace
 */
class SaveWhatsappSettingsRequest extends AbstractRequest
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
            'whatsapp_number'  => ['string'],
            'whatsapp_message' => ['string'],
        ];
    }
}
