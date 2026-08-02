<?php
namespace VMP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VMP\DTO\VendorDTO;

/**
 * Class DTOTest
 *
 * Description of administrative platform component DTOTest.
 *
 * @package vendor-marketplace
 */
class DTOTest extends TestCase
{
    /**
     * Test Vendor Dto Creation From Array functionality helper.
     *
     * @return void Output payload.
     */
    public function test_vendor_dto_creation_from_array()
    {
        $data = [
            'id' => 10,
            'user_id' => 5,
            'store_name' => 'My Awesome Store',
            'is_trusted' => 1,
            'balance' => '150.50',
            'status' => 'approved'
        ];

        $dto = VendorDTO::fromArray($data);

        $this->assertEquals(10, $dto->id);
        $this->assertEquals(5, $dto->userId);
        $this->assertEquals('My Awesome Store', $dto->storeName);
        $this->assertTrue($dto->isTrusted);
        $this->assertEquals(150.5, $dto->balance);
        $this->assertEquals('approved', $dto->status);
    }

    /**
     * Test Vendor Dto Defaults functionality helper.
     *
     * @return void Output payload.
     */
    public function test_vendor_dto_defaults()
    {
        $dto = VendorDTO::fromArray([]);

        $this->assertEquals(0, $dto->id);
        $this->assertEquals('pending', $dto->status);
        $this->assertFalse($dto->isTrusted);
        $this->assertEquals(0.0, $dto->balance);
    }

    /**
     * Test Vendor Dto To Array functionality helper.
     *
     * @return void Output payload.
     */
    public function test_vendor_dto_to_array()
    {
        $dto = new VendorDTO(id: 5, storeName: 'Test');
        $array = $dto->toArray();

        $this->assertEquals(5, $array['id']);
        $this->assertEquals('Test', $array['store_name']);
        $this->assertEquals('pending', $array['status']);
    }
}
