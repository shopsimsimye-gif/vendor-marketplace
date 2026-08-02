<?php
namespace VMP\Modules\AI\Security;

defined('ABSPATH') || exit;

class EncryptionService
{
    private string $algorithm = 'aes-256-gcm';

    public function __construct(string $algorithm = 'aes-256-gcm')
    {
        $this->algorithm = $algorithm;

        // Ensure environment supports the cipher
        $methods = openssl_get_cipher_methods();
        if ( !in_array(strtolower($this->algorithm), array_map('strtolower', $methods), true) ) {
            throw new \RuntimeException(sprintf('Required cipher %s is not supported by OpenSSL on this system.', $this->algorithm));
        }
    }

    /**
     * Encrypt plaintext using AES-256-GCM.
     * Returns array with keys: ciphertext (base64), iv (base64), tag (base64), algorithm
     */
    public function encrypt(string $plaintext, string $key): array
    {
        $key = $this->normalizeKey($key);
        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';

        // Provide tag length explicitly (16 bytes) and pass by reference
        $ciphertext_raw = openssl_encrypt($plaintext, $this->algorithm, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext_raw === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return [
            'ciphertext' => base64_encode($ciphertext_raw),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'algorithm' => strtoupper($this->algorithm),
        ];
    }

    private function normalizeKey(string $key): string
    {
        if (strlen($key) === 32) {
            return $key;
        }

        return hash('sha256', $key, true);
    }

    /**
     * Decrypt AES-256-GCM ciphertext
     * Expects ciphertext/iv/tag base64 encoded
     */
    public function decrypt(string $ciphertext_b64, string $iv_b64, string $tag_b64, string $key): string
    {
        $key = $this->normalizeKey($key);
        $ciphertext = base64_decode($ciphertext_b64);
        $iv = base64_decode($iv_b64);
        $tag = base64_decode($tag_b64);

        $plaintext = openssl_decrypt($ciphertext, $this->algorithm, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed: authentication failed or invalid data');
        }

        return $plaintext;
    }
}
