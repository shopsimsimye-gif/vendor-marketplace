<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class GetWhatsappStatsRequest
 *
 * Description of administrative platform component GetWhatsappStatsRequest.
 *
 * @package vendor-marketplace
 */
class GetWhatsappStatsRequest extends AbstractRequest
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
            'period' => ['string'],
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
        if (empty($data['period'])) {
            $data['period'] = 'month';
        }
        return $data;
    }
}
