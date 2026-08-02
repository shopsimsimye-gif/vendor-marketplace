<?php
/**
 * Class Test_Plugin_Logic
 *
 * Comprehensive tests for the Vendor Marketplace plugin.
 *
 * @package vmp
 */

namespace VMP\Tests;

use WP_UnitTestCase;
use VMP\Core\Container;
use VMP\Core\EventManager;
use VMP\Core\Application;
use VMP\Core\Kernel;
use VMP\Core\Logger;
use VMP\Core\Queue\Job;
use VMP\Core\Queue\QueueManager;
use VMP\DTO\CommissionDTO;
use VMP\DTO\OrderDTO;
use VMP\DTO\ProductDTO;
use VMP\DTO\RegisterVendorDTO;
use VMP\DTO\SubscriptionDTO;
use VMP\DTO\SubscriptionPlanDTO;
use VMP\DTO\VendorDTO;
use VMP\DTO\WithdrawalDTO;
use VMP\Events\Commission\CommissionPaid;
use VMP\Events\Order\OrderCancelled;
use VMP\Events\Order\OrderCompleted;
use VMP\Events\Order\OrderPlaced;
use VMP\Events\Product\ProductApproved;
use VMP\Events\Product\ProductCreated;
use VMP\Events\Subscription\SubscriptionActivated;
use VMP\Events\Subscription\SubscriptionExpired;
use VMP\Events\Withdrawal\WithdrawalApproved;
use VMP\Events\Withdrawal\WithdrawalRequested;
use VMP\Exceptions\AIException;
use VMP\Exceptions\AuthenticationException;
use VMP\Exceptions\AuthorizationException;
use VMP\Exceptions\BusinessRuleException;
use VMP\Exceptions\ExternalApiException;
use VMP\Exceptions\NotFoundException;
use VMP\Exceptions\PaymentException;
use VMP\Exceptions\RepositoryException;
use VMP\Exceptions\ServiceException;
use VMP\Exceptions\SubscriptionException;
use VMP\Exceptions\ValidationException;
use VMP\Exceptions\VendorException;
use VMP\Exceptions\WithdrawalException;
use VMP\Http\Middleware\RateLimitMiddleware;
use VMP\Http\Middleware\NonceMiddleware;
use VMP\Http\Middleware\AuthenticationMiddleware;
use VMP\Http\Middleware\VendorMiddleware;
use VMP\Http\Responses\SuccessResponse;
use VMP\Http\Responses\ErrorResponse;
use VMP\Http\Responses\PaginatedResponse;
use VMP\Http\Responses\ValidationResponse;
use VMP\Http\ViewModels\DashboardViewModel;
use VMP\Http\ViewModels\OrderViewModel;
use VMP\Http\ViewModels\ProductViewModel;
use VMP\Http\ViewModels\VendorViewModel;
use VMP\Policies\PolicyResolver;
use VMP\Policies\CommissionPolicy;
use VMP\Policies\DashboardPolicy;
use VMP\Policies\OrderPolicy;
use VMP\Policies\ProductPolicy;
use VMP\Policies\SubscriptionPolicy;
use VMP\Policies\VendorPolicy;
use VMP\Policies\WithdrawalPolicy;
use VMP\Support\Config;
use VMP\Support\Security;
use VMP\Support\CacheManager;
use VMP\Support\Cache\Manager as CacheManagerSupport;
use VMP\Support\Cache\RepositoryCache;
use VMP\Support\VendorHelper;
use VMP\Validators\VendorValidator;
use VMP\Modules\AI\AIConfiguration;
use VMP\Modules\AI\Cost\AIUsage;
use VMP\Modules\AI\Cost\CostTracker;
use VMP\Modules\AI\Workflows\WorkflowContext;
use VMP\Modules\AI\Results\AIResult;
use VMP\Modules\AI\Context\ImageContext;
use VMP\Modules\AI\Context\ProductContext;
use VMP\Modules\AI\Context\StoreContext;
use VMP\Modules\AI\Context\VendorContext;

/**
 * Class Test_Plugin_Logic
 *
 * Description of administrative platform component Test_Plugin_Logic.
 *
 * @package vendor-marketplace
 */
class Test_Plugin_Logic extends WP_UnitTestCase {

    /**
     * SetUp functionality helper.
     *
     * @return void Output payload.
     */
    public function setUp(): void {
        parent::setUp();
        \VMP\Core\Install::activate();
    }

