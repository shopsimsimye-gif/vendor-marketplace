<?php
namespace VMP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VMP\DTO\VendorDTO;
use VMP\Http\ViewModels\VendorViewModel;

/**
 * Class ViewModelTest
 *
 * Description of administrative platform component ViewModelTest.
 *
 * @package vendor-marketplace
 */
class ViewModelTest extends TestCase
{
    /**
     * SetUp functionality helper.
     *
     * @return void Output payload.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // محاكاة دوال الووردبريس المستخدمة في الـ ViewModel
        if (!function_exists('esc_html')) {
            /**
             * Esc Html functionality helper.
             *
             * @param mixed $text Description index.
             * @return mixed Output payload.
             */
            function esc_html($text) { return htmlspecialchars((string)$text, ENT_QUOTES); }
        }
        if (!function_exists('esc_attr')) {
            /**
             * Esc Attr functionality helper.
             *
             * @param mixed $text Description index.
             * @return mixed Output payload.
             */
            function esc_attr($text) { return htmlspecialchars((string)$text, ENT_QUOTES); }
        }
        if (!function_exists('esc_url')) {
            /**
             * Esc Url functionality helper.
             *
             * @param mixed $text Description index.
             * @return mixed Output payload.
             */
            function esc_url($text) { return filter_var($text, FILTER_SANITIZE_URL); }
        }
        if (!function_exists('wp_kses_post')) {
            /**
             * Wp Kses Post functionality helper.
             *
             * @param mixed $text Description index.
             * @return mixed Output payload.
             */
            function wp_kses_post($text) { return strip_tags($text, '<b><strong><i><em><a><p><br>'); }
        }
        if (!function_exists('__')) {
            /**
             *    functionality helper.
             *
             * @param mixed $text Description index.
             * @param mixed $domain Description index.
             * @return mixed Output payload.
             */
            function __($text, $domain) { return $text; }
        }
        if (!function_exists('home_url')) {
            /**
             * Home Url functionality helper.
             *
             * @param mixed $path Description index.
             * @return string Output payload.
             */
            function home_url($path) { return 'http://example.com' . $path; }
        }
    }

    /**
     * Test Vendor View Model Array Structure functionality helper.
     *
     * @return void Output payload.
     */
    public function test_vendor_view_model_array_structure()
    {
        $dto = new VendorDTO(
            id: 1,
            storeName: 'Test Store <script>alert(1)</script>',
            storeSlug: 'test-store',
            status: 'approved',
            balance: 50.5
        );

        $viewModel = new VendorViewModel($dto);
        $data = $viewModel->toArray();

        $this->assertEquals(1, $data['vendor_id']);
        
        // التأكد من تطبيق esc_html
        $this->assertEquals(htmlspecialchars('Test Store <script>alert(1)</script>', ENT_QUOTES), $data['store_name']);
        
        $this->assertEquals('test-store', $data['store_slug']);
        $this->assertEquals('مفعّل', $data['status_label']);
        $this->assertEquals('vmp-status--success', $data['status_class']);
        $this->assertEquals(50.5, $data['balance_raw']);
        $this->assertStringContainsString('50.50', $data['balance']);
    }
}
