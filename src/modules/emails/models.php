<?php

/**
 * Email Templates Models
 * Database operations for global email template management
 */

/**
 * Get email template by trigger
 * @return array<string, mixed>|null
 */
function get_email_template(string $trigger): ?array {
    return db_one(
        "SELECT * FROM email_templates WHERE `trigger` = ? AND is_active = 1 LIMIT 1",
        [$trigger]
    );
}

/**
 * Get all email templates
 * @return array<array<string,mixed>>
 */
function get_all_email_templates(): array {
    return db_query("SELECT * FROM email_templates ORDER BY `trigger` ASC");
}

/**
 * Save or update email template
 * Uses transaction with row-level locking to prevent race conditions
 */
function save_email_template(string $trigger, string $subject, string $body, ?int $delay_days = null, bool $is_active = true): int {
    return db_transaction(function() use ($trigger, $subject, $body, $delay_days, $is_active) {
        $existing = db_one(
            "SELECT id FROM email_templates WHERE `trigger` = ? FOR UPDATE",
            [$trigger]
        );

        if ($existing) {
            db_update(
                'email_templates',
                [
                    'subject' => $subject,
                    'body' => $body,
                    'delay_days' => $delay_days,
                    'is_active' => $is_active ? 1 : 0,
                    'updated_at' => now()
                ],
                'id = ?',
                [$existing['id']]
            );
            return $existing['id'];
        }

        return db_insert('email_templates', [
            'trigger' => $trigger,
            'subject' => $subject,
            'body' => $body,
            'delay_days' => $delay_days,
            'is_active' => $is_active ? 1 : 0
        ]);
    });
}

/**
 * Get default email template content for all triggers
 * Used by the "Reset Template" feature to restore original content
 * @return array<string,array{subject:string,body:string,delay_days:int|null}>
 */
