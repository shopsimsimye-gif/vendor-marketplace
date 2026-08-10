<?php
/**
 * OrderViewModel — عرض بيانات الطلب في لوحة البائع
 *
 * @package VMP\Http\ViewModels
 * @since 3.0.0
 */

namespace VMP\Http\ViewModels;

defined( 'ABSPATH' ) || exit;

abstract class OrderViewModel extends AbstractViewModel
{
    /**
     * بيانات الطلب الأساسية التي تتشاركها كل مشتقات عرض الطلب.
     */
    abstract public function orderId(): int;
}
