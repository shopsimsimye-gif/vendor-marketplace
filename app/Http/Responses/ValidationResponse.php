<?php
/**
 * ValidationResponse — استجابة أخطاء التحقق من الصحة
 *
 * @package VMP\Http\Responses
 * @since 3.0.0
 */

namespace VMP\Http\Responses;

defined('ABSPATH') || exit;

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
            details: $errors,  // ✅ مباشرة كـ details
            headers: $headers
        );
    }
}
