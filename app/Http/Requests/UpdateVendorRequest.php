<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\Core\Container;
use VMP\Contracts\VendorRepositoryInterface;

/**
 * Class UpdateVendorRequest
 *
 * @package vendor-marketplace
 */
class UpdateVendorRequest extends AbstractRequest
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
            'store_name' => ['nullable', 'string', 'min:3', 'max:100', 'regex:/^[\p{L}\s\-0-9]+$/u'],
            'store_slug' => ['nullable', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9\-]+$/'],
            'phone'      => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
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
            'store_name' => __('اسم المتجر', 'vmp'),
            'store_slug' => __('الرابط المختصر', 'vmp'),
            'phone'      => __('رقم الهاتف', 'vmp'),
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

        $slug = $this->input('store_slug');
        if (empty($slug)) {
            return true;
        }

        $vendorId = (int) get_user_meta(get_current_user_id(), 'vmp_vendor_id', true);
        if ($vendorId <= 0) {
            $this->addError(__('لم يتم العثور على حساب البائع.', 'vmp'));
            return false;
        }

        /** @var VendorRepositoryInterface $vendorRepo */
        $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
        if (!$vendorRepo) {
            return true; // لا يمكن التحقق — نسمح بالمرور
        }

        $existing = $vendorRepo->findBySlug($slug);
        if ($existing && (int) $existing->id !== $vendorId) {
            $this->addError(__('الرابط المختصر مستخدم مسبقاً من قبل بائع آخر.', 'vmp'));
            return false;
        }

        return true;
    }
}