function get_default_email_templates(): array {
    return [
        'submission_confirmed' => [
            'subject' => 'Submission Received - {form_name}',
            'body' => "Dear {user_name},\n\nThank you for submitting {form_name}. We have received your submission successfully.\n\nSubmission ID: {submission_id}\nSubmitted at: {submitted_at}\n\nYou can view your submission here: {submission_link}\n\nBest regards,\nThe Team",
            'delay_days' => null,
        ],
        'draft_reminder' => [
            'subject' => 'Complete Your Draft - {form_name}',
            'body' => "Dear {user_name},\n\nYou have a draft saved for {form_name} that has not been submitted yet.\n\nPlease complete and submit your form here: {form_link}\n\nBest regards,\nThe Team",
            'delay_days' => 3,
        ],
        'expiry_warning' => [
            'subject' => 'Submission Expiring Soon - {form_name}',
            'body' => "Dear {user_name},\n\nYour submission for {form_name} is expiring on {expiry_date}.\n\n{expiry_action}\n\nPlease review your submission here: {submission_link}\n\nBest regards,\nThe Team",
            'delay_days' => 7,
        ],
        'deadline_reminder' => [
            'subject' => 'Upcoming Deadline - {form_name}',
            'body' => "Dear {user_name},\n\nThe submission deadline for {form_name} is {deadline_date}.\n\nPlease submit your form before the deadline: {form_link}\n\nBest regards,\nThe Team",
            'delay_days' => 3,
        ],
        'clarification_request' => [
            'subject' => 'Clarification Needed - {form_name}',
            'body' => "Dear {user_name},\n\nAn administrator has requested clarification on your submission for {form_name}.\n\nMessage from admin:\n{admin_message}\n\nPlease respond here: {clarification_link}\n\nBest regards,\nThe Team",
            'delay_days' => null,
        ],
        'clarification_response' => [
            'subject' => 'Clarification Response Received - {form_name}',
            'body' => "Dear {user_name},\n\n{respondent_name} has responded to your clarification request for {form_name}.\n\nPlease review their response here: {submission_link}\n\nBest regards,\nThe Team",
            'delay_days' => null,
        ],
        'clarification_rejected' => [
            'subject' => 'Revision Required - {form_name}',
            'body' => "Dear {user_name},\n\nYour clarification response for {form_name} has been reviewed and requires revision.\n\nFeedback from administrator:\n{admin_feedback}\n\nPlease revise your response here: {clarification_link}\n\nBest regards,\nThe Team",
            'delay_days' => null,
        ],
        'clarification_resolved' => [
            'subject' => 'Clarification Resolved - {form_name}',
            'body' => "Dear {user_name},\n\nYour clarification response for {form_name} has been reviewed and accepted.\n\nYour submission is now back in the review queue.\n\nThank you for your prompt response.\n\nBest regards,\nThe Team",
            'delay_days' => null,
        ],
        'program_decision' => [
            'subject' => 'Decision on Your Submission - {program_name}',
            'body' => "Dear {user_name},\n\nA decision has been made regarding your submission for {program_name}.\n\nDecision: {decision}\n\n{justification}\n\nYou can view the details here: {submission_link}\n\nBest regards,\nThe Team",
            'delay_days' => null,
        ],
        'form_admin_added' => [
            'subject' => 'You\'ve Been Added as Administrator - {form_name}',
            'body' => "Dear {user_name},\n\n{granted_by} has added you as an administrator for the form \"{form_name}\".\n\nYour Permission Level: {access_level}\n\nYou can now access and manage this form here:\n{form_link}\n\nBest regards,\nThe Team",
            'delay_days' => null,
        ],
        'registration_verification' => [
            'subject' => 'Verify Your Email Address',
            'body' => "Dear {user_name},\n\nThank you for registering. Please verify your email address by clicking the link below:\n\n{verification_link}\n\nThis link will expire in 1 hour.\n\nIf you did not create an account, please ignore this email.\n\nBest regards,\nThe Team",
            'delay_days' => null,
        ],
        'email_change_verification' => [
            'subject' => 'Verify Your New Email Address',
            'body' => "Dear {user_name},\n\nYou have requested to change your email address. Please verify your new email by clicking the link below:\n\n{verification_link}\n\nThis link will expire in 1 hour.\n\nIf you did not request this change, please ignore this email.\n\nBest regards,\nThe Team",
            'delay_days' => null,
        ],
        'email_changed_notification' => [
            'subject' => 'Your Email Address Has Been Changed',
            'body' => "Dear {user_name},\n\nThis is a notification that your account email address has been changed to {new_email}.\n\nIf you did not make this change, please contact support immediately.\n\nBest regards,\nThe Team",
            'delay_days' => null,
        ],
        'password_changed_notification' => [
            'subject' => 'Your Password Has Been Changed',
            'body' => "Dear {user_name},\n\nThis is a notification that your account password has been changed.\n\nIf you did not make this change, please contact support immediately.\n\nBest regards,\nThe Team",
            'delay_days' => null,
        ],
        'password_reset' => [
            'subject' => 'Reset Your Password',
            'body' => "Dear {user_name},\n\nWe received a request to reset your password. Click the link below to set a new password:\n\n{reset_link}\n\nThis link will expire in 1 hour.\n\nIf you did not request a password reset, please ignore this email. Your password will remain unchanged.\n\nBest regards,\nThe Team",
            'delay_days' => null,
        ],
    ];
}

/**
 * Toggle email template active status
 */
function toggle_email_template(int $id): void {
    db_exec(
        "UPDATE email_templates SET is_active = NOT is_active WHERE id = ?",
        [$id]
    );
}

/**
 * Get available trigger types
 * @return array<string,array<string,mixed>>
 */
