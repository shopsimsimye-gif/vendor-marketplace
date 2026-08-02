<?php
namespace VMP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VMP\Core\Container;
use VMP\Core\Logger;
use VMP\Core\Queue\QueueManager;
use VMP\Core\Queue\Job;
use VMP\Core\Queue\JobInterface;

// دالة ووردبريس مساعدة محاكاة لوحدة الاختبار
if (!function_exists('sanitize_text_field')) {
    /**
     * Sanitize Text Field functionality helper.
     *
     * @param mixed $value Description index.
     * @return mixed Output payload.
     */
    function sanitize_text_field($value) {
        return trim($value);
    }
}
if (!function_exists('wp_json_encode')) {
    /**
     * Wp Json Encode functionality helper.
     *
     * @param mixed $data Description index.
     * @return mixed Output payload.
     */
    function wp_json_encode($data) {
        return json_encode($data);
    }
}
if (!function_exists('current_time')) {
    /**
     * Current Time functionality helper.
     *
     * @param mixed $type Description index.
     * @return mixed Output payload.
     */
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

/**
 * وظيفة محاكاة للاختبارات
 */
class FakeTestJob implements JobInterface
{
    public static bool $executed = false;
    public static array $receivedPayload = [];

    /**
     *   Construct functionality helper.
     *
     * @param array $payload Description index.
     * @return void Output payload.
     */
    public function __construct(private array $payload = []) {}

    /**
     * Handle functionality helper.
     *
     * @return void Output payload.
     */
    public function handle(): void
    {
        self::$executed = true;
        self::$receivedPayload = $this->payload;
    }

    /**
     * GetPayload functionality helper.
     *
     * @return array Output payload.
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * FromPayload functionality helper.
     *
     * @param array $payload Description index.
     * @return self Output payload.
     */
    public static function fromPayload(array $payload): self
    {
        return new self($payload);
    }
}

/**
 * وظيفة تفشل عمداً للاختبارات
 */
class FailingTestJob implements JobInterface
{
    /**
     * Handle functionality helper.
     *
     * @throws \\RuntimeException Diagnostic error when triggered.
     * @return void Output payload.
     */
    public function handle(): void
    {
        throw new \RuntimeException('خطأ متعمد');
    }

    /**
     * GetPayload functionality helper.
     *
     * @return array Output payload.
     */
    public function getPayload(): array
    {
        return [];
    }

    /**
     * FromPayload functionality helper.
     *
     * @param array $payload Description index.
     * @return self Output payload.
     */
    public static function fromPayload(array $payload): self
    {
        return new self();
    }
}

/**
 * @covers \VMP\Core\Queue\QueueManager
 * @covers \VMP\Core\Queue\Job
 */
class QueueTest extends TestCase
{
    private Container $container;
    private $loggerMock;
    private $wpdbMock;
    private QueueManager $queue;

    /**
     * SetUp functionality helper.
     *
     * @return void Output payload.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->container = new Container();
        $this->loggerMock = $this->createMock(Logger::class);
        $this->wpdbMock = $this->getMockBuilder(stdClass::class)
            ->addMethods(['insert', 'update', 'delete', 'query', 'prepare', 'get_col', 'get_results'])
            ->getMock();
            
        $this->wpdbMock->prefix = 'wp_';
        
        // إعداد الحاوية
        $this->container->instance(Logger::class, $this->loggerMock);
        
        $this->queue = new QueueManager(
            $this->container,
            $this->loggerMock,
            $this->wpdbMock
        );

        FakeTestJob::$executed = false;
        FakeTestJob::$receivedPayload = [];
    }

    /**
     * TestPushJobInsertsIntoDatabase functionality helper.
     *
     * @return void Output payload.
     */
    public function testPushJobInsertsIntoDatabase(): void
    {
        $this->wpdbMock->expects($this->once())
            ->method('insert')
            ->with(
                'wp_vmp_jobs',
                $this->callback(function ($data) {
                    return $data['job_class'] === FakeTestJob::class 
                        && $data['status'] === 'pending'
                        && json_decode($data['payload'], true) === ['id' => 123];
                })
            )
            ->willReturn(1);

        $this->wpdbMock->insert_id = 45;

        $jobId = $this->queue->push(FakeTestJob::class, ['id' => 123]);
        $this->assertSame(45, $jobId);
    }

    /**
     * TestProcessSuccessfulJob functionality helper.
     *
     * @return void Output payload.
     */
    public function testProcessSuccessfulJob(): void
    {
        $jobData = new \stdClass();
        $jobData->id = 1;
        $jobData->job_class = FakeTestJob::class;
        $jobData->payload = json_encode(['email' => 'test@test.com']);
        $jobData->status = 'processing';
        $jobData->attempts = 1;
        $jobData->error_message = null;
        $jobData->locked_at = null;
        $jobData->created_at = null;

        $job = Job::fromDbRow($jobData);

        // توقع مسح الوظيفة بعد إكمالها بنجاح
        $this->wpdbMock->expects($this->once())
            ->method('delete')
            ->with('wp_vmp_jobs', ['id' => 1])
            ->willReturn(true);

        $result = $this->queue->process($job);

        $this->assertTrue($result);
        $this->assertTrue(FakeTestJob::$executed);
        $this->assertSame(['email' => 'test@test.com'], FakeTestJob::$receivedPayload);
    }

    /**
     * TestProcessFailingJobUpdatesStatusAndAttempts functionality helper.
     *
     * @return void Output payload.
     */
    public function testProcessFailingJobUpdatesStatusAndAttempts(): void
    {
        $jobData = new \stdClass();
        $jobData->id = 2;
        $jobData->job_class = FailingTestJob::class;
        $jobData->payload = json_encode([]);
        $jobData->status = 'processing';
        $jobData->attempts = 1;
        $jobData->error_message = null;
        $jobData->locked_at = null;
        $jobData->created_at = null;

        $job = Job::fromDbRow($jobData);

        // توقع تحديث حالة الوظيفة لـ pending مع الرسالة الخطأ (لأن المحاولات 1 أقل من 3)
        $this->wpdbMock->expects($this->once())
            ->method('update')
            ->with(
                'wp_vmp_jobs',
                $this->callback(function ($data) {
                    return $data['status'] === 'pending'
                        && strpos($data['error_message'], 'خطأ متعمد') !== false
                        && $data['locked_at'] === null;
                }),
                ['id' => 2]
            )
            ->willReturn(true);

        $result = $this->queue->process($job);
        $this->assertFalse($result);
    }

    /**
     * TestProcessExceededAttemptsFailsPermanently functionality helper.
     *
     * @return void Output payload.
     */
    public function testProcessExceededAttemptsFailsPermanently(): void
    {
        $jobData = new \stdClass();
        $jobData->id = 3;
        $jobData->job_class = FailingTestJob::class;
        $jobData->payload = json_encode([]);
        $jobData->status = 'processing';
        $jobData->attempts = 3; // المحاولة الثالثة
        $jobData->error_message = null;
        $jobData->locked_at = null;
        $jobData->created_at = null;

        $job = Job::fromDbRow($jobData);

        // توقع تحديث حالة الوظيفة لـ failed بشكل نهائي
        $this->wpdbMock->expects($this->once())
            ->method('update')
            ->with(
                'wp_vmp_jobs',
                $this->callback(function ($data) {
                    return $data['status'] === 'failed';
                }),
                ['id' => 3]
            )
            ->willReturn(true);

        $result = $this->queue->process($job);
        $this->assertFalse($result);
    }
}
