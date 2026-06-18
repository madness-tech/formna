<?php

use Bramus\Router\Router;

$router = new Router();

// Authentication routes
$router->get('/login', function() {
    require __DIR__ . '/../modules/users/controllers.php';
    show_login();
});

$router->post('/login', function() {
    require __DIR__ . '/../modules/users/controllers.php';
    do_login();
});

$router->get('/register', function() {
    require __DIR__ . '/../modules/users/controllers.php';
    show_register();
});

$router->post('/register', function() {
    require __DIR__ . '/../modules/users/controllers.php';
    do_register();
});

$router->post('/logout', function() {
    require __DIR__ . '/../modules/users/controllers.php';
    do_logout();
});

// Forgot Password
$router->get('/forgot-password', function() {
    require __DIR__ . '/../modules/users/controllers.php';
    show_forgot_password();
});

$router->post('/forgot-password', function() {
    require __DIR__ . '/../modules/users/controllers.php';
    do_forgot_password();
});

$router->get('/reset-password', function() {
    require __DIR__ . '/../modules/users/controllers.php';
    show_reset_password();
});

$router->post('/reset-password', function() {
    require __DIR__ . '/../modules/users/controllers.php';
    do_reset_password();
});

// Account Settings & Email Verification
$router->get('/settings', function() {
    require __DIR__ . '/../modules/account/controllers.php';
    show_account_settings();
});

$router->post('/settings/name', function() {
    require __DIR__ . '/../modules/account/controllers.php';
    update_account_name();
});

$router->post('/settings/email', function() {
    require __DIR__ . '/../modules/account/controllers.php';
    request_email_change();
});

$router->post('/settings/password', function() {
    require __DIR__ . '/../modules/account/controllers.php';
    change_password();
});

$router->post('/settings/timezone', function() {
    require __DIR__ . '/../modules/account/controllers.php';
    update_timezone();
});

$router->post('/settings/resend-verification', function() {
    require __DIR__ . '/../modules/account/controllers.php';
    resend_verification_email();
});

$router->get('/verify-email', function() {
    require __DIR__ . '/../modules/account/controllers.php';
    verify_registration_email();
});

$router->get('/verify-email-change', function() {
    require __DIR__ . '/../modules/account/controllers.php';
    verify_email_change();
});

$router->get('/verify-pending', function() {
    require __DIR__ . '/../modules/account/controllers.php';
    show_verify_pending();
});

$router->post('/verify-pending/resend', function() {
    require __DIR__ . '/../modules/account/controllers.php';
    resend_pending_verification();
});

// MFA routes (pre-login verification and setup)
$router->get('/mfa/verify', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_show_verify();
});

$router->post('/mfa/verify', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_do_verify();
});

$router->get('/mfa/setup', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_show_setup();
});

$router->post('/mfa/setup', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_do_setup();
});

$router->get('/mfa/setup/recovery', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_show_setup_recovery();
});

$router->post('/mfa/setup/recovery', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_do_setup_complete();
});

// MFA change routes (logged-in, password-gated)
$router->get('/mfa/change', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_show_change_auth();
});

$router->get('/mfa/enable', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_show_enable_auth();
});

$router->post('/mfa/change/auth', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_do_change_auth();
});

$router->get('/mfa/change/setup', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_show_change_setup();
});

$router->post('/mfa/change/setup', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_do_change_setup();
});

$router->get('/mfa/change/recovery', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_show_change_recovery();
});

$router->post('/mfa/change/confirm', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_do_change_confirm();
});

$router->post('/mfa/enable', function() {
    require __DIR__ . '/../modules/mfa/controllers.php';
    mfa_do_enable_auth();
});

// Root path - redirect based on authentication status
$router->get('/', function() {
    if (is_logged_in()) {
        redirect('/dashboard');
    } else {
        redirect('/login');
    }
});

$router->get('/dashboard', function() {
    require __DIR__ . '/../modules/submissions/controllers.php';
    dashboard();
});

$router->get('/submissions', function() {
    require __DIR__ . '/../modules/submissions/controllers.php';
    user_submissions_list();
});

$router->get('/forms', function() {
    require __DIR__ . '/../modules/submissions/controllers.php';
    browse_forms();
});

$router->get('/forms/([\w-]+)', function($uuid) {
    require __DIR__ . '/../modules/submissions/controllers.php';
    fill_form($uuid);
});


