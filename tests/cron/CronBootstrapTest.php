<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that all cron tasks use the CLI-safe cron_bootstrap.php
 * and that the bootstrap properly loads required dependencies.
 */
final class CronBootstrapTest extends TestCase
{
    private const TASKS_DIR = __DIR__ . '/../../src/cron/tasks';
    private const CORE_DIR  = __DIR__ . '/../../src/core';

    // ═══════════════════════════════════════════════════════════════════
    // cron_bootstrap.php Validation
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function cron_bootstrap_file_exists(): void
    {
        $this->assertFileExists(
            self::CORE_DIR . '/cron_bootstrap.php',
            'cron_bootstrap.php must exist in core/'
        );
    }

    #[Test]
    public function cron_bootstrap_loads_db(): void
    {
        $contents = file_get_contents(self::CORE_DIR . '/cron_bootstrap.php');
        $this->assertStringContainsString(
            "require_once __DIR__ . '/db.php'",
            $contents,
            'cron_bootstrap.php must load db.php'
        );
    }

    #[Test]
    public function cron_bootstrap_loads_helpers(): void
    {
        $contents = file_get_contents(self::CORE_DIR . '/cron_bootstrap.php');
        $this->assertStringContainsString(
            "require_once __DIR__ . '/helpers.php'",
            $contents,
            'cron_bootstrap.php must load helpers.php'
        );
    }

    #[Test]
    public function cron_bootstrap_loads_config(): void
    {
        $contents = file_get_contents(self::CORE_DIR . '/cron_bootstrap.php');
        $this->assertStringContainsString(
            "require_once __DIR__ . '/env.php'",
            $contents,
            'cron_bootstrap.php must load environment helpers for config resolution'
        );
        $this->assertStringContainsString(
            '$config = load_config()',
            $contents,
            'cron_bootstrap.php must load config via load_config()'
        );
    }

    #[Test]
    public function cron_bootstrap_sets_globals_config(): void
    {
        $contents = file_get_contents(self::CORE_DIR . '/cron_bootstrap.php');
        $this->assertStringContainsString(
            "\$GLOBALS['config']",
            $contents,
            'cron_bootstrap.php must set $GLOBALS[\'config\'] for db() fallback'
        );
    }

    #[Test]
    public function cron_bootstrap_sets_utc_timezone(): void
    {
        $contents = file_get_contents(self::CORE_DIR . '/cron_bootstrap.php');
        $this->assertStringContainsString(
            "date_default_timezone_set('UTC')",
            $contents,
            'cron_bootstrap.php must set UTC timezone'
        );
    }

    #[Test]
    public function cron_bootstrap_prevents_web_execution(): void
    {
        $contents = file_get_contents(self::CORE_DIR . '/cron_bootstrap.php');
        $this->assertStringContainsString(
            'php_sapi_name()',
            $contents,
            'cron_bootstrap.php must check SAPI to prevent web execution'
        );
    }

    #[Test]
    public function cron_bootstrap_does_not_set_http_headers(): void
    {
        $contents = file_get_contents(self::CORE_DIR . '/cron_bootstrap.php');
        // Should NOT call header() — that's for the web bootstrap
        $this->assertStringNotContainsString(
            "header('X-Content-Type-Options",
            $contents,
            'cron_bootstrap.php must NOT set HTTP headers'
        );
    }

    #[Test]
    public function cron_bootstrap_does_not_start_session(): void
    {
        $contents = file_get_contents(self::CORE_DIR . '/cron_bootstrap.php');
        $this->assertStringNotContainsString(
            'session_start()',
            $contents,
            'cron_bootstrap.php must NOT start a session'
        );
    }

