<?php
namespace VMP\Modules\AI\Providers;

defined('ABSPATH') || exit;

use VMP\Contracts\AI\StreamingProviderInterface;
use VMP\Modules\AI\Context\CapabilityContext;

/**
 * Class UnconfiguredStreamingProvider
 *
 * Throws when streaming capability is requested but no provider is configured.
 */
class UnconfiguredStreamingProvider extends UnconfiguredProvider implements StreamingProviderInterface
{
    public function stream(CapabilityContext $context, callable $onChunk, array $options = []): void
    {
        $this->unavailable();
    }
}
