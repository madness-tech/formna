<?php

/**
 * Check if form submission deadline has passed
 *
 * @param array{settings?: array{submission_deadline?: string|null}} $form
 */
function check_form_deadline(array $form): bool {
    $settings = $form['settings'] ?? [];
    $deadline = $settings['submission_deadline'] ?? null;
    
    if ($deadline && is_past($deadline)) {
        flash('error', t('middleware.form_closed'));
        redirect('/dashboard');
    }
    
    return true;
}

/**
 * Check if user meets form prerequisites
 *
 * @param array{settings?: array{
 *     prerequisite_form_ids?: array<int,int>,
 *     conditional_access?: array<int, array{source_form_id:int,question_id:int,operator:string,value:mixed}>
 * }} $form
 */
function check_prerequisites(int $user_id, array $form): bool {
    $settings = $form['settings'] ?? [];
    $prerequisites = $settings['prerequisite_form_ids'] ?? [];
    
    // Check required form submissions
    foreach ($prerequisites as $prereq_id) {
        $submission = db_one(
            'SELECT id FROM submissions WHERE user_id = :user_id AND form_id = :form_id AND status IN (:s1, :s2)',
            ['user_id' => $user_id, 'form_id' => $prereq_id, 's1' => 'submitted', 's2' => 'clarification_requested']
        );
        
        if (!$submission) {
            flash('error', t('middleware.prerequisites_required'));
            redirect('/dashboard');
        }
    }
    
    // Check conditional access
    $conditions = $settings['conditional_access'] ?? [];
    foreach ($conditions as $condition) {
        $submission = db_one(
            'SELECT answers FROM submissions WHERE user_id = :user_id AND form_id = :form_id AND status IN (:s1, :s2)',
            ['user_id' => $user_id, 'form_id' => $condition['source_form_id'], 's1' => 'submitted', 's2' => 'clarification_requested']
        );
        
        if (!$submission) {
            flash('error', t('middleware.access_conditions_not_met'));
            redirect('/dashboard');
        }
        
        decode_json_fields($submission, ['answers']);
        $answers = $submission['answers'] ?? [];
        $answer = $answers['q_' . $condition['question_id']] ?? null;
        
        $operator = $condition['operator'];
        $expected = $condition['value'];
        
        $condition_met = match($operator) {
            'eq' => $answer == $expected,
            'neq' => $answer != $expected,
            'contains' => is_array($answer) && in_array($expected, $answer),
            default => false
        };
        
        if (!$condition_met) {
            flash('error', t('middleware.access_conditions_not_met'));
            redirect('/dashboard');
        }
    }
    
    return true;
}

/**
 * Check if a submission is still within its editable window (pure boolean, no redirect)
 *
 * @param array{submitted_at:string} $submission
 * @param array{settings?: array{
 *     is_editable_after_submit?: bool,
 *     editable_days?: int|null,
 *     editable_until_date?: string|null
 * }} $form
 */
function is_submission_editable(array $submission, array $form): bool {
    $settings = $form['settings'] ?? [];
    
    if (!($settings['is_editable_after_submit'] ?? false)) {
        return false;
    }
    
    $editable_days = $settings['editable_days'] ?? null;
    $editable_until = $settings['editable_until_date'] ?? null;
    
    if ($editable_days) {
        $deadline_datetime = datetime_add($submission['submitted_at'], "+{$editable_days} days");
        if (is_past($deadline_datetime)) {
            return false;
        }
    }
    
    if ($editable_until && is_past($editable_until)) {
        return false;
    }
    
    return true;
}

/**
 * Check if submission is editable — redirects with error if not
 *
 * @param array{submitted_at:string} $submission
 * @param array{settings?: array{
 *     is_editable_after_submit?: bool,
 *     editable_days?: int|null,
 *     editable_until_date?: string|null
 * }} $form
 */
function check_editable(array $submission, array $form): bool {
    if (!is_submission_editable($submission, $form)) {
        $settings = $form['settings'] ?? [];
        $message_key = ($settings['is_editable_after_submit'] ?? false)
            ? 'middleware.editing_expired'
            : 'middleware.not_editable';
        flash('error', t($message_key));
        redirect('/dashboard');
    }
    
    return true;
}
