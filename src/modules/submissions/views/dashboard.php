<?php
$title = t('common.dashboard');

$user = $user ?? [];
$submissions = $submissions ?? [];
$drafts = $drafts ?? [];
$program_drafts = $program_drafts ?? [];
$program_submissions = $program_submissions ?? [];
$pending_clarifications = $pending_clarifications ?? [];
$available_forms = $available_forms ?? [];
$greeting = $greeting ?? 'Hello';

// Latest program comes from controller
$latest_program = $latest_program ?? null;

// Filter clarifications: only open ones require user action
$open_clarifications = array_filter($pending_clarifications, fn($c) => $c['status'] === 'open');

// Count stats — $total_submissions is passed from the controller via a proper COUNT query
$total_submissions = $total_submissions ?? 0;
$total_drafts = count($drafts) + count($program_drafts);
$total_programs = count($program_submissions);
$total_clarifications = count($open_clarifications);

// Status styles helper
$status_styles = [
    'draft'          => 'bg-gray-100 text-gray-700',
    'submitted'      => 'bg-blue-100 text-blue-700',
    'in_review'      => 'bg-blue-100 text-blue-700',
    'approved'       => 'bg-primary-100 text-primary-700',
    'rejected'       => 'bg-red-100 text-red-700',
    'clarification_requested' => 'bg-amber-100 text-amber-700',
];

ob_start();
?>

<!-- Welcome + Stats -->
<div class="mb-6">
    <h1 class="page-title"><?= t('user_dashboard.greeting', ['greeting' => sanitize($greeting), 'name' => sanitize($user['name'] ?? 'there')]) ?></h1>
    <p class="mt-1 text-sm text-gray-500"><?= t('user_dashboard.activity_summary') ?></p>
</div>

<!-- Stats Grid -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <a href="/submissions" class="card p-4 hover:shadow-md hover:border-gray-300 transition">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-green-50 flex items-center justify-center">
                <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
            </div>
            <div>
                <p class="page-title"><?php echo $total_submissions; ?></p>
                <p class="help-text"><?= t('user_dashboard.stat_submissions') ?></p>
            </div>
        </div>
    </a>
    <a href="/my-programs" class="card p-4 hover:shadow-md hover:border-gray-300 transition">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-purple-50 flex items-center justify-center">
                <svg class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
            </div>
            <div>
                <p class="page-title"><?php echo $total_programs; ?></p>
                <p class="help-text"><?= t('user_dashboard.stat_programs') ?></p>
            </div>
        </div>
    </a>
    <a href="/submissions?status=draft" class="card p-4 hover:shadow-md hover:border-gray-300 transition">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-yellow-50 flex items-center justify-center">
                <svg class="h-5 w-5 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
            </div>
            <div>
                <p class="page-title"><?php echo $total_drafts; ?></p>
                <p class="help-text"><?= t('user_dashboard.stat_drafts') ?></p>
            </div>
        </div>
    </a>
    <a href="/requests" class="card p-4 hover:shadow-md hover:border-gray-300 transition">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 h-10 w-10 rounded-lg <?php echo $total_clarifications > 0 ? 'bg-red-50' : 'bg-gray-50'; ?> flex items-center justify-center">
                <svg class="h-5 w-5 <?php echo $total_clarifications > 0 ? 'text-red-600' : 'text-gray-400'; ?>" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
            </div>
            <div>
                <p class="page-title"><?php echo $total_clarifications; ?></p>
                <p class="help-text"><?= t('user_dashboard.stat_pending_requests') ?></p>
            </div>
        </div>
    </a>
</div>

<!-- Clarification Requests Banner -->
<?php if (!empty($open_clarifications)): ?>
<div class="mb-8">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider flex items-center gap-2">
            <span class="flex h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
            <?= t('user_dashboard.action_required') ?>
        </h2>
        <a href="/requests" class="text-xs text-primary-600 hover:text-primary-700 font-medium"><?= t('common.view_all') ?> <?= is_rtl() ? '←' : '→' ?></a>
    </div>
    <div class="space-y-2">
        <?php foreach ($open_clarifications as $clar): ?>
            <a href="/requests/<?php echo sanitize($clar['uuid']); ?>" class="block bg-white border border-red-200 rounded-lg px-4 py-3 hover:border-red-300 hover:shadow-sm transition group">
                <div class="flex items-center justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-sm font-medium text-gray-900 truncate"><?php echo sanitize($clar['form_name']); ?></p>
                        </div>
                        <p class="help-text">
                            <?= t('user_dashboard.questions_need_response', ['count' => count($clar['items'])]) ?>
                            · <?= t('user_dashboard.requested_on', ['date' => format_datetime($clar['created_at'], 'date_short')]) ?>
                        </p>
                    </div>
                    <span class="ml-3 inline-flex items-center gap-1 text-xs font-medium text-red-600 group-hover:text-red-700">
                        <?= t('user_dashboard.respond') ?>
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M15.75 19.5l-7.5-7.5 7.5-7.5' : 'M8.25 4.5l7.5 7.5-7.5 7.5' ?>" /></svg>
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Latest Program Banner -->
<?php if ($latest_program): ?>
    <div class="mb-8">
        <a href="/programs/<?php echo sanitize($latest_program['uuid']); ?>" class="block bg-gradient-to-r from-primary-50 to-primary-100 border border-primary-200 rounded-lg p-6 hover:shadow-md transition group">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="h-5 w-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                        </svg>
                        <span class="text-xs font-semibold text-primary-600 uppercase tracking-wider"><?= t('user_dashboard.latest_program') ?></span>
                    </div>
                    <h3 class="section-title group-hover:text-primary-600 transition"><?php echo sanitize($latest_program['name']); ?></h3>
                    <?php if (!empty($latest_program['description'])): ?>
                        <p class="body-text mt-2 line-clamp-2"><?php echo sanitize($latest_program['description']); ?></p>
                    <?php endif; ?>
                </div>
                <svg class="h-5 w-5 text-primary-400 group-hover:text-primary-600 transition flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M15.75 19.5l-7.5-7.5 7.5-7.5' : 'M8.25 4.5l7.5 7.5-7.5 7.5' ?>" />
                </svg>
            </div>
        </a>
    </div>
