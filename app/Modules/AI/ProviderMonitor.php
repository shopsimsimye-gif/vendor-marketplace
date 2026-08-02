<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

class ProviderMonitor
{
    private const OPTION_KEY = 'vmp_provider_health';

    public function markSuccess(string $provider): void
    {
        $data = $this->getData();
        $entry = $data[$provider] ?? ['successes' => 0, 'failures' => 0, 'last_failed_at' => null];
        $entry['successes']++;
        $data[$provider] = $entry;
        update_option(self::OPTION_KEY, $data);
    }

    public function markFailure(string $provider): void
    {
        $data = $this->getData();
        $entry = $data[$provider] ?? ['successes' => 0, 'failures' => 0, 'last_failed_at' => null];
        $entry['failures']++;
        $entry['last_failed_at'] = current_time('mysql');
        $data[$provider] = $entry;
        update_option(self::OPTION_KEY, $data);
    }

    public function getHealth(string $provider): array
    {
        $data = $this->getData();
        return $data[$provider] ?? ['successes' => 0, 'failures' => 0, 'last_failed_at' => null];
    }

    private function getData(): array
    {
        $data = get_option(self::OPTION_KEY, []);
        return is_array($data) ? $data : [];
    }
}
