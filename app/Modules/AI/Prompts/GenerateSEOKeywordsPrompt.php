<?php
namespace VMP\Modules\AI\Prompts;

defined('ABSPATH') || exit;

/**
 * Class GenerateSEOKeywordsPrompt
 *
 * Description of administrative platform component GenerateSEOKeywordsPrompt.
 *
 * @package vendor-marketplace
 */
class GenerateSEOKeywordsPrompt extends AbstractPrompt
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
            ['role' => 'system', 'content' => 'Generate relevant SEO keywords for this marketplace product. Return structured JSON.'],
            ['role' => 'user', 'content' => $this->contextMessage($context)],
        ];
    }
}
