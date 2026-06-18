<?php
/**
 * Program Detail Report View
 *
 * @var array<string, mixed>      $program    Program record (with 'forms' array from get_program_with_forms)
 * @var array<string, mixed>      $report     Aggregated program metrics
 * @var array<string, mixed>|null $date_range Active date filter or null
 */

$generated = $report['_generated_at'] ?? null;
$vol_data = $report['volume_30d'] ?? [];
$max_vol = max(array_column($vol_data, 'count') ?: [1]);
$base_url = '/admin/reports/programs/' . sanitize($program['uuid']);
$bottleneck = $report['bottleneck_stage'] ?? null;
$program_forms = $program['forms'] ?? [];

$vol_label = 'Last 30 Days';
if ($date_range) {
    $parts = [];
    if ($date_range['from']) $parts[] = date('d/m/Y', strtotime($date_range['from']));
    if ($date_range['to']) $parts[] = date('d/m/Y', strtotime($date_range['to']));
    $vol_label = implode(' — ', $parts);
}
?>

<!-- Back + Page Header -->
<a href="/admin/reports" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary-600 transition-colors mb-4">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3' : 'M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18' ?>"/></svg>
    Back to Reports
</a>

<div class="sm:flex sm:items-start sm:justify-between gap-4">
    <div>
        <h1 class="page-title"><?= sanitize($program['name']) ?></h1>
        <p class="mt-1 text-sm text-gray-500">
            Program analytics and review pipeline insights.
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
        <p class="help-text">Filter program data by date range and export submission details for further analysis.</p>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Date Range Filter -->
            <div class="space-y-3">
                <div>
                    <h3 class="text-sm font-medium text-gray-900 mb-1">Date Range Filter</h3>
                    <p class="text-xs text-gray-500">Filter program submissions by date to analyze specific periods</p>
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
                    <h3 class="text-sm font-medium text-gray-900 mb-1">Export Program Data</h3>
                    <p class="text-xs text-gray-500">Download all program submission data as CSV for external analysis</p>
                </div>
                
                <form method="POST" action="/admin/reports/export-program" class="space-y-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="program_id" value="<?= (int)$program['id'] ?>">
                    <?php if (!empty($date_range['from'])): ?>
                        <input type="hidden" name="export_from" value="<?= sanitize($date_range['from']) ?>">
                    <?php endif; ?>
                    <?php if (!empty($date_range['to'])): ?>
                        <input type="hidden" name="export_to" value="<?= sanitize($date_range['to']) ?>">
                    <?php endif; ?>
                    
                    <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                        <div class="flex items-start gap-2">
                            <svg class="h-4 w-4 text-gray-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            </svg>
                            <div>
                                <p class="text-xs font-medium text-gray-700">Export includes:</p>
                                <p class="text-xs text-gray-500 mt-0.5">Program submissions, review stages, timelines, decisions, and all form data from included forms</p>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary btn-sm w-full">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        Download Program Export
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- ── Key Metrics ───────────────────────────────────────────── -->
<div class="mt-8">
    <h2 class="section-title">Key Metrics</h2>
    <p class="help-text mb-4">High-level program submission and review performance.</p>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Submissions</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($report['total'] ?? 0) ?></p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-blue-50 p-3">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Approval Rate</p>
                    <p class="mt-2 text-3xl font-semibold text-<?= ($report['approval_rate'] ?? 0) >= 50 ? 'green' : 'gray' ?>-700"><?= $report['approval_rate'] ?? 0 ?>%</p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-green-50 p-3">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Avg. Review Time</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $report['avg_review_hours'] ?? 0 ?>h</p>
                    <p class="help-text">Submission to final decision</p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-purple-50 p-3">
                    <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Review Stages</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= count($report['funnel'] ?? []) ?></p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-indigo-50 p-3">
                    <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Status Breakdown ──────────────────────────────────────── -->
<?php if (!empty($report['by_status'])): ?>
<div class="mt-8">
    <h2 class="section-title">Status Breakdown</h2>
    <p class="help-text mb-4">Current state of all program submissions.</p>

    <div class="card">
        <div class="card-body">
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                <?php
                $colors = ['draft' => 'gray', 'submitted' => 'blue', 'in_review' => 'purple', 'approved' => 'green', 'rejected' => 'red'];
                foreach ($report['by_status'] as $st => $cnt):
                    $c = $colors[$st] ?? 'gray';
                ?>
                <div class="text-center p-3 rounded-lg bg-<?= $c ?>-50">
                    <p class="text-2xl font-semibold text-<?= $c ?>-700"><?= number_format($cnt) ?></p>
                    <p class="text-xs text-<?= $c ?>-600 mt-1"><?= sanitize(ucwords(str_replace('_', ' ', $st))) ?></p>
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

