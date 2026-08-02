<?php
namespace VMP\Http\Responses;

defined('ABSPATH') || exit;

/**
 * Class ErrorResponse
 *
 * Description of administrative platform component ErrorResponse.
 *
 * @package vendor-marketplace
 */
class ErrorResponse extends ApiResponse
{
    public function __construct(
        protected string $message = 'An error occurred',
        protected string $code = 'error',
        int $statusCode = 400,
        protected array $additionalData = [],
        array $headers = []
    ) {
        parent::__construct($statusCode, $headers);
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        $response = [
            'success' => false,
            'code'    => $this->code,
            'message' => $this->message,
        ];

        if (!empty($this->additionalData)) {
            $response = array_merge($response, $this->additionalData);
        }

        return $response;
    }
}
