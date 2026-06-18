<?php

/**
 * Internationalization (i18n) Module
 *
 * Provides translation helpers for multilingual end-user UIs.
 *
 * Locale Detection Order:
 *   1. ?lang=xx query parameter (sets cookie, then redirects without param)
 *   2. Cookie 'locale'
 *   3. Default language from database
 *
 * Translation Lookup:
 *   1. Database translations for the current locale (languages.translations JSON)
 *   2. Filesystem lang files (app/src/lang/ui/{code}.json + app/src/lang/help/{code}.json) as fallback
 *   3. English lang files as ultimate fallback
 *
 * Help Content:
 *   - Bundled help files live in lang/help/{code}.json (read-only)
 *   - Admin customisations are stored in languages.help_translations (DB)
 *   - Help center links are hidden when neither file nor DB content exists
 */

/** @var array<string, mixed>|null Cached flat translations for current locale */
$_i18n_translations = null;

/** @var string|null Current locale code */
$_i18n_locale = null;

/** @var array<string, mixed>|null Current language row from DB */
$_i18n_language = null;

/** @var array<string, array<string, mixed>>|null Cached language rows */
$_i18n_languages_cache = null;

/** @var array<string, array<string, string>> Cached file translations (UI + help) per locale */
$_i18n_file_cache = [];

/** @var array<string, array<string, string>> Cached UI-only file translations per locale */
$_i18n_ui_file_cache = [];

/**
 * Initialise locale from request.
 *
 * Call once during bootstrap, after session_start() and DB init.
 * If ?lang=xx is present and valid, sets cookie and strips the param.
 *
 * Admin requests (paths under /admin) always use English LTR regardless
 * of the user's locale cookie or the database default language.
 */
function i18n_init(): void
{
    global $_i18n_locale, $_i18n_language;

    // Admin portal + admin API routes: force English LTR — never read cookies or DB.
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    if (str_starts_with($path, '/admin') || str_starts_with($path, '/api/admin')) {
        $_i18n_locale   = 'en';
        $_i18n_language = [
            'code'              => 'en',
            'name'              => 'English',
            'native_name'       => 'English',
            'direction'         => 'ltr',
            'translations'      => null,
            'help_translations' => null,
            'is_active'         => 1,
            'is_default'        => 1,
        ];
        return;
    }

    // 1. Check ?lang= query param
    $requested = $_GET['lang'] ?? null;
    if ($requested && is_string($requested)) {
        $lang = i18n_find_active_language($requested);
        if ($lang) {
            // Set cookie for 1 year
            setcookie('locale', $lang['code'], [
                'expires'  => time() + 86400 * 365,
                'path'     => '/',
                'httponly' => false,
                'samesite' => 'Lax',
                'secure'   => $GLOBALS['config']['session']['secure'] ?? true,
            ]);
            $_i18n_locale = $lang['code'];
            $_i18n_language = $lang;

            // Redirect to strip ?lang= from URL (prevents bookmarking with param)
            $url = strtok($_SERVER['REQUEST_URI'], '?');
            $params = $_GET;
            unset($params['lang']);
            if ($params) {
                $url .= '?' . http_build_query($params);
            }
            header('Location: ' . $url);
            exit;
        }
    }

    // 2. Check cookie
    $cookie_locale = $_COOKIE['locale'] ?? null;
    if ($cookie_locale && is_string($cookie_locale)) {
        $lang = i18n_find_active_language($cookie_locale);
        if ($lang) {
            $_i18n_locale = $lang['code'];
            $_i18n_language = $lang;
            return;
        }
    }

    // 3. Default language
    $default = i18n_get_default_language();
    if ($default) {
        $_i18n_locale = $default['code'];
        $_i18n_language = $default;
    } else {
        // Ultimate fallback
        $_i18n_locale = 'en';
        $_i18n_language = [
            'code'              => 'en',
            'name'              => 'English',
            'native_name'       => 'English',
            'direction'         => 'ltr',
            'translations'      => null,
            'help_translations' => null,
            'is_active'         => 1,
            'is_default'        => 1,
        ];
    }
}

