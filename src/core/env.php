<?php

/**
 * Environment Configuration Loader
 * 
 * Loads configuration from .env file and returns the application config array.
 * This replaces the previous pattern of requiring app/src/config/app.php directly.
 */

/**
 * Load and parse .env file
 * 
 * @return array<string, string>
 */
function load_env(string $path): array {
    if (!file_exists($path)) {
        return [];
    }
    
    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            $env[$key] = $value;
        }
    }
    
    return $env;
}

/**
 * Get environment variable with fallback
 */
function env(string $key, $default = null) {
    static $env = null;
    
    if ($env === null) {
        $env_path = __DIR__ . '/../../.env';
        $env = load_env($env_path);
    }
    
    return $env[$key] ?? $default;
}

/**
 * Parse comma-separated environment variable to array
 * 
 * @return array<string>
 */
function env_array(string $key, array $default = []): array {
    $value = env($key, '');
    
    if ($value === '') {
        return $default;
    }
    
    return array_filter(array_map('trim', explode(',', $value)));
}

/**
 * Convert string boolean to actual boolean
 */
function env_bool(string $key, bool $default = false): bool {
    $value = env($key);
    
    if ($value === null) {
        return $default;
    }
    
    return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
}

/**
 * Get integer from environment
 */
function env_int(string $key, int $default = 0): int {
    $value = env($key);
    
    if ($value === null || $value === '') {
        return $default;
    }
    
    return (int) $value;
}

/**
 * Load application configuration from environment
 * 
 * @return array<string, mixed>
 */
function load_config(): array {
    return [
        'app' => [
            'name' => env('APP_NAME', 'FORMNA'),
            'base_url' => env('APP_URL', 'http://localhost:8888'),
            'environment' => env('APP_ENV', 'production'),
            'timezone' => env('APP_TIMEZONE', 'UTC'),
            'trusted_proxy_ips' => env_array('APP_TRUSTED_PROXIES'),
        ],
        
        'db' => [
            'host' => env('DB_HOST', 'localhost'),
            'port' => env_int('DB_PORT', 3306),
            'name' => env('DB_NAME', 'formna'),
            'user' => env('DB_USER', ''),
            'password' => env('DB_PASSWORD', ''),
        ],
        
        'encryption' => [
            'key' => env('APP_KEY', 'change-me-to-exactly-32-char-key'),
            'cipher' => 'aes-256-cbc',
        ],
        
        'session' => [
            'name' => env('SESSION_NAME', 'formna_session'),
            'lifetime' => env_int('SESSION_LIFETIME', 7200),
            'secure' => env_bool('SESSION_SECURE', false),
            'httponly' => env_bool('SESSION_HTTPONLY', true),
            'samesite' => env('SESSION_SAMESITE', 'Lax'),
        ],
        
        'mail' => [
            'smtp' => env_bool('MAIL_SMTP', true),
            'host' => env('MAIL_HOST', 'smtp.example.com'),
            'port' => env_int('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME', ''),
            'password' => env('MAIL_PASSWORD', ''),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
            'from_name' => env('MAIL_FROM_NAME', 'FORMNA'),
            'reply_to' => env('MAIL_REPLY_TO', 'no-reply@example.com'),
        ],
        
        'queue' => [
            'batch_size' => env_int('QUEUE_BATCH_SIZE', 20),
            'max_runtime' => env_int('QUEUE_MAX_RUNTIME', 300),
            'retry_delays' => [300, 1800, 7200],
        ],
        
        'storage' => [
            'local_path' => __DIR__ . '/../../storage/uploads',
        ],
        
        'branding' => [
            'upload_dir' => dirname(__DIR__, 2) . '/public/uploads/branding',
            'web_path' => '/uploads/branding',
        ],
        
        'reports' => [
            'cache_ttl_hours' => env_int('REPORTS_CACHE_TTL_HOURS', 6),
        ],
    ];
}
