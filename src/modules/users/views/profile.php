<?php
/**
 * Admin User Profile — read-only consolidated view
 *
 * @var array<string, mixed> $user               Target user row (with invited_by_name / invited_by_email)
 * @var array<string, mixed> $statistics         From get_user_statistics()
 * @var list<array>          $programs           Program submissions – overview quick panel (overview tab only)
 * @var array<string, mixed>|null $programs_result    Paginated programs (programs tab)
 * @var array<string, mixed>|null $submissions_result Paginated submissions (submissions tab)
 * @var array<string, mixed>|null $permissions_result Paginated permissions (permissions tab)
 * @var array<string, mixed>|null $activity_result    Paginated activity (activity tab)
 * @var list<array>          $recent_activity    Audit log entries – overview quick panel
 * @var string               $tab                Active tab slug
 */

$page_title = sanitize($user['name']) . ' — Profile';

// Badge helpers (scoped with function_exists to avoid redeclaration)
if (!function_exists('profile_role_badge')) {
    function profile_role_badge(string $role): string {
        $cls = match ($role) {
            'super_admin' => 'bg-purple-100 text-purple-800',
            'admin'       => 'bg-blue-100 text-blue-800',
            'reviewer'    => 'bg-green-100 text-green-800',
            default       => 'bg-gray-100 text-gray-800',
        };
        $label = $role === 'super_admin' ? 'Super Admin' : ucfirst($role);
        return '<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ' . $cls . '">' . sanitize($label) . '</span>';
    }

    function profile_status_badge(string $status): string {
        $cls = match ($status) {
            'active'     => 'bg-green-100 text-green-800',
            'suspended'  => 'bg-red-100 text-red-800',
            'pending'    => 'bg-yellow-100 text-yellow-800',
            default      => 'bg-gray-100 text-gray-800',
        };
        return '<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ' . $cls . '">' . sanitize(ucfirst($status)) . '</span>';
    }

    function profile_program_badge(string $status): string {
        $cls = match ($status) {
            'submitted'  => 'bg-blue-100 text-blue-800',
            'in_review'  => 'bg-yellow-100 text-yellow-800',
            'approved'   => 'bg-green-100 text-green-800',
            'rejected'   => 'bg-red-100 text-red-800',
            'draft'      => 'bg-gray-100 text-gray-800',
            default      => 'bg-gray-100 text-gray-800',
        };
        $label = $status === 'in_review' ? 'In Review' : ucfirst($status);
        return '<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ' . $cls . '">' . sanitize($label) . '</span>';
    }

    function profile_submission_badge(string $status): string {
        $cls = match ($status) {
            'submitted'    => 'bg-blue-100 text-blue-800',
            'under_review' => 'bg-yellow-100 text-yellow-800',
            'approved'     => 'bg-green-100 text-green-800',
            'rejected'     => 'bg-red-100 text-red-800',
            'draft'        => 'bg-gray-100 text-gray-800',
            default        => 'bg-gray-100 text-gray-800',
        };
        $label = $status === 'under_review' ? 'Under Review' : ucfirst($status);
        return '<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ' . $cls . '">' . sanitize($label) . '</span>';
    }

    function profile_action_label(string $action): string {
        return ucwords(str_replace('_', ' ', $action));
    }
}

$tabs = [
    'overview'    => 'Overview',
    'programs'    => 'Programs',
    'submissions' => 'Submissions',
    'permissions' => 'Permissions',
    'activity'    => 'Activity',
];

ob_start();
?>

<!-- Header -->
<div class="sm:flex sm:items-center sm:justify-between">
    <div class="sm:flex-auto">
        <h1 class="page-title"><?= sanitize($user['name'] ?: 'Unnamed User') ?></h1>
        <p class="mt-2 text-sm text-gray-700"><?= sanitize($user['email']) ?></p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <div class="flex items-center gap-3">
            <?= profile_status_badge($user['status']) ?>
            <?= profile_role_badge($user['role']) ?>
            <a href="/admin/users/<?= sanitize($user['uuid']) ?>/edit"
               class="btn btn-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                </svg>
                Edit
            </a>
        </div>
    </div>
</div>

<!-- ─── Stats Cards ─── -->
<div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Programs -->
        <div class="card p-6">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-lg bg-primary-50 flex items-center justify-center flex-shrink-0">
                    <svg class="h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-bold text-gray-900"><?= $statistics['programs']['total'] ?></p>
                    <p class="text-sm text-gray-500">Programs</p>
                </div>
            </div>
        </div>

        <!-- Submissions -->
        <div class="card p-6">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-bold text-gray-900"><?= $statistics['submissions']['total'] ?></p>
                    <p class="text-sm text-gray-500">Submissions</p>
                </div>
            </div>
        </div>
