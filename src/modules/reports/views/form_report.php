<?php
/**
 * Form Detail Report View
 *
 * @var array<string, mixed>  $form         Form record
 * @var array<string, mixed>  $report       Aggregated form metrics
 * @var array<string, mixed>  $distribution Answer distribution data
 * @var array<string, mixed>|null $scoring  Scoring distribution (null if scoring not enabled)
 * @var array<string, mixed>|null $date_range Active date filter or null
 */

$generated = $report['_generated_at'] ?? null;
$vol_data = $report['volume_30d'] ?? [];
$max_vol = max(array_column($vol_data, 'count') ?: [1]);

$user_tz = get_user_timezone();
$peak_display = null;
if ($report['peak_hour'] !== null) {
    $utc_dt = new DateTimeImmutable(sprintf('2026-01-01 %02d:00:00', $report['peak_hour']), new DateTimeZone('UTC'));
    $peak_display = $utc_dt->setTimezone(new DateTimeZone($user_tz))->format('H:i');
}

$vol_label = 'Last 30 Days';
if ($date_range) {
    $parts = [];
    if ($date_range['from']) $parts[] = date('d/m/Y', strtotime($date_range['from']));
    if ($date_range['to']) $parts[] = date('d/m/Y', strtotime($date_range['to']));
    $vol_label = implode(' — ', $parts);
}

$base_url = '/admin/reports/forms/' . sanitize($form['uuid']);
$all_statuses = array_keys($report['by_status'] ?? []);
?>

<!-- Back + Page Header -->
<a href="/admin/reports" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary-600 transition-colors mb-4">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3' : 'M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18' ?>"/></svg>
    Back to Reports
</a>

<div class="sm:flex sm:items-start sm:justify-between gap-4">
    <div>
        <h1 class="page-title"><?= sanitize($form['name']) ?></h1>
        <p class="mt-1 text-sm text-gray-500">
            Form analytics and submission insights.
            <?php if ($generated): ?>
                <span class="text-xs text-gray-400 ml-2">Updated <?= format_datetime($generated, 'short') ?></span>
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Analysis Controls -->
<div class="mt-6 card">
    <div class="card-header">
        <h2 class="eyebrow">Analysis Controls</h2>
        <p class="help-text">Filter data by date range and export results for further analysis.</p>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Date Range Filter -->
            <div class="space-y-3">
                <div>
                    <h3 class="text-sm font-medium text-gray-900 mb-1">Date Range Filter</h3>
                    <p class="text-xs text-gray-500">Filter submissions by date to analyze specific periods</p>
                </div>
                
                <form method="GET" action="<?= $base_url ?>" class="space-y-3">
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
                            <a href="<?= $base_url ?>" class="btn btn-secondary btn-sm">
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

            <!-- Data Export -->
            <div class="space-y-3">
                <div>
                    <h3 class="text-sm font-medium text-gray-900 mb-1">Export Data</h3>
                    <p class="text-xs text-gray-500">Download submission data as CSV for external analysis</p>
                </div>
                
                <form method="POST" action="/admin/reports/export" class="space-y-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_id" value="<?= (int)$form['id'] ?>">
                    <input type="hidden" name="export_from" value="<?= sanitize($date_range['from'] ?? '') ?>">
                    <input type="hidden" name="export_to" value="<?= sanitize($date_range['to'] ?? '') ?>">
                    
                    <div>
                        <label for="export_status" class="block text-xs font-medium text-gray-700 mb-1">Status Filter</label>
                        <select name="export_status" id="export_status" class="input input-sm w-full">
                            <option value="">All statuses (recommended)</option>
                            <?php foreach ($all_statuses as $st): if ($st === 'draft') continue; ?>
                            <option value="<?= sanitize($st) ?>"><?= sanitize(ucwords(str_replace('_', ' ', $st))) ?> only</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                        <div class="flex items-start gap-2">
                            <svg class="h-4 w-4 text-gray-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            </svg>
                            <div>
                                <p class="text-xs font-medium text-gray-700">Export includes:</p>
                                <p class="text-xs text-gray-500 mt-0.5">All submission data, answers, timestamps, status, and metadata <?= $date_range ? 'for the filtered date range' : 'for all time' ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary btn-sm w-full">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        Download CSV Export
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ── Key Metrics ───────────────────────────────────────────── -->
<div class="mt-8">
    <h2 class="section-title">Key Metrics</h2>
    <p class="help-text mb-4">Overview of submission activity and performance for this form.</p>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Submissions</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($report['total'] ?? 0) ?></p>
                    <p class="help-text"><?= number_format($report['unique_submitters'] ?? 0) ?> unique submitters</p>
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
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $report['completion_rate'] ?? 0 ?>%</p>
                    <p class="help-text"><?= number_format($report['drafts'] ?? 0) ?> active drafts</p>
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
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $report['avg_hours_to_submit'] ?? 0 ?>h</p>
                    <p class="help-text">Median: <?= $report['median_hours_to_submit'] ?? 0 ?>h</p>
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
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $report['clarification_rate'] ?? 0 ?>%</p>
                    <p class="help-text"><?= number_format($report['clarifications'] ?? 0) ?> total</p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-yellow-50 p-3">
                    <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Submission Volume ─────────────────────────────────────── -->
