<?php
namespace VMP\Modules\VendorRegistration\Services\Health\Checks;

use VMP\Modules\VendorRegistration\Services\Health\HealthCheckInterface;

class PoliciesHealthCheck implements HealthCheckInterface
{
    public function run($request): array
    {
        $max = 1; $score = 0; $msg = '';
        if (!empty($request->terms_accepted) || !empty($request->policies)) {
            $score = 1;
        } else {
            $msg = 'Missing policies or terms accepted';
        }

        return [
            'key' => 'policies',
            'passed' => $score >= 1,
            'score' => $score,
            'max_score' => $max,
            'message' => $msg,
        ];
    }
}
