<?php

/**
 * Help Center Module - Controllers
 *
 * End-user help documentation integrated into the portal.
 * Help content is only shown if translations exist for the current language.
 */

/**
 * Help Center Landing Page
 */
function help_index(): void {
    require_auth();

    // Check if help content exists for current language
    if (!i18n_help_exists(current_locale())) {
        redirect('/dashboard');
    }

    $breadcrumbs = [
        ['label' => t('common.dashboard'), 'url' => '/dashboard'],
        ['label' => t('nav.help_center')],
    ];

    $title = t('help.help_center');
    
    ob_start();
    include __DIR__ . '/views/index.php';
    $content = ob_get_clean();

    include __DIR__ . '/../../layouts/user.php';
}

/**
 * Individual Help Topics
 */
function help_topic(string $topic): void {
    require_auth();

    // Check if help content exists for current language
    if (!i18n_help_exists(current_locale())) {
        redirect('/dashboard');
    }

    // Map of valid topics to their view files and titles
    $topics = [
        // Account & Settings
        'change-password' => ['title' => 'help.topics.change_password', 'view' => 'change-password'],
        'update-profile' => ['title' => 'help.topics.update_profile', 'view' => 'update-profile'],
        'change-email' => ['title' => 'help.topics.change_email', 'view' => 'change-email'],
        'change-language' => ['title' => 'help.topics.change_language', 'view' => 'change-language'],
        'set-timezone' => ['title' => 'help.topics.set_timezone', 'view' => 'set-timezone'],
        'mfa-setup' => ['title' => 'help.topics.mfa_setup', 'view' => 'mfa-setup'],
        
        // Forms & Submissions
        'find-forms' => ['title' => 'help.topics.find_forms', 'view' => 'find-forms'],
        'fill-form' => ['title' => 'help.topics.fill_form', 'view' => 'fill-form'],
        'save-draft' => ['title' => 'help.topics.save_draft', 'view' => 'save-draft'],
        'upload-files' => ['title' => 'help.topics.upload_files', 'view' => 'upload-files'],
        'submit-form' => ['title' => 'help.topics.submit_form', 'view' => 'submit-form'],
        'edit-submission' => ['title' => 'help.topics.edit_submission', 'view' => 'edit-submission'],
        
        // Programs
        'what-are-programs' => ['title' => 'help.topics.what_are_programs', 'view' => 'what-are-programs'],
        'apply-to-program' => ['title' => 'help.topics.apply_to_program', 'view' => 'apply-to-program'],
        'track-application' => ['title' => 'help.topics.track_application', 'view' => 'track-application'],
        
        // Managing Submissions
        'view-submissions' => ['title' => 'help.topics.view_submissions', 'view' => 'view-submissions'],
        'submission-statuses' => ['title' => 'help.topics.submission_statuses', 'view' => 'submission-statuses'],
        
        // Clarifications
        'what-is-clarification' => ['title' => 'help.topics.what_is_clarification', 'view' => 'what-is-clarification'],
        'respond-to-clarification' => ['title' => 'help.topics.respond_to_clarification', 'view' => 'respond-to-clarification'],
        'clarification-types' => ['title' => 'help.topics.clarification_types', 'view' => 'clarification-types'],
        
        // Notifications
        'view-notifications' => ['title' => 'help.topics.view_notifications', 'view' => 'view-notifications'],
        'email-not-received' => ['title' => 'help.topics.email_not_received', 'view' => 'email-not-received'],
    ];

    if (!isset($topics[$topic])) {
        http_response_code(404);
        die('Help topic not found');
    }

    $topic_data = $topics[$topic];
    $title = t($topic_data['title']);
    $view_file = __DIR__ . '/views/' . $topic_data['view'] . '.php';

    if (!file_exists($view_file)) {
        http_response_code(404);
        die('Help content not found');
    }

    $breadcrumbs = [
        ['label' => t('common.dashboard'), 'url' => '/dashboard'],
        ['label' => t('nav.help_center'), 'url' => '/help'],
        ['label' => $title],
    ];

    ob_start();
    include $view_file;
    $content = ob_get_clean();

    include __DIR__ . '/../../layouts/user.php';
}
