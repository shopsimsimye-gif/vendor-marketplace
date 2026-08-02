<?php
namespace VMP\Events;

defined('ABSPATH') || exit;

/**
 * الحدث الأساسي المجرد
 * جميع الأحداث ترث منه.
 */
abstract class AbstractEvent
{
    private float $occurredAt;

    /**
     *   Construct functionality helper.
     *
     * @return void Output payload.
     */
    public function __construct()
    {
        $this->occurredAt = microtime(true);
    }

    /**
     * اسم الحدث الفريد (يستخدم كمفتاح في EventManager)
     */
    abstract public function getName(): string;

    /**
     * وقت وقوع الحدث
     */
    public function getOccurredAt(): float
    {
        return $this->occurredAt;
    }

    /**
     * تحويل الحدث إلى مصفوفة (مفيد للتسجيل)
     */
    public function toArray(): array
    {
        return [
            'event'       => $this->getName(),
            'occurred_at' => $this->occurredAt,
        ];
    }
}
