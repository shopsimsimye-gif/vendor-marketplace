<?php
namespace VMP\Modules\VendorRegistration\Services\Health\Checks;

use VMP\Modules\VendorRegistration\Services\Health\HealthCheckInterface;

class StoreInfoHealthCheck implements HealthCheckInterface
{
    public function run($request): array
    {
        $max = 1; $score = 0; $msg = '';
        if (!empty($request->store_name) && !empty($request->store_description)) {
            $score = 1;
        } else {
            $msg = 'Incomplete store details';
        }

        return [
            'key' => 'store_info',
            'passed' => $score >= 1,
            'score' => $score,
            'max_score' => $max,
            'message' => $msg,
        ];
    }
}
