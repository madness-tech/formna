<?php

/**
 * Language Management Controllers
 *
 * Super Admin interface for managing UI languages.
 */

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/../../core/i18n.php';
require_once __DIR__ . '/../audit/logger.php';

// ---------------------------------------------------------------------------
// Shared helpers (redirect() returns `never`, so callers can trust the return)
// ---------------------------------------------------------------------------

/** @return array<string, mixed> */
function require_language(int $id): array
{
    $language = language_get($id);
    if (!$language) {
        flash('error', 'Language not found.');
        redirect('/admin/languages');
    }
    return $language;
}

function safe_language_code(string $code): string
{
    $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', $code);
    return substr($clean, 0, 21);
}

/** @return array<string, mixed> */
function decode_json_payload(string $raw, string $redirect_url): array
{
    if (strlen($raw) > 2 * 1024 * 1024) {
        flash('error', 'Payload is too large (maximum 2 MB).');
        redirect($redirect_url);
    }

    $decoded = json_decode($raw, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        flash('error', 'Invalid JSON format: ' . json_last_error_msg());
        redirect($redirect_url);
    }

    return $decoded;
}

/** @return array<string, mixed> */
function load_bundled_json(string $path, string $redirect_url, string $label = 'file'): array
{
    if (!file_exists($path)) {
        flash('error', 'Bundled ' . $label . ' not found.');
        redirect($redirect_url);
    }

    $raw  = file_get_contents($path);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        flash('error', 'Bundled ' . $label . ' contains invalid JSON.');
        redirect($redirect_url);
    }

    return $data;
}

// ---------------------------------------------------------------------------
// Controllers
// ---------------------------------------------------------------------------

/**
 * List all languages
 */
function languages_index(): void
{
    require_auth();
    require_role('super_admin');

    $languages = languages_get_all();

    // Attach missing-key counts
    foreach ($languages as &$lang) {
        if ($lang['translations']) {
            $decoded = is_string($lang['translations']) ? json_decode($lang['translations'], true) : $lang['translations'];
            $lang['missing_count'] = $decoded ? i18n_count_missing($decoded) : count(i18n_get_all_keys());
        } else {
            // No DB translations — count from file
            $file_trans = i18n_load_file_translations($lang['code']);
            if (empty($file_trans)) {
                $lang['missing_count'] = count(i18n_get_all_keys());
            } else {
                $all_keys = i18n_get_all_keys();
                $missing = 0;
                foreach ($all_keys as $key) {
                    if (!isset($file_trans[$key]) || $file_trans[$key] === '') {
                        $missing++;
                    }
                }
                $lang['missing_count'] = $missing;
            }
        }
    }
    unset($lang);

    // Discover bundled lang files (UI) not yet imported into DB
    $db_codes        = array_column($languages, 'code');
    $bundled_available = [];
    $ui_dir          = __DIR__ . '/../../lang/ui/';
    $ui_files        = glob($ui_dir . '*.json') ?: [];

    foreach ($ui_files as $file_path) {
        $code = pathinfo($file_path, PATHINFO_FILENAME);

        // Skip languages that are already in the DB (system or previously imported)
        if (in_array($code, $db_codes, true)) {
            continue;
        }

        $raw  = file_get_contents($file_path);
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['_meta'])) {
            continue;
        }

        $meta               = $data['_meta'];
        $bundled_available[] = [
            'code'        => $code,
            'name'        => $meta['language'] ?? $code,
            'direction'   => $meta['direction'] ?? 'ltr',
        ];
    }

    $title = 'Languages';
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Languages'],
    ];

    ob_start();
    require __DIR__ . '/views/index.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Show create language form
 */
function languages_create(): void
{
    require_auth();
    require_role('super_admin');

    $title = 'Create Language';
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Languages', 'url' => '/admin/languages'],
        ['label' => 'Create'],
    ];

    ob_start();
    require __DIR__ . '/views/create.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Store a new language
 */
