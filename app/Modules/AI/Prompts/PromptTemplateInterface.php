<?php
namespace VMP\Modules\AI\Prompts;

defined('ABSPATH') || exit;

interface PromptTemplateInterface
{
    /**
     * Messages functionality helper.
     *
     * @param array $context Description index.
     * @return array Output payload.
     */
    public function messages(array $context): array;
}