    /**
     * TearDown functionality helper.
     *
     * @return void Output payload.
     */
    public function tearDown(): void {
        \VMP\Core\Install::deactivate();
        parent::tearDown();
    }

    /**
     * Test Container And Service Resolution functionality helper.
     *
     * @return mixed Output payload.
     */
    public function test_container_and_service_resolution() {
        $container = Container::getInstance();
        $this->assertInstanceOf(Container::class, $container);

        $container->bind('test_service_bind', function() {
            return new \stdClass();
        });
        $resolved_one = $container->make('test_service_bind');
        $resolved_two = $container->make('test_service_bind');
        $this->assertNotSame($resolved_one, $resolved_two);

        $container->singleton('test_singleton', function() {
            return new \stdClass();
        });
        $singleton_one = $container->make('test_singleton');
        $singleton_two = $container->make('test_singleton');
        $this->assertSame($singleton_one, $singleton_two);

        $dummy = new \stdClass();
        $container->instance('test_instance', $dummy);
        $this->assertSame($dummy, $container->make('test_instance'));
    }

    /**
     * Test Events And Listeners Dispatch functionality helper.
     *
     * @return void Output payload.
     */
    public function test_events_and_listeners_dispatch() {
        $eventManager = new EventManager();
        $this->assertInstanceOf(EventManager::class, $eventManager);

        $dispatched = false;
        $eventManager->listen('vmp_test_action_dispatch', function() use (&$dispatched) {
            $dispatched = true;
        });

        $eventManager->trigger('vmp_test_action_dispatch');
        $this->assertTrue($dispatched);
    }

