<?php
namespace VMP\Modules\VendorRegistration\Repositories;

interface VendorStoreRepositoryInterface {
    public function findByVendor(int $vendorId): ?object;
    public function findBySlug(string $slug): ?object;
    public function create(array $data): object;
    public function update(int $id, array $data): bool;
}
