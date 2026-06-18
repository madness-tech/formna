<?php
/**
 * Reports Module - Controllers
 * Analytics dashboards with role-scoped access.
 * Cache is warmed by cron — no manual refresh triggers.
 */

require_once __DIR__ . '/models.php';

/**
 * Prevent CSV formula injection by prefixing cells that start with a
 * formula trigger character (=, +, -, @) with a tab character.
 */
function _csv_safe(?string $value): string {
    if ($value === null || $value === '') return '';
    if (in_array($value[0], ['=', '+', '-', '@'])) {
        return "\t" . $value;
    }
    return $value;
}

/**
 * Reports overview dashboard
 * GET /admin/reports
 */
function reports_index(): void {
    require_auth();
    require_role('admin');

    $user = current_user();
    $date_range = parse_date_range();

    $forms_page    = max(1, (int)($_GET['fp'] ?? 1));
    $programs_page = max(1, (int)($_GET['pp'] ?? 1));

    $summary  = get_reports_summary($user, $date_range);
    $volume   = get_submission_volume($user, 30, $date_range);
    $forms    = get_reportable_forms($user, $forms_page, 20);
    $programs = get_reportable_programs($user, $programs_page, 20);
    $is_global = can_view_global_reports($user);
    $health = $is_global ? get_report_health() : null;

    $title = 'Reports & Analytics';
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Reports'],
    ];

    ob_start();
    require __DIR__ . '/views/index.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Single form detailed report
 * GET /admin/reports/forms/{uuid}
 */
function reports_form_detail(string $uuid): void {
    require_auth();
    require_role('admin');

    $user = current_user();
    $date_range = parse_date_range();

    require_once __DIR__ . '/../forms/models.php';
    $form = get_form_by_uuid($uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/reports');
    }

    // Permission check
    if (!can_view_global_reports($user) && !has_form_permission($form['id'], 'view_results')) {
        flash('error', 'You do not have permission to view this report.');
        redirect('/admin/reports');
    }

    $report = get_form_report($form['id'], $date_range);
    $distribution = get_answer_distribution($form['id']);
    $scoring = get_scoring_distribution($form['id']);

    $title = 'Form Report: ' . $form['name'];
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Reports', 'url' => '/admin/reports'],
        ['label' => 'Form Report'],
    ];

    ob_start();
    require __DIR__ . '/views/form_report.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Single program detailed report
 * GET /admin/reports/programs/{uuid}
 */
function reports_program_detail(string $uuid): void {
    require_auth();
    require_role('admin');

    $user = current_user();
    $date_range = parse_date_range();

    require_once __DIR__ . '/../programs/models.php';
    $program = get_program_with_forms_by_uuid($uuid);
    if (!$program) {
        flash('error', 'Program not found.');
        redirect('/admin/reports');
    }

    // Permission: global reporters see all; admins see programs with their forms
    if (!can_view_global_reports($user)) {
        $fids = _scoped_form_ids($user);
        $pf = $program['form_ids'] ?? [];
        if (empty(array_intersect($pf, $fids ?: []))) {
            flash('error', 'You do not have permission to view this report.');
            redirect('/admin/reports');
        }
    }

    $report = get_program_report($program['id'], $date_range);

    $title = 'Program Report: ' . $program['name'];
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Reports', 'url' => '/admin/reports'],
        ['label' => 'Program Report'],
    ];

    ob_start();
    require __DIR__ . '/views/program_report.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Export form submissions as CSV (with optional date range + status filters)
 * POST /admin/reports/export
 */
