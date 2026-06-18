<?php

/**
 * Branding Models
 * 
 * Database operations for site branding settings (brand name, color, logos).
 * Uses a single-row table (site_settings) with id=1.
 */

/**
 * Get current branding settings
 *
 * Returns the single branding row, or sensible defaults if the table
 * hasn't been seeded yet.
 *
 * @return array{brand_name: string, brand_color: string, logo_path: ?string, logo_path_dark: ?string, updated_at: ?string}
 */
function get_site_settings(): array
{
    $row = db_one('SELECT brand_name, brand_color, logo_path, logo_path_dark, updated_at FROM site_settings WHERE id = 1');

    return $row ?: [
        'brand_name'     => 'FORMNA',
        'brand_color'    => '#4f46e5',
        'logo_path'      => null,
        'logo_path_dark' => null,
        'updated_at'     => null,
    ];
}

/**
 * Update branding text settings (name + color)
 *
 * @param string $brand_name Display name (max 100 chars)
 * @param string $brand_color Hex color including # (e.g. #4f46e5)
 * @param int    $user_id     ID of the super admin making the change
 */
function update_site_branding(string $brand_name, string $brand_color, int $user_id): bool
{
    return db_update('site_settings', [
        'brand_name'  => $brand_name,
        'brand_color' => $brand_color,
        'updated_at'  => now(),
        'updated_by'  => $user_id,
    ], 'id = ?', [1]) >= 0;
}

/**
 * Update a logo filename in site settings
 *
 * @param string $column       Either 'logo_path' or 'logo_path_dark'
 * @param string|null $filename Logo filename (e.g., 'logo_1234567890.png'), or null to clear
 * @param int $user_id         ID of the super admin making the change
 */
function update_site_logo(string $column, string|null $filename, int $user_id): bool
{
    if (!in_array($column, ['logo_path', 'logo_path_dark'], true)) {
        return false;
    }

    if ($filename !== null) {
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.[a-zA-Z]{3,4}$/', $filename)) {
            error_log("update_site_logo: rejected invalid filename '{$filename}'");
            return false;
        }
    }

    return db_update('site_settings', [
        $column      => $filename,
        'updated_at' => now(),
        'updated_by' => $user_id,
    ], 'id = ?', [1]) >= 0;
}
