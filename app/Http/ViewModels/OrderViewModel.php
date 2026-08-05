<?php
/**
 * AbstractViewModel — الكلاس الأساسي المجرد
 *
 * @package VMP\Http\ViewModels
 * @since 3.0.0
 */

namespace VMP\Http\ViewModels;

defined('ABSPATH') || exit;

abstract class AbstractViewModel
{
    /**
     * تحويل الـ ViewModel إلى مصفوفة جاهزة للقالب
     */
    abstract public function toArray(): array;

    /**
     * تمرير المصفوفة إلى نطاق القالب
     */
    public function toViewData(): array
    {
        return $this->toArray();
    }

    /**
     * الهروب من نص HTML
     */
    protected function e(string $value): string
    {
        return esc_html($value);
    }

    /**
     * الهروب من قيمة Attribute
     */
    protected function attr(string $value): string
    {
        return esc_attr($value);
    }

    /**
     * الهروب من URL
     */
    protected function url(string $value): string
    {
        return esc_url($value);
    }

    /**
     * تنسيق المبلغ المالي
     *
     * @param float $amount
     * @return string HTML (يُستخدم في القوالب فقط)
     */
    protected function money(float $amount): string
    {
        if (function_exists('wc_price')) {
            return wc_price($amount);
        }
        return number_format($amount, 2) . ' ' . get_woocommerce_currency();
    }
}
