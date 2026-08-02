<?php
namespace VMP\Actions;

defined('ABSPATH') || exit;

use VMP\DTO\VendorDTO;
use VMP\Exceptions\ValidationException;
use VMP\Repositories\VendorRepository;
use VMP\Validators\VendorValidator;

/**
 * Class RegisterVendor
 *
 * Description of administrative platform component RegisterVendor.
 *
 * @package vendor-marketplace
 */
class RegisterVendor
{
    public function __construct(
        private VendorRepository $repository,
        private VendorValidator $validator
    ) {
    }

    /**
     * Execute functionality helper.
     *
     * @param array $data Description index.
     * @throws \\RuntimeException Diagnostic error when triggered.
     * @return VendorDTO Output payload.
     */
    public function execute(array $data): VendorDTO
    {
        $errors = $this->validator->validate($data);
        if ($errors !== []) {
            throw new ValidationException(implode(', ', $errors));
        }

        $id = $this->repository->create($data);
        if ($id === false) {
            throw new \RuntimeException(__('فشل إنشاء البائع', 'vmp'));
        }

        $vendor = $this->repository->find($id);
        return VendorDTO::fromArray((array) $vendor);
    }
}
