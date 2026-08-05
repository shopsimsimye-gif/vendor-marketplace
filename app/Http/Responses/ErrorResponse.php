<?php
/**
 * ErrorResponse — استجابة خطأ للـ API
 *
 * @package VMP\Http\Responses
 * @since 3.0.0
 */

namespace VMP\Http\Responses;

defined('ABSPATH') || exit;

class ErrorResponse extends ApiResponse
{
    public function __construct(
        protected string $message = 'An error occurred',
        protected string $code = 'error',
        int $statusCode = 400,
        protected array $details = [],
        array $headers = []
    ) {
        parent::__construct($statusCode, $headers);
    }

    /**
     * تحويل إلى array
     */
    public function toArray(): array
    {
        $response = [
            'success' => false,
            'error'   => [
                'code'    => $this->code,
                'message' => $this->message,
            ],
        ];

        // ✅ تفاصيل إضافية مُحصورة تحت مفتاح `details` — لا overwrite
        if (!empty($this->details)) {
            $response['error']['details'] = $this->details;
        }

        return $response;
    }
}
