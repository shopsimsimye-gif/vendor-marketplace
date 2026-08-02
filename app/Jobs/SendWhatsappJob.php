<?php
namespace VMP\Jobs;

defined('ABSPATH') || exit;

use VMP\Core\Queue\JobInterface;
use VMP\Core\Logger;

/**
 * Class SendWhatsappJob
 *
 * يتولى إرسال/تسجيل رسائل الواتساب في الخلفية
 */
class SendWhatsappJob implements JobInterface
{
    public function __construct(
        private string $phone,
        private string $message,
        private array $metadata = []
    ) {}

    /**
     * Handle functionality helper.
     *
     * @return void Output payload.
     */
    public function handle(): void
    {
        // محاكاة إرسال رسالة واتس اب وتسجيلها في نظام السجلات
        // في البيئة الحقيقية، هنا يتم الاتصال بـ API خارجي (مثل Twilio أو UltraMsg)
        
        $logger = \VMP\Core\Container::getInstance()->make(Logger::class);
        $logger->info(sprintf('محاكاة إرسال واتساب للرقم %s بنجاح', $this->phone), [
            'phone'    => $this->phone,
            'message'  => $this->message,
            'metadata' => $this->metadata,
        ]);
        
        // يمكننا إضافة تأخير بسيط لمحاكاة زمن الـ API
        usleep(100000); // 100ms
    }

    /**
     * GetPayload functionality helper.
     *
     * @return array Output payload.
     */
    public function getPayload(): array
    {
        return [
            'phone'    => $this->phone,
            'message'  => $this->message,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * FromPayload functionality helper.
     *
     * @param array $payload Description index.
     * @return self Output payload.
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            phone: $payload['phone'] ?? '',
            message: $payload['message'] ?? '',
            metadata: $payload['metadata'] ?? []
        );
    }
}
