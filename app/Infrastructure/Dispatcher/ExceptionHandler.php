<?php
namespace VMP\Infrastructure\Dispatcher;

defined('ABSPATH') || exit;

use VMP\Exceptions\ValidationException;
use VMP\Exceptions\AuthorizationException;
use VMP\Exceptions\NotFoundException;
use VMP\Exceptions\BaseException;
use VMP\Http\Responses\ErrorResponse;
use VMP\Http\Responses\ValidationResponse;
use VMP\Core\Logger;
use Exception;
use Throwable;

/**
 * Class ExceptionHandler
 *
 * Description of administrative platform component ExceptionHandler.
 *
 * @package vendor-marketplace
 */
class ExceptionHandler
{
    /**
     *   Construct functionality helper.
     *
     * @param Logger $logger Description index.
     * @return void Output payload.
     */
    public function __construct(protected Logger $logger) {}

    /**
     * Handle functionality helper.
     *
     * @param Throwable $e Description index.
     * @return void Output payload.
     */
    public function handle(Throwable $e): void
    {
        if ($e instanceof ValidationException) {
            $response = new ValidationResponse($e->getErrors());
            $response->send();
        }

        if ($e instanceof AuthorizationException) {
            $response = new ErrorResponse($e->getMessage(), 'unauthorized', 403);
            $response->send();
        }

        if ($e instanceof NotFoundException) {
            $response = new ErrorResponse($e->getMessage(), 'not_found', 404);
            $response->send();
        }

        if ($e instanceof BaseException) {
            // أخطاء متوقعة مخصصة (مثل RepositoryException, ServiceException...)
            $this->logger->error("App Exception: " . $e->getMessage(), ['exception' => $e]);
            $response = new ErrorResponse($e->getMessage(), 'app_error', 400);
            $response->send();
        }

        // أخطاء غير متوقعة
        $this->logger->error("Critical Error: " . $e->getMessage(), ['exception' => $e]);
        
        $message = (defined('WP_DEBUG') && WP_DEBUG) 
            ? $e->getMessage() 
            : __('حدث خطأ داخلي في الخادم. الرجاء المحاولة لاحقاً.', 'vmp');

        $response = new ErrorResponse($message, 'server_error', 500);
        $response->send();
    }
}
