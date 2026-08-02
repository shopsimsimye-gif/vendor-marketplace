<?php
namespace VMP\Modules\VendorRegistration\Services;

use VMP\Modules\VendorRegistration\DTOs\NewVendorDTO;
use VMP\Modules\VendorRegistration\Repositories\VendorRequestRepositoryInterface;

class RegistrationService {
    public function __construct(private VendorRequestRepositoryInterface $requestsRepo) {}

    public function register(NewVendorDTO $dto) {
        // sanitize & validate dto fields (use Validators)
        $data = [
            'user_id' => $dto->user_id ?? 0,
            'username' => sanitize_user($dto->username),
            'email' => sanitize_email($dto->email),
            'first_name' => sanitize_text_field($dto->first_name),
            'last_name' => sanitize_text_field($dto->last_name),
            'status' => 'draft',
            'draft_data' => wp_json_encode($dto->toArray()),
        ];
        $request = $this->requestsRepo->create($data);
        do_action('vmp.vendor.registered', $request);
        return $request;
    }
}
