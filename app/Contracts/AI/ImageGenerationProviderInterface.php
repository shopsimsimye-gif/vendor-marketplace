<?php
namespace VMP\Contracts\AI;

defined('ABSPATH') || exit;

/**
 * Contract for providers that create or edit promotional product images.
 */
interface ImageGenerationProviderInterface
{
    /**
     * Generate an image from a prompt and optional source assets.
     *
     * @param string $prompt Description of the desired image.
     * @param array $options Provider-specific options such as size, style, model, or reference images.
     * @return array Provider-normalized image result, for example URL, attachment ID, bytes, or metadata.
     */
    public function generate(string $prompt, array $options = []): array;
}