</div>

<!-- ─── Tabs ─── -->
<div class="mt-6 flex gap-2">
    <?php foreach ($tabs as $slug => $label): ?>
        <?php
        $is_active = ($tab === $slug);
        $url = '/admin/users/' . urlencode($user['uuid']) . '/profile' . ($slug !== 'overview' ? '?tab=' . $slug : '');
        $cls = $is_active
            ? 'filter-tab-active'
            : '';
        ?>
        <a href="<?= sanitize($url) ?>"
           class="filter-tab <?= $cls ?>">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- ─── Tab Content ─── -->
<div class="mt-6">

    <?php if ($tab === 'overview'): ?>
        <!-- ===================== OVERVIEW TAB ===================== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Account Info + Programs summary -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Account Metadata -->
                <div class="card p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Account Information</h3>

                    <?php
                        require_once __DIR__ . '/../../mfa/models.php';
                        $mfa_confirmed = mfa_user_has_confirmed($user['id']);
                        $mfa_pending = mfa_get_pending($user['id']) !== null;
                        $mfa_required = mfa_required_for_role($user['role']);
                    ?>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div>
                            <dt class="text-gray-500">Email</dt>
                            <dd class="mt-1 font-medium text-gray-900"><?= sanitize($user['email']) ?></dd>
                    </div>
                        <div>
                            <dt class="text-gray-500">Role</dt>
                            <dd class="mt-1"><?= profile_role_badge($user['role']) ?></dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Status</dt>
                            <dd class="mt-1"><?= profile_status_badge($user['status']) ?></dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Created</dt>
                            <dd class="mt-1 font-medium text-gray-900"><?= time_ago($user['created_at']) ?></dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Last Login</dt>
                            <dd class="mt-1 font-medium text-gray-900"><?= $user['last_login_at'] ? time_ago($user['last_login_at']) : 'Never' ?></dd>
                        </div>
                        <?php if (!empty($user['timezone'])): ?>
                        <div>
                            <dt class="text-gray-500">Timezone</dt>
                            <dd class="mt-1 font-medium text-gray-900"><?= sanitize($user['timezone']) ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ($user['invited_by']): ?>
                        <div>
                            <dt class="text-gray-500">Created By</dt>
                            <dd class="mt-1 font-medium text-gray-900">
                                <?= sanitize($user['invited_by_name']) ?>
                                <span class="help-text">(<?= sanitize($user['invited_by_email']) ?>)</span>
                            </dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user['email_verified_at'])): ?>
                        <div>
                            <dt class="text-gray-500">Email Verified</dt>
                            <dd class="mt-1 font-medium text-gray-900"><?= format_datetime($user['email_verified_at'], 'date') ?></dd>
                        </div>
                        <?php endif; ?>
                        <div>
                            <dt class="text-gray-500">Two-Factor Auth</dt>
                            <dd class="mt-1">
                                <?php if ($mfa_confirmed): ?>
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-green-100 text-green-800">Enabled</span>
                                <?php elseif ($mfa_pending || $mfa_required): ?>
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-yellow-100 text-yellow-800">Pending Setup</span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-800">Not Required</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>

                    <?php
                        $acting_user = current_user();
                        $can_reset_mfa = $mfa_confirmed
                            && $user['id'] !== $acting_user['id']
                            && can_manage_user($acting_user, $user)
                            && !($user['role'] === 'super_admin' && $acting_user['role'] !== 'super_admin');
                    ?>
                    <?php if ($can_reset_mfa): ?>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <form method="POST" action="/admin/users/<?= sanitize($user['uuid']) ?>/reset-mfa">
                            <?= csrf_field() ?>
                            <div class="mb-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">
                                    <?= t('mfa.admin_reset_your_password') ?>
                                </label>
                                <input type="password"
                                       name="admin_password"
                                       placeholder="<?= sanitize(t('mfa.admin_reset_password_placeholder')) ?>"
                                       class="input text-sm w-full"
                                       required
                                       autocomplete="current-password">
                            </div>
                            <button type="submit" class="btn btn-secondary text-sm text-red-700 border-red-200 hover:bg-red-50"
                                    onclick="return confirm('Are you sure you want to reset MFA for this user? They will need to set up MFA again.')">
                                <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                                Reset MFA
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Programs quick table (latest 5) -->
                <?php if (!empty($programs)): ?>
                <div class="card">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">Recent Programs</h3>
                        <a href="/admin/users/<?= sanitize($user['uuid']) ?>/profile?tab=programs" class="text-sm text-primary-600 hover:text-primary-500 font-medium">View all <?= is_rtl() ? '←' : '→' ?></a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($programs, 0, 5) as $p): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="font-medium text-gray-900"><?= sanitize($p['program_name']) ?></td>
                                <td><?= profile_program_badge($p['status']) ?></td>
                                <td class="text-gray-500"><?= $p['submitted_at'] ? time_ago($p['submitted_at']) : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right sidebar: statistics breakdown + recent activity -->
            <div class="space-y-6">

                <!-- Stats breakdown -->
                <div class="card p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Status Breakdown</h3>

                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Programs</h4>
                    <dl class="space-y-1 text-sm mb-4">
                        <div class="flex justify-between"><dt class="text-gray-500">Approved</dt><dd class="font-medium text-green-700"><?= $statistics['programs']['approved'] ?></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">In Review</dt><dd class="font-medium text-yellow-700"><?= $statistics['programs']['in_review'] ?></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Submitted</dt><dd class="font-medium text-blue-700"><?= $statistics['programs']['submitted'] ?></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Rejected</dt><dd class="font-medium text-red-700"><?= $statistics['programs']['rejected'] ?></dd></div>
                    </dl>

                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Submissions</h4>
                    <dl class="space-y-1 text-sm mb-4">
                        <div class="flex justify-between"><dt class="text-gray-500">Approved</dt><dd class="font-medium text-green-700"><?= $statistics['submissions']['approved'] ?></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Under Review</dt><dd class="font-medium text-yellow-700"><?= $statistics['submissions']['under_review'] ?></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Submitted</dt><dd class="font-medium text-blue-700"><?= $statistics['submissions']['submitted'] ?></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Rejected</dt><dd class="font-medium text-red-700"><?= $statistics['submissions']['rejected'] ?></dd></div>
                    </dl>

                    <?php if ($statistics['clarifications']['total'] > 0): ?>
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Clarifications</h4>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Open</dt><dd class="font-medium text-yellow-700"><?= $statistics['clarifications']['open'] ?></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Responded</dt><dd class="font-medium text-blue-700"><?= $statistics['clarifications']['responded'] ?></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Resolved</dt><dd class="font-medium text-green-700"><?= $statistics['clarifications']['resolved'] ?></dd></div>
                    </dl>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity (compact) -->
                <?php if (!empty($recent_activity)): ?>
                <div class="card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-semibold text-gray-900">Recent Activity</h3>
                        <a href="/admin/users/<?= sanitize($user['uuid']) ?>/profile?tab=activity" class="text-xs text-primary-600 hover:text-primary-500 font-medium">View all <?= is_rtl() ? '←' : '→' ?></a>
                    </div>
                    <div class="space-y-3">
                        <?php foreach (array_slice($recent_activity, 0, 8) as $act): ?>
                        <div class="flex items-start gap-2 text-sm">
                            <div class="mt-1.5 h-1.5 w-1.5 rounded-full bg-primary-400 flex-shrink-0"></div>
                            <div class="min-w-0">
                                <span class="font-medium text-gray-900"><?= sanitize(profile_action_label($act['action'])) ?></span>
                                <span class="text-gray-500"> on <?= sanitize($act['entity_type']) ?></span>
                                <div class="text-xs text-gray-400"><?= time_ago($act['created_at']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

<?php elseif ($tab === 'programs'): ?>
        <!-- ===================== PROGRAMS TAB ===================== -->
        <div class="card">
            <?php if (empty($programs_result['rows'])): ?>
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                    </svg>
                    <h3 class="mt-3 text-sm font-medium text-gray-900">No program submissions</h3>
                    <p class="mt-1 text-sm text-gray-500">This user has not submitted any programs yet.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Program</th>
                            <th>Status</th>
                            <th>Stage</th>
                            <th>Submitted</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($programs_result['rows'] as $p): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="font-medium text-gray-900"><?= sanitize($p['program_name']) ?></td>
                            <td><?= profile_program_badge($p['status']) ?></td>
                            <td class="text-gray-500">
                                <?php if (in_array($p['status'], ['submitted', 'in_review'])): ?>
                                    Stage <?= (int)$p['current_stage'] ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="text-gray-500"><?= $p['submitted_at'] ? time_ago($p['submitted_at']) : '—' ?></td>
                            <td class="whitespace-nowrap">
                                <a href="/admin/programs/<?= sanitize($p['program_uuid']) ?>/submissions"
                                   class="inline-flex items-center text-primary-600 hover:text-primary-900 font-medium"
                                   title="View program submissions">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    View Submissions
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                $pagination   = $programs_result;
                $base_url     = '/admin/users/' . urlencode($user['uuid']) . '/profile';
                $query_params = ['tab' => 'programs'];
                require __DIR__ . '/../../../layouts/partials/pagination.php';
                ?>
            <?php endif; ?>
    </div>

<?php elseif ($tab === 'submissions'): ?>
        <!-- ===================== SUBMISSIONS TAB ===================== -->
        <div class="card">
            <?php if (empty($submissions_result['rows'])): ?>
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <h3 class="mt-3 text-sm font-medium text-gray-900">No form submissions</h3>
                    <p class="mt-1 text-sm text-gray-500">This user has not submitted any forms yet.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Form</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions_result['rows'] as $s): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="font-medium text-gray-900"><?= sanitize($s['form_name']) ?></td>
                            <td><?= profile_submission_badge($s['status']) ?></td>
                            <td class="text-gray-500"><?= $s['submitted_at'] ? time_ago($s['submitted_at']) : time_ago($s['updated_at']) ?></td>
                            <td class="whitespace-nowrap">
                                <?php if ($s['uuid']): ?>
                                    <a href="/submissions/<?= sanitize($s['uuid']) ?>"
                                       class="inline-flex items-center text-primary-600 hover:text-primary-900 font-medium"
                                       title="View submission">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        View
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                $pagination   = $submissions_result;
                $base_url     = '/admin/users/' . urlencode($user['uuid']) . '/profile';
                $query_params = ['tab' => 'submissions'];
                require __DIR__ . '/../../../layouts/partials/pagination.php';
                ?>
            <?php endif; ?>
        </div>

