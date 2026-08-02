<?php
namespace VMP\Modules\VendorRegistration\Services\Health;

class HealthReport implements \JsonSerializable
{
    public int $percent_complete;
    public array $warnings = [];
    public int $previous_requests = 0;
    public string $last_activity = '';
    public array $details = [];

    public function __construct(int $percent = 0, array $warnings = [], int $previous = 0, string $lastActivity = '', array $details = [])
    {
        $this->percent_complete = $percent;
        $this->warnings = $warnings;
        $this->previous_requests = $previous;
        $this->last_activity = $lastActivity;
        $this->details = $details;
    }

    public function toArray(): array
    {
        return [
            'percent_complete' => $this->percent_complete,
            'warnings' => $this->warnings,
            'previous_requests' => $this->previous_requests,
            'last_activity' => $this->last_activity,
            'details' => $this->details,
        ];
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