function reports_export(): void {
    require_auth();
    require_role('admin');
    csrf_check();

    $user = current_user();
    $form_id = (int)($_POST['form_id'] ?? 0);

    if (!$form_id) {
        flash('error', 'No form selected.');
        redirect('/admin/reports');
    }

    require_once __DIR__ . '/../forms/models.php';
    $form = get_form_by_id($form_id);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/reports');
    }

    if (!can_view_global_reports($user) && !has_form_permission($form['id'], 'view_results')) {
        flash('error', 'You do not have permission to export this data.');
        redirect('/admin/reports');
    }

    // Parse optional date range and status filter from POST
    $date_range = null;
    $from = $_POST['export_from'] ?? '';
    $to = $_POST['export_to'] ?? '';
    if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $date_range = ['from' => $from, 'to' => null];
    }
    if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $date_range = $date_range ?? ['from' => null, 'to' => null];
        $date_range['to'] = $to;
    }
    $status_filter = $_POST['export_status'] ?? '';

    $export = get_exportable_submissions($form_id, $date_range, $status_filter);
    if (empty($export)) {
        flash('error', 'No data to export.');
        redirect('/admin/reports/forms/' . $form['uuid']);
    }

    $filename = 'submissions_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($form['name'])) . '_' . gmdate('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
    fputcsv($out, $export['headers'], ',', '"', '\\');
    foreach ($export['rows'] as $row) {
        fputcsv($out, array_map('_csv_safe', $row), ',', '"', '\\');
    }
    fclose($out);

    log_audit('exported_report', 'form', $form['id'], null, $form['uuid']);
    exit;
}

/**
 * Export program submissions as CSV
 * POST /admin/reports/export-program
 */
function reports_export_program(): void {
    require_auth();
    require_role('admin');
    csrf_check();

    $user = current_user();
    $program_id = (int)($_POST['program_id'] ?? 0);

    if (!$program_id) {
        flash('error', 'No program selected.');
        redirect('/admin/reports');
    }

    require_once __DIR__ . '/../programs/models.php';
    $program = get_program($program_id);
    if (!$program) {
        flash('error', 'Program not found.');
        redirect('/admin/reports');
    }

    // Permission check
    if (!can_view_global_reports($user)) {
        $fids = _scoped_form_ids($user);
        $pf = $program['form_ids'] ?? [];
        if (empty(array_intersect($pf, $fids ?: []))) {
            flash('error', 'You do not have permission to export this data.');
            redirect('/admin/reports');
        }
    }

    // Parse optional date range from POST (mirrors the form export)
    $date_range = null;
    $from = $_POST['export_from'] ?? '';
    $to = $_POST['export_to'] ?? '';
    if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $date_range = ['from' => $from, 'to' => null];
    }
    if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $date_range = $date_range ?? ['from' => null, 'to' => null];
        $date_range['to'] = $to;
    }

    $export = get_exportable_program_submissions($program_id, $date_range);
    if (empty($export)) {
        flash('error', 'No data to export.');
        redirect('/admin/reports/programs/' . $program['uuid']);
    }

    $filename = 'program_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($program['name'])) . '_' . gmdate('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
    fputcsv($out, $export['headers'], ',', '"', '\\');
    foreach ($export['rows'] as $row) {
        fputcsv($out, array_map('_csv_safe', $row), ',', '"', '\\');
    }
    fclose($out);

    log_audit('exported_report', 'program', $program['id'], null, $program['uuid']);
    exit;
}

/**
 * Cross-tabulation API endpoint
 * POST /admin/reports/cross-tab
 */
function reports_cross_tab(): void {
    require_auth();
    require_role('admin');
    csrf_check();

    $user = current_user();
    $form_a = (int)($_POST['form_a_id'] ?? 0);
    $q_a = $_POST['question_a_uid'] ?? '';
    $form_b = (int)($_POST['form_b_id'] ?? 0);
    $q_b = $_POST['question_b_uid'] ?? '';

    if (!$form_a || !$form_b || !$q_a || !$q_b) {
        json_response(['error' => 'All fields are required.'], 400);
    }

    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $q_a) || !preg_match('/^[a-zA-Z0-9_]{1,64}$/', $q_b)) {
        json_response(['error' => 'Invalid question UID format.'], 400);
    }

    $fids = _scoped_form_ids($user);
    $result = get_cross_tabulation($form_a, $q_a, $form_b, $q_b, $fids);

    if (isset($result['error'])) {
        json_response($result, 403);
    }

    json_response($result);
}
