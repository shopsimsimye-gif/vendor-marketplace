<?php
namespace VMP\Modules\VendorRegistration\Validators;

class StoreStep3Validator
{
    public static function validate(array $payload): array
    {
        $errors = [];
        $contact = $payload['contact'] ?? [];
        if (empty($contact['phone'])) $errors['contact.phone'] = 'Phone is required.';
        if (!empty($contact['email']) && !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) $errors['contact.email'] = 'Invalid email.';
        return $errors;
    }
}
