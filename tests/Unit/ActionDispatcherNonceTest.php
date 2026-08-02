<?php
namespace VMP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VMP\Infrastructure\Dispatcher\ActionDispatcher;
use VMP\Infrastructure\Dispatcher\RouteRegistry;
use VMP\Infrastructure\Dispatcher\ExceptionHandler;
use VMP\Infrastructure\Dispatcher\ControllerMethodResolver;
use VMP\Http\Requests\AbstractRequest;
use VMP\Http\Responses\ApiResponse;
use VMP\Core\Container;
use VMP\Core\Logger;
use ReflectionClass;

// كلاس مساعد للاختبار
/**
 * Class DummyRequest
 *
 * Description of administrative platform component DummyRequest.
 *
 * @package vendor-marketplace
 */
class DummyRequest extends AbstractRequest {
    public static string $lastNonceAction = '';
    public static string $lastNonceField = '';

    /**
     * FromPost functionality helper.
     *
     * @param string $nonce_action Description index.
     * @param string $nonce_field Description index.
     * @return static Output payload.
     */
    public static function fromPost(string $nonce_action = '', string $nonce_field = '_wpnonce'): static
    {
        self::$lastNonceAction = $nonce_action;
        self::$lastNonceField = $nonce_field;
        $instance = new static();
        return $instance;
    }

    /**
     * Rules functionality helper.
     *
     * @return array Output payload.
     */
    protected function rules(): array { return []; }
    /**
     * Validate functionality helper.
     *
     * @return bool Output payload.
     */
    public function validate(): bool { return true; }
}

/**
 * Class DummyController
 *
 * Description of administrative platform component DummyController.
 *
 * @package vendor-marketplace
 */
class DummyController {
    /**
     * DoSomething functionality helper.
     *
     * @param DummyRequest $request Description index.
     * @return ApiResponse Output payload.
     */
    public function doSomething(DummyRequest $request): ApiResponse
    {
        return new class extends ApiResponse {
            /**
             *   Construct functionality helper.
             *
             * @return void Output payload.
             */
            public function __construct() { parent::__construct(200, 'ok'); }
            /**
             * Send functionality helper.
             *
             * @return void Output payload.
             */
            public function send(): void {}
        };
    }
}

/**
 * اختبارات ActionDispatcher وتمرير قيم الـ nonce
 *
 * @covers \VMP\Infrastructure\Dispatcher\ActionDispatcher
 */
class ActionDispatcherNonceTest extends TestCase
{
    private ActionDispatcher $dispatcher;
    private Container $container;
    private RouteRegistry $registry;
    private ExceptionHandler $exceptionHandler;
    private ControllerMethodResolver $resolver;

    /**
     * SetUp functionality helper.
     *
     * @return void Output payload.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->container = new Container();
        $this->container->singleton(DummyController::class, fn() => new DummyController());
        
        $this->registry = new RouteRegistry();
        $this->exceptionHandler = new ExceptionHandler(new Logger());
        $this->resolver = $this->createMock(ControllerMethodResolver::class);
        
        $this->dispatcher = new ActionDispatcher(
            $this->container,
            $this->registry,
            $this->exceptionHandler,
            $this->resolver
        );
        
        // إعادة تعيين المتغيرات
        DummyRequest::$lastNonceAction = '';
        DummyRequest::$lastNonceField = '';
    }

    /**
     * TestDispatchPassesNonceToRequestFromPost functionality helper.
     *
     * @return void Output payload.
     */
    public function testDispatchPassesNonceToRequestFromPost(): void
    {
        $this->resolver->method('resolveRequestClass')
                       ->willReturn(DummyRequest::class);

        $reflection = new ReflectionClass(ActionDispatcher::class);
        $method = $reflection->getMethod('dispatch');
        $method->setAccessible(true);
        
        // تنفيذ الدالة
        $method->invokeArgs($this->dispatcher, [
            DummyController::class,
            'doSomething',
            'my_custom_action_nonce',
            'my_custom_nonce_field'
        ]);

        $this->assertSame('my_custom_action_nonce', DummyRequest::$lastNonceAction);
        $this->assertSame('my_custom_nonce_field', DummyRequest::$lastNonceField);
    }
}
