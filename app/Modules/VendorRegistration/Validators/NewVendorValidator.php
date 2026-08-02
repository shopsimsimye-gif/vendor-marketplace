<?php
namespace VMP\Modules\VendorRegistration\Validators;

class NewVendorValidator {
    public static function validate(array $data): array {
        $errors = [];
        if (empty($data['first_name'])) $errors['first_name'] = 'First name required.';
        if (empty($data['last_name'])) $errors['last_name'] = 'Last name required.';
        if (empty($data['username'])) $errors['username'] = 'Username required.';
        if (!empty($data['email']) && !is_email($data['email'])) $errors['email'] = 'Invalid email.';
        return $errors;
    }
}
