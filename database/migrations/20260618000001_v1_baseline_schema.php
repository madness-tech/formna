<?php

use Phinx\Migration\AbstractMigration;

/**
 * V1 Baseline Schema
 * 
 * Creates all tables for the FORMNA application fresh install.
 * This replaces the historical migration chain with a single baseline.
 */
final class V1BaselineSchema extends AbstractMigration
{
    public function change(): void
    {
        // Create users table
        $this->table('users', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('uuid', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('role', 'string', ['limit' => 20, 'default' => 'user'])
            ->addColumn('can_global_reports', 'boolean', ['default' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('invited_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('registered_via_version_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('email_verified_at', 'timestamp', ['null' => true])
            ->addColumn('timezone', 'string', ['limit' => 50, 'default' => 'Asia/Dubai'])
            ->addColumn('session_version', 'integer', ['default' => 1])
            ->addColumn('last_login_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['uuid'], ['unique' => true])
            ->addIndex(['invited_by'])
            ->addForeignKey('invited_by', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();

        // Create permissions table
        $this->table('permissions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('form_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('access', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'form_id'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();

        // Create forms table
        $this->table('forms', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('uuid', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('instructions', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'draft'])
            ->addColumn('active_version_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('created_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('settings', 'json', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'timestamp', ['null' => true])
            ->addIndex(['uuid'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['created_by'])
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();

        // Create form_versions table
        $this->table('form_versions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('form_id', 'integer', ['signed' => false])
            ->addColumn('version_number', 'integer', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'draft'])
            ->addColumn('published_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['form_id', 'version_number'], ['unique' => true])
            ->addForeignKey('form_id', 'forms', 'id', ['delete' => 'CASCADE'])
            ->create();

        // Create questions table
        $this->table('questions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('uid', 'char', ['limit' => 36])
            ->addColumn('form_version_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('type', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('config', 'json', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'timestamp', ['null' => true])
            ->addIndex(['uid'], ['unique' => true])
            ->addIndex(['form_version_id', 'sort_order'])
            ->addIndex(['form_version_id', 'deleted_at'])
            ->addForeignKey('form_version_id', 'form_versions', 'id', ['delete' => 'CASCADE'])
            ->create();

        // Create submissions table
        $this->table('submissions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('uuid', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('form_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('form_version_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 30, 'default' => 'draft'])
            ->addColumn('answers', 'json', ['null' => true])
            ->addColumn('submitted_at', 'timestamp', ['null' => true])
            ->addColumn('expires_at', 'timestamp', ['null' => true])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['uuid'], ['unique' => true])
            ->addIndex(['form_id', 'status'])
            ->addIndex(['user_id', 'form_id'])
            ->addIndex(['expires_at'])
            ->addIndex(['form_version_id'])
            ->addForeignKey('form_id', 'forms', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('form_version_id', 'form_versions', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();

        // Create drafts table
        $this->table('drafts', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('form_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('form_version_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('data', 'json', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'form_id'], ['unique' => true])
            ->addIndex(['form_id'])
            ->addIndex(['form_version_id'])
            ->addForeignKey('form_id', 'forms', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('form_version_id', 'form_versions', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();

        // Create audit_log table
        $this->table('audit_log', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('action', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('entity_type', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('entity_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('entity_uuid', 'string', ['limit' => 36, 'null' => true])
            ->addColumn('details', 'json', ['null' => true])
            ->addColumn('ip', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['entity_type', 'entity_id'])
            ->addIndex(['user_id'])
            ->addIndex(['created_at'])
            ->addIndex(['entity_uuid'])
            ->create();

        // Create job_queue table
        $this->table('job_queue', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('handler', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('payload', 'json', ['null' => true])
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('max_attempts', 'integer', ['default' => 3])
            ->addColumn('run_at', 'timestamp', ['null' => true])
            ->addColumn('locked_at', 'timestamp', ['null' => true])
            ->addColumn('failed_at', 'timestamp', ['null' => true])
            ->addColumn('error', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['run_at', 'locked_at'])
            ->create();

        // Create files table
        $this->table('files', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('submission_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('question_id', 'integer', ['signed' => false])
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('original_name', 'string', ['limit' => 255])
            ->addColumn('stored_path', 'string', ['limit' => 500])
            ->addColumn('mime_type', 'string', ['limit' => 100])
            ->addColumn('size_bytes', 'biginteger')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['submission_id'])
            ->addIndex(['user_id'])
            ->addIndex(['question_id'])
            ->addForeignKey('submission_id', 'submissions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('question_id', 'questions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // Create email_templates table
        $this->table('email_templates', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('trigger', 'string', ['limit' => 50])
            ->addColumn('subject', 'string', ['limit' => 255])
            ->addColumn('body', 'text')
            ->addColumn('delay_days', 'integer', ['null' => true])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['trigger'], ['unique' => true])
            ->create();

        // Create notifications table
        $this->table('notifications', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('type', 'string', ['limit' => 50])
            ->addColumn('data', 'json')
            ->addColumn('read_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'read_at'])
            ->addIndex(['created_at'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // Create clarification_requests table
        $this->table('clarification_requests', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('uuid', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('submission_id', 'integer', ['signed' => false])
            ->addColumn('requested_by', 'integer', ['signed' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'open'])
            ->addColumn('message', 'text', ['null' => true])
            ->addColumn('items', 'json')
            ->addColumn('admin_feedback', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['uuid'], ['unique' => true])
            ->addIndex(['submission_id'])
            ->addIndex(['status'])
            ->addIndex(['requested_by'])
            ->addForeignKey('submission_id', 'submissions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('requested_by', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // Create site_settings table
        $this->table('site_settings', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['signed' => false, 'default' => 1, 'null' => false])
            ->addColumn('brand_name', 'string', ['limit' => 100, 'default' => 'FORMNA'])
            ->addColumn('brand_color', 'string', ['limit' => 7, 'default' => '#4f46e5'])
            ->addColumn('logo_path', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('logo_path_dark', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('mfa_enabled_admin', 'boolean', ['default' => false])
            ->addColumn('mfa_enabled_reviewer', 'boolean', ['default' => false])
            ->addColumn('mfa_enabled_user', 'boolean', ['default' => false])
            ->addColumn('ai_enabled', 'boolean', ['default' => false])
            ->addColumn('ai_provider', 'string', ['limit' => 20, 'default' => 'openrouter'])
            ->addColumn('ai_base_url', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('ai_api_key', 'text', ['null' => true])
            ->addColumn('ai_model', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('ai_max_tokens', 'integer', ['null' => true, 'comment' => 'Max response tokens for AI summaries (null = provider default)'])
            ->addColumn('ai_site_url', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('ai_site_name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addColumn('updated_by', 'integer', ['signed' => false, 'null' => true])
            ->create();

        // Create programs table
        $this->table('programs', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('uuid', 'string', ['limit' => 36])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('form_ids', 'json')
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('review_stages', 'json', ['null' => true])
            ->addColumn('settings', 'json')
            ->addColumn('created_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['uuid'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['created_by'])
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // Create program_submissions table
        $this->table('program_submissions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('uuid', 'string', ['limit' => 36])
            ->addColumn('program_id', 'integer', ['signed' => false])
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'draft'])
            ->addColumn('current_stage', 'integer', ['default' => 1])
            ->addColumn('decisions', 'json', ['default' => '[]'])
            ->addColumn('submitted_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['uuid'], ['unique' => true])
            ->addIndex(['program_id', 'status'])
            ->addIndex(['user_id'])
            ->addForeignKey('program_id', 'programs', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // Create program_submission_entries table
        $this->table('program_submission_entries', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('program_submission_id', 'integer', ['signed' => false])
            ->addColumn('form_id', 'integer', ['signed' => false])
            ->addColumn('submission_id', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['program_submission_id', 'form_id'], ['unique' => true, 'name' => 'uq_ps_form'])
            ->addIndex(['submission_id'], ['name' => 'idx_submission_id'])
            ->addIndex(['form_id'])
            ->addForeignKey('program_submission_id', 'program_submissions', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('form_id', 'forms', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('submission_id', 'submissions', 'id', ['delete' => 'CASCADE'])
            ->create();

        // Create verification_tokens table
        $this->table('verification_tokens', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('token', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('type', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('payload', 'json', ['null' => true])
            ->addColumn('expires_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['user_id', 'type'])
            ->addIndex(['expires_at'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();

        // Create rate_limits table
        $this->table('rate_limits', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('identifier_type', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('identifier_value', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('attempts', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('locked_until', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['identifier_type', 'identifier_value'], ['name' => 'idx_rate_limit_lookup'])
            ->create();

        // Create languages table
        $this->table('languages', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('code', 'string', ['limit' => 10])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('native_name', 'string', ['limit' => 100])
            ->addColumn('direction', 'string', ['limit' => 3, 'default' => 'ltr'])
            ->addColumn('translations', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG, 'null' => true])
            ->addColumn('help_translations', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG, 'null' => true])
            ->addColumn('is_active', 'boolean', ['default' => false])
            ->addColumn('is_default', 'boolean', ['default' => false])
            ->addColumn('is_system', 'boolean', ['default' => false])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['code'], ['unique' => true])
            ->addIndex(['is_default'])
            ->addIndex(['is_active'])
            ->create();

        // Create email_resend_log table
        $this->table('email_resend_log', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'integer', ['signed' => false, 'comment' => 'User who requested the resend'])
            ->addColumn('type', 'string', ['limit' => 64, 'comment' => 'Type of verification email'])
            ->addColumn('created_at', 'datetime', ['comment' => 'When the resend was requested'])
            ->addIndex(['user_id', 'type', 'created_at'], ['name' => 'idx_user_type_created'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // Create webhooks table
        $this->table('webhooks', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('url', 'string', ['limit' => 500])
            ->addColumn('bearer_token', 'text', ['null' => true])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'timestamp', ['null' => true])
            ->addColumn('include_user_data', 'boolean', ['default' => true])
            ->addIndex(['created_by'])
            ->addIndex(['deleted_at'], ['name' => 'idx_webhooks_deleted_at'])
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // Create form_webhooks table
        $this->table('form_webhooks', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('form_id', 'integer', ['signed' => false])
            ->addColumn('webhook_id', 'integer', ['signed' => false])
            ->addIndex(['form_id', 'webhook_id'], ['unique' => true])
            ->addIndex(['form_id'], ['unique' => true, 'name' => 'idx_form_webhooks_form_id_unique'])
            ->addIndex(['webhook_id'])
            ->addForeignKey('form_id', 'forms', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('webhook_id', 'webhooks', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // Create webhook_log table
        $this->table('webhook_log', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('webhook_id', 'integer', ['signed' => false])
            ->addColumn('submission_id', 'integer', ['signed' => false])
            ->addColumn('request_payload', 'json')
            ->addColumn('response_status', 'integer', ['null' => true])
            ->addColumn('response_body', 'text', ['null' => true])
            ->addColumn('attempts', 'integer', ['default' => 1])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending'])
            ->addColumn('locked_at', 'timestamp', ['null' => true])
            ->addColumn('retry_after', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['webhook_id', 'submission_id'], ['unique' => true, 'name' => 'idx_webhook_log_webhook_submission_unique'])
            ->addIndex(['webhook_id'])
            ->addIndex(['status'])
            ->addIndex(['submission_id'])
            ->addForeignKey('webhook_id', 'webhooks', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('submission_id', 'submissions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // Create user_mfa table
        $this->table('user_mfa', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('totp_secret', 'text')
            ->addColumn('confirmed_at', 'datetime', ['null' => true])
            ->addColumn('recovery_hash', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('last_used_step', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['user_id'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();

        // Create ai_summaries table
        $this->table('ai_summaries', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('entity_type', 'string', ['limit' => 30])
            ->addColumn('entity_id', 'integer', ['signed' => false])
            ->addColumn('summary', 'text')
            ->addColumn('model_used', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('tokens_used', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('generated_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['entity_type', 'entity_id'], ['unique' => true, 'name' => 'uq_entity'])
            ->addIndex(['generated_by'])
            ->addForeignKey('generated_by', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
