<?php

/**
 * Branding Controllers
 *
 * Super Admin interface for managing site branding (name, color, logos).
 */

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/../../core/verification.php';
require_once __DIR__ . '/../audit/logger.php';

/**
 * Show branding settings page
 */
function branding_settings(): void
{
    require_auth();
    require_role('super_admin');

    $settings = get_site_settings();
    $has_override = has_css_override();

    $title = 'Branding';
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Branding'],
    ];

    ob_start();
    require __DIR__ . '/views/settings.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Save brand name and color
 */
function branding_save(): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    // Block changes if CSS override is active
    if (has_css_override()) {
        flash('error', 'Branding changes are disabled while a custom CSS override file is active.');
        redirect('/admin/branding');
    }

    $user_id    = current_user()['id'];
    $brand_name = trim($_POST['brand_name'] ?? '');
    $brand_color = trim($_POST['brand_color'] ?? '');

    // Validate brand name
    if ($brand_name === '') {
        flash('error', 'Brand name cannot be empty.');
        redirect('/admin/branding');
    }

    if (mb_strlen($brand_name) > 100) {
        flash('error', 'Brand name is too long (max 100 characters).');
        redirect('/admin/branding');
    }

    // Validate hex color
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $brand_color)) {
        flash('error', 'Invalid color format. Use a 6-digit hex color (e.g. #4f46e5).');
        redirect('/admin/branding');
    }

    if (update_site_branding($brand_name, $brand_color, $user_id)) {
        branding_cache_clear();

        log_audit('branding_updated', 'site_settings', 1, [
            'brand_name'  => $brand_name,
            'brand_color' => $brand_color,
        ], null);

        flash('success', 'Branding settings saved successfully.');
    } else {
        flash('error', 'Failed to save branding settings. Please try again.');
    }

    redirect('/admin/branding');
}

/**
 * Upload a logo (light or dark variant)
 *
 * Expects POST param 'variant' = 'light' | 'dark'
 */
function branding_logo_upload(): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    // Block changes if CSS override is active
    if (has_css_override()) {
        flash('error', 'Branding changes are disabled while a custom CSS override file is active.');
        redirect('/admin/branding');
    }

    $user_id = current_user()['id'];
    $variant = $_POST['variant'] ?? 'light';

    if (!in_array($variant, ['light', 'dark'], true)) {
        flash('error', 'Invalid logo variant.');
        redirect('/admin/branding');
    }

    $column = $variant === 'dark' ? 'logo_path_dark' : 'logo_path';

    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'No file uploaded or upload failed.');
        redirect('/admin/branding');
    }

    $file = $_FILES['logo'];

    // Validate file size (max 2 MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        flash('error', 'Logo file is too large (max 2 MB).');
        redirect('/admin/branding');
    }

    // SECURITY: Validate extension against SAFE_FILE_EXTENSIONS (single source of truth)
    // Constants and normalize_file_extension() live in core/helpers.php (autoloaded).
    $ext = normalize_file_extension(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)));
    $allowed_image_extensions = array_values(array_intersect(
        SAFE_FILE_EXTENSIONS,
        ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']
    ));

    if (!in_array($ext, $allowed_image_extensions, true)) {
        flash('error', 'Invalid file type. Allowed: ' . strtoupper(implode(', ', $allowed_image_extensions)) . '.');
        redirect('/admin/branding');
    }

    // SECURITY: Validate MIME type matches extension (defense in depth)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $mime_extensions = MIME_TYPE_MAPPING[$mime] ?? [];

    if (!in_array($ext, $mime_extensions, true)) {
        flash('error', 'File content does not match its extension.');
        redirect('/admin/branding');
    }

    // Get upload directory from config
    global $config;
    $upload_dir = $config['branding']['upload_dir'];
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Save with a cache-busting filename
    $prefix = $variant === 'dark' ? 'logo_dark_' : 'logo_';
    $filename = $prefix . time() . '.' . $ext;
    $dest_path = $upload_dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        flash('error', 'Failed to save uploaded file.');
        redirect('/admin/branding');
    }

    // Capture old logo path before updating DB
    $current = get_site_settings();
    $old_logo = $current[$column] ?? null;

    if (update_site_logo($column, $filename, $user_id)) {
        // Only delete the old file after DB is successfully updated
        if ($old_logo) {
            $old_file = $upload_dir . '/' . $old_logo;
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        branding_cache_clear();

        log_audit('branding_logo_uploaded', 'site_settings', 1, [
            'variant'  => $variant,
            'filename' => $filename,
        ], null);

        $label = $variant === 'dark' ? 'dark background' : 'light background';
        flash('success', "Logo ($label) uploaded successfully.");
    } else {
        // DB update failed — clean up the newly uploaded file
        if (file_exists($dest_path)) {
            unlink($dest_path);
        }
        flash('error', 'Failed to save logo setting.');
    }

    redirect('/admin/branding');
}

/**
 * Delete a logo (light or dark variant)
 */
function branding_logo_delete(): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    // Block changes if CSS override is active
    if (has_css_override()) {
        flash('error', 'Branding changes are disabled while a custom CSS override file is active.');
        redirect('/admin/branding');
    }

    $user_id = current_user()['id'];
    $variant = $_POST['variant'] ?? 'light';

    if (!in_array($variant, ['light', 'dark'], true)) {
        flash('error', 'Invalid logo variant.');
        redirect('/admin/branding');
    }

    $column = $variant === 'dark' ? 'logo_path_dark' : 'logo_path';
    $current = get_site_settings();

    if ($current[$column]) {
        global $config;
        $upload_dir = $config['branding']['upload_dir'];
        $file_path = $upload_dir . '/' . $current[$column];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    if (update_site_logo($column, null, $user_id)) {
        branding_cache_clear();

        log_audit('branding_logo_removed', 'site_settings', 1, [
            'variant' => $variant,
        ], null);

        $label = $variant === 'dark' ? 'dark background' : 'light background';
        flash('success', "Logo ($label) removed.");
    } else {
        flash('error', 'Failed to remove logo.');
    }

    redirect('/admin/branding');
}
