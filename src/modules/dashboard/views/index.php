<?php
/**
 * Admin Dashboard View — role-specific
 *
 * @var array<string, mixed> $data     Role-specific dashboard data (from models)
 * @var array<string, mixed> $user     Current user
 * @var string $role     Current user role
 * @var string $greeting Time-of-day greeting
 */

$is_super_admin = ($role === 'super_admin');
$is_admin       = ($role === 'admin');
$is_reviewer    = ($role === 'reviewer');

$role_labels = [
    'super_admin' => 'Super Admin',
    'admin'       => 'Admin',
    'reviewer'    => 'Reviewer',
];
?>

<!-- Header with greeting -->
<div class="sm:flex sm:items-center sm:justify-between">
    <div>
        <h1 class="page-title"><?= sanitize($greeting) ?>, <?= sanitize($user['name'] ?? 'there') ?>.</h1>
        <p class="mt-1 text-sm text-gray-500">
            <?php if ($is_super_admin): ?>
                System overview and health at a glance.
            <?php elseif ($is_admin): ?>
                Your forms, submissions, and activity.
            <?php else: ?>
                Your review assignments and progress.
            <?php endif; ?>
        </p>
    </div>
    <span class="badge px-3 py-1 mt-2 sm:mt-0
        <?php if ($is_super_admin): ?>badge-purple<?php elseif ($is_admin): ?>badge-indigo<?php else: ?>badge-teal<?php endif; ?>">
        <?= $role_labels[$role] ?? ucfirst($role) ?>
    </span>
</div>

<?php /* ═══════════════════════════════════════════════════════════════
       SUPER ADMIN DASHBOARD
       ═══════════════════════════════════════════════════════════════ */ ?>
