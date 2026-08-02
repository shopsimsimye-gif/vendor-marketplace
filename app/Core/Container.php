<?php
namespace VMP\Core;

defined('ABSPATH') || exit;

/**
 * Class Container
 *
 * Description of administrative platform component Container.
 *
 * @package vendor-marketplace
 */
class Container
{
    private static ?Container $instance = null;

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, callable|class-string> */
    private array $bindings = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    private function __construct()
    {
    }

    /**
     * GetInstance functionality helper.
     *
     * @return self Output payload.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * SetInstance functionality helper.
     *
     * @param self $container Description index.
     * @return void Output payload.
     */
    public static function setInstance(self $container): void
    {
        self::$instance = $container;
    }

    /**
     * Bind functionality helper.
     *
     * @param string $abstract Description index.
     * @param callable|string $concrete Description index.
     * @return void Output payload.
     */
    public function bind(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        $this->singletons[$abstract] = false;
    }

    /**
     * Singleton functionality helper.
     *
     * @param string $abstract Description index.
     * @param callable|string $concrete Description index.
     * @return void Output payload.
     */
    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        $this->singletons[$abstract] = true;
    }

    /**
     * Instance functionality helper.
     *
     * @param string $abstract Description index.
     * @param mixed $instance Description index.
     * @return void Output payload.
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Has functionality helper.
     *
     * @param string $abstract Description index.
     * @return bool Output payload.
     */
    public function has(string $abstract): bool
    {
        return array_key_exists($abstract, $this->instances) || array_key_exists($abstract, $this->bindings);
    }

    /**
     * Make functionality helper.
     *
     * @param string $abstract Description index.
     * @param array $parameters Description index.
     * @return mixed Output payload.
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        if (!array_key_exists($abstract, $this->bindings)) {
            if (class_exists($abstract)) {
                return $this->build($abstract, $parameters);
            }

            return null;
        }

        $concrete = $this->bindings[$abstract];
        if ($this->singletons[$abstract] ?? false) {
            if (!array_key_exists($abstract, $this->instances)) {
                $this->instances[$abstract] = $this->resolve($concrete, $parameters);
            }

            return $this->instances[$abstract];
        }

        return $this->resolve($concrete, $parameters);
    }

    /**
     * Get functionality helper.
     *
     * @param string $abstract Description index.
     * @return mixed Output payload.
     */
    public function get(string $abstract)
    {
        return $this->make($abstract);
    }

    /**
     * Forget functionality helper.
     *
     * @param string $abstract Description index.
     * @return void Output payload.
     */
    public function forget(string $abstract): void
    {
        unset($this->instances[$abstract], $this->bindings[$abstract], $this->singletons[$abstract]);
    }

    /**
     * Resolve functionality helper.
     *
     * @param callable|string $concrete Description index.
     * @param array $parameters Description index.
     * @return mixed Output payload.
     */
    private function resolve(callable|string $concrete, array $parameters = []): mixed
    {
        if (is_callable($concrete)) {
            return $concrete($this, ...$parameters);
        }

        return $this->build($concrete, $parameters);
    }

    /**
     * Build functionality helper.
     *
     * @param string $class Description index.
     * @param array $parameters Description index.
     * @return mixed Output payload.
     */
    private function build(string $class, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            return null;
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                if ($className === self::class) {
                    $arguments[] = $this;
                    continue;
                }

                if ($this->has($className)) {
                    $arguments[] = $this->make($className);
                    continue;
                }

                if (class_exists($className)) {
                    $arguments[] = $this->build($className);
                    continue;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            $arguments[] = $parameters[$parameter->getName()] ?? null;
        }

        return $reflection->newInstanceArgs($arguments);
    }
}