function languages_store(): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $code        = trim($_POST['code'] ?? '');
    $name        = trim($_POST['name'] ?? '');
    $native_name = trim($_POST['native_name'] ?? '');
    $direction   = $_POST['direction'] ?? 'ltr';
    $is_active   = isset($_POST['is_active']) ? 1 : 0;
    $translations_raw = trim($_POST['translations'] ?? '');

    if ($code === '' || $name === '' || $native_name === '') {
        flash('error', 'Code, Name, and Native Name are required.');
        redirect('/admin/languages/create');
    }

    if (!preg_match('/^[a-z]{2,10}(-[a-zA-Z]{2,10})?$/', $code)) {
        flash('error', 'Invalid language code. Use ISO format (e.g., fr, de, zh-CN).');
        redirect('/admin/languages/create');
    }

    $code = safe_language_code($code);

    if (!in_array($direction, ['ltr', 'rtl'], true)) {
        $direction = 'ltr';
    }

    if (language_get_by_code($code)) {
        flash('error', 'A language with code "' . sanitize($code) . '" already exists.');
        redirect('/admin/languages/create');
    }

    $translations = null;
    if ($translations_raw !== '') {
        $decoded = decode_json_payload($translations_raw, '/admin/languages/create');
        $translations = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    $id = language_create([
        'code'         => $code,
        'name'         => $name,
        'native_name'  => $native_name,
        'direction'    => $direction,
        'translations' => $translations,
        'is_active'    => $is_active,
    ]);

    log_audit('language_created', 'languages', $id, [
        'code' => $code,
        'name' => $name,
    ], null);

    flash('success', 'Language created successfully.');
    redirect('/admin/languages');
}

/**
 * Show edit language form
 */
function languages_edit(int $id): void
{
    require_auth();
    require_role('super_admin');

    $language = require_language($id);

    $title = 'Edit Language: ' . sanitize($language['name']);
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Languages', 'url' => '/admin/languages'],
        ['label' => 'Edit Language'],
    ];

    // Prepare translations for textarea
    $translations_json = '';
    if ($language['translations']) {
        $decoded = is_string($language['translations']) ? json_decode($language['translations'], true) : $language['translations'];
        $translations_json = $decoded ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';
    }

    // Get all keys from English for reference
    $all_keys = i18n_get_all_keys();
    $total_keys = count($all_keys);

    // Count translated
    $flat = [];
    if ($translations_json) {
        $decoded_flat = json_decode($translations_json, true);
        if ($decoded_flat) {
            $flat = i18n_flatten($decoded_flat);
        }
    } else {
        $flat = i18n_load_file_translations($language['code']);
    }

    $translated_count = 0;
    foreach ($all_keys as $key) {
        if (isset($flat[$key]) && $flat[$key] !== '') {
            $translated_count++;
        }
    }

    $has_bundled_file = file_exists(
        __DIR__ . '/../../lang/ui/' . safe_language_code($language['code']) . '.json'
    );

    // Help translations: DB overrides take precedence, file is the bundled default
    $has_bundled_help_file = file_exists(
        __DIR__ . '/../../lang/help/' . safe_language_code($language['code']) . '.json'
    );

    $help_translations_json = '';
    if (!empty($language['help_translations'])) {
        $decoded = is_string($language['help_translations'])
            ? json_decode($language['help_translations'], true)
            : $language['help_translations'];
        $help_translations_json = $decoded
            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
    }
    if ($help_translations_json === '') {
        $help_translations_json = i18n_load_help_file_raw($language['code']);
    }

    // Help translation stats
    $help_all_keys     = i18n_get_all_help_keys();
    $help_total_keys   = count($help_all_keys);
    $help_translated   = 0;
    if ($help_translations_json) {
        $help_decoded = json_decode($help_translations_json, true);
        if ($help_decoded) {
            $help_flat = i18n_flatten($help_decoded);
            foreach ($help_all_keys as $hk) {
                if (isset($help_flat[$hk]) && $help_flat[$hk] !== '') {
                    $help_translated++;
                }
            }
        }
    }

    ob_start();
    require __DIR__ . '/views/edit.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Update language metadata
 */
function languages_update(int $id): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $language = require_language($id);

    $name        = trim($_POST['name'] ?? '');
    $native_name = trim($_POST['native_name'] ?? '');
    $direction   = $_POST['direction'] ?? 'ltr';

    if ($name === '' || $native_name === '') {
        flash('error', 'Name and Native Name are required.');
        redirect('/admin/languages/' . $id . '/edit');
    }

    if (!in_array($direction, ['ltr', 'rtl'], true)) {
        $direction = 'ltr';
    }

    language_update($id, [
        'name'        => $name,
        'native_name' => $native_name,
        'direction'   => $direction,
    ]);

    log_audit('language_updated', 'languages', $id, [
        'name' => $name,
        'direction' => $direction,
    ], null);

    flash('success', 'Language updated successfully.');
    redirect('/admin/languages/' . $id . '/edit');
}

/**
 * Save translations JSON
 */
function languages_save_translations(int $id): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $language   = require_language($id);
    $redirect   = '/admin/languages/' . $id . '/edit';
    $translations_raw = trim($_POST['translations'] ?? '');

    if ($translations_raw === '') {
        language_update_translations($id, null);
        flash('success', 'Translations cleared.');
        redirect($redirect);
    }

    $decoded = decode_json_payload($translations_raw, $redirect);

    $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    language_update_translations($id, $json);

    log_audit('language_translations_updated', 'languages', $id, [
        'code' => $language['code'],
    ], null);

    flash('success', 'Translations updated successfully.');
    redirect($redirect);
}

