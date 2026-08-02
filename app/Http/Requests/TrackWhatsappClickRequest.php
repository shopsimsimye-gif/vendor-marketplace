<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class TrackWhatsappClickRequest
 *
 * Description of administrative platform component TrackWhatsappClickRequest.
 *
 * @package vendor-marketplace
 */
class TrackWhatsappClickRequest extends AbstractRequest
{
    /**
     * Authorize functionality helper.
     *
     * @return bool Output payload.
     */
    public function authorize(): bool
    {
        return true; // متاح للجميع (مسجلين وغير مسجلين)
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'vendor_id'  => ['required', 'integer'],
            'product_id' => ['integer'],
            'page_url'   => ['string'],
            'click_type' => ['string'],
        ];
    }
}
