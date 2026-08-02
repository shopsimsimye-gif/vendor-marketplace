<?php
namespace VMP\Http\Responses;

defined('ABSPATH') || exit;

use JsonSerializable;

/**
 * Class ApiResponse
 *
 * Description of administrative platform component ApiResponse.
 *
 * @package vendor-marketplace
 */
abstract class ApiResponse implements JsonSerializable
{
    public function __construct(
        protected int $statusCode = 200,
        protected array $headers = []
    ) {}

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    abstract public function toArray(): array;

    /**
     * JsonSerialize functionality helper.
     *
     * @return array Output payload.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * GetStatusCode functionality helper.
     *
     * @return int Output payload.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Send functionality helper.
     *
     * @return never Output payload.
     */
    public function send(): never
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            header('Content-Type: application/json; charset=utf-8');
            foreach ($this->headers as $key => $value) {
                header("{$key}: {$value}");
            }
        }

        echo wp_json_encode($this->toArray());
        exit;
    }
}