<?php if ($is_super_admin): ?>

    <!-- Stat Cards -->
    <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <a href="/admin/users" class="stat-card group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Active Users</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($data['active_users']) ?></p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-primary-50 p-3 group-hover:bg-primary-100 transition-colors">
                    <svg class="h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                </div>
            </div>
        </a>
        <a href="/admin/forms" class="stat-card group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Published Forms</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($data['published_forms']) ?></p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-green-50 p-3 group-hover:bg-green-100 transition-colors">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                </div>
            </div>
        </a>
        <a href="/admin/submissions" class="stat-card group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Submissions This Week</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($data['submissions_this_week']) ?></p>
                    <p class="mt-1 text-xs text-gray-400"><?= number_format($data['total_submissions']) ?> total</p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-blue-50 p-3 group-hover:bg-blue-100 transition-colors">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                </div>
            </div>
        </a>
        <a href="/admin/programs" class="stat-card group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Active Programs</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($data['active_programs']) ?></p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-purple-50 p-3 group-hover:bg-purple-100 transition-colors">
                    <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                </div>
            </div>
        </a>
    </div>

    <!-- Action Items -->
    <?php
    $has_actions = $data['pending_reviews'] > 0
        || $data['pending_clarifications'] > 0
        || $data['responded_clarifications'] > 0
        || $data['failed_webhooks_24h'] > 0;
    ?>
    <?php if ($has_actions): ?>
    <div class="card mt-6 divide-y divide-gray-200">
        <div class="px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">Needs Attention</h2>
        </div>
        <?php if ($data['pending_reviews'] > 0): ?>
        <a href="/admin/review-queue" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3">
                <span class="flex-shrink-0 w-2 h-2 rounded-full bg-yellow-400"></span>
                <span class="text-sm text-gray-900"><?= $data['pending_reviews'] ?> program submission<?= $data['pending_reviews'] != 1 ? 's' : '' ?> awaiting review</span>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= is_rtl() ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' ?>"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($data['responded_clarifications'] > 0): ?>
        <a href="/admin/submissions?clarification_status=responded" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3">
                <span class="flex-shrink-0 w-2 h-2 rounded-full bg-blue-400"></span>
                <span class="text-sm text-gray-900"><?= $data['responded_clarifications'] ?> clarification response<?= $data['responded_clarifications'] != 1 ? 's' : '' ?> to review</span>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= is_rtl() ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' ?>"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($data['pending_clarifications'] > 0): ?>
        <a href="/admin/submissions?clarification_status=open" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3">
                <span class="flex-shrink-0 w-2 h-2 rounded-full bg-orange-400"></span>
                <span class="text-sm text-gray-900"><?= $data['pending_clarifications'] ?> clarification<?= $data['pending_clarifications'] != 1 ? 's' : '' ?> pending user response</span>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= is_rtl() ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' ?>"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($data['failed_webhooks_24h'] > 0): ?>
        <a href="/admin/webhooks" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3">
                <span class="flex-shrink-0 w-2 h-2 rounded-full bg-red-500"></span>
                <span class="text-sm text-gray-900"><?= $data['failed_webhooks_24h'] ?> webhook failure<?= $data['failed_webhooks_24h'] != 1 ? 's' : '' ?> in the last 24 hours</span>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= is_rtl() ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' ?>"/></svg>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="mt-6">
        <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">Quick Actions</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <a href="/admin/forms/create" class="stat-card flex flex-col items-center gap-2 p-4 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-primary-50 p-2 group-hover:bg-primary-100 transition-colors">
                    <svg class="h-5 w-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">Create Form</span>
            </a>
            <a href="/admin/programs" class="stat-card flex flex-col items-center gap-2 p-4 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-purple-50 p-2 group-hover:bg-purple-100 transition-colors">
                    <svg class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">Programs</span>
            </a>
            <a href="/admin/users/create" class="stat-card flex flex-col items-center gap-2 p-4 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-green-50 p-2 group-hover:bg-green-100 transition-colors">
                    <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">Add User</span>
            </a>
            <a href="/admin/email-templates" class="stat-card flex flex-col items-center gap-2 p-4 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-blue-50 p-2 group-hover:bg-blue-100 transition-colors">
                    <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">Email Templates</span>
            </a>
            <a href="/admin/branding" class="stat-card flex flex-col items-center gap-2 p-4 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-pink-50 p-2 group-hover:bg-pink-100 transition-colors">
                    <svg class="h-5 w-5 text-pink-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">Branding</span>
            </a>
            <a href="/admin/audit-log" class="stat-card flex flex-col items-center gap-2 p-4 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-gray-100 p-2 group-hover:bg-gray-200 transition-colors">
                    <svg class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">Audit Log</span>
            </a>
        </div>
    </div>

    <!-- Two-column: Recent Submissions + Recent Activity -->
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Recent Submissions -->
        <div class="card">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">Recent Submissions</h2>
                <a href="/admin/submissions" class="text-xs text-primary-600 hover:text-primary-700 font-medium">View all <?= is_rtl() ? '←' : '→' ?></a>
            </div>
            <?php if (!empty($data['recent_submissions'])): ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($data['recent_submissions'] as $sub): ?>
                <a href="/admin/forms/<?= sanitize($sub['form_uuid']) ?>/submissions/<?= sanitize($sub['uuid']) ?>" class="block px-6 py-3 hover:bg-gray-50 transition-colors">
                    <p class="text-sm font-medium text-gray-900 truncate"><?= sanitize($sub['form_name']) ?></p>
                    <p class="help-text">
                        <?= sanitize($sub['user_name'] ?? $sub['user_email']) ?>
                        · <?= format_datetime($sub['submitted_at'], 'short') ?>
                    </p>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">No submissions yet</div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">Recent Activity</h2>
                <a href="/admin/audit-log" class="text-xs text-primary-600 hover:text-primary-700 font-medium">View all <?= is_rtl() ? '←' : '→' ?></a>
            </div>
            <?php if (!empty($data['recent_activity'])): ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($data['recent_activity'] as $act): ?>
                <div class="px-6 py-3">
                    <p class="text-sm text-gray-900">
                        <span class="font-medium"><?= sanitize($act['user_name'] ?? $act['user_email'] ?? 'System') ?></span>
                        <?= sanitize(str_replace('_', ' ', $act['action'])) ?>
                        <span class="text-gray-500"><?= sanitize($act['entity_type']) ?></span>
                    </p>
                    <p class="mt-1 text-xs text-gray-400"><?= format_datetime($act['created_at'], 'short') ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">No activity yet</div>
            <?php endif; ?>
        </div>
    </div>

<?php /* ═══════════════════════════════════════════════════════════════
       ADMIN DASHBOARD
       ═══════════════════════════════════════════════════════════════ */ ?>
