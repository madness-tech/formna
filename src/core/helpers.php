<?php

/**
 * Redirect to URL and exit
 */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/**
 * Set flash message in session
 */
function flash(string $type, string $message): void {
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 *
 * @return array{type:string,message:string}|null
 */
function get_flash(): ?array {
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $flash;
}

/**
 * Validate password strength.
 *
 * Enforces minimum complexity: 8+ chars, at least one uppercase,
 * one lowercase, and one digit. Returns a translated error message
 * on failure, or null when the password is acceptable.
 */
function validate_password_strength(string $password): ?string {
    if (strlen($password) < 8) {
        return t('validation.password_too_short');
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return t('validation.password_needs_uppercase');
    }
    if (!preg_match('/[a-z]/', $password)) {
        return t('validation.password_needs_lowercase');
    }
    if (!preg_match('/[0-9]/', $password)) {
        return t('validation.password_needs_digit');
    }
    return null;
}

/**
 * Validate a person's name.
 *
 * Allowed characters: letters, spaces, hyphens, apostrophes, and periods.
 */
function is_valid_person_name(string $name): bool {
    $name = trim($name);

    if ($name === '') {
        return false;
    }

    if (preg_match('/^[\p{L} .\'-]+$/u', $name) !== 1) {
        return false;
    }

    return preg_match('/\p{L}/u', $name) === 1;
}

/**
 * Sanitize input (recursive)
 */
function sanitize(mixed $input): mixed {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return is_string($input) ? htmlspecialchars($input, ENT_QUOTES, 'UTF-8') : $input;
}

/**
 * Return JSON response and exit
 */
function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get or generate CSRF token
 */
function csrf_token(): string {
    return $_SESSION['_csrf_token'] ?? '';
}

/**
 * Generate CSRF hidden input field
 */
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

/**
 * Validate CSRF token
 *
 * Supports both traditional form submissions and JSON API requests:
 * - Form submissions: Checks $_POST['_csrf'] field
 * - JSON/AJAX requests: Checks X-CSRF-Token HTTP header
 *
 * This dual-mode approach ensures CSRF protection for both
 * traditional form POSTs and modern JSON API endpoints.
 *
 * @throws void Dies with 403 if token is invalid
 */
function csrf_check(): void {
    // Check POST field first (for forms), then HTTP header (for JSON APIs)
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
}

/**
 * Paginate query results
 *
 * @param array<string|int, mixed> $params
 * @return array{rows: list<array<string,mixed>>, total:int, pages:int, page:int, per_page:int}
 */
function paginate(string $sql, array $params, int $page, int $per_page): array {
    // Validate per_page to prevent division by zero
    if ($per_page <= 0) {
        $per_page = 10;
    }
    
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM ($sql) AS count_query";
    $count_stmt = db()->prepare($count_sql);
    $count_stmt->execute($params);
    $total = (int) $count_stmt->fetchColumn();
    
    // Calculate pagination
    $pages = (int) ceil($total / $per_page);
    $page = max(1, min($page, $pages ?: 1));
    $offset = ($page - 1) * $per_page;
    
    // Append LIMIT/OFFSET directly (safe since they're cast to integers)
    $paginated_sql = "$sql LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
    $stmt = db()->prepare($paginated_sql);
    $stmt->execute($params);
    
    return [
        'rows' => $stmt->fetchAll(),
        'total' => $total,
        'pages' => $pages,
        'page' => $page,
        'per_page' => $per_page,
    ];
}

/**
 * Paginate a PHP array (for results that require post-query filtering).
 * Returns the same structure as paginate() for consistency with the pagination partial.
 *
 * @param list<mixed> $items  All items (already filtered/sorted)
 * @param int         $page
 * @param int         $per_page
 * @return array{rows: list<mixed>, total: int, pages: int, page: int, per_page: int}
 */
function paginate_array(array $items, int $page, int $per_page): array {
    if ($per_page <= 0) {
        $per_page = 10;
    }
    $total = count($items);
    $pages = max(1, (int)ceil($total / $per_page));
    $page  = max(1, min($page, $pages));
    $offset = ($page - 1) * $per_page;

    return [
        'rows'     => array_slice($items, $offset, $per_page),
        'total'    => $total,
        'pages'    => $pages,
        'page'     => $page,
        'per_page' => $per_page,
    ];
}

/**
 * Generate UUID v4
 */
function uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Get base URL with path
 */
function base_url(string $path = ''): string {
    global $config;

    $base = $config['app']['base_url'] ?? '';

    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Get full URL with scheme and domain (for emails and external links)
 * Alias for base_url() to be explicit about absolute URLs
 */
function full_url(string $path = ''): string {
    return base_url($path);
}

/**
 * Render view with layout
 *
 * @param array<string, mixed> $data
 */
function view(string $view, array $data = [], string $layout = 'user'): void {
    extract($data);
    
    ob_start();
    require __DIR__ . "/../modules/$view";
    $content = ob_get_clean();
    
    require __DIR__ . "/../layouts/$layout.php";
}

/**
 * DATETIME UTILITIES
 * All datetime functions use UTC for consistency
 */

/**
 * Get current datetime in database storage format (UTC)
 * @return string Format: 'Y-m-d H:i:s'
 */
function now(): string {
    return gmdate('Y-m-d H:i:s');
}

/**
 * Convert any datetime string to database storage format (UTC)
 * @param string|int|null $datetime Datetime string, timestamp, or null
 * @return string|null Formatted datetime or null
 */
function to_db_datetime(string|int|null $datetime): ?string {
    if ($datetime === null || $datetime === '') {
        return null;
    }
    
    if (is_numeric($datetime)) {
        return gmdate('Y-m-d H:i:s', (int)$datetime);
    }
    
    // Handle HTML5 datetime-local format (YYYY-MM-DDTHH:MM or YYYY-MM-DDTHH:MM:SS)
    // datetime-local inputs carry no timezone info, so we interpret the value in the
    // user's timezone and then convert to UTC for storage. This pairs with
    // to_datetime_local() which does the reverse (UTC → user timezone for display).
    try {
        $user_tz = new DateTimeZone(get_user_timezone());
        $dt = new DateTimeImmutable($datetime, $user_tz);
        $dt = $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Convert datetime string to timestamp
 * @param string|null $datetime Datetime string
 * @return int|null Unix timestamp or null
 */
function to_timestamp(?string $datetime): ?int {
    if ($datetime === null || $datetime === '') {
        return null;
    }
    
    $timestamp = strtotime($datetime);
    return $timestamp !== false ? $timestamp : null;
}

/**
 * Get user's timezone from session, fallback to app config timezone
 * @return string Timezone identifier
 */
function get_user_timezone(): string {
    $user = current_user();
    if (!empty($user['timezone'])) {
        return $user['timezone'];
    }
    global $config;
    return $config['app']['timezone'] ?? 'Asia/Dubai';
}

/**
 * Convert UTC datetime to user's timezone
 * @param string|null $datetime UTC datetime string from database
 * @param string|null $timezone Optional timezone override
 * @return DateTimeImmutable|null DateTime object in user's timezone or null
 */
function to_user_timezone(?string $datetime, ?string $timezone = null): ?DateTimeImmutable {
    if ($datetime === null || $datetime === '') {
        return null;
    }
    
    try {
        // Parse as UTC
        $dt = new DateTimeImmutable($datetime, new DateTimeZone('UTC'));
        
        // Convert to user's timezone
        $tz = $timezone ?? get_user_timezone();
        return $dt->setTimezone(new DateTimeZone($tz));
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Format datetime for display in user's timezone (various formats)
 * @param string|null $datetime Datetime string from database (UTC)
 * @param string $format Display format ('short', 'long', 'date', 'time', or custom)
 * @param string|null $timezone Optional timezone override
 * @return string Formatted datetime or empty string
 */
function format_datetime(?string $datetime, string $format = 'long', ?string $timezone = null): string {
    if ($datetime === null || $datetime === '') {
        return '';
    }
    
    $dt = to_user_timezone($datetime, $timezone);
    if ($dt === null) {
        return '';
    }
    
    $month_n     = (int) $dt->format('n');
    $month_long  = t('dates.months_long.'  . $month_n);
    $month_short = t('dates.months_short.' . $month_n);
    $ampm        = $dt->format('A') === 'AM' ? t('dates.am') : t('dates.pm');
    $at          = t('dates.at');
    $time_12     = $dt->format('g:i');
    $day         = $dt->format('j');
    $year        = $dt->format('Y');

    return match($format) {
        'short'      => "{$month_short} {$day}, {$year} {$time_12} {$ampm}",
        'long'       => "{$month_long} {$day}, {$year} {$at} {$time_12} {$ampm}",
        'date'       => "{$month_long} {$day}, {$year}",
        'date_short' => "{$month_short} {$day}, {$year}",
        'time'       => "{$time_12} {$ampm}",
        'year'       => $year,
        'iso'        => $dt->format('c'),
        default      => $dt->format($format),
    };
}

/**
 * Format datetime as relative time (e.g., "2 days ago", "3 hours ago")
 * @param string|null $datetime Datetime string from database (UTC)
 * @param string|null $timezone Optional timezone override
 * @return string Relative time string or empty string
 */
function time_ago(?string $datetime, ?string $timezone = null): string {
    if ($datetime === null || $datetime === '') {
        return '';
    }
    
    $dt = to_user_timezone($datetime, $timezone);
    if ($dt === null) {
        return '';
    }
    
    $now = new DateTimeImmutable('now', new DateTimeZone($timezone ?? get_user_timezone()));
    $diff = $now->getTimestamp() - $dt->getTimestamp();

    if ($diff < 0)        return t('time.in_the_future');
    if ($diff < 60)       return t('time.just_now');
    if ($diff < 3600)     return t('time.mins_ago',   ['count' => (int) floor($diff / 60)]);
    if ($diff < 86400)    return t('time.hours_ago',  ['count' => (int) floor($diff / 3600)]);
    if ($diff < 604800)   return t('time.days_ago',   ['count' => (int) floor($diff / 86400)]);
    if ($diff < 2592000)  return t('time.weeks_ago',  ['count' => (int) floor($diff / 604800)]);
    if ($diff < 31536000) return t('time.months_ago', ['count' => (int) floor($diff / 2592000)]);

    return t('time.years_ago', ['count' => (int) floor($diff / 31536000)]);
}

/**
 * Convert datetime to datetime-local input format in user's timezone
 * Used for HTML5 datetime-local inputs which don't accept timezone info
 * @param string|null $datetime Datetime string from database (UTC)
 * @param string|null $timezone Optional timezone override
 * @return string Format: 'Y-m-d\TH:i' or empty string
 */
function to_datetime_local(?string $datetime, ?string $timezone = null): string {
    if ($datetime === null || $datetime === '') {
        return '';
    }
    
    $dt = to_user_timezone($datetime, $timezone);
    if ($dt === null) {
        return '';
    }
    
    return $dt->format('Y-m-d\TH:i');
}

/**
 * Convert datetime-local input to UTC for database storage
 * @param string|null $datetime_local Datetime from datetime-local input
 * @param string|null $timezone Optional timezone (defaults to user's timezone)
 * @return string|null UTC datetime string or null
 */
function from_datetime_local(?string $datetime_local, ?string $timezone = null): ?string {
    if ($datetime_local === null || $datetime_local === '') {
        return null;
    }
    
    try {
        // Parse as user's timezone (datetime-local has no timezone info)
        $tz = $timezone ?? get_user_timezone();
        $dt = new DateTimeImmutable($datetime_local, new DateTimeZone($tz));
        
        // Convert to UTC
        $utc = $dt->setTimezone(new DateTimeZone('UTC'));
        return $utc->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get list of common timezones
 * @return array<string, string> Timezone identifiers mapped to display labels
 */
function get_timezone_list(): array {
    return [
        // North America
        'US/Eastern'              => '(UTC-05:00) Eastern Time (US & Canada)',
        'US/Central'              => '(UTC-06:00) Central Time (US & Canada)',
        'US/Mountain'             => '(UTC-07:00) Mountain Time (US & Canada)',
        'US/Pacific'              => '(UTC-08:00) Pacific Time (US & Canada)',
        'US/Alaska'               => '(UTC-09:00) Alaska',
        'Pacific/Honolulu'        => '(UTC-10:00) Hawaii',
        'America/Phoenix'         => '(UTC-07:00) Arizona',
        'America/Anchorage'       => '(UTC-09:00) Anchorage',
        'America/Chicago'         => '(UTC-06:00) Chicago',
        'America/Denver'          => '(UTC-07:00) Denver',
        'America/Los_Angeles'     => '(UTC-08:00) Los Angeles',
        'America/New_York'        => '(UTC-05:00) New York',
        'America/Toronto'         => '(UTC-05:00) Toronto',
        'America/Vancouver'       => '(UTC-08:00) Vancouver',
        'America/Winnipeg'        => '(UTC-06:00) Winnipeg',
        'America/Edmonton'        => '(UTC-07:00) Edmonton',
        'America/Halifax'         => '(UTC-04:00) Halifax',
        'America/St_Johns'        => '(UTC-03:30) Newfoundland',
        'America/Regina'          => '(UTC-06:00) Saskatchewan',

        // Mexico & Central America
        'America/Mexico_City'     => '(UTC-06:00) Mexico City',
        'America/Tijuana'         => '(UTC-08:00) Tijuana',
        'America/Guatemala'       => '(UTC-06:00) Guatemala',
        'America/Costa_Rica'      => '(UTC-06:00) Costa Rica',
        'America/Panama'          => '(UTC-05:00) Panama',

        // South America
        'America/Bogota'          => '(UTC-05:00) Bogota',
        'America/Lima'            => '(UTC-05:00) Lima',
        'America/Caracas'         => '(UTC-04:00) Caracas',
        'America/Santiago'        => '(UTC-04:00) Santiago',
        'America/Buenos_Aires'    => '(UTC-03:00) Buenos Aires',
        'America/Sao_Paulo'       => '(UTC-03:00) São Paulo',
        'America/Montevideo'      => '(UTC-03:00) Montevideo',
        'America/Guayaquil'       => '(UTC-05:00) Quito',

        // Atlantic
        'Atlantic/Reykjavik'      => '(UTC+00:00) Reykjavik',
        'Atlantic/Cape_Verde'     => '(UTC-01:00) Cape Verde',
        'Atlantic/Azores'         => '(UTC-01:00) Azores',

        // Europe
        'Europe/London'           => '(UTC+00:00) London',
        'Europe/Dublin'           => '(UTC+00:00) Dublin',
        'Europe/Lisbon'           => '(UTC+00:00) Lisbon',
        'Europe/Paris'            => '(UTC+01:00) Paris',
        'Europe/Berlin'           => '(UTC+01:00) Berlin',
        'Europe/Amsterdam'        => '(UTC+01:00) Amsterdam',
        'Europe/Brussels'         => '(UTC+01:00) Brussels',
        'Europe/Madrid'           => '(UTC+01:00) Madrid',
        'Europe/Rome'             => '(UTC+01:00) Rome',
        'Europe/Zurich'           => '(UTC+01:00) Zurich',
        'Europe/Vienna'           => '(UTC+01:00) Vienna',
        'Europe/Stockholm'        => '(UTC+01:00) Stockholm',
        'Europe/Oslo'             => '(UTC+01:00) Oslo',
        'Europe/Copenhagen'       => '(UTC+01:00) Copenhagen',
        'Europe/Warsaw'           => '(UTC+01:00) Warsaw',
        'Europe/Prague'           => '(UTC+01:00) Prague',
        'Europe/Budapest'         => '(UTC+01:00) Budapest',
        'Europe/Belgrade'         => '(UTC+01:00) Belgrade',
        'Europe/Athens'           => '(UTC+02:00) Athens',
        'Europe/Bucharest'        => '(UTC+02:00) Bucharest',
        'Europe/Helsinki'         => '(UTC+02:00) Helsinki',
        'Europe/Kiev'             => '(UTC+02:00) Kyiv',
        'Europe/Istanbul'         => '(UTC+03:00) Istanbul',
        'Europe/Moscow'           => '(UTC+03:00) Moscow',
        'Europe/Minsk'            => '(UTC+03:00) Minsk',

        // Africa
        'Africa/Cairo'            => '(UTC+02:00) Cairo',
        'Africa/Johannesburg'     => '(UTC+02:00) Johannesburg',
        'Africa/Nairobi'          => '(UTC+03:00) Nairobi',
        'Africa/Lagos'            => '(UTC+01:00) Lagos',
        'Africa/Casablanca'       => '(UTC+01:00) Casablanca',
        'Africa/Accra'            => '(UTC+00:00) Accra',
        'Africa/Addis_Ababa'      => '(UTC+03:00) Addis Ababa',
        'Africa/Dar_es_Salaam'    => '(UTC+03:00) Dar es Salaam',
        'Africa/Algiers'          => '(UTC+01:00) Algiers',
        'Africa/Tunis'            => '(UTC+01:00) Tunis',

        // Middle East
        'Asia/Dubai'              => '(UTC+04:00) Dubai',
        'Asia/Riyadh'             => '(UTC+03:00) Riyadh',
        'Asia/Kuwait'             => '(UTC+03:00) Kuwait',
        'Asia/Qatar'              => '(UTC+03:00) Qatar',
        'Asia/Bahrain'            => '(UTC+03:00) Bahrain',
        'Asia/Tehran'             => '(UTC+03:30) Tehran',
        'Asia/Jerusalem'          => '(UTC+02:00) Jerusalem',
        'Asia/Amman'              => '(UTC+03:00) Amman',
        'Asia/Beirut'             => '(UTC+02:00) Beirut',
        'Asia/Baghdad'            => '(UTC+03:00) Baghdad',
        'Asia/Muscat'             => '(UTC+04:00) Muscat',

        // Central & South Asia
        'Asia/Karachi'            => '(UTC+05:00) Karachi',
        'Asia/Kolkata'            => '(UTC+05:30) Kolkata / Mumbai / New Delhi',
        'Asia/Colombo'            => '(UTC+05:30) Colombo',
        'Asia/Kathmandu'          => '(UTC+05:45) Kathmandu',
        'Asia/Dhaka'              => '(UTC+06:00) Dhaka',
        'Asia/Almaty'             => '(UTC+06:00) Almaty',
        'Asia/Tashkent'           => '(UTC+05:00) Tashkent',
        'Asia/Tbilisi'            => '(UTC+04:00) Tbilisi',
        'Asia/Baku'               => '(UTC+04:00) Baku',
        'Asia/Yerevan'            => '(UTC+04:00) Yerevan',
        'Asia/Kabul'              => '(UTC+04:30) Kabul',

        // East & Southeast Asia
        'Asia/Bangkok'            => '(UTC+07:00) Bangkok',
        'Asia/Jakarta'            => '(UTC+07:00) Jakarta',
        'Asia/Ho_Chi_Minh'        => '(UTC+07:00) Ho Chi Minh City',
        'Asia/Singapore'          => '(UTC+08:00) Singapore',
        'Asia/Kuala_Lumpur'       => '(UTC+08:00) Kuala Lumpur',
        'Asia/Hong_Kong'          => '(UTC+08:00) Hong Kong',
        'Asia/Shanghai'           => '(UTC+08:00) Beijing / Shanghai',
        'Asia/Taipei'             => '(UTC+08:00) Taipei',
        'Asia/Manila'             => '(UTC+08:00) Manila',
        'Asia/Seoul'              => '(UTC+09:00) Seoul',
        'Asia/Tokyo'              => '(UTC+09:00) Tokyo',
        'Asia/Yangon'             => '(UTC+06:30) Yangon',
        'Asia/Phnom_Penh'         => '(UTC+07:00) Phnom Penh',
        'Asia/Brunei'             => '(UTC+08:00) Brunei',
        'Asia/Makassar'           => '(UTC+08:00) Makassar',
        'Asia/Jayapura'           => '(UTC+09:00) Jayapura',

        // Russia (additional)
        'Asia/Vladivostok'        => '(UTC+10:00) Vladivostok',
        'Asia/Novosibirsk'        => '(UTC+07:00) Novosibirsk',
        'Asia/Yekaterinburg'      => '(UTC+05:00) Yekaterinburg',
        'Asia/Kamchatka'          => '(UTC+12:00) Kamchatka',
        'Asia/Magadan'            => '(UTC+11:00) Magadan',
        'Asia/Irkutsk'            => '(UTC+08:00) Irkutsk',
        'Asia/Krasnoyarsk'        => '(UTC+07:00) Krasnoyarsk',
        'Asia/Yakutsk'            => '(UTC+09:00) Yakutsk',
        'Asia/Sakhalin'           => '(UTC+11:00) Sakhalin',
        'Asia/Omsk'               => '(UTC+06:00) Omsk',
        'Europe/Samara'           => '(UTC+04:00) Samara',
        'Europe/Kaliningrad'      => '(UTC+02:00) Kaliningrad',

        // Australia & Oceania
        'Australia/Sydney'        => '(UTC+10:00) Sydney',
        'Australia/Melbourne'     => '(UTC+10:00) Melbourne',
        'Australia/Brisbane'      => '(UTC+10:00) Brisbane',
        'Australia/Perth'         => '(UTC+08:00) Perth',
        'Australia/Adelaide'      => '(UTC+09:30) Adelaide',
        'Australia/Hobart'        => '(UTC+10:00) Hobart',
        'Australia/Darwin'        => '(UTC+09:30) Darwin',
        'Pacific/Auckland'        => '(UTC+12:00) Auckland',
        'Pacific/Fiji'            => '(UTC+12:00) Fiji',
        'Pacific/Guam'            => '(UTC+10:00) Guam',
        'Pacific/Tongatapu'       => '(UTC+13:00) Nuku\'alofa',
        'Pacific/Apia'            => '(UTC+13:00) Samoa',
        'Pacific/Noumea'          => '(UTC+11:00) New Caledonia',
        'Pacific/Port_Moresby'    => '(UTC+10:00) Port Moresby',
        'Pacific/Chatham'         => '(UTC+12:45) Chatham Islands',
        'Pacific/Midway'          => '(UTC-11:00) Midway Island',
        'Pacific/Pago_Pago'       => '(UTC-11:00) Pago Pago',

        // Caribbean
        'America/Havana'          => '(UTC-05:00) Havana',
        'America/Jamaica'         => '(UTC-05:00) Jamaica',
        'America/Puerto_Rico'     => '(UTC-04:00) Puerto Rico',
        'America/Santo_Domingo'   => '(UTC-04:00) Santo Domingo',
        'America/Port-au-Prince'  => '(UTC-05:00) Port-au-Prince',
        'America/Barbados'        => '(UTC-04:00) Barbados',
        'America/Trinidad'        => '(UTC-04:00) Trinidad',
    ];
}

/**
 * Check if a datetime is in the past
 * @param string|null $datetime Datetime string from database
 * @return bool True if datetime is in the past
 */
function is_past(?string $datetime): bool {
    if ($datetime === null || $datetime === '') {
        return false;
    }
    
    $timestamp = to_timestamp($datetime);
    if ($timestamp === null) {
        return false;
    }
    
    return $timestamp < time();
}

/**
 * Check if a datetime is in the future
 * @param string|null $datetime Datetime string from database
 * @return bool True if datetime is in the future
 */
function is_future(?string $datetime): bool {
    if ($datetime === null || $datetime === '') {
        return false;
    }
    
    $timestamp = to_timestamp($datetime);
    if ($timestamp === null) {
        return false;
    }
    
    return $timestamp > time();
}

/**
 * Check if a published form is closed (past its submission deadline)
 *
 * @param array<string, mixed> $form Form record with decoded settings
 */
function is_form_closed(array $form): bool {
    if (($form['status'] ?? '') !== 'published') {
        return false;
    }
    $deadline = $form['settings']['submission_deadline'] ?? null;
    return $deadline !== null && is_past($deadline);
}

/**
 * Check if a program is effectively closed (explicitly closed or past submission period)
 *
 * @param array<string, mixed> $program Program record with decoded settings
 */
function is_program_effectively_closed(array $program): bool {
    if (($program['status'] ?? '') === 'closed') {
        return true;
    }
    if (($program['status'] ?? '') !== 'active') {
        return false;
    }
    $end = $program['settings']['submission_period_end'] ?? null;
    return $end !== null && is_past($end);
}

/**
 * Check if a closure datetime falls within the recent grace period
 *
 * Used to show recently closed forms/programs to users for a limited time
 * so late visitors know they existed but are now closed.
 */
function is_recently_closed(?string $closed_at, int $grace_days = 7): bool {
    if ($closed_at === null || $closed_at === '') {
        return false;
    }
    $closed_ts = to_timestamp($closed_at);
    if ($closed_ts === null || $closed_ts >= time()) {
        return false;
    }
    $grace_cutoff = strtotime("-{$grace_days} days");
    return $closed_ts > $grace_cutoff;
}

/**
 * Add time to a datetime
 * @param string $datetime Base datetime
 * @param string $interval Interval string (e.g., '+1 day', '+2 hours')
 * @return string New datetime in database format
 */
function datetime_add(string $datetime, string $interval): string {
    $timestamp = to_timestamp($datetime);
    if ($timestamp === null) {
        return now();
    }
    
    $new_timestamp = strtotime($interval, $timestamp);
    if ($new_timestamp === false) {
        return $datetime;
    }
    
    return gmdate('Y-m-d H:i:s', $new_timestamp);
}

/**
 * Parse JSON request body (php://input)
 *
 * Reads and decodes the raw JSON body from the current HTTP request.
 * Returns null if the body is empty or contains invalid JSON.
 *
 * @return array<string, mixed>|null Decoded JSON body or null
 */
function json_input(): ?array {
    $body = file_get_contents('php://input');
    if ($body === false || $body === '') {
        return null;
    }
    return json_decode($body, true);
}

/**
 * Decode JSON string fields on a database row in-place
 *
 * Safely decodes specified fields from JSON strings to PHP arrays.
 * Skips fields that are missing, null, or already decoded (not a string).
 * This is idempotent — calling it multiple times on the same row is safe.
 *
 * @param array<string, mixed> $row Database row (modified by reference)
 * @param array<int, string> $fields List of field names to decode
 */
function decode_json_fields(array &$row, array $fields): void {
    foreach ($fields as $field) {
        if (isset($row[$field]) && is_string($row[$field])) {
            $row[$field] = json_decode($row[$field], true);
        }
    }
}

/**
 * Parse user agent string into human-readable components
 *
 * Returns an array with browser, os, device_type, and raw user agent.
 * If parsing fails or components are unrecognized, gracefully degrades.
 *
 * @param string $ua User agent string
 * @return array{browser: ?string, os: ?string, device_type: ?string, raw: string}
 */
function parse_user_agent(string $ua): array {
    $result = [
        'browser' => null,
        'os' => null,
        'device_type' => null,
        'raw' => $ua
    ];
    
    // Detect browser
    if (preg_match('/Edg\/(\d+)/', $ua, $m)) {
        $result['browser'] = 'Edge ' . $m[1];
    } elseif (preg_match('/Chrome\/(\d+)/', $ua, $m)) {
        $result['browser'] = 'Chrome ' . $m[1];
    } elseif (preg_match('/Firefox\/(\d+)/', $ua, $m)) {
        $result['browser'] = 'Firefox ' . $m[1];
    } elseif (preg_match('/Safari\/(\d+)/', $ua, $m) && !str_contains($ua, 'Chrome')) {
        $result['browser'] = 'Safari ' . $m[1];
    } elseif (preg_match('/OPR\/(\d+)/', $ua, $m)) {
        $result['browser'] = 'Opera ' . $m[1];
    } elseif (preg_match('/MSIE (\d+)/', $ua, $m)) {
        $result['browser'] = 'Internet Explorer ' . $m[1];
    } elseif (preg_match('/Trident\/.*rv:(\d+)/', $ua, $m)) {
        $result['browser'] = 'Internet Explorer ' . $m[1];
    }
    
    // Detect OS
    if (preg_match('/Windows NT (\d+\.\d+)/', $ua, $m)) {
        $version_map = [
            '10.0' => 'Windows 10/11',
            '6.3' => 'Windows 8.1',
            '6.2' => 'Windows 8',
            '6.1' => 'Windows 7',
            '6.0' => 'Windows Vista',
        ];
        $result['os'] = $version_map[$m[1]] ?? 'Windows NT ' . $m[1];
    } elseif (str_contains($ua, 'Mac OS X')) {
        if (preg_match('/Mac OS X (\d+[_\.]\d+)/', $ua, $m)) {
            $version = str_replace('_', '.', $m[1]);
            $result['os'] = 'macOS ' . $version;
        } else {
            $result['os'] = 'macOS';
        }
    } elseif (preg_match('/Android (\d+(?:\.\d+)?)/', $ua, $m)) {
        $result['os'] = 'Android ' . $m[1];
    } elseif (preg_match('/iPhone OS (\d+[_\.]\d+)/', $ua, $m)) {
        $version = str_replace('_', '.', $m[1]);
        $result['os'] = 'iOS ' . $version;
    } elseif (str_contains($ua, 'Linux')) {
        $result['os'] = 'Linux';
    } elseif (str_contains($ua, 'CrOS')) {
        $result['os'] = 'Chrome OS';
    }
    
    // Detect device type
    if (str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone') || str_contains($ua, 'Android')) {
        $result['device_type'] = 'Mobile';
    } elseif (str_contains($ua, 'iPad') || str_contains($ua, 'Tablet')) {
        $result['device_type'] = 'Tablet';
    } else {
        $result['device_type'] = 'Desktop';
    }
    
    return $result;
}

/**
 * FILE UPLOAD SECURITY CONSTANTS
 * Centralised here so every module (forms, branding, etc.) shares the same lists.
 */

/**
 * Hardcoded whitelist of safe file extensions.
 * This is a security-critical list that CANNOT be overridden by configuration.
 * Includes common variants (e.g. both jpg and jpeg) so the list is self-documenting;
 * callers should still run normalize_file_extension() before comparing against
 * per-question allowed_extensions lists.
 */
define('SAFE_FILE_EXTENSIONS', [
    // Documents
    'pdf', 'doc', 'docx', 'odt', 'txt', 'rtf',
    // Spreadsheets
    'xls', 'xlsx', 'ods', 'csv',
    // Presentations
    'ppt', 'pptx', 'odp',
    // Images
    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',
    // Archives (with caution)
    'zip', 'tar', 'gz',
]);

/**
 * Dangerous extensions that must NEVER be allowed
 */
define('DANGEROUS_FILE_EXTENSIONS', [
    'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
    'exe', 'com', 'bat', 'cmd', 'sh', 'bash', 'zsh',
    'js', 'jar', 'app', 'deb', 'rpm',
    'htaccess', 'htpasswd', 'ini', 'conf', 'config',
    'sql', 'db', 'sqlite', 'mdb',
    'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'rb',
]);

/**
 * MIME type to extension mapping for validation.
 * Maps expected MIME types to their allowed extensions.
 */
define('MIME_TYPE_MAPPING', [
    // Documents
    'application/pdf' => ['pdf'],
    'application/msword' => ['doc'],
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    'application/vnd.oasis.opendocument.text' => ['odt'],
    'text/plain' => ['txt'],
    'application/rtf' => ['rtf'],
    // Spreadsheets
    'application/vnd.ms-excel' => ['xls'],
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
    'application/vnd.oasis.opendocument.spreadsheet' => ['ods'],
    'text/csv' => ['csv'],
    // Presentations
    'application/vnd.ms-powerpoint' => ['ppt'],
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
    'application/vnd.oasis.opendocument.presentation' => ['odp'],
    // Images
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png' => ['png'],
    'image/gif' => ['gif'],
    'image/bmp' => ['bmp'],
    'image/webp' => ['webp'],
    // Archives
    'application/zip' => ['zip'],
    'application/x-tar' => ['tar'],
    'application/gzip' => ['gz'],
    'application/x-gzip' => ['gz'],
]);

/**
 * Normalise a file extension to its canonical form.
 *
 * Allows callers to compare against a single canonical spelling
 * (e.g. 'jpeg' → 'jpg') so per-question allowed_extensions lists
 * only need the canonical entry.
 */
function normalize_file_extension(string $ext): string {
    return match ($ext) {
        'jpeg' => 'jpg',
        'tif'  => 'tiff',
        'htm'  => 'html',
        default => $ext,
    };
}

/**
 * BRANDING UTILITIES
 * Per-request cached branding settings + color palette generation
 */

/**
 * Get branding settings (cached per request)
 *
 * Returns brand_name, brand_color, logo_path, logo_path_dark, and the generated palette.
 * Safe to call on every page load — only hits the DB once per request.
 * Uses $GLOBALS for caching so branding_cache_clear() can bust it mid-request.
 *
 * @return array{brand_name: string, brand_color: string, logo_path: ?string, logo_path_dark: ?string, palette: array<int,string>}
 */
function get_branding(): array
{
    if (isset($GLOBALS['_branding_cache'])) {
        return $GLOBALS['_branding_cache'];
    }

    // Attempt DB read; silently fall back to defaults if table is missing
    try {
        require_once __DIR__ . '/../modules/branding/models.php';
        $settings = get_site_settings();
    } catch (Throwable) {
        $settings = [
            'brand_name'     => 'FORMNA',
            'brand_color'    => '#4f46e5',
            'logo_path'      => null,
            'logo_path_dark' => null,
        ];
    }

    $GLOBALS['_branding_cache'] = [
        'brand_name'     => $settings['brand_name'],
        'brand_color'    => $settings['brand_color'],
        'logo_path'      => logo_url($settings['logo_path']),
        'logo_path_dark' => logo_url($settings['logo_path_dark'] ?? null),
        'palette'        => generate_color_palette($settings['brand_color']),
    ];

    return $GLOBALS['_branding_cache'];
}

/**
 * Clear branding cache (call after updating settings)
 */
function branding_cache_clear(): void
{
    unset($GLOBALS['_branding_cache']);
}

/**
 * Get full web path for a logo filename
 *
 * @param string|null $filename Logo filename (e.g., 'logo_1234567890.png')
 * @return string|null Full web path or null if no filename provided
 */
function logo_url(?string $filename): ?string
{
    if ($filename === null || $filename === '') {
        return null;
    }
    
    global $config;
    $web_path = $config['branding']['web_path'];
    
    return rtrim($web_path, '/') . '/' . $filename;
}

/**
 * Check if a custom CSS override file exists
 *
 * When this file is present, the branding module is disabled and
 * default CSS classes are used, allowing full customization.
 *
 * @return bool True if custom.css exists in the branding upload directory
 */
function has_css_override(): bool
{
    $override_path = dirname(__DIR__, 2) . '/public/uploads/branding/custom.css';
    return file_exists($override_path);
}

/**
 * Get the web path to the custom CSS override file
 *
 * @return string|null Web path to custom.css, or null if it doesn't exist
 */
/**
 * Append cache-busting version query string to a public asset path.
 *
 * Uses the file's last-modified timestamp so browsers fetch a fresh copy
 * whenever the file changes. Falls back to a static '1' when the file
 * cannot be found (e.g. during local development before a build).
 *
 * @param string $path Web path relative to public root (e.g. '/js/common.js')
 * @return string Path with ?v=<mtime> appended
 */
function resolve_public_asset_file(string $path): ?string {
    $normalized_path = '/' . ltrim($path, '/');
    $candidates = [];

    $document_root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($document_root !== '') {
        $candidates[] = $document_root . $normalized_path;
    }

    // Local source layout: app/src/core/helpers.php -> app/public/*
    $candidates[] = dirname(__DIR__, 2) . '/public' . $normalized_path;

    // Some production releases place /src and public web assets side-by-side.
    $candidates[] = dirname(__DIR__, 3) . $normalized_path;

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function asset(string $path): string {
    $normalized_path = '/' . ltrim($path, '/');
    $file = resolve_public_asset_file($normalized_path);

    if ($file !== null) {
        $version = (string) filemtime($file);
    } elseif (preg_match('#^/(js|css)/#', $normalized_path) === 1) {
        // Force a fresh URL for critical frontend assets even if the deploy
        // layout prevents us from resolving the file on disk.
        $version = (string) time();
    } else {
        $version = '1';
    }

    return $normalized_path . '?v=' . $version;
}

function get_css_override_path(): ?string
{
    return has_css_override() ? '/uploads/branding/custom.css' : null;
}

/**
 * Convert hex color string to HSL components
 *
 * @param string $hex 6-digit hex color with # prefix
 * @return array{0: float, 1: float, 2: float} [hue 0-360, saturation 0-100, lightness 0-100]
 */
function hex_to_hsl(string $hex): array
{
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;

    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;

    if ($max === $min) {
        return [0.0, 0.0, round($l * 100, 2)];
    }

    $d = $max - $min;
    $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

    $h = match ($max) {
        $r     => (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6,
        $g     => (($b - $r) / $d + 2) / 6,
        default => (($r - $g) / $d + 4) / 6,
    };

    return [
        round($h * 360, 2),
        round($s * 100, 2),
        round($l * 100, 2),
    ];
}

/**
 * Convert HSL components to hex color string
 *
 * @param float $h Hue 0-360
 * @param float $s Saturation 0-100
 * @param float $l Lightness 0-100
 * @return string 6-digit hex with # prefix
 */
function hsl_to_hex(float $h, float $s, float $l): string
{
    $h = fmod(fmod($h, 360) + 360, 360);
    $s = max(0, min(100, $s)) / 100;
    $l = max(0, min(100, $l)) / 100;

    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;

    [$r, $g, $b] = match (true) {
        $h < 60  => [$c, $x, 0],
        $h < 120 => [$x, $c, 0],
        $h < 180 => [0, $c, $x],
        $h < 240 => [0, $x, $c],
        $h < 300 => [$x, 0, $c],
        default  => [$c, 0, $x],
    };

    $toHex = fn(float $v): string => str_pad(dechex((int) round(($v + $m) * 255)), 2, '0', STR_PAD_LEFT);

    return '#' . $toHex($r) . $toHex($g) . $toHex($b);
}

/**
 * Generate a full 10-shade color palette from a single brand color
 *
 * The input color is mapped to the 600 shade (primary buttons, sidebar backgrounds).
 * Lighter shades (50-500) are generated by interpolating lightness up toward 97%.
 * Darker shades (700-900) are generated by interpolating lightness down.
 * Saturation is tapered at extremes for a natural, balanced palette.
 *
 * @param string $hex Brand color as 6-digit hex with # prefix
 * @return array<int, string> Shade number => hex color (50, 100, 200 … 900)
 */
function generate_color_palette(string $hex): array
{
    [$h, $s, $l] = hex_to_hsl($hex);

    // Range from input lightness up to 97% (lightest shade)
    $light_range = 97 - $l;

    // Range from input lightness down to a floor (darkest shade)
    $dark_floor = max($l * 0.28, 8);
    $dark_range = $l - $dark_floor;

    // Light shades: [shade, fraction of light_range, saturation factor]
    $light_steps = [
        [50,  1.00, 0.50],
        [100, 0.92, 0.60],
        [200, 0.80, 0.72],
        [300, 0.65, 0.82],
        [400, 0.44, 0.91],
        [500, 0.22, 0.96],
    ];

    // Dark shades: [shade, fraction of dark_range, saturation factor]
    $dark_steps = [
        [700, 0.25, 0.93],
        [800, 0.50, 0.82],
        [900, 0.75, 0.70],
    ];

    $palette = [];

    foreach ($light_steps as [$shade, $l_frac, $s_factor]) {
        $palette[$shade] = hsl_to_hex($h, $s * $s_factor, $l + $light_range * $l_frac);
    }

    $palette[600] = $hex; // Input color is the 600 shade

    foreach ($dark_steps as [$shade, $d_frac, $s_factor]) {
        $palette[$shade] = hsl_to_hex($h, $s * $s_factor, max($l - $dark_range * $d_frac, $dark_floor));
    }

    ksort($palette);
    return $palette;
}
