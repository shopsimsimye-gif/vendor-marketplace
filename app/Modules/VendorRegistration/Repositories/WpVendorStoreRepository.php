<?php
namespace VMP\Modules\VendorRegistration\Repositories;

class WpVendorStoreRepository implements VendorStoreRepositoryInterface {
    private \wpdb $wpdb;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'vmp_vendor_stores';
    }

    public function findByVendor(int $vendorId): ?object
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE vendor_id = %d LIMIT 1", $vendorId));
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?object
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE store_slug = %s LIMIT 1", $slug));
        return $row ?: null;
    }

    public function create(array $data): object
    {
        $this->wpdb->insert($this->table, $data);
        return $this->findByVendor((int)$data['vendor_id']);
    }

    public function update(int $id, array $data): bool
    {
        $updated = $this->wpdb->update($this->table, $data, ['id' => $id]);
        return $updated !== false;
    }
}
