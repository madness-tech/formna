<?php

/**
 * Forms Module - Scoring Engine
 * 
 * Pure helper functions for calculating question and form scores.
 * No database writes, no side effects — scores are calculated on-the-fly
 * from question config and submission answers.
 * 
 * Scoring config lives in the question's `config` JSON under a `scoring` key.
 * A form-level `scoring_enabled` flag in the form's `settings` JSON controls
 * whether scoring is active.
 */

// ============================================================================
// QUESTION-LEVEL SCORING
// ============================================================================

/**
 * Calculate score for a single question based on its answer
 *
 * @param array<string, mixed> $question  Question row (with decoded config)
 * @param mixed $answer    The submitted answer value
 * @return int|null        Score value, or null if no scoring configured
 */
function calculate_question_score(array $question, $answer): ?int {
    $scoring = $question['config']['scoring'] ?? null;

    if (empty($scoring)) {
        return null;
    }

    $type = $question['type'];

    // Display-only types never score
    if (in_array($type, ['heading', 'paragraph', 'divider'])) {
        return null;
    }

    // Input-type fields and file: empty / non_empty scoring
    if (in_array($type, ['text', 'textarea', 'number', 'email', 'url', 'date', 'datetime', 'file'])) {
        $is_empty = ($answer === null || $answer === '' || $answer === []);
        return (int) ($is_empty ? ($scoring['empty'] ?? 0) : ($scoring['non_empty'] ?? 0));
    }

    // Single-select fields: score by selected option value
    if (in_array($type, ['select', 'radio'])) {
        if ($answer === null || $answer === '') {
            return (int) ($scoring['__empty'] ?? 0);
        }
        return (int) ($scoring[$answer] ?? 0);
    }

    // Multi-select fields: sum scores of all selected options
    if (in_array($type, ['multiselect', 'checkbox_group'])) {
        if (!is_array($answer) || empty($answer)) {
            return (int) ($scoring['__empty'] ?? 0);
        }
        $total = 0;
        foreach (array_unique($answer) as $selected) {
            $total += (int) ($scoring[$selected] ?? 0);
        }
        return $total;
    }

    // Single checkbox (boolean): checked / unchecked
    if ($type === 'checkbox') {
        $is_checked = !empty($answer) && strtolower((string)$answer) !== 'false';
        return (int) ($is_checked ? ($scoring['checked'] ?? 0) : ($scoring['unchecked'] ?? 0));
    }

    return null;
}

/**
 * Calculate the maximum possible score for a single question
 *
 * @param array<string, mixed> $question  Question row (with decoded config)
 * @return int|null        Max possible score, or null if no scoring configured
 */
function get_question_max_score(array $question): ?int {
    $scoring = $question['config']['scoring'] ?? null;

    if (empty($scoring)) {
        return null;
    }

    $type = $question['type'];

    if (in_array($type, ['heading', 'paragraph', 'divider'])) {
        return null;
    }

    // Input-type / file: max of empty vs non_empty
    if (in_array($type, ['text', 'textarea', 'number', 'email', 'url', 'date', 'datetime', 'file'])) {
        return max((int) ($scoring['empty'] ?? 0), (int) ($scoring['non_empty'] ?? 0));
    }

    // Single-select: highest score among options
    if (in_array($type, ['select', 'radio'])) {
        $values = array_filter($scoring, fn($key) => $key !== '__empty', ARRAY_FILTER_USE_KEY);
        return empty($values) ? 0 : max(array_map('intval', $values));
    }

    // Multi-select: sum of all positive option scores (user could select all)
    if (in_array($type, ['multiselect', 'checkbox_group'])) {
        $total = 0;
        foreach ($scoring as $key => $value) {
            if ($key === '__empty') continue;
            $val = (int) $value;
            if ($val > 0) {
                $total += $val;
            }
        }
        return $total;
    }

    // Checkbox: max of checked vs unchecked
    if ($type === 'checkbox') {
        return max((int) ($scoring['checked'] ?? 0), (int) ($scoring['unchecked'] ?? 0));
    }

    return null;
}

// ============================================================================
// FORM-LEVEL SCORING
// ============================================================================

/**
 * Calculate total score for a form submission
 *
 * @param array<int, array<string, mixed>> $questions  Array of question rows (with decoded config)
 * @param array<int, mixed> $answers    Submitted answers keyed by question ID
 * @return array<string, mixed>            ['total' => N, 'max_possible' => M, 'per_question' => [...]]
 */
function calculate_form_score(array $questions, array $answers): array {
    $total = 0;
    $max_possible = 0;
    $per_question = [];
    $has_scoring = false;

    foreach ($questions as $question) {
        $answer = $answers[$question['id']] ?? null;
        $score = calculate_question_score($question, $answer);
        $max = get_question_max_score($question);

        if ($score !== null) {
            $has_scoring = true;
            $total += $score;
            $max_possible += ($max ?? 0);
            $per_question[$question['id']] = [
                'score' => $score,
                'max' => $max,
            ];
        }
    }

    return [
        'has_scoring' => $has_scoring,
        'total' => $total,
        'max_possible' => $max_possible,
        'per_question' => $per_question,
    ];
}

// ============================================================================
// PROGRAM-LEVEL SCORING
// ============================================================================

/**
 * Calculate aggregate score across multiple forms in a program
 *
 * @param array<int|string, array<string, mixed>> $form_scores  Array of form score results from calculate_form_score()
 * @return array<string, mixed>              ['total' => N, 'max_possible' => M, 'per_form' => [...]]
 */
function calculate_program_score(array $form_scores): array {
    $total = 0;
    $max_possible = 0;
    $per_form = [];
    $has_scoring = false;

    foreach ($form_scores as $form_id => $score_data) {
        if (!empty($score_data['has_scoring'])) {
            $has_scoring = true;
            $total += $score_data['total'];
            $max_possible += $score_data['max_possible'];
            $per_form[$form_id] = [
                'total' => $score_data['total'],
                'max_possible' => $score_data['max_possible'],
            ];
        }
    }

    return [
        'has_scoring' => $has_scoring,
        'total' => $total,
        'max_possible' => $max_possible,
        'per_form' => $per_form,
    ];
}

// ============================================================================
// HELPERS
// ============================================================================

/**
 * Check if a form has scoring enabled
 *
 * @param array<string, mixed> $form  Form row (with decoded settings)
 * @return bool
 */
function is_scoring_enabled(array $form): bool {
    return !empty($form['settings']['scoring_enabled']);
}

/**
 * Check if a form has any scoring configuration on its questions
 *
 * @param array<int, array<string, mixed>> $questions  Array of question rows (with decoded config)
 * @return bool
 */
function has_scoring_config(array $questions): bool {
    foreach ($questions as $question) {
        if (!empty($question['config']['scoring'])) {
            return true;
        }
    }
    return false;
}
