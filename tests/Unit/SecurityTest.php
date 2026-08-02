<?php
namespace VMP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VMP\Support\Security;

/**
 * @covers \VMP\Support\Security
 */
class SecurityTest extends TestCase
{
    // ─── Sanitize ─────────────────────────────────────────────────────────────

    /**
     * TestSanitizeTextRemovesHtml functionality helper.
     *
     * @return void Output payload.
     */
    public function testSanitizeTextRemovesHtml(): void
    {
        $result = Security::sanitizeText('<script>alert("xss")</script>نص عادي');
        $this->assertStringNotContainsString('<script>', $result);
    }

    /**
     * TestSanitizeEmailReturnsValidEmail functionality helper.
     *
     * @return void Output payload.
     */
    public function testSanitizeEmailReturnsValidEmail(): void
    {
        $result = Security::sanitizeEmail('  test@example.com  ');
        $this->assertSame('test@example.com', $result);
    }

    /**
     * TestSanitizeEmailRejectsInvalid functionality helper.
     *
     * @return void Output payload.
     */
    public function testSanitizeEmailRejectsInvalid(): void
    {
        $result = Security::sanitizeEmail('not-an-email');
        $this->assertSame('', $result);
    }

    /**
     * TestSanitizeIntConvertsToInteger functionality helper.
     *
     * @return void Output payload.
     */
    public function testSanitizeIntConvertsToInteger(): void
    {
        $this->assertSame(42, Security::sanitizeInt('42abc'));
        $this->assertSame(0, Security::sanitizeInt('abc'));
        $this->assertSame(-5, Security::sanitizeInt('-5'));
    }

    /**
     * TestSanitizeFloatConvertsToFloat functionality helper.
     *
     * @return void Output payload.
     */
    public function testSanitizeFloatConvertsToFloat(): void
    {
        $this->assertSame(3.14, Security::sanitizeFloat('3.14'));
        $this->assertSame(0.0, Security::sanitizeFloat('not a number'));
    }

    /**
     * TestSanitizeArraySanitizesNestedValues functionality helper.
     *
     * @return void Output payload.
     */
    public function testSanitizeArraySanitizesNestedValues(): void
    {
        $input = [
            'name'   => '<b>اسم</b>',
            'nested' => ['key' => '<script>bad</script>'],
        ];

        $result = Security::sanitizeArray($input);
        $this->assertStringNotContainsString('<b>', $result['name']);
        $this->assertStringNotContainsString('<script>', $result['nested']['key']);
    }

    // ─── Escape ───────────────────────────────────────────────────────────────

    /**
     * TestEscHtmlEncodesSpecialChars functionality helper.
     *
     * @return void Output payload.
     */
    public function testEscHtmlEncodesSpecialChars(): void
    {
        $result = Security::escHtml('<strong>اختبار & "أمان"</strong>');
        $this->assertStringContainsString('&lt;', $result);
        $this->assertStringContainsString('&amp;', $result);
    }

    /**
     * TestEscAttrEncodesQuotes functionality helper.
     *
     * @return void Output payload.
     */
    public function testEscAttrEncodesQuotes(): void
    {
        $result = Security::escAttr('"quote" & \'apostrophe\'');
        $this->assertStringNotContainsString('"', $result);
    }

    // ─── Password Strength ────────────────────────────────────────────────────

    /**
     * TestStrongPasswordValidation functionality helper.
     *
     * @return void Output payload.
     */
    public function testStrongPasswordValidation(): void
    {
        $this->assertTrue(Security::isStrongPassword('Password123'));
        $this->assertFalse(Security::isStrongPassword('password'));     // لا uppercase، لا رقم
        $this->assertFalse(Security::isStrongPassword('PASSWORD123')); // لا lowercase
        $this->assertFalse(Security::isStrongPassword('Pass123'));      // أقل من 8 أحرف
    }

    // ─── Whitelist ────────────────────────────────────────────────────────────

    /**
     * TestAllowedValueReturnsValueIfAllowed functionality helper.
     *
     * @return void Output payload.
     */
    public function testAllowedValueReturnsValueIfAllowed(): void
    {
        $allowed = ['pending', 'approved', 'rejected'];
        $this->assertSame('approved', Security::allowedValue('approved', $allowed));
    }

    /**
     * TestAllowedValueReturnsDefaultIfNotAllowed functionality helper.
     *
     * @return void Output payload.
     */
    public function testAllowedValueReturnsDefaultIfNotAllowed(): void
    {
        $allowed = ['pending', 'approved', 'rejected'];
        $this->assertSame('pending', Security::allowedValue('hacked', $allowed, 'pending'));
        $this->assertNull(Security::allowedValue('hacked', $allowed));
    }

    // ─── IP Anonymization ─────────────────────────────────────────────────────

    /**
     * TestAnonymizeIpv4 functionality helper.
     *
     * @return void Output payload.
     */
    public function testAnonymizeIpv4(): void
    {
        $result = Security::anonymizeIp('192.168.1.100');
        $this->assertSame('192.168.1.0', $result);
    }

    /**
     * TestAnonymizeEmptyIp functionality helper.
     *
     * @return void Output payload.
     */
    public function testAnonymizeEmptyIp(): void
    {
        $result = Security::anonymizeIp('');
        $this->assertSame('', $result);
    }

    // ─── Singleton ───────────────────────────────────────────────────────────

    /**
     * TestGetInstanceReturnsSameObject functionality helper.
     *
     * @return void Output payload.
     */
    public function testGetInstanceReturnsSameObject(): void
    {
        $a = Security::getInstance();
        $b = Security::getInstance();
        $this->assertSame($a, $b);
    }
}
