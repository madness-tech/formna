<?php

/**
 * Dashboard Module - Controllers
 *
 * Role-specific admin dashboard for super_admin, admin, and reviewer.
 */

require_once __DIR__ . '/models.php';

/**
 * Admin Dashboard — role-aware overview page
 */
function admin_dashboard(): void {
    require_auth();
    require_role('reviewer');

    $user = current_user();
    $role = $user['role'];

    if ($role === 'super_admin') {
        $data = get_super_admin_dashboard();
    } elseif ($role === 'admin') {
        $data = get_admin_dashboard($user['id']);
    } else {
        $data = get_reviewer_dashboard($user['id']);
    }

    // Admin UI uses fixed English greeting to avoid locale bleed from end-user setting
    $greeting = admin_greeting($user['timezone'] ?? 'UTC');

    $title = 'Dashboard';
    $breadcrumbs = [
        ['label' => 'Dashboard'],
    ];

    ob_start();
    include __DIR__ . '/views/index.php';
    $content = ob_get_clean();

    include __DIR__ . '/../../layouts/admin.php';
}
