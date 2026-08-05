<?php
/**
 * SuccessResponse — استجابة نجاح
 *
 * @package VMP\Http\Responses
 * @since 3.0.0
 */

namespace VMP\Http\Responses;

defined('ABSPATH') || exit;

class SuccessResponse extends ApiResponse
{
    public function __construct(
        protected mixed $data = null,
        protected string $message = '',
        int $statusCode = 200,
        array $headers = []
    ) {
        parent::__construct($statusCode, $headers);
    }

    public function toArray(): array
    {
        $response = ['success' => true];

        if ($this->message !== '') {
            $response['message'] = $this->message;
        }

        if ($this->data !== null) {
            $response['data'] = $this->data;
        }

        return $response;
    }
}
