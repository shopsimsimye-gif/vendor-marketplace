<?php
namespace VMP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VMP\Core\EventManager;
use VMP\Events\AbstractEvent;
use VMP\Events\Vendor\VendorRegistered;
use VMP\Events\Vendor\VendorApproved;
use VMP\Events\Order\OrderCompleted;

/**
 * @covers \VMP\Core\EventManager
 * @covers \VMP\Events\AbstractEvent
 */
class EventManagerTest extends TestCase
{
    private EventManager $events;

    /**
     * SetUp functionality helper.
     *
     * @return void Output payload.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->events = new EventManager();
        $this->events->flush();
    }

    // ─── Typed Events ─────────────────────────────────────────────────────────

    /**
     * TestDispatchTypedEventCallsListener functionality helper.
     *
     * @return void Output payload.
     */
    public function testDispatchTypedEventCallsListener(): void
    {
        $called = false;
        $receivedEvent = null;

        $this->events->on(VendorRegistered::class, function (VendorRegistered $event) use (&$called, &$receivedEvent) {
            $called = true;
            $receivedEvent = $event;
        });

        $event = new VendorRegistered(1, 10, 'متجر اختبار', 'test@example.com');
        $this->events->dispatch($event);

        $this->assertTrue($called, 'المستمع لم يُستدعَ');
        $this->assertSame($event, $receivedEvent, 'الحدث المُستقبَل لا يطابق الحدث المُرسَل');
    }

    /**
     * TestDispatchDoesNotCallWrongListener functionality helper.
     *
     * @return void Output payload.
     */
    public function testDispatchDoesNotCallWrongListener(): void
    {
        $called = false;

        // نسجّل مستمع لـ VendorApproved فقط
        $this->events->on(VendorApproved::class, function () use (&$called) {
            $called = true;
        });

        // نُطلق حدث مختلف (VendorRegistered)
        $event = new VendorRegistered(1, 10, 'متجر', 'test@example.com');
        $this->events->dispatch($event);

        $this->assertFalse($called, 'المستمع استُدعي بشكل خاطئ');
    }

    /**
     * TestMultipleListenersForSameEvent functionality helper.
     *
     * @return void Output payload.
     */
    public function testMultipleListenersForSameEvent(): void
    {
        $count = 0;

        $this->events->on(VendorRegistered::class, function () use (&$count) { $count++; });
        $this->events->on(VendorRegistered::class, function () use (&$count) { $count++; });
        $this->events->on(VendorRegistered::class, function () use (&$count) { $count++; });

        $this->events->dispatch(new VendorRegistered(1, 10, 'متجر', 'test@example.com'));

        $this->assertSame(3, $count, 'يجب أن يُستدعى 3 مستمعين');
    }

    /**
     * TestDispatchReturnsEvent functionality helper.
     *
     * @return void Output payload.
     */
    public function testDispatchReturnsEvent(): void
    {
        $event = new VendorRegistered(1, 10, 'متجر', 'test@example.com');
        $returned = $this->events->dispatch($event);
        $this->assertSame($event, $returned);
    }

    // ─── Event Data ───────────────────────────────────────────────────────────

    /**
     * TestVendorRegisteredEventHoldsCorrectData functionality helper.
     *
     * @return void Output payload.
     */
    public function testVendorRegisteredEventHoldsCorrectData(): void
    {
        $event = new VendorRegistered(42, 99, 'اسم المتجر', 'vendor@test.com');

        $this->assertSame(42, $event->vendorId);
        $this->assertSame(99, $event->userId);
        $this->assertSame('اسم المتجر', $event->storeName);
        $this->assertSame('vendor@test.com', $event->storeEmail);
        $this->assertSame('vendor.registered', $event->getName());
    }

    /**
     * TestOrderCompletedEventHoldsCorrectData functionality helper.
     *
     * @return void Output payload.
     */
    public function testOrderCompletedEventHoldsCorrectData(): void
    {
        $event = new OrderCompleted(5, 100, 7, 250.50);

        $this->assertSame(5, $event->vendorOrderId);
        $this->assertSame(100, $event->parentOrderId);
        $this->assertSame(7, $event->vendorId);
        $this->assertSame(250.50, $event->vendorEarnings);
        $this->assertSame('order.completed', $event->getName());
    }

    /**
     * TestEventToArrayContainsRequiredFields functionality helper.
     *
     * @return void Output payload.
     */
    public function testEventToArrayContainsRequiredFields(): void
    {
        $event = new VendorRegistered(1, 2, 'متجر', 'e@e.com');
        $array = $event->toArray();

        $this->assertArrayHasKey('event', $array);
        $this->assertArrayHasKey('occurred_at', $array);
        $this->assertArrayHasKey('vendor_id', $array);
        $this->assertSame('vendor.registered', $array['event']);
    }

    // ─── Legacy String Events ─────────────────────────────────────────────────

    /**
     * TestLegacyTriggerStillWorks functionality helper.
     *
     * @return void Output payload.
     */
    public function testLegacyTriggerStillWorks(): void
    {
        $called = false;

        $this->events->listen('vmp_vendor_approved', function ($vendorId) use (&$called) {
            $called = true;
        });

        $this->events->trigger('vmp_vendor_approved', 1);

        $this->assertTrue($called, 'Legacy trigger لم يعمل');
    }

    /**
     * TestGetListenerCount functionality helper.
     *
     * @return void Output payload.
     */
    public function testGetListenerCount(): void
    {
        $this->events->on(VendorRegistered::class, fn() => null);
        $this->events->on(VendorRegistered::class, fn() => null);

        $this->assertSame(2, $this->events->getListenerCount(VendorRegistered::class));
    }

    /**
     * TestFlushClearsAllListeners functionality helper.
     *
     * @return void Output payload.
     */
    public function testFlushClearsAllListeners(): void
    {
        $this->events->on(VendorRegistered::class, fn() => null);
        $this->events->flush();

        $this->assertSame(0, $this->events->getListenerCount(VendorRegistered::class));
    }
}