// Form filling and submission
$router->post('/forms/([\w-]+)/submit', function($uuid) {
    require __DIR__ . '/../modules/submissions/controllers.php';
    submit_form($uuid);
});

$router->get('/submissions/([\w-]+)', function($uuid) {
    require __DIR__ . '/../modules/submissions/controllers.php';
    view_submission($uuid);
});

$router->get('/submissions/([\w-]+)/edit', function($uuid) {
    require __DIR__ . '/../modules/submissions/controllers.php';
    edit_submission($uuid);
});

// File downloads (secured)
$router->get('/files/(\d+)/download', function($id) {
    require __DIR__ . '/../modules/submissions/controllers.php';
    download_file((int)$id);
});

// File preview (inline — images and PDFs rendered in browser)
$router->get('/files/(\d+)/preview', function($id) {
    require __DIR__ . '/../modules/submissions/controllers.php';
    preview_file((int)$id);
});

$router->post('/submissions/([\w-]+)/update', function($uuid) {
    require __DIR__ . '/../modules/submissions/controllers.php';
    update_submission($uuid);
});

// My Programs (User's enrollments/submissions)
$router->get('/my-programs', function() {
    require __DIR__ . '/../modules/programs/controllers.php';
    user_my_programs();
});

// Programs (User - browse available)
$router->get('/programs', function() {
    require __DIR__ . '/../modules/programs/controllers.php';
    user_programs_list();
});

$router->get('/programs/([\w-]+)', function($uuid) {
    require __DIR__ . '/../modules/programs/controllers.php';
    user_program_view($uuid);
});

$router->post('/programs/([\w-]+)/discard-draft', function($uuid) {
    require __DIR__ . '/../modules/programs/controllers.php';
    user_program_discard_draft($uuid);
});

$router->post('/programs/([\w-]+)/attach', function($uuid) {
    require __DIR__ . '/../modules/programs/controllers.php';
    user_program_attach($uuid);
});

$router->post('/programs/([\w-]+)/submit', function($uuid) {
    require __DIR__ . '/../modules/programs/controllers.php';
    user_program_submit($uuid);
});

// Clarification requests (User)
$router->get('/requests', function() {
    require __DIR__ . '/../modules/clarifications/controllers.php';
    clarification_user_list();
});

$router->get('/requests/([\w-]+)', function($uuid) {
    require __DIR__ . '/../modules/clarifications/controllers.php';
    clarification_respond_form($uuid);
});

$router->post('/requests/([\w-]+)', function($uuid) {
    require __DIR__ . '/../modules/clarifications/controllers.php';
    clarification_respond_submit($uuid);
});

// Help Center (User)
$router->get('/help', function() {
    require __DIR__ . '/../modules/help/controllers.php';
    help_index();
});

$router->get('/help/([\w-]+)', function($topic) {
    require __DIR__ . '/../modules/help/controllers.php';
    help_topic($topic);
});

