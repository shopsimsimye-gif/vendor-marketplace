<?php
namespace VMP\Modules\AI\Prompts;

defined('ABSPATH') || exit;

/**
 * Class GenerateDescriptionPrompt
 *
 * Description of administrative platform component GenerateDescriptionPrompt.
 *
 * @package vendor-marketplace
 */
class GenerateDescriptionPrompt extends AbstractPrompt
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
            ['role' => 'system', 'content' => 'Generate a persuasive product description with clear benefits and accurate specs. Return structured JSON.'],
            ['role' => 'user', 'content' => $this->contextMessage($context)],
        ];
    }
}
