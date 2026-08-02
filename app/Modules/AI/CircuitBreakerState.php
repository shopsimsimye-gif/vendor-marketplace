<?php
namespace VMP\Modules\AI;

defined('ABSPATH') || exit;

/**
 * Circuit Breaker States (PHP 7.4 compatible — class constants instead of enum)
 */
class CircuitBreakerState
{
    public const CLOSED = 'closed';       // Normal operation
    public const OPEN = 'open';           // Failing fast
    public const HALF_OPEN = 'half_open'; // Testing recovery

    private string $value;

    public function __construct(string $value)
    {
        if (!in_array($value, [self::CLOSED, self::OPEN, self::HALF_OPEN], true)) {
            throw new \InvalidArgumentException("Invalid circuit breaker state: {$value}");
        }
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public static function from(string $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
