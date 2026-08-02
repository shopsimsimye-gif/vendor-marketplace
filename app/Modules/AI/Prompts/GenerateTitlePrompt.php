<?php
namespace VMP\Modules\AI\Prompts;

defined('ABSPATH') || exit;

/**
 * Class GenerateTitlePrompt
 *
 * Description of administrative platform component GenerateTitlePrompt.
 *
 * @package vendor-marketplace
 */
class GenerateTitlePrompt extends AbstractPrompt
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
            ['role' => 'system', 'content' => 'Generate a concise, marketplace-ready product title. Return structured JSON.'],
            ['role' => 'user', 'content' => $this->contextMessage($context)],
        ];
    }
}
