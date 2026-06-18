<?php
/**
 * Reports Module - Models
 * Cached analytics queries with TTL-based invalidation.
 * Cache files stored in runtime/storage/reports/.
 *
 * Cache is warmed by cron (warm_report_cache) on a configurable interval.
 * No manual refresh triggers — all invalidation is cron-driven.
 */

const REPORT_CACHE_DIR = __DIR__ . '/../../../storage/reports';

// ── Config ──────────────────────────────────────────────────────

function _report_cache_ttl(): int {
    global $config;
    return (int)(($config['reports']['cache_ttl_hours'] ?? 6) * 3600);
}

// ── Cache Layer ─────────────────────────────────────────────────

function _report_cache_path(string $key): string {
    return REPORT_CACHE_DIR . "/{$key}.json";
}

/** @return array<string, mixed>|null */
function report_cache_get(string $key): ?array {
    $path = _report_cache_path($key);
    if (!file_exists($path)) return null;

    $raw = file_get_contents($path);
    if ($raw === false) return null;

    $data = json_decode($raw, true);
    if (!is_array($data)) return null;

    // Check TTL
    $generated = strtotime($data['_generated_at'] ?? '');
    if ($generated === false || (time() - $generated) > _report_cache_ttl()) {
        return null;
    }

    return $data;
}

/** @param array<string, mixed> $data */
function report_cache_set(string $key, array $data): void {
    if (!is_dir(REPORT_CACHE_DIR)) {
        mkdir(REPORT_CACHE_DIR, 0755, true);
    }
    $data['_generated_at'] = gmdate('Y-m-d H:i:s');
    file_put_contents(_report_cache_path($key), json_encode($data), LOCK_EX);
}

function report_cache_clear(string $key): void {
    $path = _report_cache_path($key);
    if (file_exists($path)) unlink($path);
}

function report_cache_purge_old(int $max_age_days = 30): int {
    if (!is_dir(REPORT_CACHE_DIR)) return 0;
    $cutoff = time() - ($max_age_days * 86400);
    $count = 0;
    foreach (glob(REPORT_CACHE_DIR . '/*.json') as $file) {
        if (filemtime($file) < $cutoff) { unlink($file); $count++; }
    }
    return $count;
}

/** @return array<string, mixed> */
function get_report_health(): array {
    $path = REPORT_CACHE_DIR . '/_health.json';
    if (!file_exists($path)) {
        return ['status' => 'unknown', 'last_run' => null, 'total_reports' => 0, 'completed' => 0, 'failed' => 0, 'failed_reports' => []];
    }
    $raw = file_get_contents($path);
    $data = $raw !== false ? json_decode($raw, true) : null;
    return is_array($data) ? $data : ['status' => 'unknown', 'last_run' => null, 'total_reports' => 0, 'completed' => 0, 'failed' => 0, 'failed_reports' => []];
}

// ── Permission helpers ──────────────────────────────────────────

/** @param array<string, mixed> $user */
function can_view_global_reports(array $user): bool {
    static $cache = [];
    $uid = (int)$user['id'];
    if (isset($cache[$uid])) return $cache[$uid];
    if ($user['role'] === 'super_admin') return $cache[$uid] = true;
    $row = db_one('SELECT can_global_reports FROM users WHERE id = ?', [$uid]);
    return $cache[$uid] = (bool)($row['can_global_reports'] ?? 0);
}

/**
 * @param  array<string, mixed> $user
 * @return list<int>|null
 */
function _scoped_form_ids(array $user): ?array {
    static $cache = [];
    $uid = (int)$user['id'];
    if (array_key_exists($uid, $cache)) return $cache[$uid];
    if (can_view_global_reports($user)) return $cache[$uid] = null;
    $rows = db_query(
        "SELECT DISTINCT f.id FROM forms f
         LEFT JOIN permissions p ON p.form_id = f.id AND p.user_id = ?
         WHERE f.deleted_at IS NULL AND (f.created_by = ? OR p.id IS NOT NULL)",
        [$uid, $uid]
    );
    return $cache[$uid] = array_column($rows, 'id');
}

/**
 * Build a stable cache key suffix for a user's scope.
 * Global users share one cache; admins with identical form sets share another.
 */
/** @param array<string, mixed> $user */
function _scope_key(array $user): string {
    if (can_view_global_reports($user)) return 'global';
    $fids = _scoped_form_ids($user);
    if (empty($fids)) return 'empty';
    sort($fids);
    return 'scope_' . md5(implode(',', $fids));
}

/** @param list<int>|null $form_ids */
function _form_scope_sql(string $alias, ?array $form_ids): string {
    if ($form_ids === null) return '1=1';
    if (empty($form_ids)) return '0=1';
    return "{$alias}.id IN (" . implode(',', array_map('intval', $form_ids)) . ")";
}

/** @param list<int>|null $form_ids */
function _sub_scope_sql(string $alias, ?array $form_ids): string {
    if ($form_ids === null) return '1=1';
    if (empty($form_ids)) return '0=1';
    return "{$alias}.form_id IN (" . implode(',', array_map('intval', $form_ids)) . ")";
}

