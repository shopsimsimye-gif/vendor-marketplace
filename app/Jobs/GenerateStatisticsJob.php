<?php
namespace VMP\Jobs;

defined('ABSPATH') || exit;

use VMP\Core\Queue\JobInterface;
use VMP\Services\VendorService;
use VMP\Core\Container;

/**
 * Class GenerateStatisticsJob
 *
 * يتولى تحديث وحساب إحصائيات البائعين في الخلفية بشكل غير متزامن
 */
class GenerateStatisticsJob implements JobInterface
{
    public function __construct(
        private int $vendorId
    ) {}

    /**
     * Handle functionality helper.
     *
     * @return void Output payload.
     */
    public function handle(): void
    {
        /** @var VendorService $vendorService */
        $vendorService = Container::getInstance()->make(VendorService::class);
        
        $vendorService->updateStats($this->vendorId);
    }

    /**
     * GetPayload functionality helper.
     *
     * @return array Output payload.
     */
    public function getPayload(): array
    {
        return [
            'vendor_id' => $this->vendorId,
        ];
    }

    /**
     * FromPayload functionality helper.
     *
     * @param array $payload Description index.
     * @return self Output payload.
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            vendorId: (int) ($payload['vendor_id'] ?? 0)
        );
    }
}
