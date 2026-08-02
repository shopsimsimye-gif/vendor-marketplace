<?php
namespace VMP\Modules\VendorRegistration\Services\Health\Checks;

use VMP\Modules\VendorRegistration\Services\Health\HealthCheckInterface;

class WizardCompletionHealthCheck implements HealthCheckInterface
{
    public function run($request): array
    {
        $max = 1; $score = 0; $msg = '';
        // Example: wizard_steps_completed or wizard_step
        if (!empty($request->wizard_steps_completed) && $request->wizard_steps_completed >= 3) {
            $score = 1;
        } else {
            $msg = 'Wizard incomplete';
        }

        return [
            'key' => 'wizard_completion',
            'passed' => $score >= 1,
            'score' => $score,
            'max_score' => $max,
            'message' => $msg,
        ];
    }
}
