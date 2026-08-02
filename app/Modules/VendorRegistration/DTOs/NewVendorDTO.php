<?php
namespace VMP\Modules\VendorRegistration\DTOs;

class NewVendorDTO {
    public ?int $user_id;
    public ?string $first_name;
    public ?string $last_name;
    public ?string $username;
    public ?string $email;
    public ?string $phone;

    public function __construct(array $data = []) {
        $this->user_id = $data['user_id'] ?? null;
        $this->first_name = $data['first_name'] ?? null;
        $this->last_name = $data['last_name'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->phone = $data['phone'] ?? null;
    }

    public function toArray(): array {
        return [
            'user_id' => $this->user_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