/**
 * Translate a key. Supports dot-notation (e.g. 'nav.dashboard') and
 * placeholder substitution (e.g. {name}, {count}).
 *
 * @param string $key Dot-notated translation key (section.key)
 * @param array<string, string|int> $params Placeholder replacements
 * @return string Translated (or fallback) string
 */
function t(string $key, array $params = []): string
{
    $translations = i18n_get_translations();

    // Look up in flat map
    $value = $translations[$key] ?? null;

    // Fallback: return the key itself (makes missing translations visible)
    if ($value === null || $value === '') {
        // Try English fallback if not already English
        if (current_locale() !== 'en') {
            $en = i18n_load_file_translations('en');
            $value = $en[$key] ?? $key;
        } else {
            $value = $key;
        }
    }

    // Substitute placeholders {name}
    if ($params) {
        foreach ($params as $placeholder => $replacement) {
            $value = str_replace('{' . $placeholder . '}', (string) $replacement, $value);
        }
    }

    return $value;
}

/**
 * Get current locale code (e.g. 'en', 'ar')
 */
function current_locale(): string
{
    global $_i18n_locale;
    return $_i18n_locale ?? 'en';
}

/**
 * Check if current locale is RTL
 */
function is_rtl(): bool
{
    global $_i18n_language;
    return ($_i18n_language['direction'] ?? 'ltr') === 'rtl';
}

/**
 * Get current language direction
 */
function current_direction(): string
{
    return is_rtl() ? 'rtl' : 'ltr';
}

/**
 * Detect the text direction of a user-supplied string.
 *
 * Scans the first strong character (up to 300 chars) and returns 'rtl'
 * if it belongs to an RTL script (Arabic, Hebrew, etc.), otherwise 'ltr'.
 *
 * This is intentionally lightweight — no external libraries, pure regex.
 * Used to auto-set dir="rtl|ltr" on free-text answer containers shown to admins.
 *
 * @param string $text Raw or HTML-escaped user text
 * @return string 'rtl' or 'ltr'
 */
