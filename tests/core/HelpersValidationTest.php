<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for validation helper functions
 */
final class HelpersValidationTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════
    // validate_password_strength()
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function valid_password_returns_null(): void
    {
        $this->assertNull(validate_password_strength('ValidPass123'));
        $this->assertNull(validate_password_strength('MyP@ssw0rd!'));
        $this->assertNull(validate_password_strength('Abcd1234'));
    }

    #[Test]
    public function password_too_short_returns_error(): void
    {
        $result = validate_password_strength('Short1');
        $this->assertNotNull($result);
        $this->assertSame(t('validation.password_too_short'), $result);
    }

    #[Test]
    public function password_without_uppercase_returns_error(): void
    {
        $result = validate_password_strength('alllowercase123');
        $this->assertNotNull($result);
        $this->assertSame(t('validation.password_needs_uppercase'), $result);
    }

    #[Test]
    public function password_without_lowercase_returns_error(): void
    {
        $result = validate_password_strength('ALLUPPERCASE123');
        $this->assertNotNull($result);
        $this->assertSame(t('validation.password_needs_lowercase'), $result);
    }

    #[Test]
    public function password_without_digit_returns_error(): void
    {
        $result = validate_password_strength('NoDigitsHere');
        $this->assertNotNull($result);
        $this->assertSame(t('validation.password_needs_digit'), $result);
    }

    // ═══════════════════════════════════════════════════════════════════
    // is_valid_person_name()
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    #[DataProvider('validNamesProvider')]
    public function valid_names_pass_validation(string $name): void
    {
        $this->assertTrue(is_valid_person_name($name), "Failed for: {$name}");
    }

    public static function validNamesProvider(): array
    {
        return [
            ['John Doe'],
            ['Mary-Jane Watson'],
            ["O'Brien"],
            ['Dr. Smith'],
            ['José García'],
            ['François Müller'],
            ['محمد علي'],
            ['李明'],
            ['Marie-Claire D\'Arc'],
            ['Jean-Pierre'],
            ['Anne-Marie'],
        ];
    }

    #[Test]
    #[DataProvider('invalidNamesProvider')]
    public function invalid_names_fail_validation(string $name): void
    {
        $this->assertFalse(is_valid_person_name($name), "Should fail for: {$name}");
    }

    public static function invalidNamesProvider(): array
    {
        return [
            [''],
            ['   '],
            ['123'],
            ['John123'],
            ['<script>alert(1)</script>'],
            ['John@Doe'],
            ['Name#123'],
            ['test@example.com'],
        ];
    }

    #[Test]
    public function name_with_only_spaces_fails(): void
    {
        $this->assertFalse(is_valid_person_name('     '));
    }

    #[Test]
    public function name_with_numbers_fails(): void
    {
        $this->assertFalse(is_valid_person_name('John123'));
        $this->assertFalse(is_valid_person_name('Mary2000'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // sanitize()
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function sanitize_escapes_html_entities(): void
    {
        $this->assertEquals('&lt;script&gt;', sanitize('<script>'));
        $this->assertEquals('&quot;quotes&quot;', sanitize('"quotes"'));
        $this->assertEquals('&#039;single&#039;', sanitize("'single'"));
    }

    #[Test]
    public function sanitize_handles_arrays_recursively(): void
    {
        $input = [
            'name' => '<b>Test</b>',
            'nested' => [
                'value' => '<script>alert(1)</script>'
            ]
        ];

        $result = sanitize($input);
        
        $this->assertEquals('&lt;b&gt;Test&lt;/b&gt;', $result['name']);
        $this->assertEquals('&lt;script&gt;alert(1)&lt;/script&gt;', $result['nested']['value']);
    }

    #[Test]
    public function sanitize_preserves_non_string_types(): void
    {
        $this->assertEquals(123, sanitize(123));
        $this->assertEquals(45.67, sanitize(45.67));
        $this->assertTrue(sanitize(true));
        $this->assertNull(sanitize(null));
    }

    #[Test]
    public function sanitize_empty_string_returns_empty(): void
    {
        $this->assertEquals('', sanitize(''));
    }
}
