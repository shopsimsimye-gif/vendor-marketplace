<?php
namespace VMP\Modules\VendorRegistration\Services\NotificationChannels;

class EmailChannel
{
    private string $templatesDir;

    public function __construct(string $templatesDir)
    {
        $this->templatesDir = rtrim($templatesDir, '/');
    }

    /**
     * $payload expected keys: template (string filename without path), to (email|string|array), subject (optional), data (array)
     */
    public function __invoke(array $payload): bool
    {
        $template = $payload['template'] ?? '';
        $to = $payload['to'] ?? '';
        $subject = $payload['subject'] ?? '';
        $data = $payload['data'] ?? [];

        if (empty($template) || empty($to)) return false;

        $tplPath = $this->templatesDir . '/' . $template;
        if (!file_exists($tplPath)) return false;

        // prepare body by including template and capturing output
        ob_start();
        // expose $data to template
        $payloadForTemplate = $data;
        try {
            include $tplPath;
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log('EmailChannel: template include failed ' . $e->getMessage());
            return false;
        }
        $body = ob_get_clean();

        // prepare headers
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        /**
         * Hook before sending email so a queue or logger can intercept or modify.
         * @param array $payloadForHook ['to'=>..., 'subject'=>..., 'body'=>..., 'headers'=>..., 'template'=>...]
         */
        do_action('vmp_before_send_email', [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'headers' => $headers,
            'template' => $template,
            'data' => $data,
        ]);

        // send synchronously by default
        $sent = wp_mail($to, $subject, $body, $headers);

        // hook after send
        do_action('vmp_after_send_email', [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'headers' => $headers,
            'template' => $template,
            'data' => $data,
            'sent' => $sent,
        ]);

        return (bool) $sent;
    }
}
