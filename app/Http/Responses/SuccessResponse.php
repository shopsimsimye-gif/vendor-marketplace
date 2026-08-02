<?php
namespace VMP\Http\Responses;

defined('ABSPATH') || exit;

/**
 * Class SuccessResponse
 *
 * Description of administrative platform component SuccessResponse.
 *
 * @package vendor-marketplace
 */
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

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        $response = [
            'success' => true,
        ];

        if ($this->message !== '') {
            $response['message'] = $this->message;
        }

        if ($this->data !== null) {
            $response['data'] = $this->data;
        }

        return $response;
    }
}
