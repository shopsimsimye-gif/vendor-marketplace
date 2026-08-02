<?php
namespace VMP\Modules\AI\Prompts;

defined('ABSPATH') || exit;

/**
 * Class AbstractPrompt
 *
 * Description of administrative platform component AbstractPrompt.
 *
 * @package vendor-marketplace
 */
abstract class AbstractPrompt implements PromptTemplateInterface
{
    /**
     * ContextMessage functionality helper.
     *
     * @param array $context Description index.
     * @return string Output payload.
     */
    protected function contextMessage(array $context): string
    {
        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded ?: '{}';
    }
}