    /**
     * Test Exceptions functionality helper.
     *
     * @throws \ValidationException Diagnostic error when triggered.
     * @return void Output payload.
     */
    public function test_exceptions() {
        try {
            throw new ValidationException(['email' => 'Invalid email address'], 'Failed validation');
        } catch (ValidationException $e) {
            $this->assertEquals('Failed validation', $e->getMessage());
            $this->assertArrayHasKey('email', $e->getErrors());
            $this->assertEquals('Invalid email address', $e->getErrors()['email']);
        }

        $ex = new AuthenticationException('Auth issue');
        $this->assertEquals('Auth issue', $ex->getMessage());

        $ex = new AuthorizationException('Forbidden');
        $this->assertEquals('Forbidden', $ex->getMessage());

        $ex = new BusinessRuleException('Rule violated');
        $this->assertEquals('Rule violated', $ex->getMessage());

        $ex = new ExternalApiException('API error');
        $this->assertEquals('API error', $ex->getMessage());

        $ex = new NotFoundException('Not Found');
        $this->assertEquals('Not Found', $ex->getMessage());

        $ex = new PaymentException('Payment failed');
        $this->assertEquals('Payment failed', $ex->getMessage());

        $ex = new RepositoryException('DB error');
        $this->assertEquals('DB error', $ex->getMessage());

        $ex = new ServiceException('Service error');
        $this->assertEquals('Service error', $ex->getMessage());

        $ex = new SubscriptionException('Expired subscription');
        $this->assertEquals('Expired subscription', $ex->getMessage());

        $ex = new VendorException('Vendor is blocked');
        $this->assertEquals('Vendor is blocked', $ex->getMessage());

        $ex = new WithdrawalException('Withdrawal limit reached');
        $this->assertEquals('Withdrawal limit reached', $ex->getMessage());
    /**
     * Test Container Singleton And Forget functionality helper.
     *
     * @return mixed Output payload.
     */
    public function test_container_singleton_and_forget() {
    $container = \VMP\Core\Container::getInstance();
    $container->singleton('test_key', function() {
        return new \stdClass();
    });
    $instance1 = $container->make('test_key');
    $instance2 = $container->make('test_key');
    $this->assertSame($instance1, $instance2);
    $container->forget('test_key');
    $this->assertFalse($container->has('test_key'));
}

/**
 * Test Container Set Instance functionality helper.
 *
 * @return void Output payload.
 */
public function test_container_set_instance() {
    $container = \VMP\Core\Container::getInstance();
    \VMP\Core\Container::setInstance($container);
    $this->assertSame($container, \VMP\Core\Container::getInstance());
}

/**
 * Test Event Manager Listeners functionality helper.
 *
 * @return string Output payload.
 */
public function test_event_manager_listeners() {
    $eventManager = new \VMP\Core\EventManager();
    $callback = function() { return 'triggered'; };
    $eventManager->add_listener('vmp_test_event', $callback, 10);
    $this->assertEquals(1, $eventManager->getListenerCount('vmp_test_event'));
    $eventManager->flush();
    $this->assertEquals(0, $eventManager->getListenerCount('vmp_test_event'));
}

/**
 * Test Cache Manager Operations functionality helper.
 *
 * @return void Output payload.
 */
public function test_cache_manager_operations() {
    \VMP\Support\Cache\Manager::add_key_to_group('test_item_key', 'test_group');
    $keys = get_option('vmp_cache_keys_test_group', []);
    $this->assertContains('test_item_key', $keys);
    \VMP\Support\Cache\Manager::set('test_item_key', 'some_value', 300, 'test_group');
    $this->assertEquals('some_value', \VMP\Support\Cache\Manager::get('test_item_key', 'test_group'));
    \VMP\Support\Cache\Manager::delete('test_item_key', 'test_group');
    $this->assertFalse(\VMP\Support\Cache\Manager::get('test_item_key', 'test_group'));
}

/**
 * Test Cache Manager Flush functionality helper.
 *
 * @return void Output payload.
 */
public function test_cache_manager_flush() {
    \VMP\Support\Cache\Manager::set('flush_key_1', 'val1', 300, 'flush_group');
    \VMP\Support\Cache\Manager::set('flush_key_2', 'val2', 300, 'flush_group');
    $this->assertEquals('val1', \VMP\Support\Cache\Manager::get('flush_key_1', 'flush_group'));
    \VMP\Support\Cache\Manager::flush('flush_group');
    $this->assertFalse(\VMP\Support\Cache\Manager::get('flush_key_1', 'flush_group'));
    $this->assertFalse(\VMP\Support\Cache\Manager::get('flush_key_2', 'flush_group'));
}

/**
 * Test Security Helper Methods functionality helper.
 *
 * @return void Output payload.
 */
public function test_security_helper_methods() {
    $security = \VMP\Support\Security::getInstance();
    $this->assertEquals('192.168.1.0', $security->anonymizeIp('192.168.1.25'));
    $this->assertEquals('2001:db8:85a3::', $security->anonymizeIp('2001:db8:85a3:0000:0000:8a2e:0370:7334'));
    $allowed = ['apple', 'banana', 'orange'];
    $this->assertEquals('apple', $security->allowedValue('apple', $allowed, 'banana'));
    $this->assertEquals('banana', $security->allowedValue('grape', $allowed, 'banana'));
    $this->assertTrue($security->isStrongPassword('aB1!asdfghjk'));
    $this->assertFalse($security->isStrongPassword('12345'));
    $this->assertEquals('Safe Text', $security->sanitizeText('Safe <script>alert("bad")</script> Text'));
}

/**
 * Test Security Nonce Generation And Verification functionality helper.
 *
 * @return void Output payload.
 */
public function test_security_nonce_generation_and_verification() {
    $security = \VMP\Support\Security::getInstance();
    $nonce = $security->createNonce('my_action');
    $this->assertNotEmpty($nonce);
    try {
        $security->verifyNonce($nonce, 'my_action');
        $this->assertTrue(true);
    } catch (\Exception $e) {
        $this->fail('Nonce verification failed: ' . $e->getMessage());
    }
}

/**
 * Test Vendor Helper Dashboard Url functionality helper.
 *
 * @return void Output payload.
 */
public function test_vendor_helper_dashboard_url() {
    $settings = ['dashboard_page' => 'vendor-dashboard'];
    update_option('vmp_settings', $settings);
    $url = \VMP\Support\VendorHelper::dashboard_url('products');
    $this->assertStringContainsString('vendor-dashboard', $url);
    $this->assertStringContainsString('products', $url);
}

/**
 * Test Vendor Helper Price Formatting functionality helper.
 *
 * @return mixed Output payload.
 */
public function test_vendor_helper_price_formatting() {
    $formatted = \VMP\Support\VendorHelper::price(15.5);
    $this->assertNotEmpty($formatted);
}\n}

    /**
     * Test Dtos Serialization functionality helper.
     *
     * @return void Output payload.
     */
    public function test_dtos_serialization() {
        $registerDTO = new RegisterVendorDTO('Store name', 'store