<?php elseif ($tab === 'permissions'): ?>
        <!-- ===================== PERMISSIONS TAB ===================== -->
        <div class="card">
            <?php if (empty($permissions_result['rows'])): ?>
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.623 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                    <h3 class="mt-3 text-sm font-medium text-gray-900">No form permissions</h3>
                    <p class="mt-1 text-sm text-gray-500">This user has no explicit form permissions assigned.</p>
                    <p class="mt-1 text-xs text-gray-400">Permissions are managed from individual form settings.</p>
            </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Form</th>
                            <th>Access Level</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permissions_result['rows'] as $perm): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="font-medium text-gray-900"><?= sanitize($perm['form_name']) ?></td>
                            <td>
                                <?php
                                $access_cls = match ($perm['access']) {
                                    'admin'    => 'bg-blue-100 text-blue-800',
                                    'reviewer' => 'bg-green-100 text-green-800',
                                    default    => 'bg-gray-100 text-gray-800',
                                };
                                ?>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $access_cls ?>"><?= sanitize(ucfirst($perm['access'])) ?></span>
                            </td>
                            <td class="whitespace-nowrap">
                                <a href="/admin/forms/<?= sanitize($perm['form_uuid']) ?>/settings"
                                   class="inline-flex items-center text-primary-600 hover:text-primary-900 font-medium"
                                   title="Manage form permissions">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Manage
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                $pagination   = $permissions_result;
                $base_url     = '/admin/users/' . urlencode($user['uuid']) . '/profile';
                $query_params = ['tab' => 'permissions'];
                require __DIR__ . '/../../../layouts/partials/pagination.php';
                ?>
            <?php endif; ?>
        </div>

