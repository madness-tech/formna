<?php
/**
 * Reports Overview Dashboard
 *
 * @var array<string, mixed>      $summary    Aggregated metrics (cached or live)
 * @var array<string, mixed>      $volume     Daily submission counts for chart
 * @var array<string, mixed>      $forms      Paginated result: {rows, total, pages, page, per_page}
 * @var array<string, mixed>      $programs   Paginated result: {rows, total, pages, page, per_page}
 * @var bool                      $is_global  Whether user has global report access
 * @var array<string, mixed>|null $date_range Active date filter or null
 * @var array<string, mixed>|null $health     Cron health status (global users only)
 */

$generated = $summary['_generated_at'] ?? null;
$vol_data = $volume['volume'] ?? [];
$max_vol = max(array_column($vol_data, 'count') ?: [1]);

$user_tz = get_user_timezone();
$peak_display = '—';
if ($summary['peak_hour'] !== null) {
    $utc_dt = new DateTimeImmutable(sprintf('2026-01-01 %02d:00:00', $summary['peak_hour']), new DateTimeZone('UTC'));
    $peak_display = $utc_dt->setTimezone(new DateTimeZone($user_tz))->format('H:i');
}

$vol_label = 'Last 30 Days';
if ($date_range) {
    $parts = [];
    if ($date_range['from']) $parts[] = date('d/m/Y', strtotime($date_range['from']));
    if ($date_range['to']) $parts[] = date('d/m/Y', strtotime($date_range['to']));
    $vol_label = implode(' — ', $parts);
}

$trend_change = $summary['submissions_30d_change'] ?? null;
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="page-title">Reports &amp; Analytics</h1>
    <p class="mt-1 text-sm text-gray-500">
        <?= $is_global ? 'System-wide analytics overview.' : 'Analytics for your forms and programs.' ?>
        <?php if ($generated): ?>
            <span class="text-xs text-gray-400 ml-2">Updated <?= format_datetime($generated, 'short') ?></span>
        <?php endif; ?>
    </p>
</div>

<!-- Analysis Controls -->
<div class="mb-6 card">
    <div class="card-header">
        <h2 class="eyebrow">Analysis Controls</h2>
        <p class="help-text">Filter analytics by date range to analyze trends and performance over specific periods.</p>
    </div>
    <div class="card-body">
        <div class="max-w-md">
            <div class="space-y-3">
                <div>
                    <h3 class="text-sm font-medium text-gray-900 mb-1">Date Range Filter</h3>
                    <p class="text-xs text-gray-500">View analytics for a specific time period</p>
                </div>
                
                <form method="GET" action="/admin/reports" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="filter_from" class="block text-xs font-medium text-gray-700 mb-1">From Date</label>
                            <input 
                                type="text" 
                                id="filter_from"
                                name="from" 
                                value="<?= sanitize($date_range['from'] ?? '') ?>" 
                                class="input input-sm w-full"
                                data-datepicker
                                placeholder="DD/MM/YYYY"
                            />
                        </div>
                        <div>
                            <label for="filter_to" class="block text-xs font-medium text-gray-700 mb-1">To Date</label>
                            <input 
                                type="text" 
                                id="filter_to"
                                name="to" 
                                value="<?= sanitize($date_range['to'] ?? '') ?>" 
                                class="input input-sm w-full"
                                data-datepicker
                                placeholder="DD/MM/YYYY"
                            />
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2 pt-1">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/>
                            </svg>
                            Apply Filter
                        </button>
                        
                        <?php if ($date_range): ?>
                            <a href="/admin/reports" class="btn btn-secondary btn-sm">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Clear Filter
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($date_range): ?>
                        <div class="bg-primary-50 border border-primary-200 rounded-md px-3 py-2">
                            <div class="flex items-start gap-2">
                                <svg class="h-4 w-4 text-primary-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                                </svg>
                                <div>
                                    <p class="text-xs font-medium text-primary-800">Filtered Results Active</p>
                                    <p class="text-xs text-primary-600 mt-0.5">Showing data: <?= sanitize($vol_label) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Cron Health Banner (global admins only) -->
<?php if ($health && in_array($health['status'] ?? '', ['degraded', 'critical'], true)): ?>
<div class="alert <?= ($health['status'] === 'critical') ? 'alert-danger' : 'alert-warning' ?> mt-4 flex items-start gap-3">
    <svg class="h-5 w-5 <?= ($health['status'] === 'critical') ? 'text-red-600' : 'text-yellow-600' ?> flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
    <div>
        <p class="font-medium"><?= ($health['status'] === 'critical') ? 'Report cache failed completely' : 'Report cache partially failed' ?></p>
        <p class="text-xs mt-1"><?= (int)($health['failed'] ?? 0) ?> of <?= (int)($health['total_reports'] ?? 0) ?> reports failed to generate.
            <?php if (!empty($health['failed_reports'])): ?>
                Failed: <?= sanitize(implode(', ', $health['failed_reports'])) ?>.
            <?php endif; ?>
            Last run: <?= $health['last_run'] ? format_datetime($health['last_run'], 'short') : 'unknown' ?>.
        </p>
    </div>
