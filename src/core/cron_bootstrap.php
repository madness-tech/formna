<?php

/**
 * Cron Task Bootstrap
 *
 * Lightweight bootstrap for CLI/cron context.
 * Loads config, database, and helper functions without
 * HTTP headers, sessions, or CSRF protection.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

// Load Composer autoloader (PHPMailer, Parsedown, etc.)
require_once __DIR__ . '/../../vendor/autoload.php';

// Load configuration from environment
require_once __DIR__ . '/env.php';
$config = load_config();
$GLOBALS['config'] = $config;

// UTC timezone for consistent datetime handling
date_default_timezone_set('UTC');

// Error handling
if (($config['app']['environment'] ?? 'production') === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        error_log("Error [$errno]: $errstr in $errfile on line $errline");
        return true;
    });
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
