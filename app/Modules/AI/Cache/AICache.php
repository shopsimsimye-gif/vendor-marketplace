<?php
namespace VMP\Modules\AI\Cache;

defined('ABSPATH') || exit;

use VMP\Modules\AI\AIConfiguration;

/**
 * Class AICache
 *
 * Description of administrative platform component AICache.
 *
 * @package vendor-marketplace
 */
class AICache
{
    private static array $memory = [];

    /**
     *   Construct functionality helper.
     *
     * @param AIConfiguration $configuration Description index.
     * @return void Output payload.
     */
    public function __construct(private AIConfiguration $configuration)
    {
    }

    /**
     * Remember functionality helper.
     *
     * @param string $key Description index.
     * @param callable $callback Description index.
     * @param ?int $ttl Description index.
     * @return mixed Output payload.
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (!$this->configuration->cacheEnabled()) {
            return $callback();
        }

        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get functionality helper.
     *
     * @param string $key Description index.
     * @return mixed Output payload.
     */
    public function get(string $key): mixed
    {
        $key = $this->normalizeKey($key);

        if (array_key_exists($key, self::$memory)) {
            return self::$memory[$key];
        }

        if (function_exists('wp_cache_get')) {
            $found = false;
            $value = wp_cache_get($key, 'vmp_ai', false, $found);
            if ($found) {
                self::$memory[$key] = $value;
                return $value;
            }
        }

        if (function_exists('get_transient')) {
            $value = get_transient($key);
            if ($value !== false) {
                self::$memory[$key] = $value;
                return $value;
            }
        }

        return null;
    }

    /**
     * Set functionality helper.
     *
     * @param string $key Description index.
     * @param mixed $value Description index.
     * @param ?int $ttl Description index.
     * @return bool Output payload.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $key = $this->normalizeKey($key);
        $ttl ??= $this->configuration->cacheTtl();
        self::$memory[$key] = $value;

        if (function_exists('wp_cache_set')) {
            wp_cache_set($key, $value, 'vmp_ai', $ttl);
        }

        if (function_exists('set_transient')) {
            set_transient($key, $value, $ttl);
        }

        return true;
    }

    /**
     * Forget functionality helper.
     *
     * @param string $key Description index.
     * @return void Output payload.
     */
    public function forget(string $key): void
    {
        $key = $this->normalizeKey($key);
        unset(self::$memory[$key]);

        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($key, 'vmp_ai');
        }

        if (function_exists('delete_transient')) {
            delete_transient($key);
        }
    }

    /**
     * ImageKey functionality helper.
     *
     * @param string $image Description index.
     * @param array $options Description index.
     * @return string Output payload.
     */
    public function imageKey(string $image, array $options = []): string
    {
        return 'image_' . md5($image . serialize($options));
    }

    /**
     * WorkflowKey functionality helper.
     *
     * @param string $workflow Description index.
     * @param array $context Description index.
     * @param array $options Description index.
     * @return string Output payload.
     */
    public function workflowKey(string $workflow, array $context, array $options = []): string
    {
        return 'workflow_' . md5($workflow . serialize($context) . serialize($options));
    }

    /**
     * NormalizeKey functionality helper.
     *
     * @param string $key Description index.
     * @return string Output payload.
     */
    private function normalizeKey(string $key): string
    {
        return 'vmp_ai_' . md5($key);
    }
}
