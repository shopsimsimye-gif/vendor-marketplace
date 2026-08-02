<?php
namespace VMP\Modules\VendorRegistration\Validators;

class StoreStep1Validator
{
    public static function validate(array $payload): array
    {
        $errors = [];
        $store = $payload['store'] ?? [];
        if (empty($store['store_name'])) $errors['store.store_name'] = 'Store name is required.';
        if (isset($store['store_name']) && strlen($store['store_name']) > 191) $errors['store.store_name'] = 'Store name too long.';
        // optional slug validation
        if (!empty($store['store_slug'])) {
            if (preg_match('/[^a-z0-9\-]+/i', $store['store_slug'])) $errors['store.store_slug'] = 'Invalid slug characters.';
        }
        return $errors;
    }
}
