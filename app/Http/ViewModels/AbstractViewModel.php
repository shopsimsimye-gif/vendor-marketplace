<?php
namespace VMP\Http\ViewModels;

defined('ABSPATH') || exit;

/**
 * ViewModel الأساسي المجرد
 *
 * يوفر طريقة موحدة لتمرير البيانات إلى القوالب
 * مع ضمان تنظيف المخرجات وتجنب الوصول المباشر لكائنات الـ DTO
 */
abstract class AbstractViewModel
{
    /**
     * تحويل الـ ViewModel إلى مصفوفة جاهزة للقالب
     */
    abstract public function toArray(): array;

    /**
     * تمرير المصفوفة إلى نطاق القالب عبر extract()
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
     */
    protected function money(float $amount): string
    {
        if (function_exists('wc_price')) {
            return wc_price($amount);
        }
        return number_format($amount, 2) . ' SAR';
    }
}
