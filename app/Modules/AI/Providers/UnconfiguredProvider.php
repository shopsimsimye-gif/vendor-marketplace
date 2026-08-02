<?php
namespace VMP\Modules\AI\Providers;

defined('ABSPATH') || exit;

use VMP\Exceptions\AIException;

/**
 * Class UnconfiguredProvider
 *
 * Description of administrative platform component UnconfiguredProvider.
 *
 * @package vendor-marketplace
 */
abstract class UnconfiguredProvider
{
    /**
     * Unavailable functionality helper.
     *
     * @throws \AIException Diagnostic error when triggered.
     * @return never Output payload.
     */
    protected function unavailable(): never
    {
        throw new AIException('AI provider is not configured.');
    }
}
