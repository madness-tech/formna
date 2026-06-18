<?php

use Phinx\Migration\AbstractMigration;

/**
 * V1 Seed Data
 * 
 * Inserts default data required for a fresh FORMNA installation.
 * This includes site settings, languages, and email templates.
 */
final class V1SeedData extends AbstractMigration
{
    public function up(): void
    {
        // Seed site_settings (singleton row)
        $this->execute("
            INSERT INTO site_settings (id, brand_name, brand_color)
            VALUES (1, 'FORMNA', '#4f46e5')
            ON DUPLICATE KEY UPDATE id = id
        ");

        // Seed default languages
        $this->execute("
            INSERT INTO languages (code, name, native_name, direction, is_active, is_default, is_system, sort_order, created_at) VALUES
            ('en', 'English', 'English', 'ltr', 1, 1, 1, 0, NOW()),
            ('ar', 'Arabic', 'العربية', 'rtl', 0, 0, 1, 1, NOW())
            ON DUPLICATE KEY UPDATE code = code
        ");

        // Seed default email templates
        $templates = [
            ['submission_confirmed', 'Submission Received - {form_name}', "Dear {user_name},\n\nThank you for submitting {form_name}. We have received your submission successfully.\n\nSubmission ID: {submission_id}\nSubmitted at: {submitted_at}\n\nYou can view your submission here: {submission_link}\n\nBest regards,\nThe Team", null, 1],
            ['draft_reminder', 'Complete Your Draft - {form_name}', "Dear {user_name},\n\nYou have a draft saved for {form_name} that has not been submitted yet.\n\nPlease complete and submit your form here: {form_link}\n\nBest regards,\nThe Team", 3, 1],
            ['expiry_warning', 'Submission Expiring Soon - {form_name}', "Dear {user_name},\n\nYour submission for {form_name} is expiring on {expiry_date}.\n\nPlease review your submission here: {submission_link}\n\nBest regards,\nThe Team", 7, 1],
            ['deadline_reminder', 'Upcoming Deadline - {form_name}', "Dear {user_name},\n\nThe submission deadline for {form_name} is {deadline_date}.\n\nPlease submit your form before the deadline: {form_link}\n\nBest regards,\nThe Team", 3, 1],
            ['clarification_request', 'Clarification Needed - {form_name}', "Dear {user_name},\n\nAn administrator has requested clarification on your submission for {form_name}.\n\nMessage from admin:\n{admin_message}\n\nPlease respond here: {clarification_link}\n\nBest regards,\nThe Team", null, 1],
            ['clarification_response', 'Clarification Response Received - {form_name}', "Dear {user_name},\n\n{respondent_name} has responded to your clarification request for {form_name}.\n\nPlease review their response here: {submission_link}\n\nBest regards,\nThe Team", null, 1],
            ['clarification_resolved', 'Clarification Resolved - {form_name}', "Dear {user_name},\n\nYour clarification response for {form_name} has been reviewed and accepted.\n\nYour submission is now back in the review queue.\n\nThank you for your prompt response.\n\nBest regards,\nThe Team", null, 1],
            ['clarification_rejected', 'Clarification Response Needs Revision - {form_name}', "Dear {user_name},\n\nYour clarification response for {form_name} needs revision.\n\nFeedback from admin:\n{admin_feedback}\n\nPlease revise your response here: {clarification_link}\n\nBest regards,\nThe Team", null, 1],
            ['program_decision', 'Decision on Your Submission - {program_name}', "Dear {user_name},\n\nA decision has been made regarding your submission for {program_name}.\n\nDecision: {decision}\n\n{justification}\n\nYou can view the details here: {submission_link}\n\nBest regards,\nThe Team", null, 1],
            ['registration_verification', 'Verify Your Email Address', "Dear {user_name},\n\nThank you for registering. Please verify your email address by clicking the link below:\n\n{verification_link}\n\nThis link will expire in 1 hour.\n\nIf you did not create an account, please ignore this email.\n\nBest regards,\nThe Team", null, 1],
            ['email_change_verification', 'Verify Your New Email Address', "Dear {user_name},\n\nYou have requested to change your email address. Please verify your new email by clicking the link below:\n\n{verification_link}\n\nThis link will expire in 1 hour.\n\nIf you did not request this change, please ignore this email.\n\nBest regards,\nThe Team", null, 1],
            ['email_changed_notification', 'Your Email Address Has Been Changed', "Dear {user_name},\n\nThis is a notification that your account email address has been changed to {new_email}.\n\nIf you did not make this change, please contact support immediately.\n\nBest regards,\nThe Team", null, 1],
            ['password_changed_notification', 'Your Password Has Been Changed', "Dear {user_name},\n\nThis is a notification that your account password has been changed.\n\nIf you did not make this change, please contact support immediately.\n\nBest regards,\nThe Team", null, 1],
            ['form_admin_added', "You've Been Added as Administrator - {form_name}", "Dear {user_name},\n\n{granted_by} has added you as an administrator for the form \"{form_name}\".\n\nYour Permission Level: {access_level}\n\nYou can now access and manage this form here:\n{form_link}\n\nBest regards,\nThe Team", null, 1],
        ];

        foreach ($templates as [$trigger, $subject, $body, $delay_days, $is_active]) {
            $this->table('email_templates')->insert([
                'trigger' => $trigger,
                'subject' => $subject,
                'body' => $body,
                'delay_days' => $delay_days,
                'is_active' => $is_active
            ])->saveData();
        }
    }

    public function down(): void
    {
        // Remove seed data (optional - usually seed data is not rolled back)
        $this->execute ("DELETE FROM email_templates WHERE trigger IN (
            'submission_confirmed', 'draft_reminder', 'expiry_warning', 'deadline_reminder',
            'clarification_request', 'clarification_response', 'clarification_resolved', 'clarification_rejected',
            'program_decision', 'registration_verification', 'email_change_verification',
            'email_changed_notification', 'password_changed_notification', 'form_admin_added'
        )");
        
        $this->execute("DELETE FROM languages WHERE code IN ('en', 'ar')");
        $this->execute("DELETE FROM site_settings WHERE id = 1");
    }
}
