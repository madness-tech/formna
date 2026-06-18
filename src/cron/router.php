#!/usr/bin/env php
<?php

/**
 * Cron Router — Single Entry Point
 *
 * This is the only script that needs a crontab entry:
 *   * * * * * cd /path/to/formna && php src/cron/router.php >> storage/logs/cron.log 2>&1
 *
 * It reads the schedule from config.php, determines which tasks are
 * due for the current minute, and spawns each one as a background process.
 * Individual tasks handle their own locking to prevent overlapping runs.
 */

// Prevent web execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/expression.php';

define('CRON_ROUTER_START', microtime(true));
define('CRON_DIR', __DIR__);
define('TASKS_DIR', __DIR__ . '/tasks');
define('RUNTIME_DIR', __DIR__ . '/../../storage');
define('STATE_FILE', RUNTIME_DIR . '/cron_state.json');
define('LOG_FILE', RUNTIME_DIR . '/logs/cron.log');

// Ensure runtime directories exist
if (!is_dir(RUNTIME_DIR . '/logs')) {
    mkdir(RUNTIME_DIR . '/logs', 0755, true);
}

// ── Router lock (prevent overlapping router instances) ────────────────
$routerLock = fopen(RUNTIME_DIR . '/cron_router.lock', 'c');
if (!$routerLock || !flock($routerLock, LOCK_EX | LOCK_NB)) {
    exit(0); // Another router instance is running
}

// ── Load configuration ───────────────────────────────────────────────
$configFile = CRON_DIR . '/config.php';
if (!file_exists($configFile)) {
    cron_log('ERROR', 'Config file not found: ' . $configFile);
    exit(1);
}

$tasks = require $configFile;
if (!is_array($tasks) || empty($tasks)) {
    cron_log('ERROR', 'Invalid or empty cron config');
    exit(1);
}

// ── Load state ───────────────────────────────────────────────────────
$state = load_state();

// Prune state entries for tasks no longer in config
$active_task_names = array_column(array_filter($tasks, fn($t) => !empty($t['name'])), 'name');
foreach (array_keys($state) as $state_key) {
    if (!in_array($state_key, $active_task_names, true)) {
        unset($state[$state_key]);
    }
}

// ── Determine current time (floored to the current minute) ───────────
$now = new DateTimeImmutable('now');
$currentMinute = (int) $now->format('i');
$currentHour   = (int) $now->format('G');
$currentDom    = (int) $now->format('j');
$currentMonth  = (int) $now->format('n');
$currentDow    = (int) $now->format('w'); // 0=Sunday

$dispatched = 0;
$skipped    = 0;

foreach ($tasks as $task) {
    $name = $task['name'] ?? 'unknown';

    // Skip disabled tasks
    if (empty($task['enabled'])) {
        $skipped++;
        continue;
    }

    // Validate script exists
    $scriptPath = TASKS_DIR . '/' . $task['script'];
    if (!file_exists($scriptPath)) {
        cron_log('WARN', "Script not found for task '{$name}': {$task['script']}");
        $skipped++;
        continue;
    }

    // Check schedule
    $schedule = $task['schedule'] ?? '';
    if (!cron_matches($schedule, $currentMinute, $currentHour, $currentDom, $currentMonth, $currentDow)) {
        continue;
    }

    // Prevent duplicate dispatch within the same minute
    $lastRun = $state[$name]['last_dispatched'] ?? null;
    if ($lastRun !== null) {
        $lastRunTime = new DateTimeImmutable($lastRun);
        if ($lastRunTime->format('Y-m-d H:i') === $now->format('Y-m-d H:i')) {
            continue; // Already dispatched this minute
        }
    }

    // Dispatch task as background process
    $logPath = RUNTIME_DIR . '/logs/' . str_replace('.php', '', $task['script']) . '.log';
    $logDir  = dirname($logPath);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Enforce task timeout using Unix 'timeout' command if available.
    // This prevents hung tasks from running indefinitely on DB locks or network issues.
    // Falls back to unlimited execution if timeout=0 or command unavailable.
    $timeoutSeconds = (int)($task['timeout'] ?? 0);
    $hasTimeout = (trim((string)shell_exec('which timeout 2>/dev/null')) !== '');

    if ($timeoutSeconds > 0 && $hasTimeout) {
        $cmd = sprintf(
            'timeout %d %s %s >> %s 2>&1 &',
            $timeoutSeconds,
            PHP_BINARY,
            escapeshellarg($scriptPath),
            escapeshellarg($logPath)
        );
    } else {
        $cmd = sprintf(
            '%s %s >> %s 2>&1 &',
            PHP_BINARY,
            escapeshellarg($scriptPath),
            escapeshellarg($logPath)
        );
    }

    exec($cmd);
    $dispatched++;

    // Update state
    $state[$name] = [
        'last_dispatched' => $now->format('Y-m-d H:i:s'),
        'script'          => $task['script'],
    ];

    cron_log('INFO', "Dispatched: {$name}");
}

// ── Save state ───────────────────────────────────────────────────────
save_state($state);

// ── Release router lock ──────────────────────────────────────────────
flock($routerLock, LOCK_UN);
fclose($routerLock);

$elapsed = round((microtime(true) - CRON_ROUTER_START) * 1000, 1);

if ($dispatched > 0) {
    cron_log('INFO', "Router finished: dispatched={$dispatched} skipped={$skipped} elapsed={$elapsed}ms");
}

exit(0);

// ═════════════════════════════════════════════════════════════════════
// Helper Functions (cron_matches & cron_field_matches loaded from expression.php)
// ═════════════════════════════════════════════════════════════════════

/**
 * Load state from JSON file.
 *
 * @return array<string, array{last_dispatched: string, script: string}>
 */
function load_state(): array
{
    if (!file_exists(STATE_FILE)) {
        return [];
    }

    $json = file_get_contents(STATE_FILE);
    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

/**
 * Save state to JSON file atomically.
 *
 * @param array<string, mixed> $state
 */
function save_state(array $state): void
{
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $tmp  = STATE_FILE . '.tmp';

    if (file_put_contents($tmp, $json, LOCK_EX) !== false) {
        rename($tmp, STATE_FILE);
    }
}

/**
 * Write a timestamped log line to the cron log file.
 */
function cron_log(string $level, string $message): void
{
    $line = sprintf(
        "[%s] [%s] %s\n",
        date('Y-m-d H:i:s'),
        $level,
        $message
    );

    // Write to log file
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);

    // Also echo for crontab output redirection
    echo $line;
}
