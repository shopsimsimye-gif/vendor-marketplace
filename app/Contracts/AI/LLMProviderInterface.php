<?php
namespace VMP\Contracts\AI;

defined('ABSPATH') || exit;

/**
 * Contract for text-generation providers used by AI commerce workflows.
 */
interface LLMProviderInterface extends TextProviderInterface
{
    /**
     * Generate a normalized response from chat-style messages.
     *
     * @param CapabilityContext $context Request context containing provider-neutral messages and metadata.
     * @return array Provider-normalized response containing generated content and metadata.
     */
    public function generate(CapabilityContext $context): array;
}
