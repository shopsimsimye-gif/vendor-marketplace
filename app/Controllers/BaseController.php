<?php
namespace VMP\Controllers;

defined('ABSPATH') || exit;

use VMP\Contracts\AuthorizationServiceInterface;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ErrorResponse;

/**
 * Class BaseController
 *
 * Description of administrative platform component BaseController.
 *
 * @package vendor-marketplace
 */
abstract class BaseController
{
    /**
     * @var AuthorizationServiceInterface|null
     */
    protected ?AuthorizationServiceInterface $authorization = null;

    /**
     * دالة مساعدة للتحقق من الصلاحيات يدوياً إذا دعت الحاجة
     * (على الرغم من أن الـ Request يقوم بذلك تلقائياً).
     */
    protected function authorize(string $action, mixed $model = null): void
    {
        if ($this->authorization) {
            $this->authorization->authorize($action, $model);
        }
    }
}
