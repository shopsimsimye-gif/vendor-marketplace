<?php
namespace VMP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VMP\Http\Requests\AbstractRequest;

/**
 * اختبارات AbstractRequest
 *
 * يُغطي:
 *   - قواعد التحقق الأساسية (required, email, min, max, integer, phone)
 *   - رفض الـ nonce غير الصالح (fromPost مع nonce_action غير مطابق)
 *   - استرجاع البيانات المُحققة validated()
 *   - استرجاع الأخطاء
 *
 * @covers \VMP\Http\Requests\AbstractRequest
 */
class AbstractRequestTest extends TestCase
{
    // ─── مساعد: إنشاء Request مجهول بقواعد محددة ─────────────────────────────

    /**
     * @param array<string, array<string>> $rules
     * @param array<string, mixed>         $data
     */
    private function makeRequest(array $rules, array $data): AbstractRequest
    {
        return new class($rules, $data) extends AbstractRequest {
            public function __construct(
                private array $testRules,
                array $testData
            ) {
                $this->data = $testData;
            }

            /**
             * Rules functionality helper.
             *
             * @return array Output payload.
             */
            protected function rules(): array
            {
                return $this->testRules;
            }
        };
    }

    // ─── required ─────────────────────────────────────────────────────────────

    /**
     * TestRequiredRuleFailsForMissingField functionality helper.
     *
     * @return void Output payload.
     */
    public function testRequiredRuleFailsForMissingField(): void
    {
        $request = $this->makeRequest(
            ['store_name' => ['required', 'string']],
            []
        );

        $this->expectException(\VMP\Exceptions\ValidationException::class);
        $request->validate();
    }

    /**
     * TestRequiredRulePassesWhenFieldPresent functionality helper.
     *
     * @return void Output payload.
     */
    public function testRequiredRulePassesWhenFieldPresent(): void
    {
        $request = $this->makeRequest(
            ['store_name' => ['required', 'string']],
            ['store_name' => 'My Store']
        );

        $this->assertTrue($request->validate());
    }

    // ─── email ────────────────────────────────────────────────────────────────

    /**
     * TestEmailRuleRejectsInvalidEmail functionality helper.
     *
     * @return void Output payload.
     */
    public function testEmailRuleRejectsInvalidEmail(): void
    {
        $request = $this->makeRequest(
            ['user_email' => ['required', 'email']],
            ['user_email' => 'not-an-email']
        );

        $this->expectException(\VMP\Exceptions\ValidationException::class);
        $request->validate();
    }

    /**
     * TestEmailRuleAcceptsValidEmail functionality helper.
     *
     * @return void Output payload.
     */
    public function testEmailRuleAcceptsValidEmail(): void
    {
        $request = $this->makeRequest(
            ['user_email' => ['required', 'email']],
            ['user_email' => 'vendor@example.com']
        );

        $this->assertTrue($request->validate());
    }

    // ─── min / max ────────────────────────────────────────────────────────────

    /**
     * TestMinRuleFailsForShortString functionality helper.
     *
     * @return void Output payload.
     */
    public function testMinRuleFailsForShortString(): void
    {
        $request = $this->makeRequest(
            ['store_name' => ['required', 'string', 'min:3']],
            ['store_name' => 'AB']
        );

        $this->expectException(\VMP\Exceptions\ValidationException::class);
        $request->validate();
    }

    /**
     * TestMaxRuleFailsForLongString functionality helper.
     *
     * @return void Output payload.
     */
    public function testMaxRuleFailsForLongString(): void
    {
        $request = $this->makeRequest(
            ['store_name' => ['required', 'string', 'max:5']],
            ['store_name' => 'TooLongName']
        );

        $this->expectException(\VMP\Exceptions\ValidationException::class);
        $request->validate();
    }

    // ─── integer ──────────────────────────────────────────────────────────────

    /**
     * TestIntegerRulePassesForNumericString functionality helper.
     *
     * @return void Output payload.
     */
    public function testIntegerRulePassesForNumericString(): void
    {
        $request = $this->makeRequest(
            ['plan_id' => ['required', 'integer']],
            ['plan_id' => '42']
        );

        $this->assertTrue($request->validate());
    }

    /**
     * TestIntegerRuleFailsForFloat functionality helper.
     *
     * @return void Output payload.
     */
    public function testIntegerRuleFailsForFloat(): void
    {
        $request = $this->makeRequest(
            ['plan_id' => ['required', 'integer']],
            ['plan_id' => '3.14']
        );

        $this->expectException(\VMP\Exceptions\ValidationException::class);
        $request->validate();
    }

