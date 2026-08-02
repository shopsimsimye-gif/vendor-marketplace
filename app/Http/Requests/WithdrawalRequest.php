<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

/**
 * Class WithdrawalRequest
 *
 * Description of administrative platform component WithdrawalRequest.
 *
 * @package vendor-marketplace
 */
class WithdrawalRequest extends AbstractRequest
{
    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'vendor_id'      => ['required', 'integer'],
            'amount'         => ['required', 'numeric', 'min_value:0'],
            'method'         => ['required', 'string', 'in:bank_transfer,paypal,other'],
            'method_details' => ['array'],
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
            'vendor_id'      => __('معرف البائع', 'vmp'),
            'amount'         => __('المبلغ', 'vmp'),
            'method'         => __('طريقة السحب', 'vmp'),
            'method_details' => __('تفاصيل السحب', 'vmp'),
        ];
    }

    /**
     * Validate functionality helper.
     *
     * @return bool Output payload.
     */
    public function validate(): bool
    {
        if (!parent::validate()) {
            return false;
        }

        $minWithdrawal = (float) get_option('vmp_min_withdrawal', 50);
        $amount = $this->float('amount');
        
        if ($amount < $minWithdrawal) {
            $errors = $this->errors();
            $errors[] = sprintf(__('الحد الأدنى للسحب هو %s.', 'vmp'), $minWithdrawal);
            
            $reflection = new \ReflectionClass(parent::class);
            $property = $reflection->getProperty('errors');
            $property->setAccessible(true);
            $property->setValue($this, $errors);
            
            return false;
        }

        return true;
    }
}
