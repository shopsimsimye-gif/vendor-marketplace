<?php
namespace VMP\Modules\AI\Security;

defined('ABSPATH') || exit;

class SecretManager
{
    private EncryptionService $encryption;
    private KeyManager $keyManager;

    public function __construct(EncryptionService $encryptionService, KeyManager $keyManager)
    {
        $this->encryption = $encryptionService;
        $this->keyManager = $keyManager;
    }

    public function encryptSecret(string $plaintext): array
    {
        $rawKey = $this->keyManager->getRawKey();
        if ( $rawKey === null ) {
            throw new \RuntimeException('Encryption key is not configured or invalid. Define VMP_ENCRYPTION_KEY in wp-config.php and provide a 32-byte key in base64/hex/raw.');
        }

        $payload = $this->encryption->encrypt($plaintext, $rawKey);
        // include key_version
        $payload['key_version'] = $this->keyManager->keyVersion();
        return $payload;
    }

    public function decryptSecret(string $ciphertext_b64, string $iv_b64, string $tag_b64): string
    {
        $rawKey = $this->keyManager->getRawKey();
        if ( $rawKey === null ) {
            throw new \RuntimeException('Encryption key is not configured or invalid. Define VMP_ENCRYPTION_KEY in wp-config.php and provide a 32-byte key in base64/hex/raw.');
        }

        return $this->encryption->decrypt($ciphertext_b64, $iv_b64, $tag_b64, $rawKey);
    }

    public function generateIV(): string
    {
        return base64_encode(random_bytes(12));
    }

    public function validateKey(): bool
    {
        return $this->keyManager->validateKey();
    }
}