// ── Date range helpers ──────────────────────────────────────────

/**
 * Validate and parse date range from GET parameters.
 * Returns null if no range is set, or an array with 'from' and 'to' keys.
 */
/** @return array{from: string|null, to: string|null}|null */
function parse_date_range(): ?array {
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    if (!$from && !$to) return null;
    if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
    if ($to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = '';
    if (!$from && !$to) return null;
    return ['from' => $from ?: null, 'to' => $to ?: null];
}

/**
 * Build a SQL date range clause for a given column.
 * Only accepts validated Y-m-d strings (from parse_date_range).
 */
/** @param array{from: string|null, to: string|null}|null $range */
function _date_sql(string $col, ?array $range): string {
    if (!$range) return '1=1';
    $parts = [];
    if (!empty($range['from'])) {
        $f = preg_replace('/[^0-9-]/', '', $range['from']);
        $parts[] = "{$col} >= '{$f} 00:00:00'";
    }
    if (!empty($range['to'])) {
        $t = preg_replace('/[^0-9-]/', '', $range['to']);
        $parts[] = "{$col} <= '{$t} 23:59:59'";
    }
    return $parts ? implode(' AND ', $parts) : '1=1';
}

// ── Summary dashboard ───────────────────────────────────────────

/**
 * @param  array<string, mixed>                            $user
 * @param  array{from: string|null, to: string|null}|null $date_range
 * @return array<string, mixed>
 */
function get_reports_summary(array $user, ?array $date_range = null): array {
    $scope = _scope_key($user);
    $key = "summary_{$scope}";

    // Use cache only when no date filter is active
    if (!$date_range && ($cached = report_cache_get($key))) return $cached;

    $fids = _scoped_form_ids($user);
    $fs = _form_scope_sql('f', $fids);
    $ss = _sub_scope_sql('s', $fids);
    $ds = _date_sql('s.submitted_at', $date_range);

    $data = [];

    // Core counts
    $data['total_forms'] = (int)(db_one("SELECT COUNT(*) c FROM forms f WHERE f.deleted_at IS NULL AND {$fs}")['c'] ?? 0);
    $data['published_forms'] = (int)(db_one("SELECT COUNT(*) c FROM forms f WHERE f.status='published' AND f.deleted_at IS NULL AND {$fs}")['c'] ?? 0);
    $data['total_submissions'] = (int)(db_one("SELECT COUNT(*) c FROM submissions s WHERE s.status!='draft' AND {$ss} AND {$ds}")['c'] ?? 0);
    $data['submissions_7d'] = (int)(db_one("SELECT COUNT(*) c FROM submissions s WHERE s.submitted_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) AND {$ss} AND {$ds}")['c'] ?? 0);
    $data['submissions_30d'] = (int)(db_one("SELECT COUNT(*) c FROM submissions s WHERE s.submitted_at >= DATE_SUB(NOW(),INTERVAL 30 DAY) AND {$ss} AND {$ds}")['c'] ?? 0);
    $data['active_drafts'] = (int)(db_one("SELECT COUNT(*) c FROM drafts d WHERE " . ($fids === null ? '1=1' : (empty($fids) ? '0=1' : "d.form_id IN (" . implode(',', array_map('intval', $fids)) . ")")))['c'] ?? 0);

    // Trend: prior 30-day window for comparison (only for default view)
    if (!$date_range) {
        $prior_30d = (int)(db_one("SELECT COUNT(*) c FROM submissions s WHERE s.submitted_at >= DATE_SUB(NOW(),INTERVAL 60 DAY) AND s.submitted_at < DATE_SUB(NOW(),INTERVAL 30 DAY) AND s.status!='draft' AND {$ss}")['c'] ?? 0);
        $data['submissions_30d_prior'] = $prior_30d;
        $data['submissions_30d_change'] = $prior_30d > 0
            ? round(($data['submissions_30d'] - $prior_30d) / $prior_30d * 100, 1)
            : null;
    }

    // Completion rate
    $total_started = $data['total_submissions'] + $data['active_drafts'];
    $data['completion_rate'] = $total_started > 0 ? round($data['total_submissions'] / $total_started * 100, 1) : 0;

    // Average time to submit (hours) - from created_at to submitted_at
    $avg = db_one("SELECT AVG(TIMESTAMPDIFF(HOUR, s.created_at, s.submitted_at)) AS avg_h FROM submissions s WHERE s.status != 'draft' AND s.submitted_at IS NOT NULL AND {$ss} AND {$ds}");
    $data['avg_hours_to_submit'] = round((float)($avg['avg_h'] ?? 0), 1);

    // Clarification rate
    $clar = db_one("SELECT COUNT(DISTINCT cr.submission_id) c FROM clarification_requests cr JOIN submissions s ON cr.submission_id = s.id WHERE {$ss} AND {$ds}");
    $data['clarification_rate'] = $data['total_submissions'] > 0 ? round((int)($clar['c'] ?? 0) / $data['total_submissions'] * 100, 1) : 0;

    // Peak submission hour (UTC) — stored as int, converted to user tz at display time
    $peak_h = db_one("SELECT HOUR(s.submitted_at) AS h, COUNT(*) c FROM submissions s WHERE s.submitted_at IS NOT NULL AND {$ss} AND {$ds} GROUP BY h ORDER BY c DESC LIMIT 1");
    $data['peak_hour'] = $peak_h ? (int)$peak_h['h'] : null;

    // Peak day of week
    $peak_d = db_one("SELECT DAYNAME(s.submitted_at) AS d, COUNT(*) c FROM submissions s WHERE s.submitted_at IS NOT NULL AND {$ss} AND {$ds} GROUP BY d ORDER BY c DESC LIMIT 1");
    $data['peak_day'] = $peak_d['d'] ?? null;

    // Unique submitters
    $data['unique_submitters'] = (int)(db_one("SELECT COUNT(DISTINCT s.user_id) c FROM submissions s WHERE s.status!='draft' AND {$ss} AND {$ds}")['c'] ?? 0);

    // Repeat submitters (submitted to 2+ distinct forms)
    $data['repeat_submitters'] = (int)(db_one("SELECT COUNT(*) c FROM (SELECT s.user_id FROM submissions s WHERE s.status!='draft' AND {$ss} AND {$ds} GROUP BY s.user_id HAVING COUNT(DISTINCT s.form_id)>=2) t")['c'] ?? 0);

    // Programs summary (global only — programs don't have per-admin ownership in schema)
    if ($fids === null) {
        $data['total_programs'] = (int)(db_one("SELECT COUNT(*) c FROM programs")['c'] ?? 0);
        $data['program_approval_rate'] = 0;
        $ps_total = db_one("SELECT COUNT(*) c FROM program_submissions WHERE status IN ('approved','rejected')");
        $ps_approved = db_one("SELECT COUNT(*) c FROM program_submissions WHERE status='approved'");
        $total_decided = (int)($ps_total['c'] ?? 0);
        if ($total_decided > 0) {
            $data['program_approval_rate'] = round((int)($ps_approved['c'] ?? 0) / $total_decided * 100, 1);
        }
    }

    // User growth (global only)
    if ($fids === null) {
        $data['new_users_7d'] = (int)(db_one("SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")['c'] ?? 0);
        $data['new_users_30d'] = (int)(db_one("SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(NOW(),INTERVAL 30 DAY)")['c'] ?? 0);
        $data['total_users'] = (int)(db_one("SELECT COUNT(*) c FROM users WHERE status='active'")['c'] ?? 0);
    }

    // Only write to cache when using default (no date filter) view
    if (!$date_range) report_cache_set($key, $data);
    return $data;
}

// ── Submission volume over time ─────────────────────────────────

/**
 * @param  array<string, mixed>                            $user
 * @param  array{from: string|null, to: string|null}|null $date_range
 * @return array<string, mixed>
 */
function get_submission_volume(array $user, int $days = 30, ?array $date_range = null): array {
    $scope = _scope_key($user);
    $key = "volume_{$scope}_{$days}";

    // Use cache only when no date filter is active
    if (!$date_range && ($cached = report_cache_get($key))) return $cached;

    $fids = _scoped_form_ids($user);
    $ss = _sub_scope_sql('s', $fids);

    if ($date_range) {
        // Custom date range: query between from and to
        $ds = _date_sql('s.submitted_at', $date_range);
        $rows = db_query("SELECT DATE(s.submitted_at) AS d, COUNT(*) c FROM submissions s WHERE s.status!='draft' AND {$ss} AND {$ds} GROUP BY d ORDER BY d");

        $volume = [];
        foreach ($rows as $r) $volume[$r['d']] = (int)$r['c'];

        // Fill dates between from and to (capped at 366 days)
        $start = $date_range['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $end = $date_range['to'] ?? date('Y-m-d');
        $filled = [];
        $current = $start;
        $safety = 0;
        while ($current <= $end && $safety < 366) {
            $filled[] = ['date' => $current, 'count' => $volume[$current] ?? 0];
            $current = date('Y-m-d', strtotime($current . ' +1 day'));
            $safety++;
        }

        return ['days' => count($filled), 'volume' => $filled, 'date_range' => $date_range];
    }

    $rows = db_query("SELECT DATE(s.submitted_at) AS d, COUNT(*) c FROM submissions s WHERE s.submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND s.status!='draft' AND {$ss} GROUP BY d ORDER BY d", [$days]);

    $volume = [];
    foreach ($rows as $r) $volume[$r['d']] = (int)$r['c'];

    // Fill in missing dates with 0
    $filled = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $filled[] = ['date' => $d, 'count' => $volume[$d] ?? 0];
    }

    $data = ['days' => $days, 'volume' => $filled];
    report_cache_set($key, $data);
    return $data;
}

// ── Single form report ──────────────────────────────────────────

/**
 * @param  array{from: string|null, to: string|null}|null $date_range
 * @return array<string, mixed>
 */
function get_form_report(int $form_id, ?array $date_range = null): array {
    $key = "form_{$form_id}";
    if (!$date_range && ($cached = report_cache_get($key))) return $cached;

    $ds = _date_sql('submitted_at', $date_range);

    $data = [];
    $data['total'] = (int)(db_one("SELECT COUNT(*) c FROM submissions WHERE form_id=? AND status!='draft' AND {$ds}", [$form_id])['c'] ?? 0);
    $data['drafts'] = (int)(db_one("SELECT COUNT(*) c FROM drafts WHERE form_id=?", [$form_id])['c'] ?? 0);
    $data['completion_rate'] = ($data['total'] + $data['drafts']) > 0 ? round($data['total'] / ($data['total'] + $data['drafts']) * 100, 1) : 0;

    // Status breakdown
    $rows = db_query("SELECT status, COUNT(*) c FROM submissions WHERE form_id=? AND {$ds} GROUP BY status", [$form_id]);
    $data['by_status'] = [];
    foreach ($rows as $r) $data['by_status'][$r['status']] = (int)$r['c'];

    // Avg time to submit
    $avg = db_one("SELECT AVG(TIMESTAMPDIFF(HOUR,created_at,submitted_at)) avg_h FROM submissions WHERE form_id=? AND submitted_at IS NOT NULL AND status!='draft' AND {$ds}", [$form_id]);
    $data['avg_hours_to_submit'] = round((float)($avg['avg_h'] ?? 0), 1);

    // Median time to submit (approx via percentile)
    $med = db_one("SELECT TIMESTAMPDIFF(HOUR,created_at,submitted_at) h FROM submissions WHERE form_id=? AND submitted_at IS NOT NULL AND status!='draft' AND {$ds} ORDER BY h LIMIT 1 OFFSET " . max(0, (int)floor($data['total'] / 2)), [$form_id]);
    $data['median_hours_to_submit'] = (float)($med['h'] ?? 0);

    // Peak hour (UTC — converted at display time)
    $ph = db_one("SELECT HOUR(submitted_at) h, COUNT(*) c FROM submissions WHERE form_id=? AND submitted_at IS NOT NULL AND {$ds} GROUP BY h ORDER BY c DESC LIMIT 1", [$form_id]);
    $data['peak_hour'] = $ph ? (int)$ph['h'] : null;

    // Submissions per day — custom range or last 30 days
    if ($date_range) {
        $vol = db_query("SELECT DATE(submitted_at) d, COUNT(*) c FROM submissions WHERE form_id=? AND status!='draft' AND {$ds} GROUP BY d ORDER BY d", [$form_id]);
        $vol_map = [];
        foreach ($vol as $r) $vol_map[$r['d']] = (int)$r['c'];
        $start = $date_range['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $end = $date_range['to'] ?? date('Y-m-d');
        $data['volume_30d'] = [];
        $current = $start;
        $safety = 0;
        while ($current <= $end && $safety < 366) {
            $data['volume_30d'][] = ['date' => $current, 'count' => $vol_map[$current] ?? 0];
            $current = date('Y-m-d', strtotime($current . ' +1 day'));
            $safety++;
        }
    } else {
        $vol = db_query("SELECT DATE(submitted_at) d, COUNT(*) c FROM submissions WHERE form_id=? AND submitted_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND status!='draft' GROUP BY d ORDER BY d", [$form_id]);
        $vol_map = [];
        foreach ($vol as $r) $vol_map[$r['d']] = (int)$r['c'];
        $data['volume_30d'] = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $data['volume_30d'][] = ['date' => $d, 'count' => $vol_map[$d] ?? 0];
        }
    }

    // Clarification stats for this form
    $clar = db_one("SELECT COUNT(*) c FROM clarification_requests cr JOIN submissions s ON cr.submission_id=s.id WHERE s.form_id=? AND " . _date_sql('s.submitted_at', $date_range), [$form_id]);
    $data['clarifications'] = (int)($clar['c'] ?? 0);
    $data['clarification_rate'] = $data['total'] > 0 ? round($data['clarifications'] / $data['total'] * 100, 1) : 0;

    // Unique submitters
    $data['unique_submitters'] = (int)(db_one("SELECT COUNT(DISTINCT user_id) c FROM submissions WHERE form_id=? AND status!='draft' AND {$ds}", [$form_id])['c'] ?? 0);

    if (!$date_range) report_cache_set($key, $data);
    return $data;
}

// ── Single program report ───────────────────────────────────────

/**
 * @param  array{from: string|null, to: string|null}|null $date_range
 * @return array<string, mixed>
 */
function get_program_report(int $program_id, ?array $date_range = null): array {
    $key = "program_{$program_id}";
    if (!$date_range && ($cached = report_cache_get($key))) return $cached;

    $ds = _date_sql('ps.submitted_at', $date_range);
    $data = [];

    // Status breakdown
    $rows = db_query("SELECT ps.status, COUNT(*) c FROM program_submissions ps WHERE ps.program_id=? AND {$ds} GROUP BY ps.status", [$program_id]);
    $data['by_status'] = [];
    foreach ($rows as $r) $data['by_status'][$r['status']] = (int)$r['c'];
    $data['total'] = array_sum($data['by_status']) - (int)($data['by_status']['draft'] ?? 0);

    // Approval rate
    $decided = (int)($data['by_status']['approved'] ?? 0) + (int)($data['by_status']['rejected'] ?? 0);
    $data['approval_rate'] = $decided > 0 ? round(($data['by_status']['approved'] ?? 0) / $decided * 100, 1) : 0;

    // Load all non-draft program submissions once for funnel + review time + stage timing
    $all_ps = db_query(
        "SELECT ps.current_stage, ps.decisions, ps.submitted_at FROM program_submissions ps WHERE ps.program_id=? AND ps.status != 'draft' AND {$ds}",
        [$program_id]
    );

    $review_hours = [];
    $stage_passed = [];
    $stage_rejected = [];
    $stage_hours = []; // stage_order => [hours_array]
    foreach ($all_ps as $r) {
        $decs = json_decode($r['decisions'], true) ?: [];

        // Review time: last decision vs submitted_at
        $last = end($decs);
        if ($last && !empty($last['decided_at']) && !empty($r['submitted_at'])) {
            $diff = (strtotime($last['decided_at']) - strtotime($r['submitted_at'])) / 3600;
            if ($diff > 0) $review_hours[] = $diff;
        }

        // Per-stage metrics from decisions
        $prev_time = $r['submitted_at'] ?? null;
        foreach ($decs as $d) {
            $stage_order = (int)($d['stage'] ?? 0);
            $decision = $d['decision'] ?? '';
            if ($decision === 'approved') {
                $stage_passed[$stage_order] = ($stage_passed[$stage_order] ?? 0) + 1;
            }
            if ($decision === 'rejected') {
                $stage_rejected[$stage_order] = ($stage_rejected[$stage_order] ?? 0) + 1;
            }
            // Stage timing: time from previous event to this decision
            if (!empty($d['decided_at']) && $prev_time) {
                $h = (strtotime($d['decided_at']) - strtotime($prev_time)) / 3600;
                if ($h > 0) $stage_hours[$stage_order][] = $h;
            }
            $prev_time = $d['decided_at'] ?? $prev_time;
        }
    }
    $data['avg_review_hours'] = count($review_hours) > 0 ? round(array_sum($review_hours) / count($review_hours), 1) : 0;

    // Stage funnel with timing
    $program = db_one("SELECT review_stages FROM programs WHERE id=?", [$program_id]);
    $stages = json_decode($program['review_stages'] ?? '[]', true) ?: [];
    $data['funnel'] = [];
    $slowest_stage = null;
    $slowest_avg = 0;
    foreach ($stages as $s) {
        $order = (int)$s['order'];
        $reached = 0;
        foreach ($all_ps as $r) {
            if ((int)$r['current_stage'] >= $order) $reached++;
        }
        $hours_arr = $stage_hours[$order] ?? [];
        $avg_h = count($hours_arr) > 0 ? round(array_sum($hours_arr) / count($hours_arr), 1) : 0;
        if ($avg_h > $slowest_avg) {
            $slowest_avg = $avg_h;
            $slowest_stage = $order;
        }
        $data['funnel'][] = [
            'stage' => $order,
            'name' => $s['name'] ?? "Stage {$order}",
            'reached' => $reached,
            'passed' => $stage_passed[$order] ?? 0,
            'rejected' => $stage_rejected[$order] ?? 0,
            'avg_hours' => $avg_h,
        ];
    }
    $data['bottleneck_stage'] = $slowest_stage;

    // Volume over time
    if ($date_range) {
        $vol = db_query("SELECT DATE(ps.submitted_at) d, COUNT(*) c FROM program_submissions ps WHERE ps.program_id=? AND ps.status!='draft' AND {$ds} GROUP BY d ORDER BY d", [$program_id]);
        $vol_map = [];
        foreach ($vol as $r) $vol_map[$r['d']] = (int)$r['c'];
        $start = $date_range['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $end = $date_range['to'] ?? date('Y-m-d');
        $data['volume_30d'] = [];
        $current = $start;
        $safety = 0;
        while ($current <= $end && $safety < 366) {
            $data['volume_30d'][] = ['date' => $current, 'count' => $vol_map[$current] ?? 0];
            $current = date('Y-m-d', strtotime($current . ' +1 day'));
            $safety++;
        }
    } else {
        $vol = db_query("SELECT DATE(submitted_at) d, COUNT(*) c FROM program_submissions WHERE program_id=? AND submitted_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND status!='draft' GROUP BY d ORDER BY d", [$program_id]);
        $vol_map = [];
        foreach ($vol as $r) $vol_map[$r['d']] = (int)$r['c'];
        $data['volume_30d'] = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $data['volume_30d'][] = ['date' => $d, 'count' => $vol_map[$d] ?? 0];
        }
    }

    if (!$date_range) report_cache_set($key, $data);
    return $data;
}

// ── Answer distribution for a form (SQL-based) ─────────────────

/** @return array<string, mixed> */
function get_answer_distribution(int $form_id): array {
    $key = "answers_{$form_id}";
    if ($cached = report_cache_get($key)) return $cached;

    $form = db_one("SELECT active_version_id FROM forms WHERE id=?", [$form_id]);
    if (!$form || !$form['active_version_id']) return [];

    require_once __DIR__ . '/../forms/models.php';
    $questions = get_questions((int)$form['active_version_id']);
    $analyzable = ['select', 'radio', 'checkbox_group', 'multiselect'];
    $multi_types = ['checkbox_group', 'multiselect'];

    $data = [];
    foreach ($questions as $q) {
        if (!in_array($q['type'], $analyzable)) continue;

        $label = $q['config']['label'] ?? 'Untitled';
        $options = $q['config']['options'] ?? [];
        $json_path = '$."q_' . $q['uid'] . '"';

        $counts = [];      // value => count
        $opt_labels = [];  // value => display label
        foreach ($options as $opt) {
            if (is_array($opt)) {
                $val = $opt['value'] ?? $opt['label'] ?? '';
                $lbl = $opt['label'] ?? $val;
            } else {
                $val = (string)$opt;
                $lbl = $val;
            }
            if ($val !== '') {
                $counts[$val]     = 0;
                $opt_labels[$val] = $lbl;
            }
        }

        if (in_array($q['type'], $multi_types)) {
            // Array-valued answers: use JSON_TABLE to unwrap each element
            $rows = db_query(
                "SELECT jt.val AS answer_val, COUNT(*) AS cnt
                 FROM submissions s,
                 JSON_TABLE(
                     JSON_EXTRACT(s.answers, ?),
                     '\$[*]' COLUMNS (val VARCHAR(500) PATH '\$')
                 ) jt
                 WHERE s.form_id = ? AND s.status != 'draft'
                 GROUP BY jt.val",
                [$json_path, $form_id]
            );
        } else {
            // Scalar-valued answers: direct extract and group
            $rows = db_query(
                "SELECT JSON_UNQUOTE(JSON_EXTRACT(s.answers, ?)) AS answer_val, COUNT(*) AS cnt
                 FROM submissions s
                 WHERE s.form_id = ? AND s.status != 'draft'
                 AND JSON_EXTRACT(s.answers, ?) IS NOT NULL
                 GROUP BY answer_val",
                [$json_path, $form_id, $json_path]
            );
        }

        foreach ($rows as $r) {
            $val = $r['answer_val'] ?? '';
            if (isset($counts[$val])) {
                $counts[$val] = (int)$r['cnt'];
            }
        }

        // Remap keys from stored values to human-readable labels for display
        $labeled_counts = [];
        foreach ($counts as $val => $cnt) {
            $labeled_counts[$opt_labels[$val] ?? $val] = $cnt;
        }

        $data[] = ['uid' => $q['uid'], 'label' => $label, 'type' => $q['type'], 'distribution' => $labeled_counts];
    }

    $result = ['items' => $data];
    report_cache_set($key, $result);
    return $result;
}

// ── Scoring distribution for a form ─────────────────────────────

/** @return array<string, mixed>|null */
function get_scoring_distribution(int $form_id): ?array {
    $key = "scoring_{$form_id}";
    if ($cached = report_cache_get($key)) return $cached;

    require_once __DIR__ . '/../forms/models.php';
    require_once __DIR__ . '/../forms/scoring.php';

    $form = get_form_by_id($form_id);
    if (!$form || !is_scoring_enabled($form)) return null;

    $version_id = (int)($form['active_version_id'] ?? 0);
    if (!$version_id) return null;

    $questions = get_questions($version_id);
    if (!has_scoring_config($questions)) return null;

    $subs = db_query("SELECT answers FROM submissions WHERE form_id = ? AND status != 'draft'", [$form_id]);
    if (empty($subs)) return null;

    $max_possible = 0;
    foreach ($questions as $q) {
        $max = get_question_max_score($q);
        if ($max !== null) $max_possible += $max;
    }

    $scores = [];
    foreach ($subs as $s) {
        $answers = is_string($s['answers']) ? json_decode($s['answers'], true) : ($s['answers'] ?? []);
        $mapped = [];
        foreach ($questions as $q) {
            $mapped[$q['id']] = $answers['q_' . $q['uid']] ?? null;
        }
        $result = calculate_form_score($questions, $mapped);
        if ($result['has_scoring']) {
            $scores[] = $result['total'];
        }
    }

    if (empty($scores)) return null;

    sort($scores);
    $count = count($scores);
    $data = [
        'count' => $count,
        'min' => $scores[0],
        'max' => end($scores),
        'avg' => round(array_sum($scores) / $count, 1),
        'median' => $count % 2 === 0
            ? round(($scores[$count / 2 - 1] + $scores[$count / 2]) / 2, 1)
            : $scores[(int)floor($count / 2)],
        'max_possible' => $max_possible,
        'histogram' => _build_score_histogram($scores, $max_possible),
    ];

    report_cache_set($key, $data);
    return $data;
}

/**
 * @param  list<int|float>                              $sorted_scores
 * @return list<array{label: string, count: int}>
 */
function _build_score_histogram(array $sorted_scores, int $max_possible): array {
    if (empty($sorted_scores) || $max_possible <= 0) return [];
    $bucket_count = min(5, $max_possible);
    $bucket_size = ceil($max_possible / $bucket_count);
    $buckets = [];
    for ($i = 0; $i < $bucket_count; $i++) {
        $low = $i * $bucket_size;
        $high = min(($i + 1) * $bucket_size, $max_possible);
        $cnt = 0;
        foreach ($sorted_scores as $s) {
            if ($s >= $low && ($i === $bucket_count - 1 ? $s <= $high : $s < $high)) $cnt++;
        }
        $buckets[] = ['label' => "{$low}–{$high}", 'count' => $cnt];
    }
    return $buckets;
}

// ── Cross-tabulation between two questions across forms (SQL-based) ──

/**
 * @param  list<int>|null        $form_ids
 * @return array<string, mixed>
 */
function get_cross_tabulation(int $form_a_id, string $q_uid_a, int $form_b_id, string $q_uid_b, ?array $form_ids = null): array {
    // Security: verify form_ids are accessible
    if ($form_ids !== null && (!in_array($form_a_id, $form_ids) || !in_array($form_b_id, $form_ids))) {
        return ['error' => 'Access denied to one or both forms.'];
    }

    $path_a = '$."q_' . $q_uid_a . '"';
    $path_b = '$."q_' . $q_uid_b . '"';

    // Single SQL join on user_id, aggregate in the database
    $rows = db_query(
        "SELECT
            JSON_UNQUOTE(JSON_EXTRACT(a.answers, ?)) AS val_a,
            JSON_UNQUOTE(JSON_EXTRACT(b.answers, ?)) AS val_b,
            COUNT(*) AS cnt
         FROM submissions a
         JOIN submissions b ON a.user_id = b.user_id
         WHERE a.form_id = ? AND a.status != 'draft'
           AND b.form_id = ? AND b.status != 'draft'
           AND JSON_EXTRACT(a.answers, ?) IS NOT NULL
           AND JSON_EXTRACT(b.answers, ?) IS NOT NULL
         GROUP BY val_a, val_b",
        [$path_a, $path_b, $form_a_id, $form_b_id, $path_a, $path_b]
    );

    $a_vals = [];
    $b_vals = [];
    $matrix = [];
    foreach ($rows as $r) {
        $ka = $r['val_a'] ?? '(empty)';
        $kb = $r['val_b'] ?? '(empty)';
        // Handle JSON arrays by converting to readable string
        if (str_starts_with($ka, '[')) $ka = implode(', ', json_decode($ka, true) ?: [$ka]);
        if (str_starts_with($kb, '[')) $kb = implode(', ', json_decode($kb, true) ?: [$kb]);

        $a_vals[$ka] = true;
        $b_vals[$kb] = true;
        $matrix[$ka][$kb] = (int)$r['cnt'];
    }

    return ['a_values' => array_keys($a_vals), 'b_values' => array_keys($b_vals), 'matrix' => $matrix];
}

// ── Forms list for report selection ─────────────────────────────

/**
 * @param  array<string, mixed> $user
 * @return array<string, mixed>
 */
function get_reportable_forms(array $user, int $page = 1, int $per_page = 20): array {
    $fids = _scoped_form_ids($user);
    $fs = _form_scope_sql('f', $fids);
    $sql = "SELECT f.id, f.uuid, f.name, f.status,
        (SELECT COUNT(*) FROM submissions WHERE form_id=f.id AND status!='draft') AS sub_count,
        (SELECT MAX(submitted_at) FROM submissions WHERE form_id=f.id AND status!='draft') AS last_submission_at
        FROM forms f WHERE f.deleted_at IS NULL AND {$fs} ORDER BY sub_count DESC, f.name";
    return paginate($sql, [], $page, $per_page);
}

/**
 * @param  array<string, mixed> $user
 * @return array<string, mixed>
 */
function get_reportable_programs(array $user, int $page = 1, int $per_page = 20): array {
    if (can_view_global_reports($user)) {
        $sql = "SELECT p.id, p.uuid, p.name, p.status,
            (SELECT COUNT(*) FROM program_submissions WHERE program_id=p.id AND status!='draft') AS sub_count,
            (SELECT MAX(submitted_at) FROM program_submissions WHERE program_id=p.id AND status!='draft') AS last_submission_at
            FROM programs p ORDER BY sub_count DESC, p.name";
        return paginate($sql, [], $page, $per_page);
    }
    // Admin sees programs whose forms they own — filter in PHP, then manually paginate
    $fids = _scoped_form_ids($user);
    if (empty($fids)) {
        return ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1, 'per_page' => $per_page];
    }
    $all = db_query("SELECT id, uuid, name, status, form_ids FROM programs");
    $filtered = [];
    foreach ($all as $p) {
        $pf = json_decode($p['form_ids'], true) ?: [];
        if (array_intersect($pf, $fids)) {
            $cnt = db_one("SELECT COUNT(*) c FROM program_submissions WHERE program_id=? AND status!='draft'", [$p['id']]);
            $p['sub_count'] = (int)($cnt['c'] ?? 0);
            $last = db_one("SELECT MAX(submitted_at) last_at FROM program_submissions WHERE program_id=? AND status!='draft'", [$p['id']]);
            $p['last_submission_at'] = $last['last_at'] ?? null;
            unset($p['form_ids']);
            $filtered[] = $p;
        }
    }
    usort($filtered, fn($a, $b) => $b['sub_count'] - $a['sub_count'] ?: strcmp($a['name'], $b['name']));
    $total = count($filtered);
    $pages = max(1, (int)ceil($total / $per_page));
    $page  = max(1, min($page, $pages));
    return [
        'rows'     => array_slice($filtered, ($page - 1) * $per_page, $per_page),
        'total'    => $total,
        'pages'    => $pages,
        'page'     => $page,
        'per_page' => $per_page,
    ];
}

