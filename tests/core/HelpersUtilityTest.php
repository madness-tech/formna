<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for utility helper functions
 */
final class HelpersUtilityTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════
    // uuid()
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function uuid_generates_valid_format(): void
    {
        $uuid = uuid();
        
        // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    #[Test]
    public function uuid_generates_unique_values(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = uuid();
        }
        
        $unique = array_unique($uuids);
        $this->assertCount(100, $unique);
    }

    // ═══════════════════════════════════════════════════════════════════
    // paginate_array()
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function paginate_array_returns_correct_structure(): void
    {
        $items = range(1, 25);
        $result = paginate_array($items, 1, 10);
        
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
    }

    #[Test]
    public function paginate_array_first_page(): void
    {
        $items = range(1, 25);
        $result = paginate_array($items, 1, 10);
        
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $result['rows']);
        $this->assertEquals(25, $result['total']);
        $this->assertEquals(3, $result['pages']);
        $this->assertEquals(1, $result['page']);
    }

    #[Test]
    public function paginate_array_last_page(): void
    {
        $items = range(1, 25);
        $result = paginate_array($items, 3, 10);
        
        $this->assertEquals([21, 22, 23, 24, 25], $result['rows']);
        $this->assertEquals(3, $result['page']);
    }

    #[Test]
    public function paginate_array_handles_empty_array(): void
    {
        $result = paginate_array([], 1, 10);
        
        $this->assertEmpty($result['rows']);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(1, $result['pages']);
    }

    #[Test]
    public function paginate_array_handles_invalid_per_page(): void
    {
        $items = range(1, 25);
        $result = paginate_array($items, 1, 0);
        
        // Should default to 10
        $this->assertCount(10, $result['rows']);
        $this->assertEquals(10, $result['per_page']);
    }

    #[Test]
    public function paginate_array_clamps_page_number(): void
    {
        $items = range(1, 25);
        
        // Page too high
        $result = paginate_array($items, 999, 10);
        $this->assertEquals(3, $result['page']); // Should be clamped to max page
        
        // Page too low
        $result = paginate_array($items, -5, 10);
        $this->assertEquals(1, $result['page']); // Should be clamped to 1
    }

    // ═══════════════════════════════════════════════════════════════════
    // normalize_file_extension()
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    #[DataProvider('fileExtensionProvider')]
    public function normalize_file_extension_works_correctly(string $input, string $expected): void
    {
        $this->assertEquals($expected, normalize_file_extension($input));
    }

    public static function fileExtensionProvider(): array
    {
        return [
            ['jpg', 'jpg'],
            ['JPG', 'JPG'],
            ['Jpg', 'Jpg'],
            ['.pdf', '.pdf'],
            ['..docx', '..docx'],
            ['PNG', 'PNG'],
            ['jpeg', 'jpg'],
            ['tif', 'tiff'],
            ['htm', 'html'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // Datetime functions
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function now_returns_valid_datetime(): void
    {
        $now = now();
        
        // Should match Y-m-d H:i:s format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $now);
        
        // Should be parseable
        $parsed = strtotime($now);
        $this->assertNotFalse($parsed);
    }

    #[Test]
    public function to_db_datetime_handles_null(): void
    {
        $this->assertNull(to_db_datetime(null));
        $this->assertNull(to_db_datetime(''));
    }

    #[Test]
    public function to_db_datetime_handles_timestamps(): void
    {
        $timestamp = 1609459200; // 2021-01-01 00:00:00 UTC
        $result = to_db_datetime($timestamp);
        
        $this->assertEquals('2021-01-01 00:00:00', $result);
    }

    #[Test]
    public function to_db_datetime_handles_date_strings(): void
    {
        $result = to_db_datetime('2023-06-15 14:30:00');
        
        $this->assertIsString($result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    #[Test]
    public function to_timestamp_converts_datetime(): void
    {
        $datetime = '2021-01-01 00:00:00';
        $timestamp = to_timestamp($datetime);
        
        $this->assertEquals(1609459200, $timestamp);
    }

    #[Test]
    public function to_timestamp_handles_null(): void
    {
        $this->assertNull(to_timestamp(null));
        $this->assertNull(to_timestamp(''));
    }

    #[Test]
    public function datetime_add_works_correctly(): void
    {
        $start = '2023-01-01 00:00:00';
        
        // Add 1 day
        $result = datetime_add($start, '+1 day');
        $this->assertEquals('2023-01-02 00:00:00', $result);
        
        // Add 2 hours
        $result = datetime_add($start, '+2 hours');
        $this->assertEquals('2023-01-01 02:00:00', $result);
        
        // Add 1 month
        $result = datetime_add($start, '+1 month');
        $this->assertEquals('2023-02-01 00:00:00', $result);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Color utility functions
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function hex_to_hsl_converts_correctly(): void
    {
        // Test white
        $hsl = hex_to_hsl('#ffffff');
        $this->assertEquals([0.0, 0.0, 100.0], $hsl);
        
        // Test black
        $hsl = hex_to_hsl('#000000');
        $this->assertEquals([0.0, 0.0, 0.0], $hsl);
    }

    #[Test]
    public function hsl_to_hex_converts_correctly(): void
    {
        // White: HSL(0, 0%, 100%)
        $hex = hsl_to_hex(0, 0, 100);
        $this->assertEquals('#ffffff', $hex);
        
        // Black: HSL(0, 0%, 0%)
        $hex = hsl_to_hex(0, 0, 0);
        $this->assertEquals('#000000', $hex);
    }

    #[Test]
    public function generate_color_palette_creates_variants(): void
    {
        $palette = generate_color_palette('#4f46e5');
        
        $this->assertArrayHasKey('50', $palette);
        $this->assertArrayHasKey('100', $palette);
        $this->assertArrayHasKey('500', $palette);
        $this->assertArrayHasKey('900', $palette);
        
        // Each should be a valid hex color
        foreach ($palette as $shade => $color) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $color);
        }
    }
}