<?php elseif ($is_admin): ?>

    <!-- Stat Cards -->
    <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-3">
        <a href="/admin/forms" class="stat-card group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">My Forms</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($data['total_forms']) ?></p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-primary-50 p-3 group-hover:bg-primary-100 transition-colors">
                    <svg class="h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                </div>
            </div>
        </a>
        <a href="/admin/submissions" class="stat-card group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Submissions This Week</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($data['submissions_this_week']) ?></p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-blue-50 p-3 group-hover:bg-blue-100 transition-colors">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                </div>
            </div>
        </a>
        <a href="/admin/programs" class="stat-card group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Active Programs</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($data['active_programs']) ?></p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-purple-50 p-3 group-hover:bg-purple-100 transition-colors">
                    <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                </div>
            </div>
        </a>
    </div>

    <!-- Action Items -->
    <?php
    $has_actions = $data['pending_reviews'] > 0
        || $data['pending_clarifications'] > 0
        || $data['responded_clarifications'] > 0;
    ?>
    <?php if ($has_actions): ?>
    <div class="card mt-6 divide-y divide-gray-200">
        <div class="px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">Needs Attention</h2>
        </div>
        <?php if ($data['pending_reviews'] > 0): ?>
        <a href="/admin/review-queue" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3">
                <span class="flex-shrink-0 w-2 h-2 rounded-full bg-yellow-400"></span>
                <span class="text-sm text-gray-900"><?= $data['pending_reviews'] ?> program submission<?= $data['pending_reviews'] != 1 ? 's' : '' ?> awaiting review</span>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= is_rtl() ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' ?>"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($data['responded_clarifications'] > 0): ?>
        <a href="/admin/submissions?clarification_status=responded" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3">
                <span class="flex-shrink-0 w-2 h-2 rounded-full bg-blue-400"></span>
                <span class="text-sm text-gray-900"><?= $data['responded_clarifications'] ?> clarification response<?= $data['responded_clarifications'] != 1 ? 's' : '' ?> to review</span>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= is_rtl() ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' ?>"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($data['pending_clarifications'] > 0): ?>
        <a href="/admin/submissions?clarification_status=open" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3">
                <span class="flex-shrink-0 w-2 h-2 rounded-full bg-orange-400"></span>
                <span class="text-sm text-gray-900"><?= $data['pending_clarifications'] ?> clarification<?= $data['pending_clarifications'] != 1 ? 's' : '' ?> pending user response</span>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= is_rtl() ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' ?>"/></svg>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="mt-6">
        <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">Quick Actions</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="/admin/forms/create" class="stat-card flex flex-col items-center gap-2 p-4 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-primary-50 p-2 group-hover:bg-primary-100 transition-colors">
                    <svg class="h-5 w-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">Create Form</span>
            </a>
            <a href="/admin/programs" class="stat-card flex flex-col items-center gap-2 p-4 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-purple-50 p-2 group-hover:bg-purple-100 transition-colors">
                    <svg class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">Programs</span>
            </a>
            <a href="/admin/review-queue" class="stat-card flex flex-col items-center gap-2 p-4 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-teal-50 p-2 group-hover:bg-teal-100 transition-colors">
                    <svg class="h-5 w-5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">Review Queue</span>
            </a>
            <a href="/admin/submissions" class="stat-card flex flex-col items-center gap-2 p-4 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-blue-50 p-2 group-hover:bg-blue-100 transition-colors">
                    <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">All Submissions</span>
            </a>
        </div>
    </div>

    <!-- My Forms -->
    <?php if (!empty($data['my_forms'])): ?>
    <div class="card mt-6">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">My Forms</h2>
            <a href="/admin/forms" class="text-xs text-primary-600 hover:text-primary-700 font-medium">View all <?= is_rtl() ? '←' : '→' ?></a>
        </div>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Form</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">Submissions</th>
                        <th scope="col" class="text-right">This Week</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['my_forms'] as $form): ?>
                    <tr class="hover:bg-gray-50">
                        <td>
                            <a href="/admin/forms/<?= sanitize($form['uuid']) ?>/builder" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                                <?= sanitize($form['name']) ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($form['status'] === 'published'): ?>
                                <span class="badge badge-green">Published</span>
                            <?php else: ?>
                                <span class="badge badge-gray">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right text-gray-900"><?= number_format($form['submission_count']) ?></td>
                        <td class="text-right text-gray-900"><?= number_format($form['submissions_this_week']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Submissions to My Forms -->
    <div class="card mt-6">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">Recent Submissions</h2>
            <a href="/admin/submissions" class="text-xs text-primary-600 hover:text-primary-700 font-medium">View all <?= is_rtl() ? '←' : '→' ?></a>
        </div>
        <?php if (!empty($data['recent_submissions'])): ?>
        <div class="divide-y divide-gray-200">
            <?php foreach ($data['recent_submissions'] as $sub): ?>
            <a href="/admin/forms/<?= sanitize($sub['form_uuid']) ?>/submissions/<?= sanitize($sub['uuid']) ?>" class="block px-6 py-3 hover:bg-gray-50 transition-colors">
                <p class="text-sm font-medium text-gray-900 truncate"><?= sanitize($sub['form_name']) ?></p>
                <p class="help-text">
                    <?= sanitize($sub['user_name'] ?? $sub['user_email']) ?>
                    · <?= format_datetime($sub['submitted_at'], 'short') ?>
                </p>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">No submissions yet</div>
        <?php endif; ?>
    </div>

<?php /* ═══════════════════════════════════════════════════════════════
       REVIEWER DASHBOARD
       ═══════════════════════════════════════════════════════════════ */ ?>
<?php else: ?>

    <!-- Stat Cards -->
    <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <a href="/admin/review-queue" class="card p-5 hover:border-teal-300 transition-colors group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Pending Reviews</p>
                    <p class="mt-2 text-3xl font-semibold <?= $data['pending_reviews'] > 0 ? 'text-amber-600' : 'text-gray-900' ?>"><?= number_format($data['pending_reviews']) ?></p>
                </div>
                <div class="flex-shrink-0 rounded-lg <?= $data['pending_reviews'] > 0 ? 'bg-amber-50 group-hover:bg-amber-100' : 'bg-gray-50 group-hover:bg-gray-100' ?> p-3 transition-colors">
                    <svg class="h-6 w-6 <?= $data['pending_reviews'] > 0 ? 'text-amber-600' : 'text-gray-400' ?>" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </a>
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Completed This Week</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($data['completed_this_week']) ?></p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-green-50 p-3">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Completed</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($data['completed_reviews']) ?></p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-blue-50 p-3">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 019 9v.375M10.125 2.25A3.375 3.375 0 0113.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 013.375 3.375M9 15l2.25 2.25L15 12"/></svg>
                </div>
            </div>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Assigned Programs</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900"><?= number_format($data['assigned_programs']) ?></p>
                </div>
                <div class="flex-shrink-0 rounded-lg bg-purple-50 p-3">
                    <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to action when there are pending reviews -->
    <?php if ($data['pending_reviews'] > 0): ?>
    <div class="mt-6 bg-amber-50 border border-amber-200 rounded-lg p-5">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 rounded-full bg-amber-100 p-2">
                    <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-amber-900">You have <?= $data['pending_reviews'] ?> review<?= $data['pending_reviews'] != 1 ? 's' : '' ?> waiting</p>
                    <p class="text-xs text-amber-700 mt-0.5">Submissions are waiting for your evaluation.</p>
                </div>
            </div>
            <a href="/admin/review-queue" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-medium text-sm transition-colors">
                Start Reviewing
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18' : 'M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3' ?>"/></svg>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Upcoming Reviews -->
    <div class="card mt-6">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">Your Review Queue</h2>
            <a href="/admin/review-queue" class="text-xs text-primary-600 hover:text-primary-700 font-medium">View all <?= is_rtl() ? '←' : '→' ?></a>
        </div>
        <?php if (!empty($data['upcoming_reviews'])): ?>
        <div class="divide-y divide-gray-200">
            <?php foreach ($data['upcoming_reviews'] as $review): ?>
            <?php
                $submitted_ts = strtotime($review['submitted_at']);
                $days_waiting = max(0, (int)floor((time() - $submitted_ts) / 86400));
                $waiting_class = $days_waiting >= 7 ? 'text-red-600 font-semibold' : ($days_waiting >= 3 ? 'text-amber-600 font-medium' : 'text-gray-500');
            ?>
            <a href="/admin/review-queue/<?= sanitize($review['uuid']) ?>" class="block px-6 py-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900"><?= sanitize($review['user_name'] ?? $review['user_email']) ?></p>
                        <p class="help-text">
                            <?= sanitize($review['program_name']) ?>
                            · Stage <?= (int)$review['current_stage'] ?>
                        </p>
                    </div>
                    <div class="ml-4 flex items-center gap-3">
                        <span class="text-xs <?= $waiting_class ?>">
                            <?php if ($days_waiting === 0): ?>
                                Today
                            <?php elseif ($days_waiting === 1): ?>
                                1 day ago
                            <?php else: ?>
                                <?= $days_waiting ?> days ago
                            <?php endif; ?>
                        </span>
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= is_rtl() ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' ?>"/></svg>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="px-6 py-10 text-center">
            <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="mt-3 text-sm font-medium text-gray-900">You're all caught up!</p>
            <p class="help-text">No pending reviews at this time.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions for Reviewer -->
    <div class="mt-6">
        <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">Quick Actions</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <a href="/admin/review-queue" class="card flex flex-col items-center gap-2 p-4 hover:border-teal-300 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-teal-50 p-2 group-hover:bg-teal-100 transition-colors">
                    <svg class="h-5 w-5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">Review Queue</span>
            </a>
            <a href="/admin/audit-log" class="card flex flex-col items-center gap-2 p-4 hover:border-teal-300 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-gray-100 p-2 group-hover:bg-gray-200 transition-colors">
                    <svg class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">Audit Log</span>
            </a>
            <a href="/dashboard" class="card flex flex-col items-center gap-2 p-4 hover:border-teal-300 hover:shadow-sm transition-all text-center group">
                <div class="rounded-lg bg-primary-50 p-2 group-hover:bg-primary-100 transition-colors">
                    <svg class="h-5 w-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-700">User Portal</span>
            </a>
        </div>
    </div>

<?php endif; ?>
