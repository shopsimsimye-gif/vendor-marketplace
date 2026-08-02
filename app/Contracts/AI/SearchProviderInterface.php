<?php
namespace VMP\Contracts\AI;

defined('ABSPATH') || exit;

/**
 * Contract for web/search providers used to enrich product and pricing intelligence.
 */
interface SearchProviderInterface
{
    /**
     * Search external sources and return normalized results.
     *
     * @param string $query Search query.
     * @param array $options Provider-specific options such as locale, market, freshness, or result limit.
     * @return array Normalized search results with title, URL, snippet, source, and metadata where available.
     */
    public function search(string $query, array $options = []): array;
}
