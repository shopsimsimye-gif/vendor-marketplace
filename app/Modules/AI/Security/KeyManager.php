<?php
namespace VMP\Modules\AI\Security;

defined('ABSPATH') || exit;

class KeyManager
{
    private string $constant_name = 'VMP_ENCRYPTION_KEY';

    public function __construct(string $constant_name = 'VMP_ENCRYPTION_KEY')
    {
        $this->constant_name = $constant_name;
    }

    public function getKey(): ?string
    {
        if ( defined($this->constant_name) && !empty(constant($this->constant_name)) ) {
            return constant($this->constant_name);
        }

        return null;
    }

    /**
     * Return the raw binary key (32 bytes) decoded from configured constant.
     * Supports base64, hex (64 hex chars), or raw string of length 32.
     */
    public function getRawKey(): ?string
    {
        $k = $this->getKey();
        if ( empty($k) ) {
            return null;
        }

        // Try base64
        $decoded = base64_decode($k, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }

        // Try hex
        if (ctype_xdigit($k) && strlen($k) === 64) {
            $bin = hex2bin($k);
            if ($bin !== false && strlen($bin) === 32) {
                return $bin;
            }
        }

        // Raw string
        if (strlen($k) === 32) {
            return $k;
        }

        // Fallback: derive 32-byte key via SHA-256 of provided secret
        $derived = hash('sha256', $k, true);
        if ($derived !== false && strlen($derived) === 32) {
            return $derived;
        }

        return null;
    }

    public function validateKey(?string $key = null): bool
    {
        $k = $key ?? $this->getKey();
        if ( empty($k) ) {
            return false;
        }

        return $this->getRawKey() !== null;
    }

    public function keyVersion(): int
    {
        return (int) get_option('vmp_encryption_key_version', 1);
    }

    public function setKeyVersion(int $v): void
    {
        update_option('vmp_encryption_key_version', (int) $v);
    }

    public function rotateKey(string $newKey): void
    {
        // Rotation is a higher-level operation managed by SecretManager/WP-CLI.
        // This just sets the option to the new version placeholder if needed.
        // Real re-encryption is handled elsewhere.
        // Increment key version
        $current = $this->keyVersion();
        $this->setKeyVersion($current + 1);
    }
}
