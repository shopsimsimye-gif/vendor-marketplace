<?php
namespace VMP\Core\Queue;

defined('ABSPATH') || exit;

/**
 * Interface JobInterface
 *
 * الواجهة التي يجب أن تطبقها جميع الوظائف غير المتزامنة
 */
interface JobInterface
{
    /**
     * تشغيل الوظيفة
     *
     * @return void
     * @throws \Exception في حالة فشل التنفيذ
     */
    public function handle(): void;

    /**
     * جلب البيانات السياقية للوظيفة
     *
     * @return array
     */
    public function getPayload(): array;

    /**
     * بناء كائن الوظيفة من البيانات السياقية
     *
     * @param array $payload
     * @return self
     */
    public static function fromPayload(array $payload): self;
}