// Admin routes (protected)
$router->mount('/admin', function() use ($router) {
    // Admin Dashboard - main overview page
    $router->get('/dashboard', function() {
        require __DIR__ . '/../modules/dashboard/controllers.php';
        admin_dashboard();
    });
    
    // Also handle /admin route (redirect to dashboard)
    $router->get('/', function() {
        redirect('/admin/dashboard');
    });
    
    // Forms Management
    $router->get('/forms', function() {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_index();
    });
    
    $router->get('/forms/create', function() {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_create();
    });
    
    $router->post('/forms', function() {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_store();
    });
    
    $router->get('/forms/([\w-]+)/builder', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_builder($uuid);
    });
    
    $router->post('/forms/([\w-]+)/create-draft', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_create_draft($uuid);
    });
    
    $router->post('/forms/([\w-]+)/discard-draft', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_discard_draft($uuid);
    });
    
    $router->get('/forms/([\w-]+)/settings', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_settings($uuid);
    });
    
    $router->post('/forms/([\w-]+)/settings', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_save_settings($uuid);
    });
    
    $router->get('/forms/([\w-]+)/scoring', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_scoring($uuid);
    });
    
    $router->post('/forms/([\w-]+)/scoring', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_save_scoring($uuid);
    });
    
    $router->get('/forms/([\w-]+)/preview', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_preview($uuid);
    });
    
    $router->post('/forms/([\w-]+)/publish', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_publish($uuid);
    });
    
    $router->post('/forms/([\w-]+)/new-version', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_new_version($uuid);
    });
    
    $router->post('/forms/([\w-]+)/unpublish', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_unpublish($uuid);
    });
    
    $router->post('/forms/([\w-]+)/republish', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_republish($uuid);
    });
    
    $router->post('/forms/([\w-]+)/delete', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_delete($uuid);
    });
    
    $router->get('/forms/([\w-]+)/submissions', function($uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_submissions_list($uuid);
    });
    
    $router->get('/forms/([\w-]+)/submissions/([\w-]+)', function($form_uuid, $submission_uuid) {
        require __DIR__ . '/../modules/forms/controllers.php';
        forms_submission_detail($form_uuid, $submission_uuid);
    });
    
    // All Submissions View (Admin)
    $router->get('/submissions', function() {
        require __DIR__ . '/../modules/submissions/controllers.php';
        admin_submissions_list();
    });
    
    // Submission Actions
    $router->get('/submissions/([\w-]+)', function($submission_uuid) {
        require __DIR__ . '/../modules/submissions/controllers.php';
        view_submission($submission_uuid);
    });
    
    // Clarification Requests (Admin)
    $router->get('/submissions/([\w-]+)/clarify', function($submission_uuid) {
        require __DIR__ . '/../modules/clarifications/controllers.php';
        clarification_create_form($submission_uuid);
    });
    
    $router->post('/submissions/([\w-]+)/clarify', function($submission_uuid) {
        require __DIR__ . '/../modules/clarifications/controllers.php';
        clarification_store($submission_uuid);
    });
    
    $router->get('/clarifications/([\w-]+)', function($uuid) {
        require __DIR__ . '/../modules/clarifications/controllers.php';
        admin_view_clarification($uuid);
    });
    
    // Email Templates Management (Super Admin only)
    $router->get('/email-templates', function() {
        require __DIR__ . '/../modules/emails/controllers.php';
        email_templates_manage();
    });
    
    $router->post('/email-templates/save', function() {
        require __DIR__ . '/../modules/emails/controllers.php';
        email_template_save();
    });
    
    $router->post('/email-templates/reset', function() {
        require __DIR__ . '/../modules/emails/controllers.php';
        email_template_reset();
    });
    
    $router->post('/email-templates/toggle', function() {
        require __DIR__ . '/../modules/emails/controllers.php';
        email_template_toggle();
    });
    
    $router->post('/email-templates/test', function() {
        require __DIR__ . '/../modules/emails/controllers.php';
        email_test_send();
    });
    
    // Programs Management
    $router->get('/programs', function() {
        require __DIR__ . '/../modules/programs/controllers.php';
        programs_index();
    });
    
    $router->post('/programs/store', function() {
        require __DIR__ . '/../modules/programs/controllers.php';
        program_store();
    });
    
    $router->post('/programs/delete', function() {
        require __DIR__ . '/../modules/programs/controllers.php';
        program_delete();
    });
    
    $router->get('/programs/([\w-]+)/setup', function($uuid) {
        require __DIR__ . '/../modules/programs/controllers.php';
        program_setup($uuid);
    });
    
    $router->post('/programs/([\w-]+)/setup', function($uuid) {
        require __DIR__ . '/../modules/programs/controllers.php';
        program_save($uuid);
    });
    
    $router->get('/programs/([\w-]+)/submissions', function($uuid) {
        require __DIR__ . '/../modules/programs/controllers.php';
        program_submissions_list($uuid);
    });
    
    $router->post('/programs/([\w-]+)/end', function($uuid) {
        require __DIR__ . '/../modules/programs/controllers.php';
        program_end($uuid);
    });
    
    $router->post('/programs/([\w-]+)/publish', function($uuid) {
        require __DIR__ . '/../modules/programs/controllers.php';
        program_publish($uuid);
    });
    
    $router->post('/programs/([\w-]+)/unpublish', function($uuid) {
        require __DIR__ . '/../modules/programs/controllers.php';
        program_unpublish($uuid);
    });
    
    // User Management
    $router->get('/users', function() {
        require __DIR__ . '/../modules/users/controllers.php';
        admin_users_index();
    });
    
    $router->get('/users/create', function() {
        require __DIR__ . '/../modules/users/controllers.php';
        admin_users_create();
    });
    
    $router->post('/users', function() {
        require __DIR__ . '/../modules/users/controllers.php';
        admin_users_store();
    });
    
    $router->get('/users/([\w-]+)/profile', function($uuid) {
        require __DIR__ . '/../modules/users/controllers.php';
        admin_users_profile($uuid);
    });
    
    $router->get('/users/([\w-]+)/edit', function($uuid) {
        require __DIR__ . '/../modules/users/controllers.php';
        admin_users_edit($uuid);
    });
    
    $router->post('/users/([\w-]+)', function($uuid) {
        require __DIR__ . '/../modules/users/controllers.php';
        admin_users_update($uuid);
    });
    
    $router->post('/users/([\w-]+)/reset-password', function($uuid) {
        require __DIR__ . '/../modules/users/controllers.php';
        admin_users_reset_password($uuid);
    });
    
    $router->post('/users/([\w-]+)/toggle-status', function($uuid) {
        require __DIR__ . '/../modules/users/controllers.php';
        admin_users_toggle_status($uuid);
    });
    
    $router->post('/users/([\w-]+)/reset-mfa', function($uuid) {
        require __DIR__ . '/../modules/mfa/controllers.php';
        admin_users_reset_mfa($uuid);
    });
    
    // MFA Settings (super admin only)
    $router->get('/mfa/settings', function() {
        require __DIR__ . '/../modules/mfa/controllers.php';
        mfa_admin_settings();
    });
    
    $router->post('/mfa/settings', function() {
        require __DIR__ . '/../modules/mfa/controllers.php';
        mfa_admin_settings_save();
    });
    
    // AI Settings (super admin only)
    $router->get('/ai-settings', function() {
        require __DIR__ . '/../modules/ai/controllers.php';
        ai_settings_page();
    });

    $router->post('/ai-settings', function() {
        require __DIR__ . '/../modules/ai/controllers.php';
        ai_settings_save();
    });

    $router->post('/ai-settings/test', function() {
        require __DIR__ . '/../modules/ai/controllers.php';
        ai_settings_test();
    });

    // Audit Log (for reviewers and above)
    $router->get('/audit-log', function() {
        require __DIR__ . '/../modules/audit/controllers.php';
        audit_log_index();
    });
    
    // Webhooks (super admin only)
    $router->get('/webhooks', function() {
        require __DIR__ . '/../modules/webhooks/controllers.php';
        webhooks_index();
    });
    
    $router->get('/webhooks/create', function() {
        require __DIR__ . '/../modules/webhooks/controllers.php';
        webhooks_create();
    });
    
    $router->post('/webhooks', function() {
        require __DIR__ . '/../modules/webhooks/controllers.php';
        webhooks_store();
    });
    
    $router->get('/webhooks/(\d+)/edit', function($id) {
        require __DIR__ . '/../modules/webhooks/controllers.php';
        webhooks_edit((int)$id);
    });
    
    $router->post('/webhooks/(\d+)', function($id) {
        require __DIR__ . '/../modules/webhooks/controllers.php';
        webhooks_update((int)$id);
    });
    
    $router->post('/webhooks/(\d+)/delete', function($id) {
        require __DIR__ . '/../modules/webhooks/controllers.php';
        webhooks_delete((int)$id);
    });
    
    $router->get('/webhooks/(\d+)/logs', function($id) {
        require __DIR__ . '/../modules/webhooks/controllers.php';
        webhooks_logs((int)$id);
    });
    
    $router->post('/webhooks/(\d+)/toggle', function($id) {
        require __DIR__ . '/../modules/webhooks/controllers.php';
        webhooks_toggle((int)$id);
    });
    
    // Review Queue (for reviewers)
    $router->get('/review-queue', function() {
        require __DIR__ . '/../modules/programs/controllers.php';
        program_review_queue();
    });
    
    $router->get('/review-queue/([\w-]+)', function($psUuid) {
        require __DIR__ . '/../modules/programs/controllers.php';
        program_review_submission($psUuid);
    });
    
    $router->post('/review-queue/([\w-]+)/decide', function($psUuid) {
        require __DIR__ . '/../modules/programs/controllers.php';
        program_decide($psUuid);
    });
    
    // Reports (admin and above)
    $router->get('/reports', function() {
        require __DIR__ . '/../modules/reports/controllers.php';
        reports_index();
    });
    
    $router->get('/reports/forms/([\w-]+)', function($uuid) {
        require __DIR__ . '/../modules/reports/controllers.php';
        reports_form_detail($uuid);
    });
    
    $router->get('/reports/programs/([\w-]+)', function($uuid) {
        require __DIR__ . '/../modules/reports/controllers.php';
        reports_program_detail($uuid);
    });
    
    $router->post('/reports/export', function() {
        require __DIR__ . '/../modules/reports/controllers.php';
        reports_export();
    });
    
    $router->post('/reports/export-program', function() {
        require __DIR__ . '/../modules/reports/controllers.php';
        reports_export_program();
    });
    
    $router->post('/reports/cross-tab', function() {
        require __DIR__ . '/../modules/reports/controllers.php';
        reports_cross_tab();
    });
    
    // Branding (super admin only)
    $router->get('/branding', function() {
        require __DIR__ . '/../modules/branding/controllers.php';
        branding_settings();
    });
    
    $router->post('/branding', function() {
        require __DIR__ . '/../modules/branding/controllers.php';
        branding_save();
    });
    
    $router->post('/branding/logo', function() {
        require __DIR__ . '/../modules/branding/controllers.php';
        branding_logo_upload();
    });
    
    $router->post('/branding/logo/delete', function() {
        require __DIR__ . '/../modules/branding/controllers.php';
        branding_logo_delete();
    });
    
    // Languages (super admin only)
    $router->get('/languages', function() {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_index();
    });
    
    $router->get('/languages/create', function() {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_create();
    });
    
    $router->get('/languages/template', function() {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_download_template();
    });
    
    $router->post('/languages', function() {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_store();
    });
    
    $router->get('/languages/(\d+)/edit', function($id) {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_edit((int)$id);
    });
    
    $router->post('/languages/import-bundled', function() {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_import_bundled();
    });

    $router->post('/languages/delete-bundled-file', function() {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_delete_bundled_file();
    });

    $router->post('/languages/(\d+)', function($id) {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_update((int)$id);
    });
    
    $router->post('/languages/(\d+)/translations', function($id) {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_save_translations((int)$id);
    });

    $router->post('/languages/(\d+)/restore-from-file', function($id) {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_restore_from_file((int)$id);
    });

    $router->get('/languages/help-template', function() {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_download_help_template();
    });

    $router->post('/languages/(\d+)/help-translations', function($id) {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_save_help_translations((int)$id);
    });

    $router->post('/languages/(\d+)/restore-help-from-file', function($id) {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_restore_help_from_file((int)$id);
    });
    
    $router->post('/languages/(\d+)/toggle', function($id) {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_toggle((int)$id);
    });
    
    $router->post('/languages/(\d+)/default', function($id) {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_set_default((int)$id);
    });
    
    $router->post('/languages/(\d+)/delete', function($id) {
        require __DIR__ . '/../modules/languages/controllers.php';
        languages_destroy((int)$id);
    });
});

