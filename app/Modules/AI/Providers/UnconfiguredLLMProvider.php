<?php
namespace VMP\Modules\AI\Providers;

defined('ABSPATH') || exit;

use VMP\Contracts\AI\LLMProviderInterface;

/**
 * Class UnconfiguredLLMProvider
 *
 * Description of administrative platform component UnconfiguredLLMProvider.
 *
 * @package vendor-marketplace
 */
class UnconfiguredLLMProvider extends UnconfiguredProvider implements LLMProviderInterface
{
    /**
     * Generate functionality helper.
     *
     * @param array $messages Description index.
     * @param array $options Description index.
     * @return array Output payload.
     */
    public function generate(array $messages, array $options = []): array
    {
        $this->unavailable();
    }
}
