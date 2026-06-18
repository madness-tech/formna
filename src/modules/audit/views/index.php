<?php
/**
 * Audit Log Index View
 * 
 * Shows audit log entries with role-based scoping and filtering.
 * 
 * SECURITY: Access controlled by audit_log_index() controller
 * - Reviewers see only their own actions
 * - Admins see own + reviewer actions
 * - Super admins see all admin/reviewer actions (user actions optional)
 *
 * @var array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int} $result
 * @var list<string> $available_actions
 * @var list<string> $available_entity_types
 * @var array{action: string, entity_type: string, user_search: string, date_from: string, date_to: string, include_users: bool, sort: string} $current_filters
 */

$user = current_user();

// Build a human-readable description from action + entity_type + details
if (!function_exists('audit_description')) {
    /** @param array<string, mixed> $entry */
    function audit_description(array $entry): string {
        $details = is_array($entry['details']) ? $entry['details'] : [];
        $entity = sanitize(ucwords(str_replace('_', ' ', $entry['entity_type'])));
        $name = $details['name'] ?? null;
        $name_str = $name ? " — " . sanitize($name) : '';
        
        $d = fn(?string $key) => isset($details[$key]) ? sanitize((string)$details[$key]) : null;
        
        return match($entry['action']) {
            'login' => 'Logged in',
            'logout' => 'Logged out',
            'registered' => 'Registered an account',
            'created' => "Created {$entity}{$name_str}",
            'updated' => audit_updated_description($entity, $details),
            'deleted' => "Deleted {$entity}{$name_str}",
            'published' => "Published {$entity}{$name_str}",
            'unpublished' => "Unpublished {$entity}{$name_str}",
            'closed' => "Closed {$entity}{$name_str}",
            'submitted' => "Submitted {$entity}",
            'reordered' => "Reordered {$entity}" . ($d('count') !== null ? " ({$d('count')} items)" : ''),
            'rectified' => "Rectified {$entity}",
            'user_created' => "Created user account" . ($d('role') !== null ? " (role: {$d('role')})" : ''),
            'user_role_changed' => "Changed user role" . ($d('old_role') !== null && $d('new_role') !== null ? " ({$d('old_role')} → {$d('new_role')})" : ''),
            'user_status_changed' => "Changed user status" . ($d('old_status') !== null && $d('new_status') !== null ? " ({$d('old_status')} → {$d('new_status')})" : ''),
            'user_password_reset' => "Reset user password",
            'reviewer_changed' => "Changed reviewer assignment" . ($d('action') !== null ? " ({$d('action')})" : ''),
            'program_decision' => "Review decision" . ($d('decision') !== null ? ": {$d('decision')}" : ''),
            'clarification_requested' => "Requested clarification on {$entity}",
            'clarification_responded' => "Responded to clarification",
            'clarification_resolved' => "Resolved clarification",
            'clarification_rejected' => "Rejected clarification response",
            'clarification_cancelled' => "Cancelled clarification request",
            default => sanitize(ucwords(str_replace('_', ' ', $entry['action']))) . " {$entity}",
        };
    }
}

if (!function_exists('audit_updated_description')) {
    /** @param array<string, mixed> $details */
    function audit_updated_description(string $entity, array $details): string {
        $d = fn(?string $key) => isset($details[$key]) ? sanitize((string)$details[$key]) : null;
        
        $action = $d('action');
        $name = $d('name');
        
        if ($action === 'scoring_updated') return "Updated scoring for {$entity}";
        if ($action === 'settings_updated') return "Updated settings for {$entity}";
        if ($action === 'edited_answers') return "Edited answers on {$entity}";
        if ($action === 'form_removed') return "Removed form from {$entity}";
        if ($d('status') !== null) return "Updated {$entity} status to {$d('status')}";
        if ($name !== null) return "Updated {$entity} — {$name}";
        
        return "Updated {$entity}";
    }
}

