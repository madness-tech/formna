<?php
$page_title = 'User Management';
$current_user = current_user();

// Ensure expected view data is initialized to avoid undefined variable notices
if (!isset($counts)) {
    $counts = [];
}

if (!isset($visible_roles)) {
    $visible_roles = [];
}

if (!isset($role_filter)) {
    $role_filter = null;
}

if (!isset($status_filter)) {
    $status_filter = null;
}

if (!isset($search)) {
    $search = null;
}

if (!isset($pagination)) {
    $pagination = [
        'page' => 1,
        'pages' => 1,
        'total' => 0,
        'per_page' => 25,
    ];
}

// Helper functions for badges (guarded to avoid redeclaration across views)
if (!function_exists('get_role_badge_class')) {
    function get_role_badge_class(string $role): string {
        return match($role) {
            'super_admin' => 'bg-purple-100 text-purple-800',
            'admin' => 'bg-blue-100 text-blue-800',
            'reviewer' => 'bg-green-100 text-green-800',
            'user' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }
}

if (!function_exists('get_status_badge_class')) {
    function get_status_badge_class(string $status): string {
        return match($status) {
            'active' => 'bg-green-100 text-green-800',
            'suspended' => 'bg-red-100 text-red-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }
}

if (!function_exists('format_role')) {
    function format_role(string $role): string {
        return match($role) {
            'super_admin' => 'Super Admin',
            default => ucfirst($role)
        };
    }
}

ob_start();
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Users</h1>
        <p class="mt-2 text-sm text-gray-700">Manage user accounts, roles, and permissions.</p>
    </div>
    <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <?php if (!empty(get_assignable_roles($current_user))): ?>
            <a href="/admin/users/create" class="btn btn-primary">
                <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add User
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Role Filter Tabs -->
<div class="mt-6 flex gap-2">
    <?php
    $all_count = array_sum($counts);
    $all_tabs = [
        ['label' => 'All Users', 'count' => $all_count, 'role' => null],
        ['label' => 'Super Admins', 'count' => $counts['super_admin'] ?? 0, 'role' => 'super_admin'],
        ['label' => 'Admins', 'count' => $counts['admin'] ?? 0, 'role' => 'admin'],
        ['label' => 'Reviewers', 'count' => $counts['reviewer'] ?? 0, 'role' => 'reviewer'],
        ['label' => 'Users', 'count' => $counts['user'] ?? 0, 'role' => 'user'],
    ];
    
    // SECURITY: Filter tabs based on visible roles
    $tabs = [];
    foreach ($all_tabs as $tab) {
        if ($tab['role'] === null) {
            // Always show "All Users" tab
            $tabs[] = $tab;
        } elseif (in_array($tab['role'], $visible_roles)) {
            // Only show role tabs for roles the user can see
            $tabs[] = $tab;
        }
    }
    
    foreach ($tabs as $tab):
        $is_active = ($role_filter === $tab['role']);
        $url = '/admin/users';
        $params = [];
        if ($tab['role']) $params['role'] = $tab['role'];
        if ($status_filter) $params['status'] = $status_filter;
        if ($search) $params['search'] = $search;
        if (!empty($params)) $url .= '?' . http_build_query($params);
        
        $class = $is_active
            ? 'filter-tab-active'
            : '';
    ?>
        <a href="<?= sanitize($url) ?>" class="filter-tab <?= $class ?>">
            <?= sanitize($tab['label']) ?>
            <span class="ml-2 rounded-full <?= $is_active ? 'bg-primary-100 text-primary-600' : 'bg-gray-100 text-gray-900' ?> py-0.5 px-2.5 text-xs font-medium">
                <?= $tab['count'] ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="mt-6">
        <form method="GET" action="/admin/users" class="flex flex-wrap gap-3 items-center">
            <?php if ($role_filter): ?>
                <input type="hidden" name="role" value="<?= sanitize($role_filter) ?>">
            <?php endif; ?>
            
            <!-- Status Filter -->
            <div class="flex items-center gap-2">
                <label for="status">Status:</label>
                <select id="status" name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
            
            <!-- Search -->
            <div class="flex-1 min-w-64">
                <div class="relative">
                    <div class="absolute inset-y-0 flex items-center pointer-events-none" style="<?= is_rtl() ? 'right:0;padding-right:0.75rem' : 'left:0;padding-left:0.75rem' ?>">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                    </div>
                    <input type="search" name="search" placeholder="Search by name or email..."
                           value="<?= sanitize($search ?? '') ?>"
                           class="focus:ring-1" style="<?= is_rtl() ? 'padding-right:2.5rem;padding-left:0.75rem' : 'padding-left:2.5rem;padding-right:0.75rem' ?>">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                Search
            </button>
            
            <?php if ($status_filter || $search): ?>
                <a href="/admin/users<?= $role_filter ? '?role=' . urlencode($role_filter) : '' ?>"
                   class="btn btn-secondary gap-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Clear Filters
                </a>
            <?php endif; ?>
    </form>
</div>

<!-- Users Table -->
<div class="card mt-6">
    <?php if (empty($users)): ?>
        <!-- Empty State -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900">No users found.</h3>
            <p class="mt-1 text-sm text-gray-500">Adjust your filters or create a new user.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Last Login</th>
                        <th scope="col">Created</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= sanitize($u['name'] ?: 'N/A') ?>
                                    <?php if ($u['id'] == $current_user['id']): ?>
                                        <span class="help-text">(You)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-gray-500">
                                <?= sanitize($u['email']) ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="badge <?= get_role_badge_class($u['role']) ?>">
                                    <?= format_role($u['role']) ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="badge <?= get_status_badge_class($u['status']) ?>">
                                    <?= ucfirst($u['status']) ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-gray-500">
                                <?= $u['last_login_at'] ? time_ago($u['last_login_at']) : 'Never' ?>
                            </td>
                            <td class="whitespace-nowrap text-gray-500">
                                <?= time_ago($u['created_at']) ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php if (can_manage_user($current_user, $u)): ?>
                                    <a href="/admin/users/<?= $u['uuid'] ?>/profile"
                                       class="inline-flex items-center text-primary-600 hover:text-primary-900 font-medium"
                                       title="View profile">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        View Profile
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php
        $base_url = '/admin/users';
        $query_params = [];
        if ($role_filter) $query_params['role'] = $role_filter;
        if ($status_filter) $query_params['status'] = $status_filter;
        if ($search) $query_params['search'] = $search;
        require __DIR__ . '/../../../layouts/partials/pagination.php';
        ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/admin.php';
?>
