<?php
// Rate limiting and reCAPTCHA verification added to RegistrationController (helpers)

namespace VMP\Modules\VendorRegistration\Controllers;

use WP_REST_Request;
use WP_REST_Response;

trait RegistrationSecurityHelpers
{
    private function getIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function rateLimitExceeded(string $key, int $limit, int $periodSeconds): bool
    {
        $transientKey = 'vmp_rl_' . md5($key);
        $data = get_transient($transientKey) ?: ['count' => 0, 'reset' => time() + $periodSeconds];
        if ($data['count'] >= $limit && time() < $data['reset']) {
            return true;
        }
        return false;
    }

    private function incrementRateLimit(string $key, int $periodSeconds): void
    {
        $transientKey = 'vmp_rl_' . md5($key);
        $data = get_transient($transientKey) ?: ['count' => 0, 'reset' => time() + $periodSeconds];
        $data['count'] = ($data['count'] ?? 0) + 1;
        set_transient($transientKey, $data, $periodSeconds);
    }

    private function verifyRecaptcha(string $token): bool
    {
        $secret = get_option('vmp_recaptcha_secret_key');
        if (empty($secret) || empty($token)) return false;

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $this->getIp(),
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) return false;
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        return !empty($json['success']);
    }
}