/**
 * Toggle language active status
 */
function languages_toggle(int $id): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $language = require_language($id);

    if ($language['is_active']) {
        if ($language['is_default']) {
            flash('error', 'Cannot deactivate the default language. Set a different default first.');
            redirect('/admin/languages');
        }

        if (languages_count_active() <= 1) {
            flash('error', 'At least one language must remain active.');
            redirect('/admin/languages');
        }

        language_deactivate($id);

        log_audit('language_deactivated', 'languages', $id, ['code' => $language['code']], null);
        flash('success', 'Language "' . sanitize($language['name']) . '" deactivated.');
    } else {
        language_activate($id);

        log_audit('language_activated', 'languages', $id, ['code' => $language['code']], null);
        flash('success', 'Language "' . sanitize($language['name']) . '" activated.');
    }

    redirect('/admin/languages');
}

/**
 * Set a language as default
 */
function languages_set_default(int $id): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $language = require_language($id);

    language_set_default($id);

    log_audit('language_default_changed', 'languages', $id, ['code' => $language['code']], null);
    flash('success', 'Default language changed to "' . sanitize($language['name']) . '".');
    redirect('/admin/languages');
}

/**
 * Delete a language
 */
function languages_destroy(int $id): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $language = require_language($id);

    if ($language['is_system']) {
        flash('error', 'System languages (English and Arabic) cannot be deleted.');
        redirect('/admin/languages');
    }

    if ($language['is_default']) {
        flash('error', 'Cannot delete the default language. Change the default first.');
        redirect('/admin/languages');
    }

    language_delete($id);

    log_audit('language_deleted', 'languages', $id, ['code' => $language['code'], 'name' => $language['name']], null);
    flash('success', 'Language "' . sanitize($language['name']) . '" deleted.');
    redirect('/admin/languages');
}

/**
 * Download English UI template JSON for reference
 */
function languages_download_template(): void
{
    require_auth();
    require_role('super_admin');

    $file = __DIR__ . '/../../lang/ui/en.json';
    if (!file_exists($file)) {
        flash('error', 'English UI template file not found.');
        redirect('/admin/languages');
    }

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="ui_translation_template.json"');
    echo file_get_contents($file);
    exit;
}

/**
 * Download English Help template JSON for reference
 */
function languages_download_help_template(): void
{
    require_auth();
    require_role('super_admin');

    $file = __DIR__ . '/../../lang/help/en.json';
    if (!file_exists($file)) {
        flash('error', 'English help template file not found.');
        redirect('/admin/languages');
    }

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="help_translation_template.json"');
    echo file_get_contents($file);
    exit;
}

/**
 * Import a bundled language into the DB.
 *
 * Reads app/src/lang/ui/{code}.json for UI translations and
 * app/src/lang/help/{code}.json for help translations (if present),
 * then creates a new (inactive) language row pre-populated with both.
 */