</div>
<?php elseif ($health && ($health['status'] ?? '') === 'unknown'): ?>
<div class="alert alert-info mt-4 flex items-start gap-3">
    <svg class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
    <div>
        <p class="font-medium">Report cache not yet initialized</p>
        <p class="text-xs mt-1">The cron job has not run yet. Data shown is generated live and may be slower.</p>
    </div>
</div>
<?php endif; ?>

<!-- ── At a Glance ───────────────────────────────────────────── -->
<div class="mt-8">
    <h2 class="section-title">At a Glance</h2>
    <p class="help-text mb-4">Key metrics across all <?= $is_global ? 'forms and programs' : 'your forms' ?>.</p>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Submissions</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($summary['total_submissions'] ?? 0) ?></p>
                    <p class="help-text">
                        <?= number_format($summary['submissions_7d'] ?? 0) ?> this week · <?= number_format($summary['submissions_30d'] ?? 0) ?> this month
                        <?php if ($trend_change !== null): ?>
                            <span class="ml-1 <?= $trend_change >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $trend_change >= 0 ? '↑' : '↓' ?> <?= abs($trend_change) ?>%
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-blue-50 p-3">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Completion Rate</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $summary['completion_rate'] ?? 0 ?>%</p>
                    <p class="help-text"><?= number_format($summary['active_drafts'] ?? 0) ?> active drafts</p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-green-50 p-3">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Avg. Time to Submit</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $summary['avg_hours_to_submit'] ?? 0 ?>h</p>
                    <p class="help-text">From draft creation to submission</p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-purple-50 p-3">
                    <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Clarification Rate</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $summary['clarification_rate'] ?? 0 ?>%</p>
                    <p class="help-text">Submissions needing clarification</p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-yellow-50 p-3">
                    <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Metrics -->
<div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
    <div class="stat-card">
        <p class="text-sm font-medium text-gray-500">Published Forms</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($summary['published_forms'] ?? 0) ?></p>
        <p class="help-text"><?= number_format($summary['total_forms'] ?? 0) ?> total</p>
    </div>
    <div class="stat-card">
        <p class="text-sm font-medium text-gray-500">Unique Submitters</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($summary['unique_submitters'] ?? 0) ?></p>
        <p class="help-text"><?= number_format($summary['repeat_submitters'] ?? 0) ?> submitted to 2+ forms</p>
    </div>
    <div class="stat-card">
        <p class="text-sm font-medium text-gray-500">Peak Submission Hour</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900"><?= sanitize($peak_display) ?></p>
        <p class="help-text"><?= sanitize($user_tz) ?> · <?= sanitize($summary['peak_day'] ?? '—') ?> is busiest day</p>
    </div>
    <?php if ($is_global): ?>
    <div class="stat-card">
        <p class="text-sm font-medium text-gray-500">Active Users</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($summary['total_users'] ?? 0) ?></p>
        <p class="help-text">+<?= number_format($summary['new_users_30d'] ?? 0) ?> this month</p>
    </div>
    <?php else: ?>
    <div></div>
    <?php endif; ?>
</div>

<?php if ($is_global && isset($summary['total_programs'])): ?>
<div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-3">
    <div class="stat-card">
        <p class="text-sm font-medium text-gray-500">Programs</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($summary['total_programs']) ?></p>
    </div>
    <div class="stat-card">
        <p class="text-sm font-medium text-gray-500">Program Approval Rate</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $summary['program_approval_rate'] ?>%</p>
    </div>
    <div class="stat-card">
        <p class="text-sm font-medium text-gray-500">New Users (7d)</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($summary['new_users_7d'] ?? 0) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- ── Submission Trends ─────────────────────────────────────── -->
<div class="mt-8">
    <h2 class="section-title">Submission Trends</h2>
    <p class="help-text mb-4">Daily submission volume — <?= sanitize($vol_label) ?>.</p>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($vol_data)): ?>
            <div class="flex items-end gap-px" style="height:120px">
                <?php foreach ($vol_data as $day): ?>
                <?php $h = $max_vol > 0 ? max(2, round(($day['count'] / $max_vol) * 100)) : 2; ?>
                <div class="flex-1 h-full relative" title="<?= sanitize($day['date']) ?>: <?= (int)$day['count'] ?>">
                    <div class="absolute bottom-0 inset-x-0 bg-primary-500 hover:bg-primary-600 rounded-t transition-all" style="height:<?= $h ?>px"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-between mt-2 text-xs text-gray-400">
                <span><?= sanitize($vol_data[0]['date'] ?? '') ?></span>
                <span><?= sanitize(end($vol_data)['date'] ?? '') ?></span>
            </div>
            <?php else: ?>
            <p class="text-sm text-gray-400 text-center py-8">No submission data for this period.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Forms & Programs ──────────────────────────────────────── -->
