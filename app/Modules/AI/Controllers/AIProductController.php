<?php
namespace VMP\Modules\AI\Controllers;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;
use VMP\Services\SubscriptionService;
use VMP\Modules\AI\Services\AIProductDraftService;

/**
 * Class AIProductController
 *
 * Description of administrative platform component AIProductController.
 *
 * @package vendor-marketplace
 */
class AIProductController
{
    public function __construct(
        private VendorRepositoryInterface $vendors,
        private AIProductDraftService $drafts,
        private SubscriptionService $subscriptionService
    ) {
    }

    /**
     * CreateJob functionality helper.
     *
     * @return void Output payload.
     */
    public function createJob(): void
    {
        try {
            $this->verifyRequest();
            $vendor = $this->currentVendor();
            $attachmentId = $this->handleUpload();

            // التحقق من توفر ميزة إنشاء المنتج بالذكاء الاصطناعي
            if (!$this->subscriptionService->hasFeature((int) $vendor->id, 'ai_product_generator')) {
                throw new \RuntimeException(__('ميزة إنشاء المنتج بالذكاء الاصطناعي غير متاحة في خطة الاشتراك الحالية.', 'vmp'));
            }

            $job = $this->drafts->createJob($vendor, $attachmentId);

            wp_send_json_success(['job' => $job]);
        } catch (\Throwable $e) {
            // Detailed logging for local debugging
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log('[VMP-AI] createJob exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * GetJob functionality helper.
     *
     * @return void Output payload.
     */
    public function getJob(): void
    {
        try {
            $this->verifyRequest();
            $vendor = $this->currentVendor();
            $jobId = sanitize_text_field($_POST['job_id'] ?? '');
            $job = $this->drafts->getJob($jobId, (int) $vendor->id);

            if (!$job) {
                wp_send_json_error(['message' => __('العملية غير موجودة.', 'vmp')]);
            }

            wp_send_json_success(['job' => $job]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Return timeline/events for a given job
     */
    public function getJobTimeline(): void
    {
        try {
            $this->verifyRequest();
            $vendor = $this->currentVendor();
            $jobId = sanitize_text_field($_POST['job_id'] ?? '');

            $job = $this->drafts->getJob($jobId, (int) $vendor->id);
            if (!$job) {
                wp_send_json_error(['message' => __('العملية غير موجودة.', 'vmp')]);
            }

            // Use repository directly
            $repository = \VMP\Core\Container::getInstance()->make(\VMP\Modules\AI\Repositories\AIJobRepository::class);
            $events = $repository->getTimeline($jobId);

            $response = [
                'status' => $job['status'] ?? 'UNKNOWN',
                'progress' => $job['progress'] ?? 0,
                'current_step' => $job['current_step'] ?? '',
                'provider' => $job['provider'] ?? '',
                'events' => $events,
            ];

            wp_send_json_success($response);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[VMP-AI] getJobTimeline exception: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Regenerate functionality helper.
     *
     * @return void Output payload.
     */
    public function regenerate(): void
    {
        try {
            $this->verifyRequest();
            $vendor = $this->currentVendor();
            $job = $this->drafts->regenerate(
                sanitize_text_field($_POST['job_id'] ?? ''),
                $vendor,
                sanitize_key($_POST['part'] ?? '')
            );

            wp_send_json_success(['job' => $job]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Publish functionality helper.
     *
     * @return void Output payload.
     */
    public function publish(): void
    {
        try {
            $this->verifyRequest();
            $vendor = $this->currentVendor();
            $result = $this->drafts->publish(
                sanitize_text_field($_POST['job_id'] ?? ''),
                $vendor,
                wp_unslash($_POST)
            );

            wp_send_json_success($result + ['message' => __('تم إرسال المنتج للمراجعة بنجاح.', 'vmp')]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * VerifyRequest functionality helper.
     *
     * @throws \\RuntimeException Diagnostic error when triggered.
     * @return void Output payload.
     */
    private function verifyRequest(): void
    {
        if (!check_ajax_referer('vmp_public_nonce', 'nonce', false)) {
            throw new \RuntimeException(__('طلب غير مصرح به.', 'vmp'));
        }

        $workflowId = sanitize_key($_POST['workflow_id'] ?? 'product-image-v1');
        if ($workflowId !== 'product-image-v1') {
            throw new \RuntimeException(__('سير العمل المطلوب غير مدعوم.', 'vmp'));
        }
    }

    /**
     * CurrentVendor functionality helper.
     *
     * @throws \\RuntimeException Diagnostic error when triggered.
     * @return object Output payload.
     */
    private function currentVendor(): object
    {
        $userId = get_current_user_id();
        if (!$userId) {
            throw new \RuntimeException(__('يجب تسجيل الدخول أولاً.', 'vmp'));
        }

        $vendor = $this->vendors->findByUserId($userId);
        if (!$vendor || $vendor->status !== 'approved') {
            throw new \RuntimeException(__('يجب أن تكون بائعاً معتمداً لاستخدام هذه الميزة.', 'vmp'));
        }

        return $vendor;
    }

    /**
     * HandleUpload functionality helper.
     *
     * @throws \\RuntimeException Diagnostic error when triggered.
     * @return int Output payload.
     */
    private function handleUpload(): int
    {
        if (empty($_FILES['image'])) {
            throw new \RuntimeException(__('يرجى رفع صورة المنتج.', 'vmp'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachmentId = media_handle_upload('image', 0);
        if (is_wp_error($attachmentId)) {
            throw new \RuntimeException($attachmentId->get_error_message());
        }

        return (int) $attachmentId;
    }
}
