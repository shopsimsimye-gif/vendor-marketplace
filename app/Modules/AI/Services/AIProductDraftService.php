<?php
namespace VMP\Modules\AI\Services;

defined('ABSPATH') || exit;

use VMP\Contracts\ProductRepositoryInterface;
use VMP\Contracts\VendorRepositoryInterface;
use VMP\Core\Queue\QueueInterface;
use VMP\Exceptions\AIException;
use VMP\Modules\AI\Context\ImageContext;
use VMP\Modules\AI\Context\ProductContext;
use VMP\Modules\AI\Context\StoreContext;
use VMP\Modules\AI\Context\VendorContext;
use VMP\Modules\AI\Jobs\ProcessAIProductDraftJob;
use VMP\Modules\AI\Pipelines\ProductGenerationPipeline;
use VMP\Modules\AI\Repositories\AIJobRepository;
use VMP\Modules\AI\Results\AIResult;
use VMP\Modules\AI\States\AIProductWorkflowState;

/**
 * Class AIProductDraftService
 *
 * Description of administrative platform component AIProductDraftService.
 *
 * @package vendor-marketplace
 */
class AIProductDraftService
{
    public function __construct(
        private ProductGenerationPipeline $pipeline,
        private ProductRepositoryInterface $products,
        private VendorRepositoryInterface $vendors,
        private AIJobRepository $jobs,
        private QueueInterface $queue
    ) {
    }

    /**
     * CreateJob functionality helper.
     *
     * @param object $vendor Description index.
     * @param int $attachmentId Description index.
     * @return array Output payload.
     */
    public function createJob(object $vendor, int $attachmentId): array
    {
        $job = $this->jobs->create([
            'vendor_id' => (int) $vendor->id,
            'attachment_id' => $attachmentId,
            'status' => AIProductWorkflowState::QUEUED,
            'progress' => 10,
            'current_step' => AIProductWorkflowState::QUEUED,
            'logs' => [[
                'level' => 'info',
                'message' => __('تم إنشاء مهمة الذكاء الاصطناعي وإضافتها للطابور.', 'vmp'),
                'at' => current_time('mysql'),
            ]],
        ]);

        // Record job created and queued events
        try {
            $this->jobs->appendEvent($job['id'], 'JobCreated', ['vendor_id' => (int) $vendor->id, 'attachment_id' => $attachmentId]);
            $this->jobs->appendEvent($job['id'], 'Queued', ['queue_adapter' => get_class($this->queue)]);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[VMP-AI] createJob appendEvent failed: ' . $e->getMessage());
            }
        }

        // Enqueue the new AIJobWorker which runs the WorkflowEngine steps
        $queueId = $this->queue->push(\VMP\Modules\AI\Jobs\AIJobWorker::class, [
            'job_id' => $job['id'],
            'vendor_id' => (int) $vendor->id,
        ]);

        if ($queueId <= 0) {
            return $this->jobs->update($job['id'], [
                'status' => AIProductWorkflowState::FAILED,
                'current_step' => AIProductWorkflowState::FAILED,
                'progress' => 100,
                'error' => __('تعذر إضافة مهمة الذكاء الاصطناعي إلى الطابور.', 'vmp'),
            ]) ?? $job;
        }

        $this->jobs->appendLog($job['id'], 'info', __('تمت جدولة المهمة في طابور الخلفية.', 'vmp'), [
            'queue_id' => $queueId,
        ]);

