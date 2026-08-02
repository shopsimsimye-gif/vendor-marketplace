<?php
namespace VMP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use VMP\Repositories\VendorRepository;

/**
 * Class VendorRepositoryTest
 *
 * Description of administrative platform component VendorRepositoryTest.
 *
 * @package vendor-marketplace
 */
class VendorRepositoryTest extends TestCase
{
    protected VendorRepository $repository;

    /**
     * SetUp functionality helper.
     *
     * @return void Output payload.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // يفترض بيئة ووردبريس مع جداول الإضافة مثبتة
        // سيتم تشغيله فقط في بيئة اختبار متكاملة
        if (!class_exists('wpdb')) {
            $this->markTestSkipped('This test requires a WordPress environment.');
        }
        
        $this->repository = new VendorRepository();
    }

    /**
     * Test Can Create And Find Vendor functionality helper.
     *
     * @return void Output payload.
     */
    public function test_can_create_and_find_vendor()
    {
        global $wpdb;
        
        // تجهيز البيانات
        $data = [
            'user_id' => 9999, // افتراض وجود مستخدم أو إهمال المفتاح الأجنبي لأغراض الاختبار
            'store_name' => 'Integration Test Store',
            'store_slug' => 'integration-test-store',
            'store_email' => 'integration@test.com',
            'status' => 'pending'
        ];

        // الإنشاء
        $vendorId = $this->repository->create($data);
        $this->assertGreaterThan(0, $vendorId, 'Vendor creation failed');

        // البحث
        $vendor = $this->repository->find($vendorId);
        $this->assertNotNull($vendor);
        $this->assertEquals('Integration Test Store', $vendor->store_name);
        $this->assertEquals('pending', $vendor->status);

        // التحديث
        $updated = $this->repository->update($vendorId, ['status' => 'approved']);
        $this->assertTrue($updated);
        
        $vendor = $this->repository->find($vendorId);
        $this->assertEquals('approved', $vendor->status);

        // الحذف
        $deleted = $this->repository->delete($vendorId);
        $this->assertTrue($deleted);
        
        $vendor = $this->repository->find($vendorId);
        $this->assertNull($vendor, 'Vendor should be deleted');
    }
}
