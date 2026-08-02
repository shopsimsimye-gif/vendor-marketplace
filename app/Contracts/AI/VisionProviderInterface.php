<?php
namespace VMP\Contracts\AI;

defined('ABSPATH') || exit;

/**
 * Contract for providers that can inspect product images and extract structured data.
 */
interface VisionProviderInterface
{
    /**
     * Analyze an image and return provider-normalized attributes.
     *
     * @param string $image Image URL, local path, or attachment identifier understood by the adapter.
     * @param array $options Provider-specific options such as model, locale, or schema hints.
     * @return array Structured vision result, for example attributes, detected text, labels, and confidence.
     */
    public function analyze(string $image, array $options = []): array;
}