// ── Exportable data ─────────────────────────────────────────────

/**
 * @param  array{from: string|null, to: string|null}|null $date_range
 * @return array<string, mixed>
 */
function get_exportable_submissions(int $form_id, ?array $date_range = null, string $status_filter = ''): array {
    require_once __DIR__ . '/../forms/models.php';
    $form = db_one("SELECT active_version_id FROM forms WHERE id=?", [$form_id]);
    if (!$form || !$form['active_version_id']) return [];
    $questions = get_questions((int)$form['active_version_id']);

    $ds = _date_sql('s.submitted_at', $date_range);
    $status_sql = "s.status != 'draft'";
    $status_params = [];
    if ($status_filter && preg_match('/^[a-z_]+$/', $status_filter)) {
        $status_sql = "s.status = ?";
        $status_params = [$status_filter];
    }

    $subs = db_query(
        "SELECT s.uuid, s.status, s.submitted_at, s.created_at, s.answers, u.name, u.email
         FROM submissions s JOIN users u ON s.user_id=u.id
         WHERE s.form_id=? AND {$status_sql} AND {$ds}
         ORDER BY s.submitted_at DESC",
        array_merge([$form_id], $status_params)
    );

    $headers = ['Submission ID', 'User Name', 'Email', 'Status', 'Submitted At', 'Created At'];
    foreach ($questions as $q) $headers[] = $q['config']['label'] ?? 'Q-' . $q['uid'];

    $rows = [];
    foreach ($subs as $s) {
        $answers = is_string($s['answers']) ? json_decode($s['answers'], true) : ($s['answers'] ?? []);
        $row = [$s['uuid'], $s['name'], $s['email'], $s['status'], $s['submitted_at'], $s['created_at']];
        foreach ($questions as $q) {
            $val = $answers['q_' . $q['uid']] ?? '';
            $row[] = is_array($val) ? implode('; ', $val) : (string)$val;
        }
        $rows[] = $row;
    }

    return ['headers' => $headers, 'rows' => $rows];
}

