<?php
namespace VMP\Modules\VendorRegistration\Validators;

class StoreStep4Validator
{
    public static function validate(array $payload): array
    {
        $errors = [];
        $policies = $payload['policies'] ?? [];
        // basic checks: length limits
        if (!empty($policies['shipping']) && strlen($policies['shipping']) > 5000) $errors['policies.shipping'] = 'Shipping policy too long.';
        if (!empty($policies['returns']) && strlen($policies['returns']) > 5000) $errors['policies.returns'] = 'Return policy too long.';
        return $errors;
    }
}
