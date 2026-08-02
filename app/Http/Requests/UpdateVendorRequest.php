<?php
namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\Core\Container;
use VMP\Contracts\VendorRepositoryInterface;

/**
 * Class UpdateVendorRequest
 *
 * Description of administrative platform component UpdateVendorRequest.
 *
 * @package vendor-marketplace
 */
class UpdateVendorRequest extends AbstractRequest
{
    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array
    {
        return [
            'store_name' => ['string', 'min:3', 'max:100'],
            'store_slug' => ['string'],
            'phone'      => ['string', 'phone'],
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

        $errors = [];

        $storeName = $this->string('store_name');
        if ($storeName && !preg_match('/^[\p{L}\s\-0-9]+$/u', $storeName)) {
            $errors[] = __('اسم المتجر يحتوي على أحرف غير مسموحة.', 'vmp');
        }

        $slug = $this->string('store_slug');
        if ($slug && !preg_match('/^[a-z0-9\-]+$/', $slug)) {
            $errors[] = __('الرابط المختصر (slug) يجب أن يحتوي على أحرف وأرقام وشرطات فقط.', 'vmp');
        }

        if ($slug) {
            $vendor_id = (int) get_user_meta(get_current_user_id(), 'vmp_vendor_id', true);
            if ($vendor_id) {
                /** @var VendorRepositoryInterface $vendorRepo */
                $vendorRepo = Container::getInstance()->make(VendorRepositoryInterface::class);
                if ($vendorRepo) {
                    $existing = $vendorRepo->findBySlug($slug);
                    if ($existing && (int) $existing->id !== $vendor_id) {
                        $errors[] = __('الرابط المختصر مستخدم مسبقاً من قبل بائع آخر.', 'vmp');
                    }
                }
            }
        }

        if (!empty($errors)) {
            $reflection = new \ReflectionClass(parent::class);
            $property = $reflection->getProperty('errors');
            $property->setAccessible(true);
            $existingErrors = $property->getValue($this);
            $property->setValue($this, array_merge($existingErrors, $errors));
            
            return false;
        }

        return true;
    }
}
