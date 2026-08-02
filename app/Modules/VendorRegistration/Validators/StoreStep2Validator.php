<?php
namespace VMP\Modules\VendorRegistration\Validators;

class StoreStep2Validator
{
    public static function validate(array $payload): array
    {
        $errors = [];
        $branding = $payload['branding'] ?? [];
        // logo and banner are URIs or attachment ids handled elsewhere; check simple constraints
        if (isset($branding['brand_color']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $branding['brand_color'])) {
            $errors['branding.brand_color'] = 'Invalid color. Use hex, e.g. #RRGGBB.';
        }
        return $errors;
    }
}
