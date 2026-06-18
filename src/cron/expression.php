<?php

/**
 * Cron Expression Parser & Matcher
 *
 * Provides functions to parse and match standard 5-field cron expressions.
 * Format: minute hour day_of_month month day_of_week
 *
 * Supports:
 *   *        every unit
 *   5        exact value
 *   1,3,5    comma-separated list
 *   1-5      range
 *   * /5     every 5 units (without space)
 *   1-30/5   every 5 units within range
 */

/**
 * Check whether a cron expression matches the given time components.
 *
 * @param string $expression  Cron expression "min hour dom month dow"
 * @param int    $minute      0-59
 * @param int    $hour        0-23
 * @param int    $dom         1-31  (day of month)
 * @param int    $month       1-12
 * @param int    $dow         0-6   (0 = Sunday)
 */
function cron_matches(string $expression, int $minute, int $hour, int $dom, int $month, int $dow): bool
{
    $parts = preg_split('/\s+/', trim($expression));
    if (count($parts) !== 5) {
        return false;
    }

    return cron_field_matches($parts[0], $minute, 0, 59)
        && cron_field_matches($parts[1], $hour,   0, 23)
        && cron_field_matches($parts[2], $dom,    1, 31)
        && cron_field_matches($parts[3], $month,  1, 12)
        && cron_field_matches($parts[4], $dow,    0, 6);
}

/**
 * Check whether a single cron field matches a value.
 *
 * @param string $field  The cron field expression
 * @param int    $value  The current value to test
 * @param int    $min    Minimum allowed value for this field
 * @param int    $max    Maximum allowed value for this field
 */
function cron_field_matches(string $field, int $value, int $min, int $max): bool
{
    // Handle comma-separated list
    if (str_contains($field, ',')) {
        foreach (explode(',', $field) as $part) {
            if (cron_field_matches(trim($part), $value, $min, $max)) {
                return true;
            }
        }
        return false;
    }

    // Handle step values (*/5 or 1-30/5)
    if (str_contains($field, '/')) {
        [$range, $step] = explode('/', $field, 2);
        $step = (int) $step;
        if ($step < 1) {
            return false;
        }

        if ($range === '*') {
            return ($value - $min) % $step === 0;
        }

        // Range with step: 1-30/5
        if (str_contains($range, '-')) {
            [$rangeMin, $rangeMax] = array_map('intval', explode('-', $range, 2));
            return $value >= $rangeMin && $value <= $rangeMax && ($value - $rangeMin) % $step === 0;
        }

        return false;
    }

    // Handle range: 1-5
    if (str_contains($field, '-')) {
        [$rangeMin, $rangeMax] = array_map('intval', explode('-', $field, 2));
        return $value >= $rangeMin && $value <= $rangeMax;
    }

    // Wildcard
    if ($field === '*') {
        return true;
    }

    // Exact value
    return (int) $field === $value;
}

/**
 * Get the next N times a cron expression would fire, starting from the given time.
 *
 * Useful for debugging and display. Iterates minute-by-minute.
 *
 * @param string             $expression  Cron expression
 * @param DateTimeImmutable  $from        Start time
 * @param int                $count       Number of matches to find
 * @return list<DateTimeImmutable>
 */
function cron_next_runs(string $expression, DateTimeImmutable $from, int $count = 5): array
{
    $matches = [];
    $current = $from;
    $maxIterations = 525960; // ~1 year of minutes

    for ($i = 0; $i < $maxIterations && count($matches) < $count; $i++) {
        $minute = (int) $current->format('i');
        $hour   = (int) $current->format('G');
        $dom    = (int) $current->format('j');
        $month  = (int) $current->format('n');
        $dow    = (int) $current->format('w');

        if (cron_matches($expression, $minute, $hour, $dom, $month, $dow)) {
            $matches[] = $current;
        }

        $current = $current->modify('+1 minute');
    }

    return $matches;
}

/**
 * Validate a cron expression format.
 *
 * @return array{valid: bool, error?: string}
 */
function cron_validate(string $expression): array
{
    $parts = preg_split('/\s+/', trim($expression));

    if (count($parts) !== 5) {
        return ['valid' => false, 'error' => 'Expected 5 fields, got ' . count($parts)];
    }

    $fieldNames = ['minute', 'hour', 'day of month', 'month', 'day of week'];
    $ranges     = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 6]];

    for ($i = 0; $i < 5; $i++) {
        $field = $parts[$i];
        [$min, $max] = $ranges[$i];

        if (!cron_validate_field($field, $min, $max)) {
            return ['valid' => false, 'error' => "Invalid {$fieldNames[$i]} field: '{$field}'"];
        }
    }

    return ['valid' => true];
}

/**
 * Validate a single cron field.
 */
function cron_validate_field(string $field, int $min, int $max): bool
{
    if ($field === '*') {
        return true;
    }

    // Comma-separated
    if (str_contains($field, ',')) {
        foreach (explode(',', $field) as $part) {
            if (!cron_validate_field(trim($part), $min, $max)) {
                return false;
            }
        }
        return true;
    }

    // Step
    if (str_contains($field, '/')) {
        $segments = explode('/', $field, 2);
        if (count($segments) !== 2) {
            return false;
        }

        $step = (int) $segments[1];
        if ($step < 1) {
            return false;
        }

        $range = $segments[0];
        if ($range === '*') {
            return true;
        }

        if (str_contains($range, '-')) {
            return cron_validate_field($range, $min, $max);
        }

        return false;
    }

    // Range
    if (str_contains($field, '-')) {
        $parts = explode('-', $field, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $lo = (int) $parts[0];
        $hi = (int) $parts[1];

        return $lo >= $min && $hi <= $max && $lo <= $hi;
    }

    // Exact value
    if (is_numeric($field)) {
        $val = (int) $field;
        return $val >= $min && $val <= $max;
    }

    return false;
}
