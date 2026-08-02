<?php
namespace VMP\Modules\AI\Workflows;

defined('ABSPATH') || exit;

/**
 * Class WorkflowContext
 *
 * Description of administrative platform component WorkflowContext.
 *
 * @package vendor-marketplace
 */
class WorkflowContext
{
    /**
     *   Construct functionality helper.
     *
     * @param array $data Description index.
     * @return void Output payload.
     */
    public function __construct(private array $data = [])
    {
    }

    /**
     * Get functionality helper.
     *
     * @param string $key Description index.
     * @param mixed $default Description index.
     * @return mixed Output payload.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Set functionality helper.
     *
     * @param string $key Description index.
     * @param mixed $value Description index.
     * @return self Output payload.
     */
    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Merge functionality helper.
     *
     * @param array $data Description index.
     * @return self Output payload.
     */
    public function merge(array $data): self
    {
        $this->data = array_replace_recursive($this->data, $data);
        return $this;
    }

    /**
     * All functionality helper.
     *
     * @return array Output payload.
     */
    public function all(): array
    {
        return $this->data;
    }
}
