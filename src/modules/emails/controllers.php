<?php

/**
 * Email Template Controllers
 * Super Admin interface for managing global email templates
 */

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/sender.php';

/**
 * Show global email templates management page
 */
function email_templates_manage(): void {
    require_auth();
    require_role('super_admin');
    
    $templates = get_all_email_templates();
    $triggers = get_email_triggers();
    
    $template_map = [];
    foreach ($templates as $template) {
        $template_map[$template['trigger']] = $template;
    }
    
    $title = 'Email Templates';
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Email Templates']
    ];
    
    ob_start();
    include __DIR__ . '/views/manage.php';
    $content = ob_get_clean();
    
    include __DIR__ . '/../../layouts/admin.php';
}

/**
 * Save email template
 */
function email_template_save(): void {
    require_auth();
    require_role('super_admin');
    csrf_check();
    
    $trigger = $_POST['trigger'] ?? null;
    
    if (!$trigger) {
        flash('error', 'Missing required fields.');
        redirect('/admin/email-templates');
    }
    
    $triggers = get_email_triggers();
    if (!isset($triggers[$trigger])) {
        flash('error', 'Invalid trigger type.');
        redirect('/admin/email-templates');
    }
    
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $delay_days = !empty($_POST['delay_days']) ? intval($_POST['delay_days']) : null;
    $is_active = isset($_POST['is_active']);
    
    if (empty($subject) || empty($body)) {
        flash('error', 'Subject and body are required.');
        redirect('/admin/email-templates');
    }
    
    $template_id = save_email_template($trigger, $subject, $body, $delay_days, $is_active);
    
    log_audit('updated', 'email_template', $template_id, [
        'trigger' => $trigger
    ], null);
    
    flash('success', 'Email template saved successfully.');
    redirect('/admin/email-templates');
}

/**
 * Reset email template to its default content
 */
function email_template_reset(): void {
    require_auth();
    require_role('super_admin');
    csrf_check();
    
    $trigger = $_POST['trigger'] ?? null;
    
    if (!$trigger) {
        flash('error', 'Template trigger required.');
        redirect('/admin/email-templates');
    }
    
    $defaults = get_default_email_templates();
    
    if (!isset($defaults[$trigger])) {
        flash('error', 'No default content available for this template.');
        redirect('/admin/email-templates');
    }
    
    $default = $defaults[$trigger];
    $template_id = save_email_template(
        $trigger,
        $default['subject'],
        $default['body'],
        $default['delay_days'],
        true
    );
    
    log_audit('reset', 'email_template', $template_id, [
        'trigger' => $trigger
    ], null);
    
    flash('success', 'Email template has been reset to its default content.');
    redirect('/admin/email-templates');
}

/**
 * Toggle email template status
 */
function email_template_toggle(): void {
    require_auth();
    require_role('super_admin');
    csrf_check();
    
    $id = (json_input())['id'] ?? null;
    
    if (!$id) {
        json_response(['success' => false, 'message' => 'Template ID required'], 400);
    }
    
    $template = db_one("SELECT * FROM email_templates WHERE id = ?", [$id]);
    
    if (!$template) {
        json_response(['success' => false, 'message' => 'Template not found'], 404);
    }
    
    toggle_email_template($id);
    
    json_response(['success' => true]);
}

/**
 * Send test email
 */
function email_test_send(): void {
    require_auth();
    require_role('super_admin');
    csrf_check();
    
    $user = current_user();
    
    if (send_test_email($user['email'])) {
        flash('success', 'Test email sent to ' . $user['email']);
    } else {
        flash('error', 'Failed to send test email. Check error logs.');
    }
    
    redirect('/admin/email-templates');
}
