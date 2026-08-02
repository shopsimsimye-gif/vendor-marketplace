<?php
namespace VMP\Http\Responses;

defined('ABSPATH') || exit;

/**
 * كلاس لتوحيد تنسيق استجابات JSON الخاصة بالإضافة (AJAX & API)
 *
 * @package VMP\Http\Responses
 */
class JsonResponse
{
    /**
     * إرسال استجابة نجاح (HTTP 200)
     *
     * @param string $message
     * @param mixed $data
     * @param int $statusCode
     * @return never
     */
    public static function success(string $message = '', mixed $data = null, int $statusCode = 200): void
    {
        $response = [
            'success' => true,
        ];

        if (!empty($message)) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        wp_send_json($response, $statusCode);
    }

    /**
     * إرسال استجابة خطأ
     *
     * @param string $message
     * @param array $errors
     * @param int $statusCode (الافتراضي 400 Bad Request)
     * @return never
     */
    public static function error(string $message, array $errors = [], int $statusCode = 400): void
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        wp_send_json($response, $statusCode);
    }

    /**
     * إرسال خطأ الصلاحيات (HTTP 403 Forbidden)
     *
     * @param string $message
     * @return never
     */
    public static function forbidden(string $message = ''): void
    {
        self::error(
            $message ?: __('عذراً، ليس لديك صلاحية للقيام بهذا الإجراء.', 'vmp'),
            [],
            403
        );
    }

    /**
     * إرسال خطأ غير مصرح (HTTP 401 Unauthorized)
     *
     * @param string $message
     * @return never
     */
    public static function unauthorized(string $message = ''): void
    {
        self::error(
            $message ?: __('يجب تسجيل الدخول أولاً.', 'vmp'),
            [],
            401
        );
    }

    /**
     * إرسال استجابة غير موجود (HTTP 404 Not Found)
     *
     * @param string $message
     * @return never
     */
    public static function notFound(string $message = ''): void
    {
        self::error(
            $message ?: __('المورد المطلوب غير موجود.', 'vmp'),
            [],
            404
        );
    }
}