// API routes for AJAX operations
$router->mount('/api', function() use ($router) {
    // Question Management API
    $router->post('/questions', function() {
        require __DIR__ . '/../modules/forms/api.php';
        api_add_question();
    });
    
    // IMPORTANT: More specific routes must come before generic ones
    $router->post('/questions/reorder', function() {
        require __DIR__ . '/../modules/forms/api.php';
        api_reorder_questions();
    });
    
    $router->get('/questions/([\w-]+)', function($uid) {
        require __DIR__ . '/../modules/forms/api.php';
        api_get_question($uid);
    });
    
    $router->delete('/questions/([\w-]+)', function($uid) {
        require __DIR__ . '/../modules/forms/api.php';
        api_delete_question($uid);
    });
    
    $router->post('/questions/([\w-]+)', function($uid) {
        require __DIR__ . '/../modules/forms/api.php';
        api_update_question($uid);
    });
    
    // Draft Management API
    $router->post('/drafts/([\w-]+)', function($form_uuid) {
        require __DIR__ . '/../modules/submissions/api.php';
        api_save_draft($form_uuid);
    });
    
    $router->get('/drafts/([\w-]+)', function($form_uuid) {
        require __DIR__ . '/../modules/submissions/api.php';
        api_load_draft($form_uuid);
    });
    
    $router->delete('/drafts/([\w-]+)', function($form_uuid) {
        require __DIR__ . '/../modules/submissions/api.php';
        api_delete_draft($form_uuid);
    });
    
    // File Upload API
    $router->post('/files/upload', function() {
        require __DIR__ . '/../modules/submissions/api.php';
        api_upload_file();
    });
    
    // File Delete API (remove pre-submission uploads)
    $router->delete('/files/(\d+)', function($id) {
        require __DIR__ . '/../modules/submissions/api.php';
        api_delete_file((int)$id);
    });
    
    // Notifications API
    $router->get('/notifications', function() {
        require __DIR__ . '/../modules/notifications/controllers.php';
        api_get_notifications();
    });
    
    $router->post('/notifications/(\d+)/read', function($id) {
        require __DIR__ . '/../modules/notifications/controllers.php';
        api_mark_notification_read((int)$id);
    });
    
    $router->post('/notifications/read-all', function() {
        require __DIR__ . '/../modules/notifications/controllers.php';
        api_mark_all_notifications_read();
    });
    
    $router->post('/notifications/clear-all', function() {
        require __DIR__ . '/../modules/notifications/controllers.php';
        api_clear_all_notifications();
    });
    
    // Clarification API
    $router->post('/clarifications/([\w-]+)/resolve', function($uuid) {
        require __DIR__ . '/../modules/clarifications/controllers.php';
        clarification_resolve($uuid);
    });
    
    $router->post('/clarifications/([\w-]+)/reject', function($uuid) {
        require __DIR__ . '/../modules/clarifications/controllers.php';
        clarification_reject($uuid);
    });
    
    $router->post('/clarifications/([\w-]+)/cancel', function($uuid) {
        require __DIR__ . '/../modules/clarifications/controllers.php';
        clarification_cancel($uuid);
    });
    
    // Admin Notifications API (same handlers, but under /api/admin so i18n
    // forces English — prevents end-user language leaking into admin UI)
    $router->get('/admin/notifications', function() {
        require __DIR__ . '/../modules/notifications/controllers.php';
        api_get_notifications();
    });
    
    $router->post('/admin/notifications/(\d+)/read', function($id) {
        require __DIR__ . '/../modules/notifications/controllers.php';
        api_mark_notification_read((int)$id);
    });
    
    $router->post('/admin/notifications/read-all', function() {
        require __DIR__ . '/../modules/notifications/controllers.php';
        api_mark_all_notifications_read();
    });
    
    $router->post('/admin/notifications/clear-all', function() {
        require __DIR__ . '/../modules/notifications/controllers.php';
        api_clear_all_notifications();
    });
    
    // AI API (admin/reviewer)
    $router->post('/admin/ai/summarize', function() {
        require __DIR__ . '/../modules/ai/controllers.php';
        api_ai_summarize();
    });

    $router->get('/admin/ai/summary/([\w_]+)/([\w-]+)', function($entityType, $entityUuid) {
        require __DIR__ . '/../modules/ai/controllers.php';
        api_ai_get_summary($entityType, $entityUuid);
    });

    // Form Admin Management API
    $router->get('/admin/users/search', function() {
        require __DIR__ . '/../modules/forms/api.php';
        api_admin_users_search();
    });
    
    $router->get('/admin/forms/([\w-]+)/admins', function($form_uuid) {
        require __DIR__ . '/../modules/forms/api.php';
        api_form_admins_list($form_uuid);
    });
    
    $router->post('/admin/forms/([\w-]+)/admins', function($form_uuid) {
        require __DIR__ . '/../modules/forms/api.php';
        api_form_admins_add($form_uuid);
    });
    
    $router->patch('/admin/forms/([\w-]+)/admins/(\d+)', function($form_uuid, $user_id) {
        require __DIR__ . '/../modules/forms/api.php';
        api_form_admins_update($form_uuid, (int)$user_id);
    });
    
    $router->delete('/admin/forms/([\w-]+)/admins/(\d+)', function($form_uuid, $user_id) {
        require __DIR__ . '/../modules/forms/api.php';
        api_form_admins_remove($form_uuid, (int)$user_id);
    });
    
});

return $router;
