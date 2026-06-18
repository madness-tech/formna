<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for the cron configuration file.
 *
 * Validates that every configured task has a valid schedule expression,
 * points to an existing script file, and has all required keys.
 */
final class CronConfigTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $tasks;

    protected function setUp(): void
    {
        $configFile = __DIR__ . '/../../src/cron/config.php';
        $this->assertFileExists($configFile, 'cron_config.php must exist');
        $this->tasks = require $configFile;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Structure
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function config_returns_non_empty_array(): void
    {
        $this->assertIsArray($this->tasks);
        $this->assertNotEmpty($this->tasks);
    }

    #[Test]
    public function every_task_has_required_keys(): void
    {
        $requiredKeys = ['name', 'script', 'schedule', 'enabled', 'timeout', 'description'];

        foreach ($this->tasks as $i => $task) {
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $task,
                    "Task at index {$i} is missing required key '{$key}'"
                );
            }
        }
    }

    #[Test]
    public function task_names_are_unique(): void
    {
        $names = array_column($this->tasks, 'name');
        $this->assertCount(
            count(array_unique($names)),
            $names,
            'Duplicate task names found: ' . implode(', ', array_diff_assoc($names, array_unique($names)))
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // Script Existence
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function every_script_file_exists(): void
    {
        $scriptsDir = __DIR__ . '/../../src/cron/tasks';

        foreach ($this->tasks as $task) {
            $path = $scriptsDir . '/' . $task['script'];
            $this->assertFileExists(
                $path,
                "Script not found for task '{$task['name']}': {$task['script']}"
            );
        }
    }

    #[Test]
    public function every_script_has_php_opening_tag(): void
    {
        $scriptsDir = __DIR__ . '/../../src/cron/tasks';

        foreach ($this->tasks as $task) {
            $path = $scriptsDir . '/' . $task['script'];
            $contents = file_get_contents($path);
            $this->assertStringContainsString(
                '<?php',
                $contents,
                "Script '{$task['script']}' does not contain a PHP opening tag"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Schedule Validation
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function every_schedule_is_valid_cron_expression(): void
    {
        foreach ($this->tasks as $task) {
            $result = cron_validate($task['schedule']);
            $this->assertTrue(
                $result['valid'],
                "Invalid schedule for task '{$task['name']}': {$task['schedule']} — " . ($result['error'] ?? '')
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Timeout
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function every_timeout_is_positive_integer(): void
    {
        foreach ($this->tasks as $task) {
            $this->assertIsInt(
                $task['timeout'],
                "Timeout for task '{$task['name']}' must be an integer"
            );
            $this->assertGreaterThan(
                0,
                $task['timeout'],
                "Timeout for task '{$task['name']}' must be positive"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Expected Task Coverage
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function contains_all_expected_tasks(): void
    {
        $expectedNames = [
            'process_email_queue',
            'process_webhook_queue',
            'cleanup_orphaned_uploads',
            'cleanup_expired_tokens',
            'cleanup_expired_rate_limits',
            'cleanup_abandoned_program_drafts',
            'cleanup_old_resend_logs',
            'purge_completed_jobs',
            'purge_old_audit_logs',
            'purge_old_notifications',
            'purge_old_webhook_logs',
            'purge_old_report_cache',
            'warm_report_cache',
            'send_deadline_reminders',
            'send_draft_reminders',
            'send_expiry_warnings',
        ];

        $actualNames = array_column($this->tasks, 'name');

        foreach ($expectedNames as $expected) {
            $this->assertContains(
                $expected,
                $actualNames,
                "Expected task '{$expected}' not found in cron config"
            );
        }
    }

    #[Test]
    public function total_task_count_matches_expected(): void
    {
        $this->assertCount(16, $this->tasks, 'Expected 16 cron tasks in config');
    }
}
