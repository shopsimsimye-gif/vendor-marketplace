<?php
namespace VMP\Modules\AI\Context;

defined('ABSPATH') || exit;

/**
 * Class VendorContext
 *
 * Description of administrative platform component VendorContext.
 *
 * @package vendor-marketplace
 */
class VendorContext implements PromptContextInterface
{
    public function __construct(
        public readonly int $vendorId = 0,
        public readonly string $storeName = '',
        public readonly string $tone = 'professional'
    ) {
    }

    /**
     * FromArray functionality helper.
     *
     * @param array $data Description index.
     * @return self Output payload.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            vendorId: (int) ($data['vendor_id'] ?? 0),
            storeName: (string) ($data['store_name'] ?? ''),
            tone: (string) ($data['tone'] ?? 'professional')
        );
    }

    /**
     * ToPromptContext functionality helper.
     *
     * @return array Output payload.
     */
    public function toPromptContext(): array
    {
        return [
            'vendor' => [
                'id' => $this->vendorId,
                'store_name' => $this->storeName,
                'tone' => $this->tone,
            ],
        ];
    }
}