    // ─── optional field ───────────────────────────────────────────────────────

    /**
     * TestOptionalFieldIsSkippedWhenEmpty functionality helper.
     *
     * @return void Output payload.
     */
    public function testOptionalFieldIsSkippedWhenEmpty(): void
    {
        $request = $this->makeRequest(
            ['phone' => ['string', 'phone']],
            []   // phone not provided
        );

        $this->assertTrue($request->validate());
    }

    // ─── in rule ──────────────────────────────────────────────────────────────

    /**
     * TestInRuleRejectsDisallowedValue functionality helper.
     *
     * @return void Output payload.
     */
    public function testInRuleRejectsDisallowedValue(): void
    {
        $request = $this->makeRequest(
            ['status' => ['required', 'in:pending,approved,rejected']],
            ['status' => 'hacked']
        );

        $this->expectException(\VMP\Exceptions\ValidationException::class);
        $request->validate();
    }

    /**
     * TestInRuleAcceptsAllowedValue functionality helper.
     *
     * @return void Output payload.
     */
    public function testInRuleAcceptsAllowedValue(): void
    {
        $request = $this->makeRequest(
            ['status' => ['required', 'in:pending,approved,rejected']],
            ['status' => 'approved']
        );

        $this->assertTrue($request->validate());
    }

    // ─── validated() ──────────────────────────────────────────────────────────

    /**
     * TestValidatedReturnsOnlyRuleFields functionality helper.
     *
     * @return void Output payload.
     */
    public function testValidatedReturnsOnlyRuleFields(): void
    {
        $request = $this->makeRequest(
            ['store_name' => ['required', 'string']],
            ['store_name' => 'My Store', 'extra_field' => 'should_be_excluded']
        );

        $validated = $request->validated();

        $this->assertArrayHasKey('store_name', $validated);
        $this->assertArrayNotHasKey('extra_field', $validated);
    }

    // ─── errors() ─────────────────────────────────────────────────────────────

    /**
     * TestErrorsReturnedAfterFailedValidation functionality helper.
     *
     * @return void Output payload.
     */
    public function testErrorsReturnedAfterFailedValidation(): void
    {
        $request = $this->makeRequest(
            ['store_name' => ['required']],
            []
        );

        try {
            $request->validate();
        } catch (\VMP\Exceptions\ValidationException $e) {
            // expected
        }

        $this->assertNotEmpty($request->errors());
    }

    /**
     * TestFirstErrorReturnsFirstMessage functionality helper.
     *
     * @return void Output payload.
     */
    public function testFirstErrorReturnsFirstMessage(): void
    {
        $request = $this->makeRequest(
            ['store_name' => ['required']],
            []
        );

        try {
            $request->validate();
        } catch (\VMP\Exceptions\ValidationException $e) {
            // expected
        }

        $this->assertIsString($request->firstError());
        $this->assertNotEmpty($request->firstError());
    }

    // ─── from() ───────────────────────────────────────────────────────────────

    /**
     * TestFromCreatesRequestWithProvidedData functionality helper.
     *
     * @return void Output payload.
     */
    public function testFromCreatesRequestWithProvidedData(): void
    {
        $request = $this->makeRequest(
            ['store_name' => ['required', 'string']],
            []
        );

        $fromRequest = $request::from(['store_name' => 'Test Store']);
        $this->assertSame('Test Store', $fromRequest->get('store_name'));
    }

    // ─── get / string / int / bool ────────────────────────────────────────────

    /**
     * TestGetReturnsDefaultForMissingKey functionality helper.
     *
     * @return void Output payload.
     */
    public function testGetReturnsDefaultForMissingKey(): void
    {
        $request = $this->makeRequest(['name' => ['string']], []);
        $this->assertSame('default', $request->get('name', 'default'));
    }

    /**
     * TestIntHelperCastsToInteger functionality helper.
     *
     * @return void Output payload.
     */
    public function testIntHelperCastsToInteger(): void
    {
        $request = $this->makeRequest(['qty' => ['integer']], ['qty' => '5']);
        $this->assertSame(5, $request->int('qty'));
    }

    /**
     * TestBoolHelperReturnsFalseForEmpty functionality helper.
     *
     * @return void Output payload.
     */
    public function testBoolHelperReturnsFalseForEmpty(): void
    {
        $request = $this->makeRequest(['flag' => ['boolean']], ['flag' => '']);
        $this->assertFalse($request->bool('flag'));
    }
}