<?php elseif ($tab === 'activity'): ?>
        <!-- ===================== ACTIVITY TAB ===================== -->
        <div class="card">
            <?php if (empty($activity_result['rows'])): ?>
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-3 text-sm font-medium text-gray-900">No activity recorded</h3>
                    <p class="mt-1 text-sm text-gray-500">No audit trail found for this user.</p>
            </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Details</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity_result['rows'] as $act): ?>
                        <?php
                            $details = $act['details'];
                            if (is_string($details)) {
                                $decoded = json_decode($details, true);
                                $details = $decoded ?: [];
                            }
                            $detail_str = '';
                            if (!empty($details) && is_array($details)) {
                                $parts = [];
                                foreach (array_slice($details, 0, 3) as $k => $v) {
                                    if (is_scalar($v)) {
                                        $parts[] = sanitize($k) . ': ' . sanitize((string)$v);
                                    }
                                }
                                $detail_str = implode(', ', $parts);
                            }
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="font-medium text-gray-900"><?= sanitize(profile_action_label($act['action'])) ?></td>
                            <td class="text-gray-500">
                                <?= sanitize(ucwords(str_replace('_', ' ', $act['entity_type']))) ?>
                                <?php if (!empty($act['entity_uuid'])): ?>
                                    <br><span class="font-mono text-xs text-gray-400"><?= sanitize($act['entity_uuid']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-gray-500 max-w-xs truncate"><?= $detail_str ?></td>
                            <td class="text-gray-500 whitespace-nowrap"><?= time_ago($act['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                $pagination   = $activity_result;
                $base_url     = '/admin/users/' . urlencode($user['uuid']) . '/profile';
                $query_params = ['tab' => 'activity'];
                require __DIR__ . '/../../../layouts/partials/pagination.php';
                ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/admin.php';
?>
