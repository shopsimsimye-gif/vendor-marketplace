<?php
namespace VMP\Modules\AI\Prompts;

defined('ABSPATH') || exit;

/**
 * Class AnalyzeImagePrompt
 *
 * Description of administrative platform component AnalyzeImagePrompt.
 *
 * @package vendor-marketplace
 */
class AnalyzeImagePrompt extends AbstractPrompt
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
            ['role' => 'system', 'content' => 'Analyze the product image and extract visible product attributes, text, materials, colors, and warnings. Return structured JSON.'],
            ['role' => 'user', 'content' => $this->contextMessage($context)],
        ];
    }
}