<!-- ── Review Funnel ─────────────────────────────────────────── -->
<?php if (!empty($report['funnel'])): ?>
<div class="mt-8">
    <h2 class="section-title">Review Funnel</h2>
    <p class="help-text mb-4">How submissions progress through each review stage, including pass and rejection rates.<?php if ($bottleneck !== null): ?> <span class="text-amber-600 font-medium">Slowest stage highlighted.</span><?php endif; ?></p>

    <div class="card">
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th>Reached</th>
                        <th>Passed</th>
                        <th>Rejected</th>
                        <th>Pass Rate</th>
                        <th>Avg. Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['funnel'] as $stage):
                        $passed = (int)($stage['passed'] ?? 0);
                        $rejected = (int)($stage['rejected'] ?? 0);
                        $decided = $passed + $rejected;
                        $pass_rate = $decided > 0 ? round($passed / $decided * 100, 1) : 0;
                        $reached = (int)($stage['reached'] ?? 0);
                        $is_bottleneck = $bottleneck !== null && $stage['stage'] === $bottleneck;
                    ?>
                    <tr class="<?= $is_bottleneck ? 'bg-amber-50' : 'hover:bg-gray-50' ?>">
                        <td class="text-sm font-medium text-gray-900">
                            <div class="flex items-center gap-2">
                                <span><?= sanitize($stage['name']) ?></span>
                                <?php if ($is_bottleneck): ?>
                                    <span class="badge badge-yellow">Bottleneck</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Stage <?= (int)$stage['stage'] ?></div>
                        </td>
                        <td class="text-sm text-gray-700"><?= number_format($reached) ?></td>
                        <td>
                            <div class="text-sm font-medium text-green-700"><?= number_format($passed) ?></div>
                            <div class="text-xs text-gray-500"><?= $reached > 0 ? round($passed / $reached * 100, 1) : 0 ?>% of reached</div>
                        </td>
                        <td>
                            <div class="text-sm font-medium text-red-700"><?= number_format($rejected) ?></div>
                            <div class="text-xs text-gray-500"><?= $reached > 0 ? round($rejected / $reached * 100, 1) : 0 ?>% of reached</div>
                        </td>
                        <td>
                            <div class="text-sm font-medium text-gray-900"><?= $pass_rate ?>%</div>
                            <div class="text-xs text-gray-500">of decisions</div>
                        </td>
                        <td>
                            <?php if (!empty($stage['avg_hours'])): ?>
                                <div class="text-sm font-medium <?= $is_bottleneck ? 'text-amber-700' : 'text-gray-900' ?>"><?= $stage['avg_hours'] ?>h</div>
                                <div class="text-xs text-gray-500">Average review time</div>
                            <?php else: ?>
                                <div class="text-sm text-gray-400">—</div>
                                <div class="text-xs text-gray-400">No decisions yet</div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 text-xs text-gray-500">
            Pass rate is based on decided submissions (approved + rejected) per stage. “Reached” includes submissions currently in review.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Submission Volume ─────────────────────────────────────── -->
<div class="mt-8">
    <h2 class="section-title">Submission Volume</h2>
    <p class="help-text mb-4">Daily program submissions — <?= sanitize($vol_label) ?>.</p>

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

<!-- ── Included Forms ────────────────────────────────────────── -->
<?php if (!empty($program_forms)):
    $pf_per_page = 10;
    $pf_total    = count($program_forms);
    $pf_pages    = max(1, (int)ceil($pf_total / $pf_per_page));
    $pf_page     = max(1, min((int)($_GET['pf'] ?? 1), $pf_pages));
    $pf_rows     = array_slice($program_forms, ($pf_page - 1) * $pf_per_page, $pf_per_page);
?>
<div class="mt-8">
    <h2 class="section-title">Included Forms</h2>
    <p class="help-text mb-4">Forms that feed into this program's review pipeline.</p>

    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3 class="eyebrow">Forms</h3>
            <span class="text-xs text-gray-400">
                <?= number_format($pf_total) ?> form<?= $pf_total != 1 ? 's' : '' ?> total
                <?php if ($pf_pages > 1): ?>
                    &middot; page <?= $pf_page ?> of <?= $pf_pages ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Form Name</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pf_rows as $pf): ?>
                <tr class="hover:bg-gray-50">
                    <td class="text-sm font-medium text-gray-900"><?= sanitize($pf['name']) ?></td>
                    <td>
                        <?php if (($pf['status'] ?? '') === 'published'): ?>
                            <span class="badge badge-green">Published</span>
                        <?php else: ?>
                            <span class="badge badge-gray"><?= sanitize(ucfirst($pf['status'] ?? 'draft')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="whitespace-nowrap text-right font-medium">
                        <div class="flex items-center justify-end">
                            <a href="/admin/reports/forms/<?= sanitize($pf['uuid']) ?>" class="inline-flex items-center text-primary-600 hover:text-primary-900 font-medium">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                View Report
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        // Preserve date filter params when paginating forms
        $pagination   = ['page' => $pf_page, 'pages' => $pf_pages, 'total' => $pf_total, 'per_page' => $pf_per_page];
        $page_param   = 'pf';
        $query_params = array_filter(['from' => $date_range['from'] ?? '', 'to' => $date_range['to'] ?? ''], fn($v) => $v !== '');
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>
    </div>
</div>
<?php endif; ?>