function get_email_triggers(): array {
    return [
        'registration_verification' => [
            'label' => 'Registration Verification',
            'description' => 'Sent when a new user registers to verify their email address',
            'supports_delay' => false,
            'placeholders' => ['{user_name}', '{user_email}', '{verification_link}']
        ],
        'email_change_verification' => [
            'label' => 'Email Change Verification',
            'description' => 'Sent when a user requests to change their email address',
            'supports_delay' => false,
            'placeholders' => ['{user_name}', '{user_email}', '{verification_link}']
        ],
        'email_changed_notification' => [
            'label' => 'Email Changed Notification',
            'description' => 'Sent to the old email address after an email change is confirmed',
            'supports_delay' => false,
            'placeholders' => ['{user_name}', '{user_email}', '{new_email}']
        ],
        'password_changed_notification' => [
            'label' => 'Password Changed Notification',
            'description' => 'Sent when a user changes their password',
            'supports_delay' => false,
            'placeholders' => ['{user_name}', '{user_email}']
        ],
        'password_reset' => [
            'label' => 'Password Reset',
            'description' => 'Sent when a user requests to reset their forgotten password',
            'supports_delay' => false,
            'placeholders' => ['{user_name}', '{user_email}', '{reset_link}']
        ],
        'draft_reminder' => [
            'label' => 'Draft Reminder',
            'description' => 'Sent to users who have saved a draft but not submitted',
            'supports_delay' => true,
            'placeholders' => ['{user_name}', '{user_email}', '{form_name}', '{dashboard_link}', '{form_link}']
        ],
        'submission_confirmed' => [
            'label' => 'Submission Confirmation',
            'description' => 'Sent immediately after successful form submission',
            'supports_delay' => false,
            'placeholders' => ['{user_name}', '{form_name}', '{submission_id}', '{submission_link}', '{submitted_at}']
        ],
        'expiry_warning' => [
            'label' => 'Expiry Warning',
            'description' => 'Sent before a submission is about to expire',
            'supports_delay' => true,
            'placeholders' => ['{user_name}', '{form_name}', '{expiry_date}', '{expiry_action}', '{submission_link}']
        ],
        'deadline_reminder' => [
            'label' => 'Deadline Reminder',
            'description' => 'Sent before form submission deadline',
            'supports_delay' => true,
            'placeholders' => ['{user_name}', '{form_name}', '{deadline_date}', '{form_link}']
        ],
        'clarification_request' => [
            'label' => 'Clarification Request',
            'description' => 'Sent when admin requests clarification on submission',
            'supports_delay' => false,
            'placeholders' => ['{user_name}', '{form_name}', '{clarification_link}', '{admin_message}']
        ],
        'clarification_response' => [
            'label' => 'Clarification Response',
            'description' => 'Sent to admin when a user responds to a clarification request',
            'supports_delay' => false,
            'placeholders' => ['{user_name}', '{user_email}', '{form_name}', '{respondent_name}', '{submission_link}']
        ],
        'clarification_rejected' => [
            'label' => 'Clarification Rejected',
            'description' => 'Sent to user when admin rejects their clarification response and requests revision',
            'supports_delay' => false,
            'placeholders' => ['{user_name}', '{form_name}', '{admin_feedback}', '{clarification_link}']
        ],
        'clarification_resolved' => [
            'label' => 'Clarification Resolved',
            'description' => 'Sent when a clarification request is resolved by an administrator',
            'supports_delay' => false,
            'placeholders' => ['{user_name}', '{form_name}', '{submission_link}']
        ],
        'program_decision' => [
            'label' => 'Program Decision',
            'description' => 'Sent when a program review decision is made',
            'supports_delay' => false,
            'placeholders' => ['{user_name}', '{program_name}', '{decision}', '{justification}', '{submission_link}']
        ],
        'form_admin_added' => [
            'label' => 'Form Admin Added',
            'description' => 'Sent when a user is added as an administrator for a form',
            'supports_delay' => false,
            'placeholders' => ['{user_name}', '{form_name}', '{access_level}', '{granted_by}', '{form_link}']
        ]
    ];
}
