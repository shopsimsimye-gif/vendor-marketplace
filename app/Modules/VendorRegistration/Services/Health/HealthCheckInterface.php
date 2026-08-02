<?php
namespace VMP\Modules\VendorRegistration\Services\Health;

interface HealthCheckInterface
{
    /**
     * Run the health check against a vendor request record (object/array)
     * Returns an associative array with keys:
     * - passed: bool
     * - score: int (weight earned)
     * - max_score: int (weight possible)
     * - message: string
     * - key: string (id of check)
     *
     * @param object|array $request
     * @return array
     */
    public function run($request): array;
}
