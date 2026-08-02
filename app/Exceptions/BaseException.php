<?php
namespace VMP\Exceptions;

defined('ABSPATH') || exit;

use Exception;
use Throwable;

/**
 * Class BaseException
 *
 * Description of administrative platform component BaseException.
 *
 * @package vendor-marketplace
 */
abstract class BaseException extends Exception
{
    /**
     *   Construct functionality helper.
     *
     * @param string $message Description index.
     * @param int $code Description index.
     * @param ?Throwable $previous Description index.
     * @return void Output payload.
     */
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