function languages_import_bundled(): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $code = safe_language_code(trim($_POST['code'] ?? ''));

    if ($code === '') {
        flash('error', 'Invalid language code.');
        redirect('/admin/languages');
    }

    if (in_array($code, ['en', 'ar'], true)) {
        flash('error', 'System languages are already installed.');
        redirect('/admin/languages');
    }

    if (language_get_by_code($code)) {
        flash('error', 'This language is already in the database.');
        redirect('/admin/languages');
    }

    $ui_data = load_bundled_json(
        __DIR__ . '/../../lang/ui/' . $code . '.json',
        '/admin/languages',
        'UI language file'
    );

    $meta        = $ui_data['_meta'] ?? [];
    $name        = $meta['language'] ?? $code;
    $native_name = $meta['native_name'] ?? $name;
    $direction   = in_array($meta['direction'] ?? 'ltr', ['ltr', 'rtl'], true)
                   ? $meta['direction']
                   : 'ltr';

    $id = language_create([
        'code'         => $code,
        'name'         => $name,
        'native_name'  => $native_name,
        'direction'    => $direction,
        'translations' => json_encode($ui_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        'is_active'    => 0,
    ]);

    $help_file = __DIR__ . '/../../lang/help/' . $code . '.json';
    if (file_exists($help_file)) {
        $help_raw  = file_get_contents($help_file);
        $help_data = json_decode($help_raw, true);
        if (is_array($help_data)) {
            language_update_help_translations(
                $id,
                json_encode($help_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }
    }

    log_audit('language_imported_from_bundle', 'languages', $id, [
        'code' => $code,
        'name' => $name,
    ], null);

    flash('success', 'Language "' . sanitize($name) . '" imported. Activate it when ready.');
    redirect('/admin/languages');
}

/**
 * Restore a language's UI translations from its bundled file,
 * overwriting any custom edits saved in the DB.
 */
function languages_restore_from_file(int $id): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $language = require_language($id);
    $code     = safe_language_code($language['code']);
    $redirect = '/admin/languages/' . $id . '/edit';

    $data = load_bundled_json(
        __DIR__ . '/../../lang/ui/' . $code . '.json',
        $redirect,
        'UI file'
    );

    language_update_translations($id, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    log_audit('language_translations_restored_from_file', 'languages', $id, [
        'code' => $code,
    ], null);

    flash('success', 'UI translations restored and saved to the database.');
    redirect($redirect);
}

/**
 * Permanently delete a bundled language file from the filesystem.
 *
 * Only non-system files (i.e., not en.json / ar.json) may be deleted.
 * If the language has already been imported into the DB, the DB record
 * is unaffected — only the physical file is removed.
 */
function languages_delete_bundled_file(): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $code = safe_language_code(trim($_POST['code'] ?? ''));

    if ($code === '') {
        flash('error', 'Invalid language code.');
        redirect('/admin/languages');
    }

    if (in_array($code, ['en', 'ar'], true)) {
        flash('error', 'System language files cannot be deleted.');
        redirect('/admin/languages');
    }

    $file_path = __DIR__ . '/../../lang/ui/' . $code . '.json';

    if (!file_exists($file_path)) {
        flash('error', 'Bundled language file not found.');
        redirect('/admin/languages');
    }

    if (!unlink($file_path)) {
        flash('error', 'Failed to delete the bundled language file. Check server file permissions.');
        redirect('/admin/languages');
    }

    $help_file = __DIR__ . '/../../lang/help/' . $code . '.json';
    if (file_exists($help_file)) {
        unlink($help_file);
    }

    log_audit('language_bundle_file_deleted', 'languages', 0, [
        'code' => $code,
    ], null);

    flash('success', 'Bundled file for "' . sanitize($code) . '" removed from server.');
    redirect('/admin/languages');
}

/**
 * Save help translations JSON to the database.
 *
 * Help translations are stored in languages.help_translations (DB column)
 * and overlay the bundled lang/help/{code}.json file at runtime.
 * Filesystem help files are never modified.
 */
function languages_save_help_translations(int $id): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $language = require_language($id);
    $code     = safe_language_code($language['code']);
    $redirect = '/admin/languages/' . $id . '/edit';
    $help_translations_raw = trim($_POST['help_translations'] ?? '');

    if ($help_translations_raw === '') {
        language_update_help_translations($id, null);

        log_audit('language_help_translations_cleared', 'languages', $id, [
            'code' => $code,
        ], null);

        flash('success', 'Help translations cleared.');
        redirect($redirect);
    }

    $decoded = decode_json_payload($help_translations_raw, $redirect);

    if (!isset($decoded['help']) || !is_array($decoded['help'])) {
        flash('error', 'Invalid help translation structure: JSON must contain a top-level "help" object. Use the English template as a reference.');
        redirect($redirect);
    }

    if (!isset($decoded['_meta'])) {
        $decoded = array_merge([
            '_meta' => [
                'language'  => $language['name'],
                'code'      => $code,
                'direction' => $language['direction'],
                'version'   => '1.0.0',
            ]
        ], $decoded);
    }

    $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    language_update_help_translations($id, $json);

    log_audit('language_help_translations_updated', 'languages', $id, [
        'code' => $code,
    ], null);

    flash('success', 'Help translations saved.');
    redirect($redirect);
}

/**
 * Restore a language's help translations from its bundled file,
 * overwriting any custom edits stored in the DB.
 *
 * Reads the read-only lang/help/{code}.json and writes the content
 * into the DB help_translations column — same pattern as UI restore.
 */
function languages_restore_help_from_file(int $id): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $language = require_language($id);
    $code     = safe_language_code($language['code']);
    $redirect = '/admin/languages/' . $id . '/edit';

    $data = load_bundled_json(
        __DIR__ . '/../../lang/help/' . $code . '.json',
        $redirect,
        'help file'
    );

    language_update_help_translations(
        $id,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    log_audit('language_help_translations_restored_from_file', 'languages', $id, [
        'code' => $code,
    ], null);

    flash('success', 'Help translations restored and saved to the database.');
    redirect($redirect);
}
