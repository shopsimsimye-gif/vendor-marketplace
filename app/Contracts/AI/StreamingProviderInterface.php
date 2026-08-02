<?php
namespace VMP\Contracts\AI;

defined('ABSPATH') || exit;

use VMP\Modules\AI\Context\CapabilityContext;

/**
 * Contract for providers capable of streaming AI responses.
 */
interface StreamingProviderInterface
{
    /**
     * Stream a normalized response from provider-neutral messages.
     *
     * @param CapabilityContext $context Capability request context.
     * @param callable $onChunk Callback invoked for each chunk of streamed data.
     * @param array $options Provider-specific options such as model, locale, or stream format.
     * @return void
     */
    public function stream(CapabilityContext $context, callable $onChunk, array $options = []): void;
}
