<?php
namespace VMP\Modules\VendorRegistration\Services;

class NotificationService implements NotificationServiceInterface
{
    /** @var array<string, callable> */
    private array $channels;

    /**
     * $channels should be a map like ['email' => EmailChannelInstance, 'in_app' => InAppChannelInstance]
     */
    public function __construct(array $channels = [])
    {
        $this->channels = $channels;
    }

    public function notify(array $channels, array $payload): bool
    {
        $ok = true;
        foreach ($channels as $ch) {
            if (!isset($this->channels[$ch])) {
                // channel not configured — skip but mark as not ok
                $ok = false;
                continue;
            }
            try {
                $res = call_user_func($this->channels[$ch], $payload);
                if ($res === false) $ok = false;
            } catch (\Throwable $e) {
                // swallow to allow other channels to proceed but mark overall failure
                $ok = false;
                error_log('NotificationService channel ' . $ch . ' failed: ' . $e->getMessage());
            }
        }
        return $ok;
    }
}
