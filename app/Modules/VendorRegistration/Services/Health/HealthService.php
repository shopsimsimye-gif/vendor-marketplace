<?php
namespace VMP\Modules\VendorRegistration\Services\Health;

use VMP\Modules\VendorRegistration\Repositories\VendorRequestRepository;

class HealthService
{
    private VendorRequestRepository $repo;
    /** @var HealthCheckInterface[] */
    private array $checks = [];

    public function __construct(VendorRequestRepository $repo, array $checks = [])
    {
        $this->repo = $repo;

        if (!empty($checks)) {
            $this->checks = $checks;
        } else {
            // default checks
            $this->checks = [
                new Checks\BrandingHealthCheck(),
                new Checks\StoreInfoHealthCheck(),
                new Checks\PoliciesHealthCheck(),
                new Checks\VerificationHealthCheck(),
                new Checks\WizardCompletionHealthCheck(),
            ];
        }
    }

    public function getReport(int $id): HealthReport
    {
        $r = $this->repo->find($id);
        if (!$r) {
            return new HealthReport(0, ['not_found'], 0, '', []);
        }

        $totalScore = 0;
        $maxScore = 0;
        $warnings = [];
        $details = [];

        foreach ($this->checks as $check) {
            try {
                $res = $check->run($r);
            } catch (\Throwable $e) {
                // treat as failed check with zero score
                $res = ['passed' => false, 'score' => 0, 'max_score' => $res['max_score'] ?? 1, 'message' => 'exception'];
            }

            $totalScore += (int)($res['score'] ?? 0);
            $maxScore += (int)($res['max_score'] ?? 1);

            if (empty($res['passed'])) {
                $warnings[] = $res['message'] ?? 'failed';
            }

            $details[] = [
                'key' => $res['key'] ?? null,
                'passed' => (bool)($res['passed'] ?? false),
                'score' => (int)($res['score'] ?? 0),
                'max_score' => (int)($res['max_score'] ?? 1),
                'message' => $res['message'] ?? '',
            ];
        }

        $percent = $maxScore > 0 ? (int) ( ($totalScore / $maxScore) * 100 ) : 0;

        $prev = $this->repo->countRequestsByVendor($r->user_id ?? 0);

        $report = new HealthReport($percent, $warnings, (int)$prev, $r->updated_at ?? '', $details);

        return $report;
    }
}