<?php
$_rpt_from = $date_range['from'] ?? '';
$_rpt_to   = $date_range['to']   ?? '';
?>
<div class="mt-8 space-y-8">
    <h2 class="section-title">Forms &amp; Programs</h2>
    <p class="help-text -mt-6">Drill into individual form or program reports.</p>

    <!-- Forms Section -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3 class="eyebrow">Forms</h3>
            <span class="text-xs text-gray-400">
                <?= number_format($forms['total']) ?> form<?= $forms['total'] != 1 ? 's' : '' ?> total
                <?php if ($forms['pages'] > 1): ?>
                    &middot; page <?= $forms['page'] ?> of <?= $forms['pages'] ?>
                <?php endif; ?>
            </span>
        </div>
        <?php if (!empty($forms['rows'])): ?>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Form Name</th>
                        <th scope="col">Status</th>
                        <th scope="col">Submissions</th>
                        <th scope="col">Last Submission</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($forms['rows'] as $f): ?>
                <tr class="hover:bg-gray-50">
                    <td class="text-sm font-medium text-gray-900"><?= sanitize($f['name']) ?></td>
                    <td>
                        <?php if ($f['status'] === 'published'): ?>
                            <span class="badge badge-green">Published</span>
                        <?php else: ?>
                            <span class="badge badge-gray">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-gray-900"><?= number_format($f['sub_count']) ?></td>
                    <td class="text-sm text-gray-500 whitespace-nowrap">
                        <?= $f['last_submission_at'] ? format_datetime($f['last_submission_at'], 'short') : '—' ?>
                    </td>
                    <td class="whitespace-nowrap text-right font-medium">
                        <div class="flex items-center justify-end">
                            <a href="/admin/reports/forms/<?= sanitize($f['uuid']) ?>" class="inline-flex items-center text-primary-600 hover:text-primary-900 font-medium">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                View
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">No forms available.</div>
        <?php endif; ?>
        <?php
        $pagination   = $forms;
        $base_url     = '/admin/reports';
        $page_param   = 'fp';
        $query_params = array_filter(['pp' => (string)$programs['page'], 'from' => $_rpt_from, 'to' => $_rpt_to], fn($v) => $v !== '');
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>
    </div>

    <!-- Programs Section -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3 class="eyebrow">Programs</h3>
            <span class="text-xs text-gray-400">
                <?= number_format($programs['total']) ?> program<?= $programs['total'] != 1 ? 's' : '' ?> total
                <?php if ($programs['pages'] > 1): ?>
                    &middot; page <?= $programs['page'] ?> of <?= $programs['pages'] ?>
                <?php endif; ?>
            </span>
        </div>
        <?php if (!empty($programs['rows'])): ?>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Program Name</th>
                        <th scope="col">Status</th>
                        <th scope="col">Submissions</th>
                        <th scope="col">Last Submission</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($programs['rows'] as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="text-sm font-medium text-gray-900"><?= sanitize($p['name']) ?></td>
                    <td>
                        <?php if ($p['status'] === 'active'): ?>
                            <span class="badge badge-green">Active</span>
                        <?php elseif ($p['status'] === 'closed'): ?>
                            <span class="badge badge-gray">Closed</span>
                        <?php else: ?>
                            <span class="badge badge-yellow">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-gray-900"><?= number_format($p['sub_count']) ?></td>
                    <td class="text-sm text-gray-500 whitespace-nowrap">
                        <?= $p['last_submission_at'] ? format_datetime($p['last_submission_at'], 'short') : '—' ?>
                    </td>
                    <td class="whitespace-nowrap text-right font-medium">
                        <div class="flex items-center justify-end">
                            <a href="/admin/reports/programs/<?= sanitize($p['uuid']) ?>" class="inline-flex items-center text-primary-600 hover:text-primary-900 font-medium">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                View
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">No programs available.</div>
        <?php endif; ?>
        <?php
        $pagination   = $programs;
        $base_url     = '/admin/reports';
        $page_param   = 'pp';
        $query_params = array_filter(['fp' => (string)$forms['page'], 'from' => $_rpt_from, 'to' => $_rpt_to], fn($v) => $v !== '');
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>
    </div>
</div>

<!-- Flatpickr date picker -->
<link rel="stylesheet" href="<?= asset('/css/flatpickr.min.css') ?>">
<script src="<?= asset('/js/vendor/flatpickr.min.js') ?>"></script>
<script>
document.querySelectorAll('[data-datepicker]').forEach(function(el) {
    flatpickr(el, {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        maxDate: 'today',
        allowInput: true
    });
});
</script>
