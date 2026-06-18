<?php

/**
 * PHPUnit Test Bootstrap
 * 
 * Loads Composer autoloader and core app files needed for testing.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/support/DatabaseTestCase.php';

// Load environment and config
require_once __DIR__ . '/../src/core/env.php';

// Load i18n functions (required by helpers)
require_once __DIR__ . '/../src/core/i18n.php';

// Ensure a predictable session array exists for tests that rely on auth helpers.
$_SESSION ??= [];

// Provide a safe default PDO double for legacy tests that may indirectly hit
// db_* helpers through i18n/branding/auth-related fallbacks.
$GLOBALS['_test_pdo'] ??= new TestPdo();

// Disable auth.php's automatic session-version DB check in PHPUnit processes.
$GLOBALS['FORMNA_DISABLE_SESSION_VERSION_CHECK'] = true;

// Stub the i18n init to avoid database dependencies in tests
if (!function_exists('i18n_init')) {
    function i18n_init(): void {}
}

// Load core helper functions
require_once __DIR__ . '/../src/core/helpers.php';
require_once __DIR__ . '/../src/core/encryption.php';
require_once __DIR__ . '/../src/core/db.php';
require_once __DIR__ . '/../src/core/auth.php';

// Load cron expression functions (for cron tests)
if (file_exists(__DIR__ . '/../src/cron/expression.php')) {
    require_once __DIR__ . '/../src/cron/expression.php';
}
