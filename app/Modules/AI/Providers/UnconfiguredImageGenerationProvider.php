<?php
namespace VMP\Modules\AI\Providers;

defined('ABSPATH') || exit;

use VMP\Contracts\AI\ImageGenerationProviderInterface;

/**
 * Class UnconfiguredImageGenerationProvider
 *
 * Description of administrative platform component UnconfiguredImageGenerationProvider.
 *
 * @package vendor-marketplace
 */
class UnconfiguredImageGenerationProvider extends UnconfiguredProvider implements ImageGenerationProviderInterface
{
    /**
     * Generate functionality helper.
     *
     * @param string $prompt Description index.
     * @param array $options Description index.
     * @return array Output payload.
     */
    public function generate(string $prompt, array $options = []): array
    {
        $this->unavailable();
    }
}
