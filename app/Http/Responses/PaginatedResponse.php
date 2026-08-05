<?php
/**
 * PaginatedResponse — استجابة مع ترقيم
 *
 * @package VMP\Http\Responses
 * @since 3.0.0
 */

namespace VMP\Http\Responses;

defined('ABSPATH') || exit;

class PaginatedResponse extends SuccessResponse
{
    public function __construct(
        array $items,
        int $total,
        int $perPage,
        int $currentPage,
        string $message = '',
        int $statusCode = 200,
        array $headers = []
    ) {
        $totalPages = (int) ceil($total / max(1, $perPage));

        $data = [
            'items'      => $items,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $currentPage,
                'total_pages'  => $totalPages,
                'has_next'     => $currentPage < $totalPages,
                'has_prev'     => $currentPage > 1,
            ],
        ];

        parent::__construct(
            data: $data,
            message: $message,
            statusCode: $statusCode,
            headers: $headers
        );
    }
}