    #[Test]
    public function cron_bootstrap_does_not_load_i18n(): void
    {
        $contents = file_get_contents(self::CORE_DIR . '/cron_bootstrap.php');
        $this->assertStringNotContainsString(
            'i18n_init()',
            $contents,
            'cron_bootstrap.php must NOT initialize i18n'
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // Task Bootstrap Validation
    // ═══════════════════════════════════════════════════════════════════

    /**
     * @return list<array{0: string}>
     */
    public static function taskFileProvider(): array
    {
        $config = require __DIR__ . '/../../src/cron/config.php';
        $tasks = [];
        foreach ($config as $task) {
            $tasks[$task['name']] = [$task['script']];
        }
        return $tasks;
    }

    #[Test]
    #[DataProvider('taskFileProvider')]
    public function every_task_uses_cron_bootstrap(string $script): void
    {
        $path = self::TASKS_DIR . '/' . $script;
        $contents = file_get_contents($path);

        $this->assertStringContainsString(
            'cron_bootstrap.php',
            $contents,
            "Task '{$script}' must include cron_bootstrap.php"
        );
    }

    #[Test]
    #[DataProvider('taskFileProvider')]
    public function no_task_uses_web_bootstrap_directly(string $script): void
    {
        $path = self::TASKS_DIR . '/' . $script;
        $contents = file_get_contents($path);

        // Count occurrences of bootstrap.php — should only appear as part of "cron_bootstrap.php"
        $allBootstrapMatches = preg_match_all('/require(?:_once)?\s+__DIR__\s*\.\s*[\'"][^\']*\/bootstrap\.php[\'"]/', $contents, $matches);
        $cronBootstrapMatches = preg_match_all('/cron_bootstrap\.php/', $contents, $cronMatches);

        // Every bootstrap.php reference should be cron_bootstrap.php
        // The only exception is process_email_queue.php which may load vendor/autoload.php
        // (which internally loads bootstrap.php, but that's not a direct require)
        $directWebBootstrap = preg_match('/require(?:_once)?\s+__DIR__\s*\.\s*[\'"][^\']*\/core\/bootstrap\.php[\'"]/', $contents);

        $this->assertEquals(
            0,
            $directWebBootstrap,
            "Task '{$script}' must NOT directly include the web bootstrap.php — use cron_bootstrap.php instead"
        );
    }

    #[Test]
    #[DataProvider('taskFileProvider')]
    public function no_task_has_redundant_db_include(string $script): void
    {
        $path = self::TASKS_DIR . '/' . $script;
        $contents = file_get_contents($path);

        // Tasks should not explicitly include db.php — cron_bootstrap handles it
        $this->assertDoesNotMatchRegularExpression(
            '/require(?:_once)?\s+__DIR__\s*\.\s*[\'"][^\']*\/core\/db\.php[\'"]/',
            $contents,
            "Task '{$script}' should not include db.php — cron_bootstrap.php already loads it"
        );
    }

    #[Test]
    #[DataProvider('taskFileProvider')]
    public function no_task_has_redundant_helpers_include(string $script): void
    {
        $path = self::TASKS_DIR . '/' . $script;
        $contents = file_get_contents($path);

        // Tasks should not explicitly include helpers.php — cron_bootstrap handles it
        $this->assertDoesNotMatchRegularExpression(
            '/require(?:_once)?\s+__DIR__\s*\.\s*[\'"][^\']*\/core\/helpers\.php[\'"]/',
            $contents,
            "Task '{$script}' should not include helpers.php — cron_bootstrap.php already loads it"
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // Composer Platform Config
    // ═══════════════════════════════════════════════════════════════════

    #[Test]
    public function composer_json_has_platform_php_constraint(): void
    {
        $composerPath = __DIR__ . '/../../composer.json';
        $this->assertFileExists($composerPath);

        $composer = json_decode(file_get_contents($composerPath), true);
        $this->assertNotNull($composer, 'composer.json must be valid JSON');

        $this->assertArrayHasKey('config', $composer, 'composer.json must have config section');
        $this->assertArrayHasKey('platform', $composer['config'], 'composer.json config must have platform section');
        $this->assertArrayHasKey('php', $composer['config']['platform'], 'composer.json platform must specify php version');

        // Version must be 8.2+ (minimum supported)
        $this->assertStringStartsWith(
            '8.',
            $composer['config']['platform']['php'],
            'Platform PHP version must target PHP 8.x'
        );
    }
}
