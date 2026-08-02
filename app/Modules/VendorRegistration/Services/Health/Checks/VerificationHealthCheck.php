<?php
namespace VMP\Modules\VendorRegistration\Services\Health\Checks;

use VMP\Modules\VendorRegistration\Services\Health\HealthCheckInterface;

class VerificationHealthCheck implements HealthCheckInterface
{
    public function run($request): array
    {
        $max = 1; $score = 0; $msg = '';
        // Example: vendor verification could be an 'is_verified' flag or documents
        if (!empty($request->is_verified) || !empty($request->verification_docs)) {
            $score = 1;
        } else {
            $msg = 'Vendor not verified';
        }

        return [
            'key' => 'verification',
            'passed' => $score >= 1,
            'score' => $score,
            'max_score' => $max,
            'message' => $msg,
        ];
    }
}
