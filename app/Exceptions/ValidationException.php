<?php
namespace VMP\Exceptions;

defined('ABSPATH') || exit;

use Throwable;

/**
 * Class ValidationException
 *
 * Description of administrative platform component ValidationException.
 *
 * @package vendor-marketplace
 */
class ValidationException extends BaseException
{
    /**
     * @var array<string>
     */
    protected array $errors = [];

    /**
     *   Construct functionality helper.
     *
     * @param array $errors Description index.
     * @param string $message Description index.
     * @param int $code Description index.
     * @param ?Throwable $previous Description index.
     * @return void Output payload.
     */
    public function __construct(array $errors, string $message = "Validation failed", int $code = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