if (!function_exists('get_action_badge')) {
    /** @return array{0: string, 1: string} */
    function get_action_badge(string $action): array {
        return match(true) {
            str_contains($action, 'created') || str_contains($action, 'registered') || $action === 'user_created'
                => ['bg-green-100 text-green-800', 'Create'],
            str_contains($action, 'updated') || str_contains($action, 'rectified') || str_contains($action, 'changed') || $action === 'user_password_reset'
                => ['bg-blue-100 text-blue-800', 'Update'],
            str_contains($action, 'published') || str_contains($action, 'submitted')
                => ['bg-emerald-100 text-emerald-800', 'Publish'],
            str_contains($action, 'deleted')
                => ['bg-red-100 text-red-800', 'Delete'],
            str_contains($action, 'closed') || str_contains($action, 'unpublished')
                => ['bg-orange-100 text-orange-800', 'Close'],
            str_contains($action, 'decision')
                => ['bg-yellow-100 text-yellow-800', 'Decision'],
            str_contains($action, 'clarification')
                => ['bg-purple-100 text-purple-800', 'Clarification'],
            $action === 'login' || $action === 'logout'
                => ['bg-gray-100 text-gray-700', 'Auth'],
            str_contains($action, 'reorder')
                => ['bg-sky-100 text-sky-800', 'Reorder'],
            default => ['bg-primary-100 text-primary-800', 'Action'],
        };
    }
}

