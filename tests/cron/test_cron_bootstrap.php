#!/usr/bin/env php
<?php

/**
 * Standalone Cron Bootstrap Validation Tests
 *
 * Validates that all cron tasks use the CLI-safe cron_bootstrap.php,
 * that the bootstrap correctly loads required dependencies, and that
 * no task directly includes the web-only bootstrap.php.
 *
 * Run: php tests/cron/test_cron_bootstrap.php
 */

$projectRoot = realpath(__DIR__ . '/../../');
$coreDir     = $projectRoot . '/src/core';
$tasksDir    = $projectRoot . '/src/cron/tasks';
$configFile  = $projectRoot . '/src/cron/config.php';
$composerFile = $projectRoot . '/composer.json';

$passed = 0;
$failed = 0;
$errors = [];

function assert_test(string $name, bool $condition, string $failMessage, int &$passed, int &$failed, array &$errors): void {
    if ($condition) {
        $passed++;
        echo "  ✓ {$name}\n";
    } else {
        $failed++;
        $errors[] = "{$name}: {$failMessage}";
        echo "  ✗ {$name}\n    → {$failMessage}\n";
    }
}

// ═══════════════════════════════════════════════════════════════════════
echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║        Cron Bootstrap Validation Tests                      ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// ── 1. cron_bootstrap.php Structure ─────────────────────────────────
echo "1. cron_bootstrap.php Structure\n";
echo "   ─────────────────────────────\n";

$cronBootstrapPath = $coreDir . '/cron_bootstrap.php';
$bootstrapExists = file_exists($cronBootstrapPath);
assert_test('File exists', $bootstrapExists, 'cron_bootstrap.php not found', $passed, $failed, $errors);

if ($bootstrapExists) {
    $contents = file_get_contents($cronBootstrapPath);

    assert_test(
        'Loads config',
        str_contains($contents, "require __DIR__ . '/../config/app.php'"),
        'Must load app config',
        $passed, $failed, $errors
    );

    assert_test(
        'Sets $GLOBALS[\'config\']',
        str_contains($contents, "\$GLOBALS['config']"),
        'Must set $GLOBALS[\'config\'] for db() fallback',
        $passed, $failed, $errors
    );

    assert_test(
        'Loads db.php',
        str_contains($contents, "require_once __DIR__ . '/db.php'"),
        'Must load db.php for database functions',
        $passed, $failed, $errors
    );

    assert_test(
        'Loads helpers.php',
        str_contains($contents, "require_once __DIR__ . '/helpers.php'"),
        'Must load helpers.php for utility functions',
        $passed, $failed, $errors
    );

    assert_test(
        'Sets UTC timezone',
        str_contains($contents, "date_default_timezone_set('UTC')"),
        'Must set UTC timezone',
        $passed, $failed, $errors
    );

    assert_test(
        'Prevents web execution',
        str_contains($contents, 'php_sapi_name()'),
        'Must check SAPI to prevent web execution',
        $passed, $failed, $errors
    );

    assert_test(
        'No HTTP headers',
        !str_contains($contents, "header('X-Content-Type-Options"),
        'Must NOT set HTTP security headers',
        $passed, $failed, $errors
    );

    assert_test(
        'No session_start()',
        !str_contains($contents, 'session_start()'),
        'Must NOT start a session',
        $passed, $failed, $errors
    );

    assert_test(
        'No CSRF init',
        !str_contains($contents, '_csrf_token'),
        'Must NOT initialize CSRF tokens',
        $passed, $failed, $errors
    );

    assert_test(
        'No i18n_init()',
        !str_contains($contents, 'i18n_init()'),
        'Must NOT initialize i18n',
        $passed, $failed, $errors
    );

    assert_test(
        'Has error handling',
        str_contains($contents, 'error_reporting'),
        'Must set up error handling',
        $passed, $failed, $errors
    );
}

// ── 2. All Tasks Use cron_bootstrap.php ──────────────────────────────
echo "\n2. Task Bootstrap Includes\n";
echo "   ────────────────────────\n";

$config = require $configFile;
$taskCount = count($config);

assert_test(
    "Config has {$taskCount} tasks",
    $taskCount === 16,
    "Expected 16 tasks, found {$taskCount}",
    $passed, $failed, $errors
);

