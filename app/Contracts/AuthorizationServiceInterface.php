<?php
namespace VMP\Contracts;

defined('ABSPATH') || exit;

use VMP\Exceptions\AuthorizationException;

interface AuthorizationServiceInterface
{
    /**
     * التحقق من الصلاحية ويرمي Exception إذا فشل.
     *
     * @param string $action
     * @param mixed $model
     * @return bool
     * @throws AuthorizationException
     */
    public function authorize(string $action, mixed $model = null): bool;

    /**
     * التحقق من الصلاحية ويعيد قيمة منطقية.
     *
     * @param string $action
     * @param mixed $model
     * @return bool
     */
    public function check(string $action, mixed $model = null): bool;
}
