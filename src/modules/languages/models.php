<?php

/**
 * Language Models
 *
 * Database operations for the languages table.
 */

/**
 * Get all languages ordered by sort_order
 *
 * @return list<array<string, mixed>>
 */
function languages_get_all(): array
{
    return db_query('SELECT * FROM languages ORDER BY sort_order ASC, name ASC');
}

/**
 * Get a single language by ID
 *
 * @return array<string, mixed>|null
 */
function language_get(int $id): ?array
{
    return db_one('SELECT * FROM languages WHERE id = :id', ['id' => $id]);
}

/**
 * Get a single language by code
 *
 * @return array<string, mixed>|null
 */
function language_get_by_code(string $code): ?array
{
    return db_one('SELECT * FROM languages WHERE code = :code', ['code' => $code]);
}

/**
 * Get the current default language
 *
 * @return array<string, mixed>|null
 */
function language_get_default(): ?array
{
    return db_one('SELECT * FROM languages WHERE is_default = 1 LIMIT 1');
}

/**
 * Get count of active languages
 */
function languages_count_active(): int
{
    $row = db_one('SELECT COUNT(*) as cnt FROM languages WHERE is_active = 1');
    return (int) ($row['cnt'] ?? 0);
}

/**
 * Create a new language
 *
 * @param array<string, mixed> $data
 */
function language_create(array $data): int
{
    // Get next sort_order
    $row = db_one('SELECT MAX(sort_order) as max_sort FROM languages');
    $sort_order = ($row['max_sort'] ?? 0) + 1;

    return db_insert('languages', [
        'code'         => $data['code'],
        'name'         => $data['name'],
        'native_name'  => $data['native_name'],
        'direction'    => $data['direction'],
        'translations' => $data['translations'] ?? null,
        'is_active'    => $data['is_active'] ?? 0,
        'is_default'   => 0,
        'is_system'    => 0,
        'sort_order'   => $sort_order,
        'created_at'   => now(),
    ]);
}

/**
 * Update language metadata (name, native_name, direction)
 *
 * @param array<string, mixed> $data
 */
function language_update(int $id, array $data): bool
{
    $fields = [
        'name'        => $data['name'],
        'native_name' => $data['native_name'],
        'direction'   => $data['direction'],
        'updated_at'  => now(),
    ];

    return db_update('languages', $fields, 'id = ?', [$id]) >= 0;
}

/**
 * Update translations JSON for a language
 */
function language_update_translations(int $id, ?string $translations_json): bool
{
    return db_update('languages', [
        'translations' => $translations_json,
        'updated_at'   => now(),
    ], 'id = ?', [$id]) >= 0;
}

/**
 * Update help translations JSON for a language
 */
function language_update_help_translations(int $id, ?string $help_translations_json): bool
{
    return db_update('languages', [
        'help_translations' => $help_translations_json,
        'updated_at'        => now(),
    ], 'id = ?', [$id]) >= 0;
}

/**
 * Set a language as active
 */
function language_activate(int $id): bool
{
    return db_update('languages', [
        'is_active'  => 1,
        'updated_at' => now(),
    ], 'id = ?', [$id]) >= 0;
}

/**
 * Set a language as inactive
 */
function language_deactivate(int $id): bool
{
    return db_update('languages', [
        'is_active'  => 0,
        'updated_at' => now(),
    ], 'id = ?', [$id]) >= 0;
}

/**
 * Set a language as the default.
 * Clears default flag from all other languages.
 */
function language_set_default(int $id): bool
{
    return db_transaction(function () use ($id) {
        // Clear current default
        db_exec('UPDATE languages SET is_default = 0 WHERE is_default = 1');

        // Set new default (also ensure it's active)
        db_exec(
            'UPDATE languages SET is_default = 1, is_active = 1, updated_at = :now WHERE id = :id',
            ['now' => now(), 'id' => $id]
        );

        return true;
    });
}

/**
 * Delete a language (only if not system and not default)
 */
function language_delete(int $id): bool
{
    $lang = language_get($id);
    if (!$lang || $lang['is_system'] || $lang['is_default']) {
        return false;
    }

    return db_exec('DELETE FROM languages WHERE id = :id AND is_system = 0 AND is_default = 0', ['id' => $id]) > 0;
}
