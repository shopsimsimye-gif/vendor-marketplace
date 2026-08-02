<?php
namespace VMP\Core;

defined('ABSPATH') || exit;

use VMP\Events\AbstractEvent;

/**
 * EventManager — يدعم كلاً من:
 *  1. الأحداث المكتوبة (Typed Events) من خلال dispatch(AbstractEvent)
 *  2. الأحداث النصية القديمة (legacy string events) للتوافق الكامل مع الإصدارات السابقة
 */
class EventManager
{
    /** @var array<string, callable[]> */
    protected array $listeners = [];

    // ─── Typed Event System ───────────────────────────────────────────────────

    /**
     * تسجيل مستمع لحدث مكتوب (Typed Event)
     * يُحدَّد الحدث تلقائياً من اسم الكلاس
     *
     * @param string   $eventClass اسم الكلاس الكامل للحدث (e.g. VendorApproved::class)
     * @param callable $listener   أي callable أو ListenerInterface
     */
    public function on(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * إطلاق حدث مكتوب — يُستدعى بكائن الحدث مباشرةً
     * يُرسَل الكائن إلى جميع المستمعين المسجلين للكلاس.
     */
    public function dispatch(AbstractEvent $event): AbstractEvent
    {
        $eventClass = get_class($event);

        foreach ($this->listeners[$eventClass] ?? [] as $listener) {
            if (is_callable($listener)) {
                $listener($event);
            }
        }

        // تكامل مع WordPress actions لتسهيل التكامل الخارجي
        do_action('vmp_event', $event);
        do_action('vmp_event_' . $event->getName(), $event);

        return $event;
    }

    // ─── Legacy String Event System (Backward Compatibility) ──────────────────

    /**
     * تسجيل مستمع لحدث نصي قديم
     */
    public function listen(string $event, callable $cb): void
    {
        $this->listeners[$event][] = $cb;
    }

    /**
     * @deprecated استخدم listen() بدلاً منه
     */
    public function add_listener(string $event, callable $cb, int $priority = 10): void
    {
        $this->listen($event, $cb);
    }

    /**
     * إطلاق حدث نصي قديم مع دعم وسائط متعددة
     *
     * @deprecated استخدم dispatch(AbstractEvent) بدلاً منه
     */
    public function trigger(string $event, mixed ...$args): void
    {
        foreach ($this->listeners[$event] ?? [] as $cb) {
            call_user_func_array($cb, $args);
        }
    }

    /**
     * عدد المستمعين لحدث معين (مفيد في الاختبارات)
     */
    public function getListenerCount(string $eventKey): int
    {
        return count($this->listeners[$eventKey] ?? []);
    }

    /**
     * إزالة جميع المستمعين (مفيد في الاختبارات)
     */
    public function flush(): void
    {
        $this->listeners = [];
    }
}