<?php endif; ?>

<!-- Drafts (Forms + Programs combined) -->
<?php if (!empty($drafts) || !empty($program_drafts)): ?>
<div class="mb-8" id="drafts-section">
    <h2 class="text-sm font-semibold text-gray-900 mb-3 uppercase tracking-wider"><?= t('user_dashboard.continue_where_left_off') ?></h2>
    <div class="space-y-2" id="drafts-container">
        <?php foreach ($drafts as $draft): ?>
            <div class="flex items-center justify-between bg-white border border-yellow-200 rounded-lg px-4 py-3" id="draft-<?php echo sanitize($draft['form_uuid']); ?>">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-primary-50 text-primary-600"><?= t('user_dashboard.form_label') ?></span>
                        <p class="text-sm font-medium text-gray-900 truncate"><?php echo sanitize($draft['form_name']); ?></p>
                    </div>
                    <p class="help-text"><?= t('user_dashboard.draft_saved', ['date' => format_datetime($draft['updated_at'], 'short')]) ?></p>
                </div>
                <a href="/forms/<?php echo sanitize($draft['form_uuid']); ?>" class="btn btn-primary btn-sm ml-4">
                    <?= t('common.continue') ?>
                </a>
            </div>
        <?php endforeach; ?>
        <?php foreach ($program_drafts as $pd): ?>
            <div class="flex items-center justify-between bg-white border border-yellow-200 rounded-lg px-4 py-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-purple-50 text-purple-600"><?= t('user_dashboard.program_label') ?></span>
                        <p class="text-sm font-medium text-gray-900 truncate"><?php echo sanitize($pd['program_name']); ?></p>
                    </div>
                    <p class="help-text"><?= t('user_dashboard.draft_saved', ['date' => format_datetime($pd['updated_at'], 'short')]) ?></p>
                </div>
                <a href="/programs/<?php echo sanitize($pd['program_uuid']); ?>" class="btn btn-primary btn-sm ml-4">
                    <?= t('common.continue') ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>

<!-- Full-width layout: Recent Forms + Recent Submissions in rows -->
<div class="space-y-6">
    <!-- Recent Forms -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider"><?= t('user_dashboard.recent_forms') ?></h2>
            <a href="/forms" class="text-xs text-primary-600 hover:text-primary-700 font-medium"><?= t('common.browse_all') ?> <?= is_rtl() ? '←' : '→' ?></a>
        </div>
        <div class="card">
            <?php if (empty($available_forms)): ?>
                <div class="p-6 text-center">
                    <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                    <p class="mt-2 text-sm text-gray-500"><?= t('user_dashboard.no_forms_available') ?></p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($available_forms as $form): ?>
                        <a href="/forms/<?php echo sanitize($form['uuid']); ?>" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition group">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 truncate group-hover:text-primary-600"><?php echo sanitize($form['name']); ?></p>
                                <?php if (!empty($form['description'])): ?>
                                    <p class="help-text line-clamp-2"><?php echo sanitize($form['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <svg class="h-5 w-5 text-gray-400 group-hover:text-primary-600 transition flex-shrink-0 ml-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M15.75 19.5l-7.5-7.5 7.5-7.5' : 'M8.25 4.5l7.5 7.5-7.5 7.5' ?>" />
                            </svg>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Submissions -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider"><?= t('user_dashboard.recent_submissions') ?></h2>
            <a href="/submissions" class="text-xs text-primary-600 hover:text-primary-700 font-medium"><?= t('common.view_all') ?> <?= is_rtl() ? '←' : '→' ?></a>
        </div>
        <div class="card">
            <?php if (empty($submissions)): ?>
                <div class="p-6 text-center">
                    <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                    <p class="mt-2 text-sm text-gray-500"><?= t('user_dashboard.no_submissions_yet') ?></p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($submissions as $sub): ?>
                        <?php $badge = $status_styles[$sub['status'] ?? ''] ?? 'bg-gray-100 text-gray-700'; ?>
                        <a href="/submissions/<?php echo sanitize($sub['uuid']); ?>" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 truncate"><?php echo sanitize($sub['form_name']); ?></p>
                                <p class="help-text"><?php echo format_datetime($sub['submitted_at'], 'date_short'); ?></p>
                            </div>
                            <?php
                            $status_labels = [
                                'draft' => t('submission.status_draft'),
                                'submitted' => t('submission.status_submitted'),
                                'in_review' => t('submission.status_in_review'),
                                'approved' => t('submission.status_approved'),
                                'rejected' => t('submission.status_rejected'),
                                'clarification_requested' => t('submission.status_clarification'),
                            ];
                            ?>
                            <span class="ml-3 inline-flex rounded-full px-2 py-0.5 text-xs font-medium <?php echo $badge; ?>">
                                <?= $status_labels[$sub['status']] ?? ucfirst(str_replace('_', ' ', $sub['status'])) ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/user.php';
?>
