<?php
/**
 * ApiResponse — كلاس أساسي لاستجابات JSON
 *
 * @package VMP\Http\Responses
 * @since 3.0.0
 */

namespace VMP\Http\Responses;

defined('ABSPATH') || exit;

use JsonSerializable;

abstract class ApiResponse implements JsonSerializable
{
    public function __construct(
        protected int $statusCode = 200,
        protected array $headers = []
    ) {}

    /**
     * تحويل الاستجابة إلى array
     */
    abstract public function toArray(): array;

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * الحصول على HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * إرسال الاستجابة وإنهاء التنفيذ
     *
     * @return void
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            header('Content-Type: application/json');

            foreach ($this->headers as $key => $value) {
                $safeKey   = str_replace(["\r", "\n"], '', (string) $key);
                $safeValue = str_replace(["\r", "\n"], '', (string) $value);
                header("{$safeKey}: {$safeValue}");
            }
        }

        $json = wp_json_encode($this->toArray());

        if ($json === false) {
            $json = wp_json_encode([
                'success' => false,
                'message' => 'JSON encoding failed.',
            ]);
        }

        echo $json;
        exit;
    }
}
