<?php
namespace VMP\Http\Responses;

defined('ABSPATH') || exit;

/**
 * Class ValidationResponse
 *
 * Description of administrative platform component ValidationResponse.
 *
 * @package vendor-marketplace
 */
class ValidationResponse extends ErrorResponse
{
    public function __construct(
        array $errors,
        string $message = 'Validation failed',
        int $statusCode = 422,
        array $headers = []
    ) {
        parent::__construct(
            message: $message,
            code: 'validation_error',
            statusCode: $statusCode,
            additionalData: ['errors' => $errors],
            headers: $headers
        );
    }
}
