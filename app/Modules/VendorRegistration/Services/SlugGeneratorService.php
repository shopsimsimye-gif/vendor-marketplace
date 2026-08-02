<?php
namespace VMP\Modules\VendorRegistration\Services;

use VMP\Modules\VendorRegistration\Repositories\VendorStoreRepositoryInterface;

class SlugGeneratorService
{
    public function __construct(private VendorStoreRepositoryInterface $storesRepo)
    {
    }

    /**
     * Generate a unique slug based on a desired base string.
     * Will sanitize and append -2, -3 ... if collisions occur.
     */
    public function generateUnique(string $base, int $vendorId = 0): string
    {
        $base = sanitize_title($base);
        if (empty($base)) {
            $base = 'vendor-' . ($vendorId ?: wp_rand(1000, 9999));
        }

        $slug = $base;
        $attempt = 1;
        while (true) {
            $existing = $this->storesRepo->findBySlug($slug);
            if (!$existing) {
                return $slug;
            }
            // if existing belongs to same vendor, reuse
            if ($vendorId && (int)$existing->vendor_id === (int)$vendorId) {
                return $slug;
            }
            $attempt++;
            $slug = $base . '-' . $attempt;
            // safety break
            if ($attempt > 200) {
                // fallback
                return $slug . '-' . time();
            }
        }
    }
}
