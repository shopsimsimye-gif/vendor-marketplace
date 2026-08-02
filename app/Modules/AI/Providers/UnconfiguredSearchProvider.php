<?php
namespace VMP\Modules\AI\Providers;

defined('ABSPATH') || exit;

use VMP\Contracts\AI\SearchProviderInterface;

/**
 * Class UnconfiguredSearchProvider
 *
 * Description of administrative platform component UnconfiguredSearchProvider.
 *
 * @package vendor-marketplace
 */
class UnconfiguredSearchProvider extends UnconfiguredProvider implements SearchProviderInterface
{
    /**
     * Search functionality helper.
     *
     * @param string $query Description index.
     * @param array $options Description index.
     * @return array Output payload.
     */
    public function search(string $query, array $options = []): array
    {
        $this->unavailable();
    }
}
