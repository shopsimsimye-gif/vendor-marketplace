<?php
namespace VMP\Jobs;

defined('ABSPATH') || exit;

use VMP\Core\Queue\JobInterface;

/**
 * Class SendEmailJob
 *
 * يتولى إرسال رسائل البريد الإلكتروني في الخلفية
 */
class SendEmailJob implements JobInterface
{
    public function __construct(
        private string $to,
        private string $subject,
        private string $body,
        private array $headers = []
    ) {}

    /**
     * Handle functionality helper.
     *
     * @throws \\RuntimeException Diagnostic error when triggered.
     * @return void Output payload.
     */
    public function handle(): void
    {
        $sent = wp_mail($this->to, $this->subject, $this->body, $this->headers);

        if (!$sent) {
            throw new \RuntimeException(sprintf('فشل إرسال الإيميل إلى %s', $this->to));
        }
    }

    /**
     * GetPayload functionality helper.
     *
     * @return array Output payload.
     */
    public function getPayload(): array
    {
        return [
            'to'      => $this->to,
            'subject' => $this->subject,
            'body'    => $this->body,
            'headers' => $this->headers,
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
            to: $payload['to'] ?? '',
            subject: $payload['subject'] ?? '',
            body: $payload['body'] ?? '',
            headers: $payload['headers'] ?? []
        );
    }
}
