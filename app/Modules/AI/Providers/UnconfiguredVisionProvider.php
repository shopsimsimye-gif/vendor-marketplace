<?php
namespace VMP\Modules\AI\Providers;

defined('ABSPATH') || exit;

use VMP\Contracts\AI\VisionProviderInterface;

/**
 * Class UnconfiguredVisionProvider
 *
 * Description of administrative platform component UnconfiguredVisionProvider.
 *
 * @package vendor-marketplace
 */
class UnconfiguredVisionProvider extends UnconfiguredProvider implements VisionProviderInterface
{
    /**
     * Analyze functionality helper.
     *
     * @param string $image Description index.
     * @param array $options Description index.
     * @return array Output payload.
     */
    public function analyze(string $image, array $options = []): array
    {
        $this->unavailable();
    }
}
