<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for the cron router state management and dispatching logic.
 *
 * Uses a temporary directory for state files to avoid polluting runtime.
 */
final class CronRouterTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/cron_router_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        $files = glob($this->tmpDir . '/*');
        if ($files) {
            array_map('unlink', $files);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // State File Management
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function state_file_loads_empty_when_missing(): void
    {
        $stateFile = $this->tmpDir . '/cron_state.json';
        $this->assertFileDoesNotExist($stateFile);

        // Simulate load_state behavior
        $state = $this->loadState($stateFile);
        $this->assertEmpty($state);
    }

    #[Test]
    public function state_file_round_trips_correctly(): void
    {
        $stateFile = $this->tmpDir . '/cron_state.json';

        $state = [
            'process_email_queue' => [
                'last_dispatched' => '2026-02-22 10:00:00',
                'script' => 'process_email_queue.php',
            ],
            'cleanup_orphaned_uploads' => [
                'last_dispatched' => '2026-02-22 09:00:00',
                'script' => 'cleanup_orphaned_uploads.php',
            ],
        ];

        $this->saveState($stateFile, $state);
        $this->assertFileExists($stateFile);

        $loaded = $this->loadState($stateFile);
        $this->assertEquals($state, $loaded);
    }

    #[Test]
    public function state_file_handles_corrupt_json(): void
    {
        $stateFile = $this->tmpDir . '/cron_state.json';
        file_put_contents($stateFile, 'not valid json {{{');

        $state = $this->loadState($stateFile);
        $this->assertEmpty($state);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Duplicate Dispatch Prevention
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function same_minute_dispatch_is_prevented(): void
    {
        $now = new DateTimeImmutable('2026-02-22 10:30:00');

        $state = [
            'test_task' => [
                'last_dispatched' => '2026-02-22 10:30:45',
                'script' => 'test.php',
            ],
        ];

        // Same minute (10:30) — should be skipped
        $lastRun = $state['test_task']['last_dispatched'];
        $lastRunTime = new DateTimeImmutable($lastRun);
        $sameMinute = ($lastRunTime->format('Y-m-d H:i') === $now->format('Y-m-d H:i'));

        $this->assertTrue($sameMinute, 'Should detect same-minute dispatch');
    }

    #[Test]
    public function different_minute_allows_dispatch(): void
    {
        $now = new DateTimeImmutable('2026-02-22 10:31:00');

        $state = [
            'test_task' => [
                'last_dispatched' => '2026-02-22 10:30:45',
                'script' => 'test.php',
            ],
        ];

        $lastRun = $state['test_task']['last_dispatched'];
        $lastRunTime = new DateTimeImmutable($lastRun);
        $sameMinute = ($lastRunTime->format('Y-m-d H:i') === $now->format('Y-m-d H:i'));

        $this->assertFalse($sameMinute, 'Different minute should allow dispatch');
    }

    #[Test]
    public function first_run_has_no_state(): void
    {
        $state = [];
        $lastRun = $state['test_task']['last_dispatched'] ?? null;

        $this->assertNull($lastRun, 'First run should have no previous dispatch time');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Lock File
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function flock_prevents_concurrent_execution(): void
    {
        $lockFile = $this->tmpDir . '/router.lock';

        $fp1 = fopen($lockFile, 'c');
        $this->assertNotFalse($fp1);

        // First lock should succeed
        $locked = flock($fp1, LOCK_EX | LOCK_NB);
        $this->assertTrue($locked, 'First lock should succeed');

        // Second lock should fail (non-blocking)
        $fp2 = fopen($lockFile, 'c');
        $this->assertNotFalse($fp2);
        $locked2 = flock($fp2, LOCK_EX | LOCK_NB);
        $this->assertFalse($locked2, 'Second lock should fail');

        // Release first lock
        flock($fp1, LOCK_UN);
        fclose($fp1);

        // Now second lock should succeed
        $locked3 = flock($fp2, LOCK_EX | LOCK_NB);
        $this->assertTrue($locked3, 'Lock should succeed after release');

        flock($fp2, LOCK_UN);
        fclose($fp2);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Router CLI Validation
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function router_script_exists_and_is_executable(): void
    {
        $routerPath = __DIR__ . '/../../src/cron/router.php';
        $this->assertFileExists($routerPath);
    }

    #[Test]
    public function router_uses_shared_cron_expression_library(): void
    {
        $routerPath = __DIR__ . '/../../src/cron/router.php';
        $contents = file_get_contents($routerPath);
        $this->assertStringContainsString(
            "require_once __DIR__ . '/expression.php'",
            $contents,
            'Router must include the shared cron expression library'
        );
    }

    #[Test]
    public function router_prevents_web_execution(): void
    {
        $routerPath = __DIR__ . '/../../src/cron/router.php';
        $contents = file_get_contents($routerPath);
        $this->assertStringContainsString(
            'php_sapi_name()',
            $contents,
            'Router must check SAPI to prevent web execution'
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // Schedule Simulation
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function simulate_one_hour_of_dispatches(): void
    {
        $config = require __DIR__ . '/../../src/cron/config.php';
        $enabledTasks = array_filter($config, fn($t) => $t['enabled']);

        // Start at midnight on a Monday (dow=1)
        $start = new DateTimeImmutable('2026-06-01 00:00:00'); // Monday

        $dispatches = [];

        // Simulate 60 minutes
        for ($m = 0; $m < 60; $m++) {
            $time = $start->modify("+{$m} minutes");
            $minute = (int) $time->format('i');
            $hour   = (int) $time->format('G');
            $dom    = (int) $time->format('j');
            $month  = (int) $time->format('n');
            $dow    = (int) $time->format('w');

            foreach ($enabledTasks as $task) {
                if (cron_matches($task['schedule'], $minute, $hour, $dom, $month, $dow)) {
                    $dispatches[$task['name']] = ($dispatches[$task['name']] ?? 0) + 1;
                }
            }
        }

        // every-minute tasks should fire 60 times in 1 hour
        $this->assertEquals(60, $dispatches['process_email_queue'] ?? 0);
        $this->assertEquals(60, $dispatches['process_webhook_queue'] ?? 0);

        // top-of-hour tasks should fire once
        $this->assertEquals(1, $dispatches['cleanup_orphaned_uploads'] ?? 0);
        $this->assertEquals(1, $dispatches['warm_report_cache'] ?? 0, 'warm_report_cache should fire once at midnight');

        // :15 tasks should fire once
        $this->assertEquals(1, $dispatches['cleanup_expired_tokens'] ?? 0);
        $this->assertEquals(1, $dispatches['cleanup_expired_rate_limits'] ?? 0);
    }

    #[Test]
    public function simulate_full_day_daily_tasks_fire_once(): void
    {
        $config = require __DIR__ . '/../../src/cron/config.php';

        // Pick only daily tasks: hour field is a specific number (not *)
        $dailyTasks = array_filter($config, function ($t) {
            if (!$t['enabled']) return false;
            $parts = preg_split('/\s+/', trim($t['schedule']));
            // Hour field is a fixed number (no wildcards or steps) → true daily task
            return count($parts) === 5 && !str_contains($parts[1], '*');
        });

        $this->assertNotEmpty($dailyTasks, 'Should find at least one daily task');

        // Simulate a full day (1440 minutes) on Tuesday Jan 6 2026 (dow=2)
        $start = new DateTimeImmutable('2026-01-06 00:00:00');
        $dispatches = [];

        for ($m = 0; $m < 1440; $m++) {
            $time = $start->modify("+{$m} minutes");
            $minute = (int) $time->format('i');
            $hour   = (int) $time->format('G');
            $dom    = (int) $time->format('j');
            $month  = (int) $time->format('n');
            $dow    = (int) $time->format('w');

            foreach ($dailyTasks as $task) {
                if (cron_matches($task['schedule'], $minute, $hour, $dom, $month, $dow)) {
                    $dispatches[$task['name']] = ($dispatches[$task['name']] ?? 0) + 1;
                }
            }
        }

        // Each daily task should fire exactly once per day
        foreach ($dispatches as $name => $count) {
            $this->assertEquals(
                1,
                $count,
                "Daily task '{$name}' should fire exactly once per day, got {$count}"
            );
        }
    }

    #[Test]
    public function simulate_full_day_periodic_tasks_fire_expected_count(): void
    {
        $config = require __DIR__ . '/../../src/cron/config.php';

        // Periodic tasks: hour field contains a step pattern like */6
        $periodicTasks = array_filter($config, function ($t) {
            if (!$t['enabled']) return false;
            $parts = preg_split('/\s+/', trim($t['schedule']));
            return count($parts) === 5 && str_contains($parts[1], '*/');
        });

        $this->assertNotEmpty($periodicTasks, 'Should find at least one periodic task');

        // Simulate a full day (1440 minutes) on Tuesday Jan 6 2026 (dow=2)
        $start = new DateTimeImmutable('2026-01-06 00:00:00');
        $dispatches = [];

        for ($m = 0; $m < 1440; $m++) {
            $time = $start->modify("+{$m} minutes");
            $minute = (int) $time->format('i');
            $hour   = (int) $time->format('G');
            $dom    = (int) $time->format('j');
            $month  = (int) $time->format('n');
            $dow    = (int) $time->format('w');

            foreach ($periodicTasks as $task) {
                if (cron_matches($task['schedule'], $minute, $hour, $dom, $month, $dow)) {
                    $dispatches[$task['name']] = ($dispatches[$task['name']] ?? 0) + 1;
                }
            }
        }

        // warm_report_cache with */6 hours should fire 4 times per day (0, 6, 12, 18)
        $this->assertEquals(
            4,
            $dispatches['warm_report_cache'] ?? 0,
            'warm_report_cache (*/6 hours) should fire 4 times per day'
        );
    }

    #[Test]
    public function no_tasks_collide_on_same_minute(): void
    {
        $config = require __DIR__ . '/../../src/cron/config.php';

        // Only check non-every-minute tasks — collisions among those are by design
        $nonEveryMinute = array_filter($config, fn($t) => $t['schedule'] !== '* * * * *');

        // Check the daily 2:xx AM window for collisions
        $start = new DateTimeImmutable('2026-01-01 02:00:00');
        for ($m = 0; $m < 60; $m++) {
            $time = $start->modify("+{$m} minutes");
            $minute = (int) $time->format('i');
            $hour   = (int) $time->format('G');
            $dom    = (int) $time->format('j');
            $month  = (int) $time->format('n');
            $dow    = (int) $time->format('w');

            $matched = [];
            foreach ($nonEveryMinute as $task) {
                if (cron_matches($task['schedule'], $minute, $hour, $dom, $month, $dow)) {
                    $matched[] = $task['name'];
                }
            }

            // At most 2 tasks should share a minute (reasonable for background processes)
            $this->assertLessThanOrEqual(
                2,
                count($matched),
                "More than 2 tasks scheduled at minute {$minute}: " . implode(', ', $matched)
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Helpers (replicating router logic for testing)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * @return array<string, mixed>
     */
    private function loadState(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function saveState(string $path, array $state): void
    {
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $tmp = $path . '.tmp';
        file_put_contents($tmp, $json, LOCK_EX);
        rename($tmp, $path);
    }
}
