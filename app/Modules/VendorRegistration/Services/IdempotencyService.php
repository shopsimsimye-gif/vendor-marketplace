<?php
namespace VMP\Modules\VendorRegistration\Services;

class IdempotencyService
{
    /**
     * Check if a key exists. Returns true if already used.
     */
    public function exists(string $key): bool
    {
        if (empty($key)) return false;
        $tkey = $this->transientKey($key);
        return (bool) get_transient($tkey);
    }

    /**
     * Mark a key as used for $ttl seconds
     */
    public function mark(string $key, int $ttl = 86400): void
    {
        if (empty($key)) return;
        $tkey = $this->transientKey($key);
        set_transient($tkey, time(), $ttl);
    }

    private function transientKey(string $key): string
    {
        return 'vmp_idemp_' . md5($key);
    }
}