if (!function_exists('get_role_badge_color')) {
    function get_role_badge_color(?string $role): string {
        return match($role) {
            'super_admin' => 'bg-purple-100 text-purple-700',
            'admin' => 'bg-primary-100 text-primary-700',
            'reviewer' => 'bg-blue-100 text-blue-700',
            'user' => 'bg-gray-100 text-gray-600',
            default => 'bg-gray-100 text-gray-500'
        };
    }
}
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Audit Log</h1>
        <p class="mt-2 text-sm text-gray-700">
            <?php if ($user['role'] === 'reviewer'): ?>
                Track your activity in the system
            <?php elseif ($user['role'] === 'admin'): ?>
                Monitor your activity and reviewer actions
            <?php else: ?>
                Monitor all administrative actions across the system
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Filters -->
<div class="card mt-6 mb-6 p-6">
    <h2 class="section-title mb-4">Filters</h2>
    <form method="GET" action="/admin/audit-log" class="space-y-4">
        <!-- Row 1: Action, Entity Type, Sort -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label>Action</label>
                <select name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($available_actions as $action): ?>
                        <option value="<?= sanitize($action) ?>" <?= $current_filters['action'] === $action ? 'selected' : '' ?>>
                            <?= sanitize($action) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>Entity Type</label>
                <select name="entity_type">
                    <option value="">All Entities</option>
                    <?php foreach ($available_entity_types as $et): ?>
                        <option value="<?= sanitize($et) ?>" <?= $current_filters['entity_type'] === $et ? 'selected' : '' ?>>
                            <?= sanitize(ucwords(str_replace('_', ' ', $et))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>Sort By</label>
                <select name="sort">
                    <option value="date_desc" <?= $current_filters['sort'] === 'date_desc' ? 'selected' : '' ?>>Date (Newest First)</option>
                    <option value="date_asc" <?= $current_filters['sort'] === 'date_asc' ? 'selected' : '' ?>>Date (Oldest First)</option>
                    <option value="action_asc" <?= $current_filters['sort'] === 'action_asc' ? 'selected' : '' ?>>Action (A-Z)</option>
                    <option value="action_desc" <?= $current_filters['sort'] === 'action_desc' ? 'selected' : '' ?>>Action (Z-A)</option>
                    <option value="user_asc" <?= $current_filters['sort'] === 'user_asc' ? 'selected' : '' ?>>User (A-Z)</option>
                    <option value="user_desc" <?= $current_filters['sort'] === 'user_desc' ? 'selected' : '' ?>>User (Z-A)</option>
                    <option value="entity_asc" <?= $current_filters['sort'] === 'entity_asc' ? 'selected' : '' ?>>Entity (A-Z)</option>
                    <option value="entity_desc" <?= $current_filters['sort'] === 'entity_desc' ? 'selected' : '' ?>>Entity (Z-A)</option>
                </select>
            </div>
        </div>
        
        <!-- Row 2: Date Range, User Search -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label>Date From</label>
                <input type="date" name="date_from" id="filter_date_from"
                       value="<?= sanitize($current_filters['date_from']) ?>"
                       max="<?= sanitize($current_filters['date_to']) ?>">
            </div>
            
            <div>
                <label>Date To</label>
                <input type="date" name="date_to" id="filter_date_to"
                       value="<?= sanitize($current_filters['date_to']) ?>"
                       min="<?= sanitize($current_filters['date_from']) ?>">
            </div>
            
            <div>
                <label>Search User</label>
                <input type="text" name="user_search" value="<?= sanitize($current_filters['user_search']) ?>" 
                       placeholder="Name or email...">
            </div>
        </div>
        
        <!-- Row 3: Checkboxes and Buttons -->
        <div class="flex items-end justify-between">
            <div class="flex items-center">
                <?php if ($user['role'] === 'super_admin'): ?>
                    <input type="checkbox" name="include_users" id="include_users" value="1" 
                           <?= $current_filters['include_users'] ? 'checked' : '' ?>
                           class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                    <label for="include_users" class="ml-2">
                        Include user (non-admin) actions
                    </label>
                <?php endif; ?>
            </div>
            
            <div class="flex gap-2">
                <a href="/admin/audit-log" class="btn btn-secondary">
                    Clear Filters
                </a>
                <button type="submit" class="btn btn-primary">
                    Apply Filters
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Audit Log -->
<div class="card">
    <?php if ($result['total'] > 0): ?>
        <div class="card-header">
            <p class="text-sm text-gray-700">
                Showing <span class="font-medium"><?= number_format(($result['page'] - 1) * $result['per_page'] + 1) ?></span> 
                to <span class="font-medium"><?= number_format(min($result['page'] * $result['per_page'], $result['total'])) ?></span> 
                of <span class="font-medium"><?= number_format($result['total']) ?></span> entries
            </p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($result['rows'])): ?>
        <div class="px-6 py-12 text-center">
            <div class="text-gray-400 mb-2">
                <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"></path>
                </svg>
            </div>
            <p class="text-gray-500 text-lg">No audit entries found</p>
            <p class="text-gray-400 text-sm mt-1">Try adjusting your filters</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php foreach ($result['rows'] as $idx => $entry): ?>
                <?php 
                    $badge = get_action_badge($entry['action']);
                    decode_json_fields($entry, ['details']);
                    $details = $entry['details'];
                ?>
                <!-- Main Row -->
                <div class="audit-row cursor-pointer hover:bg-gray-50 transition-colors" data-target="audit-detail-<?= $idx ?>">
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <!-- Action Badge -->
                                <span class="badge <?= $badge[0] ?> shrink-0">
                                    <?= $badge[1] ?>
                                </span>
                                
                                <!-- Description -->
                                <p class="text-sm text-gray-900 truncate min-w-0">
                                    <?= audit_description($entry) ?>
                                </p>
                            </div>
                            
                            <div class="flex items-center gap-4 shrink-0 ml-4">
                                <!-- User -->
                                <div class="text-right hidden sm:block">
                                    <p class="text-sm font-medium text-gray-700"><?= sanitize($entry['user_name'] ?? 'System') ?></p>
                                    <?php if ($entry['user_role']): ?>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium <?= get_role_badge_color($entry['user_role']) ?>">
                                            <?= sanitize(ucwords(str_replace('_', ' ', $entry['user_role']))) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Timestamp -->
                                <span class="help-text whitespace-nowrap w-36 text-right">
                                    <?= format_datetime($entry['created_at'], 'short') ?>
                                </span>
                                
                                <!-- Expand icon -->
                                <svg class="h-4 w-4 text-gray-400 audit-chevron transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Expanded Detail Panel -->
                    <div id="audit-detail-<?= $idx ?>" class="hidden border-t border-gray-100 bg-gray-50 px-6 py-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">User</p>
                                <p class="text-gray-900 font-medium"><?= sanitize($entry['user_name'] ?? 'System') ?></p>
                                <?php if ($entry['user_email']): ?>
                                    <p class="text-gray-600"><?= sanitize($entry['user_email']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Action</p>
                                <p class="text-gray-900 break-all"><?= sanitize($entry['action']) ?></p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Entity</p>
                                <p class="text-gray-900"><?= sanitize(ucwords(str_replace('_', ' ', $entry['entity_type']))) ?></p>
                                <?php if (!empty($entry['entity_uuid'])): ?>
                                    <p class="text-gray-500 font-mono text-xs mt-0.5"><?= sanitize($entry['entity_uuid']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">IP Address</p>
                                <p class="text-gray-900 font-mono text-xs"><?= sanitize($entry['ip']) ?></p>
                            </div>
                        </div>
                        
                        <?php if ($details): ?>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Metadata</p>
                                <div class="bg-white rounded border border-gray-200 p-3 overflow-x-auto">
                                    <pre class="text-xs text-gray-700 font-mono whitespace-pre-wrap"><?= sanitize(json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Pagination -->
    <?php
    $pagination = $result;
    $base_url = '/admin/audit-log';
    $query_params = [];
    if ($current_filters['action']) $query_params['action'] = $current_filters['action'];
    if ($current_filters['entity_type']) $query_params['entity_type'] = $current_filters['entity_type'];
    if ($current_filters['user_search']) $query_params['user_search'] = $current_filters['user_search'];
    if ($current_filters['date_from']) $query_params['date_from'] = $current_filters['date_from'];
    if ($current_filters['date_to']) $query_params['date_to'] = $current_filters['date_to'];
    if ($current_filters['include_users']) $query_params['include_users'] = '1';
    if ($current_filters['sort'] && $current_filters['sort'] !== 'date_desc') $query_params['sort'] = $current_filters['sort'];
    require __DIR__ . '/../../../layouts/partials/pagination.php';
    ?>
</div>

<!-- Date range constraint -->
<script>
const dateFrom = document.getElementById('filter_date_from');
const dateTo = document.getElementById('filter_date_to');
dateFrom.addEventListener('change', () => { dateTo.min = dateFrom.value; });
dateTo.addEventListener('change', () => { dateFrom.max = dateTo.value; });
</script>

<!-- Expand/collapse row script -->
<script>
document.querySelectorAll('.audit-row').forEach(row => {
    const mainArea = row.querySelector(':scope > div:first-child');
    const targetId = row.dataset.target;
    const detail = document.getElementById(targetId);
    const chevron = row.querySelector('.audit-chevron');
    
    if (!mainArea || !detail) return;
    
    mainArea.addEventListener('click', () => {
        const isOpen = !detail.classList.contains('hidden');
        
        // Close all other open panels
        document.querySelectorAll('.audit-row').forEach(otherRow => {
            const otherId = otherRow.dataset.target;
            const otherDetail = document.getElementById(otherId);
            const otherChevron = otherRow.querySelector('.audit-chevron');
            if (otherDetail && otherId !== targetId) {
                otherDetail.classList.add('hidden');
                otherChevron?.classList.remove('rotate-180');
            }
        });
        
        // Toggle current panel
        detail.classList.toggle('hidden');
        chevron?.classList.toggle('rotate-180');
    });
});
</script>