foreach ($config as $task) {
    $name   = $task['name'];
    $script = $task['script'];
    $path   = $tasksDir . '/' . $script;

    if (!file_exists($path)) {
        assert_test("{$name}: file exists", false, "Script {$script} not found", $passed, $failed, $errors);
        continue;
    }

    $taskContents = file_get_contents($path);

    // Must include cron_bootstrap.php
    assert_test(
        "{$name}: uses cron_bootstrap.php",
        str_contains($taskContents, 'cron_bootstrap.php'),
        "Must include cron_bootstrap.php",
        $passed, $failed, $errors
    );

    // Must NOT directly include the web bootstrap.php
    $hasWebBootstrap = (bool) preg_match(
        '/require(?:_once)?\s+__DIR__\s*\.\s*[\'"][^\']*\/core\/bootstrap\.php[\'"]/',
        $taskContents
    );
    assert_test(
        "{$name}: no web bootstrap.php",
        !$hasWebBootstrap,
        "Must NOT directly include core/bootstrap.php",
        $passed, $failed, $errors
    );

    // Must NOT redundantly include db.php
    $hasRedundantDb = (bool) preg_match(
        '/require(?:_once)?\s+__DIR__\s*\.\s*[\'"][^\']*\/core\/db\.php[\'"]/',
        $taskContents
    );
    assert_test(
        "{$name}: no redundant db.php",
        !$hasRedundantDb,
        "Redundant db.php include — cron_bootstrap already loads it",
        $passed, $failed, $errors
    );

    // Must NOT redundantly include helpers.php
    $hasRedundantHelpers = (bool) preg_match(
        '/require(?:_once)?\s+__DIR__\s*\.\s*[\'"][^\']*\/core\/helpers\.php[\'"]/',
        $taskContents
    );
    assert_test(
        "{$name}: no redundant helpers.php",
        !$hasRedundantHelpers,
        "Redundant helpers.php include — cron_bootstrap already loads it",
        $passed, $failed, $errors
    );
}

// ── 3. PHP Syntax Check ──────────────────────────────────────────────
echo "\n3. PHP Syntax Validation\n";
echo "   ──────────────────────\n";

// Check cron_bootstrap.php syntax
$output = [];
$exitCode = 0;
exec('php -l ' . escapeshellarg($cronBootstrapPath) . ' 2>&1', $output, $exitCode);
assert_test(
    'cron_bootstrap.php syntax valid',
    $exitCode === 0,
    implode("\n", $output),
    $passed, $failed, $errors
);

// Check all task files syntax
foreach ($config as $task) {
    $path = $tasksDir . '/' . $task['script'];
    if (!file_exists($path)) continue;

    $output = [];
    $exitCode = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
    assert_test(
        "{$task['name']}: syntax valid",
        $exitCode === 0,
        implode("\n", $output),
        $passed, $failed, $errors
    );
}

// ── 4. Composer Platform Config ──────────────────────────────────────
echo "\n4. Composer Configuration\n";
echo "   ────────────────────────\n";

$composerExists = file_exists($composerFile);
assert_test('composer.json exists', $composerExists, 'composer.json not found', $passed, $failed, $errors);

if ($composerExists) {
    $composer = json_decode(file_get_contents($composerFile), true);

    assert_test(
        'Valid JSON',
        $composer !== null,
        'composer.json is not valid JSON',
        $passed, $failed, $errors
    );

    if ($composer) {
        $hasConfig = isset($composer['config']);
        assert_test(
            'Has config section',
            $hasConfig,
            'Missing config section',
            $passed, $failed, $errors
        );

        $hasPlatform = isset($composer['config']['platform']);
        assert_test(
            'Has platform config',
            $hasPlatform,
            'Missing config.platform section',
            $passed, $failed, $errors
        );

        $hasPHP = isset($composer['config']['platform']['php']);
        assert_test(
            'Has platform PHP version',
            $hasPHP,
            'Missing config.platform.php',
            $passed, $failed, $errors
        );

        if ($hasPHP) {
            $phpVersion = $composer['config']['platform']['php'];
            assert_test(
                "Platform PHP targets 8.4 (found: {$phpVersion})",
                str_starts_with($phpVersion, '8.4'),
                'Platform PHP must target 8.4.x to match server',
                $passed, $failed, $errors
            );
        }

        // Verify main PHP requirement is ^8.4
        $requirePHP = $composer['require']['php'] ?? null;
        assert_test(
            "PHP requirement is ^8.4 (found: {$requirePHP})",
            $requirePHP === '^8.4',
            'Required PHP version should be ^8.4',
            $passed, $failed, $errors
        );
    }
}

// ── 5. db.php Function Definitions ──────────────────────────────────
echo "\n5. Database Functions Available\n";
echo "   ─────────────────────────────\n";

$dbContents = file_get_contents($coreDir . '/db.php');
$requiredFunctions = ['db', 'db_query', 'db_one', 'db_exec', 'db_insert', 'db_update', 'db_transaction'];

foreach ($requiredFunctions as $fn) {
    assert_test(
        "db.php defines {$fn}()",
        str_contains($dbContents, "function {$fn}("),
        "Function {$fn}() not found in db.php",
        $passed, $failed, $errors
    );
}

// ═══════════════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════════════

$total = $passed + $failed;
echo "\n══════════════════════════════════════════════════════════════\n";
echo "Results: {$passed}/{$total} passed";

if ($failed > 0) {
    echo ", {$failed} FAILED\n\n";
    echo "Failures:\n";
    foreach ($errors as $i => $error) {
        echo "  " . ($i + 1) . ". {$error}\n";
    }
    echo "\n";
    exit(1);
} else {
    echo " — ALL PASSED ✓\n\n";
    exit(0);
}
