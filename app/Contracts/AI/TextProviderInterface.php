<?php
namespace VMP\Contracts\AI;

defined('ABSPATH') || exit;

use VMP\Modules\AI\Context\CapabilityContext;

/**
 * Contract for text providers used by AI commerce workflows.
 */
interface TextProviderInterface
{
    /**
     * Generate a normalized response from provider-neutral messages or prompts.
     *
     * @param CapabilityContext $context Contextual request payload for the text capability.
     * @return array Provider-normalized response containing generated content and metadata.
     */
    public function generate(CapabilityContext $context): array;
}
