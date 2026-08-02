<?php
namespace VMP\Modules\AI\Repositories;

use VMP\Modules\AI\Security\SecretManager;

defined('ABSPATH') || exit;

class ProviderSecretRepository
{
    private \wpdb $wpdb;
    private SecretManager $secretManager;
    private string $table;

    public function __construct($wpdb, SecretManager $secretManager)
    {
        $this->wpdb = $wpdb;
        $this->secretManager = $secretManager;
        $this->table = $this->wpdb->prefix . 'vmp_ai_provider_secrets';
    }

    public function set(string $provider, string $secret_name, string $plaintext, int $created_by = 0): int
    {
        $payload = $this->secretManager->encryptSecret($plaintext);

        $data = [
            'provider' => $provider,
            'secret_name' => $secret_name,
            'encrypted_value' => $payload['ciphertext'],
            'iv' => $payload['iv'],
            'tag' => $payload['tag'],
            'algorithm' => $payload['algorithm'] ?? 'AES-256-GCM',
            'version' => $payload['version'] ?? '',
            'key_version' => $payload['key_version'] ?? $this->secretManager->validateKey() ? 1 : 1,
            'created_by' => $created_by,
            'updated_by' => $created_by,
            'is_active' => 1,
        ];

        $format = array_fill(0, count($data), '%s');
        $this->wpdb->insert($this->table, $data, $format);
        return (int) $this->wpdb->insert_id;
    }

    public function get(string $provider, string $secret_name): ?string
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE provider = %s AND secret_name = %s AND is_active = 1 ORDER BY id DESC LIMIT 1", $provider, $secret_name), ARRAY_A);
        if ( !$row ) {
            return null;
        }

        return $this->secretManager->decryptSecret($row['encrypted_value'], $row['iv'], $row['tag']);
    }

    public function findByProvider(string $provider): array
    {
        return $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE provider = %s", $provider), ARRAY_A);
    }

    public function rotateSecret(int $id, string $newPlaintext, int $rotated_by = 0): bool
    {
        // Deactivate old record and insert new one
        $old = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        if ( !$old ) {
            return false;
        }

        $this->wpdb->update($this->table, ['is_active' => 0, 'updated_by' => $rotated_by, 'last_rotated_at' => current_time('mysql')], ['id' => $id], ['%d','%d','%s'], ['%d']);

        $this->set($old['provider'], $old['secret_name'], $newPlaintext, $rotated_by);
        return true;
    }
}
