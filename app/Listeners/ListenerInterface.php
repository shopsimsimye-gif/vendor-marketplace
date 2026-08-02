<?php
namespace VMP\Listeners;

defined('ABSPATH') || exit;

/**
 * الواجهة الأساسية لجميع المستمعين
 */
interface ListenerInterface
{
    /**
     * معالجة الحدث المُستقبَل
     *
     * @param object $event أي حدث يرث من AbstractEvent
     */
    public function handle(object $event): void;
}