// ── Program submissions export ──────────────────────────────────

/**
 * @param  array{from: string|null, to: string|null}|null $date_range
 * @return array<string, mixed>
 */
function get_exportable_program_submissions(int $program_id, ?array $date_range = null): array {
    $program = db_one("SELECT name, form_ids, review_stages FROM programs WHERE id=?", [$program_id]);
    if (!$program) return [];

    $stages = json_decode($program['review_stages'] ?? '[]', true) ?: [];
    $stage_names = [];
    foreach ($stages as $s) $stage_names[(int)$s['order']] = $s['name'] ?? "Stage {$s['order']}";

    $ds = _date_sql('ps.submitted_at', $date_range);
    $subs = db_query(
        "SELECT ps.uuid, ps.status, ps.current_stage, ps.decisions, ps.submitted_at, ps.created_at, u.name, u.email
         FROM program_submissions ps
         JOIN users u ON ps.user_id = u.id
         WHERE ps.program_id = ? AND ps.status != 'draft' AND {$ds}
         ORDER BY ps.submitted_at DESC",
        [$program_id]
    );

    $headers = ['Submission ID', 'User Name', 'Email', 'Status', 'Current Stage', 'Submitted At', 'Created At'];
    // Add a column per review stage showing the decision
    foreach ($stage_names as $order => $name) {
        $headers[] = $name . ' Decision';
        $headers[] = $name . ' Decided At';
    }

    $rows = [];
    foreach ($subs as $s) {
        $decs = json_decode($s['decisions'], true) ?: [];
        $dec_by_stage = [];
        foreach ($decs as $d) $dec_by_stage[(int)$d['stage']] = $d;

        $stage_label = $stage_names[(int)$s['current_stage']] ?? "Stage {$s['current_stage']}";
        $row = [$s['uuid'], $s['name'], $s['email'], $s['status'], $stage_label, $s['submitted_at'], $s['created_at']];

        foreach ($stage_names as $order => $name) {
            $d = $dec_by_stage[$order] ?? null;
            $row[] = $d ? ($d['decision'] ?? '') : '';
            $row[] = $d ? ($d['decided_at'] ?? '') : '';
        }
        $rows[] = $row;
    }

    return ['headers' => $headers, 'rows' => $rows];
}