<div class="mt-8">
    <h2 class="section-title">Submission Volume</h2>
    <p class="help-text mb-4">Daily activity — <?= sanitize($vol_label) ?>.<?php if ($peak_display !== null): ?> Peak hour: <?= sanitize($peak_display) ?> <?= sanitize($user_tz) ?>.<?php endif; ?></p>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($vol_data)): ?>
            <div class="flex items-end gap-px" style="height:100px">
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

<!-- ── Status Breakdown ──────────────────────────────────────── -->
<?php if (!empty($report['by_status'])): ?>
<div class="mt-8">
    <h2 class="section-title">Status Breakdown</h2>
    <p class="help-text mb-4">How submissions are distributed across processing states.</p>

    <div class="card">
        <div class="card-body">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <?php
                $status_colors = ['submitted' => 'blue', 'draft' => 'gray', 'approved' => 'green', 'rejected' => 'red', 'clarification_requested' => 'yellow', 'under_review' => 'purple'];
                foreach ($report['by_status'] as $status => $cnt):
                    $color = $status_colors[$status] ?? 'gray';
                ?>
                <div class="text-center p-3 rounded-lg bg-<?= $color ?>-50">
                    <p class="text-2xl font-semibold text-<?= $color ?>-700"><?= number_format($cnt) ?></p>
                    <p class="text-xs text-<?= $color ?>-600 mt-1"><?= sanitize(ucwords(str_replace('_', ' ', $status))) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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

<!-- ── Scoring Distribution ──────────────────────────────────── -->
<?php if ($scoring): ?>
<div class="mt-8">
    <h2 class="section-title">Score Distribution</h2>
    <p class="help-text mb-4">How submissions score against the form's rubric (max <?= number_format($scoring['max_possible']) ?> points).</p>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-4 mb-4">
        <div class="stat-card">
            <p class="text-sm font-medium text-gray-500">Average</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $scoring['avg'] ?></p>
            <p class="help-text">of <?= $scoring['max_possible'] ?> possible</p>
        </div>
        <div class="stat-card">
            <p class="text-sm font-medium text-gray-500">Median</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $scoring['median'] ?></p>
        </div>
        <div class="stat-card">
            <p class="text-sm font-medium text-gray-500">Lowest</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $scoring['min'] ?></p>
        </div>
        <div class="stat-card">
            <p class="text-sm font-medium text-gray-500">Highest</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $scoring['max'] ?></p>
        </div>
    </div>

    <?php if (!empty($scoring['histogram'])): ?>
    <?php $hist_max = max(array_column($scoring['histogram'], 'count') ?: [1]); ?>
    <div class="card">
        <div class="card-body">
            <div class="space-y-2">
                <?php foreach ($scoring['histogram'] as $bucket): ?>
                <?php $pct = $hist_max > 0 ? round($bucket['count'] / $hist_max * 100) : 0; ?>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-600 w-20 text-right font-mono"><?= sanitize($bucket['label']) ?></span>
                    <div class="flex-1 h-5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-primary-400 rounded-full transition-all" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="text-xs font-medium text-gray-700 w-10 text-right"><?= $bucket['count'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-3"><?= number_format($scoring['count']) ?> scored submission<?= $scoring['count'] != 1 ? 's' : '' ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Answer Distribution ───────────────────────────────────── -->
<?php $dist_items = $distribution['items'] ?? []; ?>
<?php if (!empty($dist_items)): ?>
<div class="mt-8">
    <h2 class="section-title">Answer Distribution</h2>
    <p class="help-text mb-4">Response patterns for multiple-choice and selection questions.</p>

    <div class="card divide-y divide-gray-200">
        <?php foreach ($dist_items as $q): ?>
        <div class="px-6 py-4">
            <p class="text-sm font-medium text-gray-900 mb-3"><?= sanitize($q['label']) ?></p>
            <?php
            $total_answers = array_sum($q['distribution']);
            foreach ($q['distribution'] as $opt => $cnt):
                $pct = $total_answers > 0 ? round($cnt / $total_answers * 100, 1) : 0;
            ?>
            <div class="flex items-center gap-3 mb-1.5">
                <span class="text-xs text-gray-600 w-32 truncate" title="<?= sanitize($opt) ?>"><?= sanitize($opt) ?></span>
                <div class="flex-1 h-4 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-primary-400 rounded-full" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="text-xs font-medium text-gray-700 w-16 text-right"><?= $cnt ?> (<?= $pct ?>%)</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
