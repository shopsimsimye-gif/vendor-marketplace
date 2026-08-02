<?php
namespace VMP\Modules\VendorRegistration\Services\Health\Checks;

use VMP\Modules\VendorRegistration\Services\Health\HealthCheckInterface;

class BrandingHealthCheck implements HealthCheckInterface
{
    public function run($request): array
    {
        $max = 1; $score = 0; $msg = '';
        if (!empty($request->logo) || !empty($request->banner)) {
            $score = 1;
        } else {
            $msg = 'Missing logo or banner';
        }

        return [
            'key' => 'branding',
            'passed' => $score >= 1,
            'score' => $score,
            'max_score' => $max,
            'message' => $msg,
        ];
    }
}
