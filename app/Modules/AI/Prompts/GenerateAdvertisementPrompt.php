<?php
namespace VMP\Modules\AI\Prompts;

defined('ABSPATH') || exit;

/**
 * Class GenerateAdvertisementPrompt
 *
 * Description of administrative platform component GenerateAdvertisementPrompt.
 *
 * @package vendor-marketplace
 */
class GenerateAdvertisementPrompt extends AbstractPrompt
{
    /**
     * Messages functionality helper.
     *
     * @param array $context Description index.
     * @return array Output payload.
     */
    public function messages(array $context): array
    {
        return [
            ['role' => 'system', 'content' => 'Generate short advertising copy suitable for social media and marketplace promotions. Return structured JSON.'],
            ['role' => 'user', 'content' => $this->contextMessage($context)],
        ];
    }
}
