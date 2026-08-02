<?php
namespace VMP\Modules\VendorRegistration\Validators;

class StoreStep5Validator
{
    public static function validate(array $payload): array
    {
        $errors = [];
        $social = $payload['social'] ?? [];
        if (!empty($social['facebook']) && !filter_var($social['facebook'], FILTER_VALIDATE_URL)) $errors['social.facebook'] = 'Invalid Facebook URL.';
        if (!empty($social['instagram']) && !filter_var($social['instagram'], FILTER_VALIDATE_URL)) $errors['social.instagram'] = 'Invalid Instagram URL.';
        return $errors;
    }
}
