<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class SaveWhatsappSettingsRequest
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
        return current_user_can('vmp_vendor');
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'whatsapp_number'  => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'whatsapp_message' => ['nullable', 'string', 'max:1000'],
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
            'whatsapp_number'  => __('رقم واتساب', 'vmp'),
            'whatsapp_message' => __('رسالة واتساب', 'vmp'),
        ];
    }
}