function detect_text_direction(string $text): string
{
    // Work on a limited prefix for performance; strip HTML entities/tags first
    $sample = html_entity_decode(strip_tags(mb_substr($text, 0, 300)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // RTL Unicode blocks:
    //   Arabic:           U+0600–U+06FF
    //   Arabic Supplement:U+0750–U+077F
    //   Arabic Extended-B:U+0870–U+089F
    //   Arabic Extended-A:U+08A0–U+08FF
    //   Hebrew:           U+0590–U+05FF
    //   Thaana (Dhivehi): U+0780–U+07BF
    //   N'Ko:             U+07C0–U+07FF
    //   Syriac:           U+0700–U+074F
    //   Arabic Pres. A:   U+FB50–U+FDFF
    //   Arabic Pres. B:   U+FE70–U+FEFF
    if (preg_match('/[\x{0590}-\x{05FF}\x{0600}-\x{06FF}\x{0700}-\x{077F}\x{0780}-\x{07FF}\x{0870}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $sample)) {
        return 'rtl';
    }

    return 'ltr';
}

/**
 * Get all active languages (for language switcher).
 * Result is cached per-request to avoid redundant DB queries.
 *
 * @return list<array<string, mixed>>
 */
function i18n_active_languages(): array
{
    global $_i18n_languages_cache;

    if ($_i18n_languages_cache !== null) {
        return $_i18n_languages_cache;
    }

    try {
        $_i18n_languages_cache = db_query(
            'SELECT id, code, name, native_name, direction, is_default
             FROM languages
             WHERE is_active = 1
             ORDER BY is_default DESC, name ASC'
        );
    } catch (Throwable) {
        // Table might not exist yet (pre-migration)
        $_i18n_languages_cache = [['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_default' => 1]];
    }

    return $_i18n_languages_cache;
}

/**
 * Check if language switcher should be shown (more than one active language)
 */
function i18n_show_switcher(): bool
{
    return count(i18n_active_languages()) > 1;
}

// ─── Internal helpers ──────────────────────────────────────────────

/**
 * Find an active language by code
 *
 * @return array<string, mixed>|null
 */
function i18n_find_active_language(string $code): ?array
{
    $code = preg_replace('/[^a-zA-Z0-9_-]/', '', $code);
    try {
        return db_one(
            'SELECT * FROM languages WHERE code = :code AND is_active = 1',
            ['code' => $code]
        );
    } catch (Throwable) {
        return null;
    }
}

/**
 * Get the default language row
 *
 * @return array<string, mixed>|null
 */
function i18n_get_default_language(): ?array
{
    try {
        return db_one('SELECT * FROM languages WHERE is_default = 1 LIMIT 1');
    } catch (Throwable) {
        return null;
    }
}

/**
 * Get flat translations map for current locale.
 * Merges: file defaults ← DB overrides.
 * Cached per-request.
 *
 * @return array<string, string>
 */
function i18n_get_translations(): array
{
    global $_i18n_translations, $_i18n_language;

    if ($_i18n_translations !== null) {
        return $_i18n_translations;
    }

    $locale = current_locale();

    // 1. Load from filesystem (en.json / ar.json etc.)
    $file_trans = i18n_load_file_translations($locale);

    // 2. Load from DB (may override file defaults)
    $db_trans = [];
    foreach (['translations', 'help_translations'] as $column) {
        if ($_i18n_language && !empty($_i18n_language[$column])) {
            $raw = $_i18n_language[$column];
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            if (is_array($decoded)) {
                $db_trans = array_merge($db_trans, i18n_flatten($decoded));
            }
        }
    }

    // Merge: file first, DB overrides (skip empty DB values)
    $_i18n_translations = $file_trans;
    foreach ($db_trans as $k => $v) {
        if ($v !== '') {
            $_i18n_translations[$k] = $v;
        }
    }

    return $_i18n_translations;
}

/**
 * Load translations from filesystem JSON files.
 * Loads both UI translations (lang/ui/{code}.json) and help translations (lang/help/{code}.json).
 *
 * @return array<string, string> Flattened key→value map
 */
function i18n_load_file_translations(string $code): array
{
    global $_i18n_file_cache;

    if (isset($_i18n_file_cache[$code])) {
        return $_i18n_file_cache[$code];
    }

    $safe_code = preg_replace('/[^a-zA-Z0-9_-]/', '', $code);
    $ui_file = __DIR__ . '/../lang/ui/' . $safe_code . '.json';
    $help_file = __DIR__ . '/../lang/help/' . $safe_code . '.json';

    $translations = [];

    // Load UI translations
    if (file_exists($ui_file)) {
        $json = json_decode(file_get_contents($ui_file), true);
        if (is_array($json)) {
            $translations = i18n_flatten($json);
        }
    }

    // Load help translations (if available)
    if (file_exists($help_file)) {
        $json = json_decode(file_get_contents($help_file), true);
        if (is_array($json)) {
            $translations = array_merge($translations, i18n_flatten($json));
        }
    }

    $_i18n_file_cache[$code] = $translations;
    return $_i18n_file_cache[$code];
}

/**
 * Check if help content exists for a given language code.
 * Returns true if a bundled help file exists on disk OR if the
 * language has help_translations stored in the database.
 *
 * @param string $code Language code (e.g., 'en', 'ar')
 * @return bool True if help content is available for this language
 */
function i18n_help_exists(string $code): bool
{
    global $_i18n_language;

    $safe_code = preg_replace('/[^a-zA-Z0-9_-]/', '', $code);

    if (file_exists(__DIR__ . '/../lang/help/' . $safe_code . '.json')) {
        return true;
    }

    if ($_i18n_language && $_i18n_language['code'] === $safe_code) {
        return !empty($_i18n_language['help_translations']);
    }

    try {
        $lang = db_one(
            'SELECT help_translations FROM languages WHERE code = :code',
            ['code' => $safe_code]
        );
        return $lang && !empty($lang['help_translations']);
    } catch (Throwable) {
        return false;
    }
}

/**
 * Flatten a nested associative array into dot-notated keys.
 * Skips the special '_meta' key.
 *
 * Example: ['nav' => ['home' => 'Home']] → ['nav.home' => 'Home']
 *
 * @param array<string, mixed> $array
 * @return array<string, string>
 */
function i18n_flatten(array $array, string $prefix = ''): array
{
    $result = [];

    foreach ($array as $key => $value) {
        if ($key === '_meta') {
            continue;
        }

        $full_key = $prefix ? "{$prefix}.{$key}" : $key;

        if (is_array($value)) {
            $result = array_merge($result, i18n_flatten($value, $full_key));
        } else {
            $result[$full_key] = (string) $value;
        }
    }

    return $result;
}

/**
 * Load only UI translations (excluding help) from a filesystem JSON file.
 *
 * @return array<string, string> Flattened key→value map
 */
function i18n_load_ui_file_translations(string $code): array
{
    global $_i18n_ui_file_cache;

    if (isset($_i18n_ui_file_cache[$code])) {
        return $_i18n_ui_file_cache[$code];
    }

    $safe_code = preg_replace('/[^a-zA-Z0-9_-]/', '', $code);
    $ui_file = __DIR__ . '/../lang/ui/' . $safe_code . '.json';

    if (!file_exists($ui_file)) {
        $_i18n_ui_file_cache[$code] = [];
        return [];
    }

    $json = json_decode(file_get_contents($ui_file), true);
    if (!is_array($json)) {
        $_i18n_ui_file_cache[$code] = [];
        return [];
    }

    $_i18n_ui_file_cache[$code] = i18n_flatten($json);
    return $_i18n_ui_file_cache[$code];
}

/**
 * Get the full set of UI translation keys from the English source file.
 * Excludes help keys. Useful for comparing/validating other languages.
 *
 * @return list<string>
 */
function i18n_get_all_keys(): array
{
    $en = i18n_load_ui_file_translations('en');
    return array_keys($en);
}

/**
 * Count how many UI translation keys are missing (empty) in a given translations array.
 *
 * @param array<string, mixed> $translations
 */
function i18n_count_missing(array $translations): int
{
    $all_keys = i18n_get_all_keys();
    $flat = i18n_flatten($translations);

    $missing = 0;
    foreach ($all_keys as $key) {
        if (!isset($flat[$key]) || $flat[$key] === '') {
            $missing++;
        }
    }

    return $missing;
}

/**
 * Clear all file-translations caches.
 * Needed after writing help/UI JSON files from the admin panel
 * so the updated data is available in the same request.
 */
function i18n_clear_file_cache(): void
{
    global $_i18n_translations, $_i18n_file_cache, $_i18n_ui_file_cache;
    $_i18n_translations  = null;
    $_i18n_file_cache    = [];
    $_i18n_ui_file_cache = [];
}

/**
 * Load raw (non-flattened) help translations from a help JSON file.
 * Used by the admin editor to populate the help textarea.
 *
 * @param string $code Language code
 * @return string Pretty-printed JSON string, or empty string if no file
 */
function i18n_load_help_file_raw(string $code): string
{
    $safe_code = preg_replace('/[^a-zA-Z0-9_-]/', '', $code);
    $help_file = __DIR__ . '/../lang/help/' . $safe_code . '.json';

    if (!file_exists($help_file)) {
        return '';
    }

    $raw = file_get_contents($help_file);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return '';
    }

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Get all help translation keys from the English help source file.
 *
 * @return list<string>
 */
function i18n_get_all_help_keys(): array
{
    $safe_code = 'en';
    $help_file = __DIR__ . '/../lang/help/' . $safe_code . '.json';

    if (!file_exists($help_file)) {
        return [];
    }

    $raw = file_get_contents($help_file);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }

    return array_keys(i18n_flatten($data));
}
