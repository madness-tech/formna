<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for the cron expression parser and matcher.
 *
 * Covers: wildcard, exact value, comma lists, ranges, steps, combined,
 * full expression matching, validation, and next-run calculation.
 */
final class CronExpressionTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════
    // cron_field_matches — Wildcard
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function wildcard_matches_any_value(): void
    {
        $this->assertTrue(cron_field_matches('*', 0, 0, 59));
        $this->assertTrue(cron_field_matches('*', 30, 0, 59));
        $this->assertTrue(cron_field_matches('*', 59, 0, 59));
    }

    // ═══════════════════════════════════════════════════════════════════
    // cron_field_matches — Exact Value
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function exact_value_matches_only_that_value(): void
    {
        $this->assertTrue(cron_field_matches('0', 0, 0, 59));
        $this->assertTrue(cron_field_matches('30', 30, 0, 59));
        $this->assertFalse(cron_field_matches('30', 29, 0, 59));
        $this->assertFalse(cron_field_matches('30', 31, 0, 59));
    }

    #[Test]
    public function exact_value_works_for_hours(): void
    {
        $this->assertTrue(cron_field_matches('8', 8, 0, 23));
        $this->assertFalse(cron_field_matches('8', 9, 0, 23));
    }

    // ═══════════════════════════════════════════════════════════════════
    // cron_field_matches — Comma Lists
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function comma_list_matches_listed_values(): void
    {
        $this->assertTrue(cron_field_matches('1,3,5', 1, 0, 59));
        $this->assertTrue(cron_field_matches('1,3,5', 3, 0, 59));
        $this->assertTrue(cron_field_matches('1,3,5', 5, 0, 59));
        $this->assertFalse(cron_field_matches('1,3,5', 2, 0, 59));
        $this->assertFalse(cron_field_matches('1,3,5', 4, 0, 59));
    }

    #[Test]
    public function comma_list_handles_spaces(): void
    {
        $this->assertTrue(cron_field_matches('10, 20, 30', 20, 0, 59));
    }

    // ═══════════════════════════════════════════════════════════════════
    // cron_field_matches — Ranges
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function range_matches_inclusive_bounds(): void
    {
        $this->assertTrue(cron_field_matches('1-5', 1, 0, 59));
        $this->assertTrue(cron_field_matches('1-5', 3, 0, 59));
        $this->assertTrue(cron_field_matches('1-5', 5, 0, 59));
        $this->assertFalse(cron_field_matches('1-5', 0, 0, 59));
        $this->assertFalse(cron_field_matches('1-5', 6, 0, 59));
    }

    #[Test]
    public function range_works_for_days_of_week(): void
    {
        // Monday (1) through Friday (5)
        $this->assertTrue(cron_field_matches('1-5', 1, 0, 6));
        $this->assertTrue(cron_field_matches('1-5', 5, 0, 6));
        $this->assertFalse(cron_field_matches('1-5', 0, 0, 6)); // Sunday
        $this->assertFalse(cron_field_matches('1-5', 6, 0, 6)); // Saturday
    }

    // ═══════════════════════════════════════════════════════════════════
    // cron_field_matches — Step Values
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function wildcard_step_matches_every_nth(): void
    {
        // */5 starting from min (0): 0, 5, 10, 15, ...
        $this->assertTrue(cron_field_matches('*/5', 0, 0, 59));
        $this->assertTrue(cron_field_matches('*/5', 5, 0, 59));
        $this->assertTrue(cron_field_matches('*/5', 10, 0, 59));
        $this->assertTrue(cron_field_matches('*/5', 55, 0, 59));
        $this->assertFalse(cron_field_matches('*/5', 1, 0, 59));
        $this->assertFalse(cron_field_matches('*/5', 3, 0, 59));
    }

    #[Test]
    public function wildcard_step_with_non_zero_min(): void
    {
        // */2 for months (min=1): 1, 3, 5, 7, 9, 11
        $this->assertTrue(cron_field_matches('*/2', 1, 1, 12));
        $this->assertTrue(cron_field_matches('*/2', 3, 1, 12));
        $this->assertFalse(cron_field_matches('*/2', 2, 1, 12));
    }

    #[Test]
    public function range_step_matches_within_range(): void
    {
        // 1-30/5: 1, 6, 11, 16, 21, 26
        $this->assertTrue(cron_field_matches('1-30/5', 1, 0, 59));
        $this->assertTrue(cron_field_matches('1-30/5', 6, 0, 59));
        $this->assertTrue(cron_field_matches('1-30/5', 11, 0, 59));
        $this->assertTrue(cron_field_matches('1-30/5', 26, 0, 59));
        $this->assertFalse(cron_field_matches('1-30/5', 0, 0, 59));
        $this->assertFalse(cron_field_matches('1-30/5', 2, 0, 59));
        $this->assertFalse(cron_field_matches('1-30/5', 31, 0, 59));
    }

    #[Test]
    public function step_of_zero_never_matches(): void
    {
        $this->assertFalse(cron_field_matches('*/0', 0, 0, 59));
    }

    #[Test]
    public function unsupported_step_syntax_does_not_match(): void
    {
        // Only */n and a-b/n are supported by the parser
        $this->assertFalse(cron_field_matches('5/10', 5, 0, 59));
    }

    // ═══════════════════════════════════════════════════════════════════
    // cron_matches — Full Expression
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function every_minute_matches_any_time(): void
    {
        $this->assertTrue(cron_matches('* * * * *', 0, 0, 1, 1, 0));
        $this->assertTrue(cron_matches('* * * * *', 30, 12, 15, 6, 3));
        $this->assertTrue(cron_matches('* * * * *', 59, 23, 31, 12, 6));
    }

    #[Test]
    public function top_of_every_hour(): void
    {
        // 0 * * * *
        $this->assertTrue(cron_matches('0 * * * *', 0, 0, 1, 1, 0));
        $this->assertTrue(cron_matches('0 * * * *', 0, 12, 15, 6, 3));
        $this->assertFalse(cron_matches('0 * * * *', 1, 0, 1, 1, 0));
        $this->assertFalse(cron_matches('0 * * * *', 30, 12, 15, 6, 3));
    }

    #[Test]
    public function specific_time_daily(): void
    {
        // 30 2 * * *  (2:30 AM every day)
        $this->assertTrue(cron_matches('30 2 * * *', 30, 2, 1, 1, 0));
        $this->assertTrue(cron_matches('30 2 * * *', 30, 2, 15, 6, 3));
        $this->assertFalse(cron_matches('30 2 * * *', 31, 2, 1, 1, 0));
        $this->assertFalse(cron_matches('30 2 * * *', 30, 3, 1, 1, 0));
    }

    #[Test]
    public function weekday_schedule(): void
    {
        // 0 9 * * 1-5  (9 AM Monday-Friday)
        $this->assertTrue(cron_matches('0 9 * * 1-5', 0, 9, 1, 1, 1));  // Monday
        $this->assertTrue(cron_matches('0 9 * * 1-5', 0, 9, 1, 1, 5));  // Friday
        $this->assertFalse(cron_matches('0 9 * * 1-5', 0, 9, 1, 1, 0)); // Sunday
        $this->assertFalse(cron_matches('0 9 * * 1-5', 0, 9, 1, 1, 6)); // Saturday
    }

    #[Test]
    public function every_15_minutes(): void
    {
        // */15 * * * *
        $this->assertTrue(cron_matches('*/15 * * * *', 0, 0, 1, 1, 0));
        $this->assertTrue(cron_matches('*/15 * * * *', 15, 0, 1, 1, 0));
        $this->assertTrue(cron_matches('*/15 * * * *', 30, 0, 1, 1, 0));
        $this->assertTrue(cron_matches('*/15 * * * *', 45, 0, 1, 1, 0));
        $this->assertFalse(cron_matches('*/15 * * * *', 1, 0, 1, 1, 0));
        $this->assertFalse(cron_matches('*/15 * * * *', 14, 0, 1, 1, 0));
    }

    #[Test]
    public function first_of_month_at_midnight(): void
    {
        // 0 0 1 * *
        $this->assertTrue(cron_matches('0 0 1 * *', 0, 0, 1, 1, 0));
        $this->assertTrue(cron_matches('0 0 1 * *', 0, 0, 1, 6, 3));
        $this->assertFalse(cron_matches('0 0 1 * *', 0, 0, 2, 1, 0));
        $this->assertFalse(cron_matches('0 0 1 * *', 1, 0, 1, 1, 0));
    }

    #[Test]
    public function specific_months(): void
    {
        // 0 8 1 1,4,7,10 *  (quarterly on the 1st at 8 AM)
        $this->assertTrue(cron_matches('0 8 1 1,4,7,10 *', 0, 8, 1, 1, 0));
        $this->assertTrue(cron_matches('0 8 1 1,4,7,10 *', 0, 8, 1, 7, 0));
        $this->assertFalse(cron_matches('0 8 1 1,4,7,10 *', 0, 8, 1, 2, 0));
        $this->assertFalse(cron_matches('0 8 1 1,4,7,10 *', 0, 8, 1, 5, 0));
    }

    #[Test]
    public function invalid_expression_returns_false(): void
    {
        $this->assertFalse(cron_matches('', 0, 0, 1, 1, 0));
        $this->assertFalse(cron_matches('* *', 0, 0, 1, 1, 0));
        $this->assertFalse(cron_matches('* * * * * *', 0, 0, 1, 1, 0));
    }

    #[Test]
    public function expression_with_extra_whitespace_still_matches(): void
    {
        $this->assertTrue(cron_matches('  0   9   *   *   1-5  ', 0, 9, 1, 1, 1));
    }

    // ═══════════════════════════════════════════════════════════════════
    // cron_matches — Real Schedule Expressions from Config
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function config_schedule_every_minute(): void
    {
        // process_email_queue: * * * * *
        for ($m = 0; $m < 60; $m++) {
            $this->assertTrue(
                cron_matches('* * * * *', $m, 10, 15, 6, 3),
                "Every-minute schedule should match minute {$m}"
            );
        }
    }

    #[Test]
    public function config_schedule_top_of_hour(): void
    {
        // cleanup_orphaned_uploads: 0 * * * *
        $this->assertTrue(cron_matches('0 * * * *', 0, 0, 1, 1, 0));
        $this->assertTrue(cron_matches('0 * * * *', 0, 23, 31, 12, 6));
        $this->assertFalse(cron_matches('0 * * * *', 1, 0, 1, 1, 0));
    }

    #[Test]
    public function config_schedule_fifteen_past_hour(): void
    {
        // cleanup_expired_tokens: 15 * * * *
        $this->assertTrue(cron_matches('15 * * * *', 15, 5, 10, 3, 2));
        $this->assertFalse(cron_matches('15 * * * *', 14, 5, 10, 3, 2));
        $this->assertFalse(cron_matches('15 * * * *', 16, 5, 10, 3, 2));
    }

    #[Test]
    public function config_schedule_daily_2am(): void
    {
        // cleanup_abandoned_program_drafts: 0 2 * * *
        $this->assertTrue(cron_matches('0 2 * * *', 0, 2, 1, 1, 0));
        $this->assertFalse(cron_matches('0 2 * * *', 0, 3, 1, 1, 0));
        $this->assertFalse(cron_matches('0 2 * * *', 1, 2, 1, 1, 0));
    }

    #[Test]
    public function config_schedule_daily_8am(): void
    {
        // send_draft_reminders / send_expiry_warnings: 0 8 * * *
        $this->assertTrue(cron_matches('0 8 * * *', 0, 8, 15, 6, 3));
        $this->assertFalse(cron_matches('0 8 * * *', 0, 7, 15, 6, 3));
    }

    // ═══════════════════════════════════════════════════════════════════
    // cron_validate
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function validate_accepts_standard_expressions(): void
    {
        $this->assertTrue(cron_validate('* * * * *')['valid']);
        $this->assertTrue(cron_validate('0 * * * *')['valid']);
        $this->assertTrue(cron_validate('*/5 * * * *')['valid']);
        $this->assertTrue(cron_validate('0 2 * * *')['valid']);
        $this->assertTrue(cron_validate('0 9 * * 1-5')['valid']);
        $this->assertTrue(cron_validate('0 8 1 1,4,7,10 *')['valid']);
        $this->assertTrue(cron_validate('1-30/5 * * * *')['valid']);
    }

    #[Test]
    public function validate_rejects_wrong_field_count(): void
    {
        $result = cron_validate('* *');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('5 fields', $result['error'] ?? '');
    }

    #[Test]
    public function validate_rejects_out_of_range_values(): void
    {
        $result = cron_validate('60 * * * *');
        $this->assertFalse($result['valid']);

        $result = cron_validate('* 24 * * *');
        $this->assertFalse($result['valid']);

        $result = cron_validate('* * 32 * *');
        $this->assertFalse($result['valid']);

        $result = cron_validate('* * * 13 *');
        $this->assertFalse($result['valid']);

        $result = cron_validate('* * * * 7');
        $this->assertFalse($result['valid']);
    }

    #[Test]
    public function validate_rejects_reversed_range(): void
    {
        $result = cron_validate('5-1 * * * *');
        $this->assertFalse($result['valid']);
    }

    #[Test]
    public function validate_rejects_unsupported_step_base(): void
    {
        $result = cron_validate('5/10 * * * *');
        $this->assertFalse($result['valid']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // cron_next_runs
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function next_runs_finds_correct_future_times(): void
    {
        // Starting at 2026-01-15 00:00 Thursday (dow=4), schedule: 30 2 * * *
        $from = new DateTimeImmutable('2026-01-15 00:00:00');
        $runs = cron_next_runs('30 2 * * *', $from, 3);

        $this->assertCount(3, $runs);
        $this->assertEquals('2026-01-15 02:30', $runs[0]->format('Y-m-d H:i'));
        $this->assertEquals('2026-01-16 02:30', $runs[1]->format('Y-m-d H:i'));
        $this->assertEquals('2026-01-17 02:30', $runs[2]->format('Y-m-d H:i'));
    }

    #[Test]
    public function next_runs_every_5_minutes(): void
    {
        $from = new DateTimeImmutable('2026-06-01 10:00:00');
        $runs = cron_next_runs('*/5 * * * *', $from, 4);

        $this->assertCount(4, $runs);
        $this->assertEquals('10:00', $runs[0]->format('H:i'));
        $this->assertEquals('10:05', $runs[1]->format('H:i'));
        $this->assertEquals('10:10', $runs[2]->format('H:i'));
        $this->assertEquals('10:15', $runs[3]->format('H:i'));
    }

    #[Test]
    public function next_runs_returns_empty_for_impossible_schedule(): void
    {
        // 0 0 31 2 * — Feb 31 never exists, but matcher checks field-by-field
        // Actually cron_matches will match 31 as dom when month is 2 because it doesn't
        // validate calendar logic. This tests the iteration limit.
        $from = new DateTimeImmutable('2026-01-01 00:00:00');
        $runs = cron_next_runs('0 0 30 2 *', $from, 1);

        // Feb 30 never occurs in any year, so 0 results within the 1-year window
        $this->assertCount(0, $runs);
    }

    #[Test]
    public function next_runs_returns_empty_when_count_is_zero(): void
    {
        $from = new DateTimeImmutable('2026-01-01 00:00:00');
        $runs = cron_next_runs('* * * * *', $from, 0);

        $this->assertSame([], $runs);
    }
}