        return $this->getJob($job['id'], (int) $vendor->id) ?? $job;
    }

    /**
     * GetJob functionality helper.
     *
     * @param string $jobId Description index.
     * @param int $vendorId Description index.
     * @return ?array Output payload.
     */
    public function getJob(string $jobId, int $vendorId): ?array
    {
        return $this->jobs->findForVendor($jobId, $vendorId);
    }

    /**
     * Regenerate functionality helper.
     *
     * @param string $jobId Description index.
     * @param object $vendor Description index.
     * @param string $part Description index.
     * @throws \AIException Diagnostic error when triggered.
     * @return array Output payload.
     */
    public function regenerate(string $jobId, object $vendor, string $part): array
    {
        $job = $this->requireJob($jobId, (int) $vendor->id);
        $result = AIResult::fromArray(is_array($job['result'] ?? null) ? $job['result'] : []);
        $data = $result->toArray();
        $version = $this->promptVersion($part);

        if ($part === 'title') {
            $data['title'] = $this->fallbackTitle($job['attachment_id'], 'محسن');
        } elseif ($part === 'description') {
            $data['description'] = $this->fallbackDescription($vendor, true);
        } elseif ($part === 'keywords') {
            $data['keywords'] = $this->fallbackKeywords($vendor);
        } else {
            throw new AIException(__('نوع إعادة التوليد غير مدعوم.', 'vmp'));
        }

        $data['warnings'][] = __('تمت إعادة توليد هذا الجزء فقط دون تشغيل سير العمل الكامل.', 'vmp');
        $data['metadata']['prompt_versions'][$part] = $version;
        $data['metadata']['regenerated_parts'][] = [
            'part' => $part,
            'at' => current_time('mysql'),
        ];

        $job = $this->jobs->update($job['id'], [
            'result' => AIResult::fromArray($data)->toArray(),
            'status' => AIProductWorkflowState::REVIEW,
            'progress' => 100,
            'current_step' => AIProductWorkflowState::REVIEW,
        ]) ?? $job;

        return $job;
    }

    /**
     * Publish functionality helper.
     *
     * @param string $jobId Description index.
     * @param object $vendor Description index.
     * @param array $data Description index.
     * @throws \AIException Diagnostic error when triggered.
     * @return array Output payload.
     */
    public function publish(string $jobId, object $vendor, array $data): array
    {
        $job = $this->requireJob($jobId, (int) $vendor->id);
        $result = AIResult::fromArray(is_array($job['result'] ?? null) ? $job['result'] : []);

        $title = sanitize_text_field($data['title'] ?? $result->title);
        if ($title === '') {
            throw new AIException(__('عنوان المنتج مطلوب.', 'vmp'));
        }

        $settings = get_option('vmp_settings', []);
        $autoApprove = isset($settings['general']['auto_approve_products']) && $settings['general']['auto_approve_products'] === '1';
        $productStatus = $autoApprove ? 'publish' : 'pending';
        $vendorProductStatus = $autoApprove ? 'approved' : 'pending';

        $product = new \WC_Product_Simple();
        $product->set_name($title);
        $product->set_description(wp_kses_post($data['description'] ?? $result->description));
        $product->set_short_description(wp_kses_post($data['short_description'] ?? $result->shortDescription));
        $product->set_regular_price((float) ($data['regular_price'] ?? 0));
        $product->set_status($productStatus);
        $product->set_image_id((int) ($job['attachment_id'] ?? 0));

        $productId = $product->save();
        if (!$productId) {
            throw new AIException(__('تعذر إنشاء مسودة المنتج.', 'vmp'));
        }

        update_post_meta($productId, '_vmp_ai_job_id', $job['id']);
        update_post_meta($productId, '_vmp_ai_result', $result->toArray());
        update_post_meta($productId, '_vmp_ai_workflow_version', $job['workflow_version'] ?? 'product-image-v1');

        $vendorProductId = $this->products->create((int) $vendor->id, (int) $productId, [
            'status' => $vendorProductStatus,
            'is_featured' => false,
        ]);

        if (!$vendorProductId) {
            wp_delete_post($productId, true);
            throw new AIException(__('تعذر ربط المنتج بالبائع.', 'vmp'));
        }

        $job = $this->jobs->update($job['id'], [
            'status' => AIProductWorkflowState::PUBLISHED,
            'current_step' => AIProductWorkflowState::PUBLISHED,
            'progress' => 100,
            'product_id' => (int) $productId,
            'vendor_product_id' => (int) $vendorProductId,
        ]) ?? $job;

        return [
            'job' => $job,
            'product_id' => (int) $productId,
            'vendor_product_id' => (int) $vendorProductId,
            'edit_url' => add_query_arg(['vmp_page' => 'edit-product', 'id' => $vendorProductId], home_url('/vendor-dashboard/')),
        ];
    }

    /**
     * ProcessJob functionality helper.
     *
     * @param string $jobId Description index.
     * @param object $vendor Description index.
     * @return void Output payload.
     */
    public function processQueuedJob(string $jobId, int $vendorId): void
    {
        try {
            $vendor = $this->vendors->find($vendorId);
            if (!$vendor) {
                throw new AIException(__('البائع المرتبط بمهمة الذكاء الاصطناعي غير موجود.', 'vmp'));
            }

            $job = $this->requireJob($jobId, (int) $vendor->id);
            $imageUrl = wp_get_attachment_url((int) $job['attachment_id']);

            $result = null;
            $lastError = '';
            $maxAttempts = 2;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $this->jobs->update($jobId, ['retries' => $attempt - 1]);
                    $this->transition($jobId, AIProductWorkflowState::ANALYZING_IMAGE, 25);
                    $this->transition($jobId, AIProductWorkflowState::SEARCHING, 45);
                    $this->transition($jobId, AIProductWorkflowState::GENERATING_TITLE, 60);
                    $this->transition($jobId, AIProductWorkflowState::GENERATING_DESCRIPTION, 75);
                    $this->transition($jobId, AIProductWorkflowState::GENERATING_SEO, 88);

                    $result = $this->pipeline->run(
                        new ImageContext($imageUrl ?: '', (int) $job['attachment_id']),
                        new ProductContext(locale: get_locale()),
                        new VendorContext((int) $vendor->id, (string) $vendor->store_name),
                        new StoreContext((string) $vendor->store_name, get_locale(), function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '')
                    );
                    break;
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                    $this->jobs->appendLog($jobId, 'warning', __('فشلت محاولة مزود الذكاء الاصطناعي وسيتم إعادة المحاولة.', 'vmp'), [
                        'attempt' => $attempt,
                        'error' => $lastError,
                    ]);
                }
            }

            if (!$result instanceof AIResult) {
                $result = $this->fallbackResult($job, $vendor, $lastError);
                $this->jobs->appendLog($jobId, 'warning', __('تم استخدام المسودة الاحتياطية بعد فشل مزود الذكاء الاصطناعي.', 'vmp'), [
                    'error' => $lastError,
                ]);
            }

            $data = $result->toArray();
            $this->jobs->update($jobId, [
                'status' => AIProductWorkflowState::REVIEW,
                'progress' => 100,
                'current_step' => AIProductWorkflowState::REVIEW,
                'result' => $data,
                'provider' => (string) ($data['provider'] ?? ''),
                'cost' => (float) ($data['cost'] ?? 0.0),
                'tokens' => ['total' => (int) ($data['tokens'] ?? 0)],
                'latency' => (int) ($data['latency_ms'] ?? 0),
                'error' => '',
            ]);
        } catch (\Throwable $e) {
            // Log detailed exception for debugging when WP_DEBUG is enabled
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log('[VMP-AI] processQueuedJob exception for job ' . $jobId . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
            $this->failJob($jobId, $e->getMessage());
            throw $e;
        }
    }

    /**
     * FallbackResult functionality helper.
     *
     * @param array $job Description index.
     * @param object $vendor Description index.
     * @param string $reason Description index.
     * @return AIResult Output payload.
     */
    private function fallbackResult(array $job, object $vendor, string $reason): AIResult
    {
        return AIResult::fromArray([
            'title' => $this->fallbackTitle((int) $job['attachment_id']),
            'description' => $this->fallbackDescription($vendor),
            'short_description' => $this->fallbackShortDescription($vendor),
            'keywords' => $this->fallbackKeywords($vendor),
            'specifications' => [
                __('مصدر البيانات', 'vmp') => __('صورة المنتج', 'vmp'),
                __('الحالة', 'vmp') => __('تحتاج مراجعة البائع', 'vmp'),
            ],
            'confidence' => 0.62,
            'warnings' => [
                __('مزود الذكاء الاصطناعي غير مهيأ بعد، لذلك تم إنشاء مسودة أولية قابلة للمراجعة.', 'vmp'),
                $reason,
            ],
            'provider' => 'fallback',
            'latency_ms' => 0,
            'tokens' => 0,
            'cost' => 0.0,
            'status' => 'draft',
            'review_status' => 'pending_review',
            'metadata' => [
                'workflow_version' => 'product-image-v1',
                'provider_version' => 'fallback-v1',
                'confidence_breakdown' => [
                    'title' => 0.7,
                    'description' => 0.62,
                    'seo' => 0.58,
                    'image' => 0.76,
                ],
                'prompt_versions' => [
                    'title' => $this->promptVersion('title'),
                    'description' => $this->promptVersion('description'),
                    'keywords' => $this->promptVersion('keywords'),
                ],
            ],
        ]);
    }

    /**
     * FallbackTitle functionality helper.
     *
     * @param int $attachmentId Description index.
     * @param string $suffix Description index.
     * @return string Output payload.
     */
    private function fallbackTitle(int $attachmentId, string $suffix = ''): string
    {
        $name = get_the_title($attachmentId);
        $name = $name ? sanitize_text_field($name) : __('منتج جديد من صورة', 'vmp');

        return trim($name . ($suffix ? ' - ' . $suffix : ''));
    }

    /**
     * FallbackDescription functionality helper.
     *
     * @param object $vendor Description index.
     * @param bool $regenerated Description index.
     * @return string Output payload.
     */
    private function fallbackDescription(object $vendor, bool $regenerated = false): string
    {
        $prefix = $regenerated
            ? __('وصف محسّن قابل للتعديل لهذا المنتج.', 'vmp')
            : __('مسودة وصف أولية لهذا المنتج بناءً على الصورة المرفوعة.', 'vmp');

        return $prefix . "\n\n" . sprintf(__('يرجى مراجعة التفاصيل وإضافة المقاسات أو المواد أو شروط الاستخدام قبل النشر في متجر %s.', 'vmp'), $vendor->store_name);
    }

    private function fallbackShortDescription(object $vendor): string
    {
        return sprintf(__('مسودة منتج قابلة للمراجعة من متجر %s.', 'vmp'), $vendor->store_name);
    }

    /**
     * FallbackKeywords functionality helper.
     *
     * @param object $vendor Description index.
     * @return array Output payload.
     */
    private function fallbackKeywords(object $vendor): array
    {
        return array_values(array_filter([
            __('منتج', 'vmp'),
            __('متجر', 'vmp'),
            sanitize_text_field((string) $vendor->store_name),
        ]));
    }

    /**
     * RequireJob functionality helper.
     *
     * @param string $jobId Description index.
     * @param int $vendorId Description index.
     * @throws \AIException Diagnostic error when triggered.
     * @return array Output payload.
     */
    private function requireJob(string $jobId, int $vendorId): array
    {
        $job = $this->getJob($jobId, $vendorId);
        if (!$job) {
            throw new AIException(__('عملية الذكاء الاصطناعي غير موجودة.', 'vmp'));
        }

        return $job;
    }

    /**
     * SaveJob functionality helper.
     *
     * @param array $job Description index.
     * @return void Output payload.
     */
    private function transition(string $jobId, string $state, int $progress): void
    {
        $this->jobs->update($jobId, [
            'status' => $state,
            'current_step' => $state,
            'progress' => $progress,
        ]);

        $this->jobs->appendLog($jobId, 'info', sprintf(__('انتقلت المهمة إلى المرحلة: %s', 'vmp'), $state));

        // Record a timeline event (step started)
        try {
            $this->jobs->appendEvent($jobId, 'StepStarted', ['step' => $state, 'progress' => $progress]);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[VMP-AI] transition->appendEvent failed: ' . $e->getMessage());
            }
        }
    }

    private function failJob(string $jobId, string $error): void
    {
        if ($jobId === '') {
            return;
        }

        try {
            $this->jobs->update($jobId, [
                'status' => AIProductWorkflowState::FAILED,
                'current_step' => AIProductWorkflowState::FAILED,
                'progress' => 100,
                'error' => $error,
            ]);
            $this->jobs->appendLog($jobId, 'error', __('فشلت مهمة إنشاء المنتج بالذكاء الاصطناعي.', 'vmp'), [
                'error' => $error,
            ]);
        } catch (\Throwable) {
            // The queue manager will persist the original failure.
        }
    }

    /**
     * PromptVersion functionality helper.
     *
     * @param string $part Description index.
     * @return string Output payload.
     */
    private function promptVersion(string $part): string
    {
        return $part . '-prompt-v1';
    }
}
