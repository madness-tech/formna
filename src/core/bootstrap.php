<?php

// Security headers (defense-in-depth for all dynamic responses)
ini_set('expose_php', '0');
header_remove('X-Powered-By');
header_remove('Server');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Load configuration from environment
require_once __DIR__ . '/env.php';
$config = load_config();

if (($config['app']['environment'] ?? 'production') !== 'development') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// PHP's internal timezone must always be UTC so that strtotime(), date(),
// and similar functions interpret DB-stored datetimes correctly.
// User-facing display converts to local timezones via format_datetime() / to_datetime_local().
date_default_timezone_set('UTC');

// Error handling
if ($config['app']['environment'] === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        error_log("Error [$errno]: $errstr in $errfile on line $errline");
        return true;
    });
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', $config['session']['httponly'] ? '1' : '0');
    ini_set('session.cookie_secure', $config['session']['secure'] ? '1' : '0');
    ini_set('session.cookie_samesite', $config['session']['samesite']);
    ini_set('session.gc_maxlifetime', (string)$config['session']['lifetime']);
    session_name($config['session']['name']);
    session_start();
}

// CSRF token initialization
if (!isset($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

// Create storage directory if it doesn't exist
if (!file_exists($config['storage']['local_path'])) {
    mkdir($config['storage']['local_path'], 0755, true);
}

// Initialise internationalisation (locale detection, translations)
// Skipped in CLI — cron tasks use cron_bootstrap.php and don't need locale detection
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/i18n.php';
    i18n_init();
}
