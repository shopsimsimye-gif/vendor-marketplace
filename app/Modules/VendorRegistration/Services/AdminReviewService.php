<?php
namespace VMP\Modules\VendorRegistration\Services;

use VMP\Modules\VendorRegistration\Repositories\VendorRequestRepositoryInterface;
use VMP\Modules\VendorRegistration\Services\ActivityLogService;
use VMP\Modules\VendorRegistration\Services\EventBus;
use VMP\Modules\VendorRegistration\Events\VendorActivated;
use VMP\Modules\VendorRegistration\Events\StoreChangesRequested;
use VMP\Modules\VendorRegistration\Events\VendorRejected;

class AdminReviewService
{
    private VendorRequestRepositoryInterface $requestsRepo;
    private StateMachine $stateMachine;
    private EventBus $eventBus;
    private ActivityLogService $logger;

    public function __construct(VendorRequestRepositoryInterface $requestsRepo, StateMachine $stateMachine, EventBus $eventBus, ActivityLogService $logger)
    {
        $this->requestsRepo = $requestsRepo;
        $this->stateMachine = $stateMachine;
        $this->eventBus = $eventBus;
        $this->logger = $logger;
    }

    public function activate(int $requestId, int $adminId): bool
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $request = $this->requestsRepo->find($requestId);
            if (!$request) {
                throw new \InvalidArgumentException('Request not found');
            }

            $from = $request->status ?? 'draft';
            $to = 'active';
            if (!$this->stateMachine->canTransition($from, $to)) {
                throw new \RuntimeException("Cannot transition from {$from} to {$to}");
            }

            $ok = $this->requestsRepo->updateStatus($requestId, $to, null);
            if (!$ok) {
                $wpdb->query('ROLLBACK');
                return false;
            }

            // reload
            $request = $this->requestsRepo->find($requestId);

            // log activity
            $this->logger->log($adminId, 'Vendor activated', ['request_id' => $requestId]);

            // dispatch event
            $event = new VendorActivated($request, $adminId);
            $this->eventBus->dispatch($event);

            $wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public function requestChanges(int $requestId, int $adminId, string $message): bool
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $request = $this->requestsRepo->find($requestId);
            if (!$request) throw new \InvalidArgumentException('Request not found');

            $from = $request->status ?? 'draft';
            $to = 'changes_requested';
            // allow request changes from many states; check permissive
            if (!$this->stateMachine->canTransition($from, $to)) {
                // allow but log
            }

            $ok = $this->requestsRepo->updateStatus($requestId, $to, $message);
            if (!$ok) { $wpdb->query('ROLLBACK'); return false; }

            $request = $this->requestsRepo->find($requestId);

            $this->logger->log($adminId, 'Store changes requested', ['request_id'=>$requestId, 'message'=>$message]);

            $event = new StoreChangesRequested($request, $adminId, $message);
            $this->eventBus->dispatch($event);

            $wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public function reject(int $requestId, int $adminId, string $reason): bool
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $request = $this->requestsRepo->find($requestId);
            if (!$request) throw new \InvalidArgumentException('Request not found');

            $from = $request->status ?? 'draft';
            $to = 'rejected';
            if (!$this->stateMachine->canTransition($from, $to)) {
                // allow but log
            }

            $ok = $this->requestsRepo->updateStatus($requestId, $to, $reason);
            if (!$ok) { $wpdb->query('ROLLBACK'); return false; }

            $request = $this->requestsRepo->find($requestId);

            $this->logger->log($adminId, 'Vendor rejected', ['request_id'=>$requestId, 'reason'=>$reason]);

            $event = new VendorRejected($request, $adminId, $reason);
            $this->eventBus->dispatch($event);

            $wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
}
