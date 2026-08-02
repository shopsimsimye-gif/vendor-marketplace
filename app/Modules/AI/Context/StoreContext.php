<?php
namespace VMP\Modules\AI\Context;

defined('ABSPATH') || exit;

/**
 * Class StoreContext
 *
 * Description of administrative platform component StoreContext.
 *
 * @package vendor-marketplace
 */
class StoreContext implements PromptContextInterface
{
    public function __construct(
        public readonly string $name = '',
        public readonly string $market = '',
        public readonly string $currency = '',
        public readonly array $policies = []
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
            name: (string) ($data['name'] ?? ''),
            market: (string) ($data['market'] ?? ''),
            currency: (string) ($data['currency'] ?? ''),
            policies: is_array($data['policies'] ?? null) ? $data['policies'] : []
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
            'store' => [
                'name' => $this->name,
                'market' => $this->market,
                'currency' => $this->currency,
                'policies' => $this->policies,
            ],
        ];
    }
}
