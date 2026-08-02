<?php
namespace VMP\Modules\VendorRegistration\Services;

class StateMachine {
    private array $transitions = [
        'guest' => ['account_created'],
        'account_created' => ['draft'],
        'draft' => ['submitted'],
        'submitted' => ['under_review'],
        'under_review' => ['store_setup', 'rejected'],
        'rejected' => ['draft'],
        'store_setup' => ['store_active'],
        'store_active' => ['active'],
    ];

    public function canTransition(string $from, string $to): bool {
        return in_array($to, $this->transitions[$from] ?? [], true);
    }
}
