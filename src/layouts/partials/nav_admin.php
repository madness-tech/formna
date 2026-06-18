<?php
$user = current_user();
$role = $user['role'] ?? 'user';
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

if (!function_exists('is_active_admin_nav')) {
    function is_active_admin_nav(string $match, string $current_path, bool $exact): bool {
        if ($exact) {
            return $current_path === $match;
        }
        return str_starts_with($current_path, $match);
    }
}
?>

<nav class="flex flex-1 flex-col min-h-0">
    <!-- Scrollable nav items -->
    <div class="flex-1 overflow-y-auto px-4 py-4 sidebar-scroll-dark">
        <ul role="list" class="flex flex-col gap-y-5">
            <!-- Overview -->
            <li>
                <ul role="list" class="-mx-2 space-y-1">
                    <?php if (in_array($role, ['super_admin', 'admin', 'reviewer'])): ?>
                        <?php $active = is_active_admin_nav('/admin/dashboard', $current_path, true); ?>
                        <li>
                            <a href="/admin/dashboard" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                                <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                                </span>
                                Dashboard
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </li>

            <!-- Content Management -->
            <?php if (in_array($role, ['super_admin', 'admin', 'reviewer'])): ?>
            <li>
                <div class="text-xs font-semibold leading-6 text-primary-200 uppercase tracking-wider">Operations</div>
                <ul role="list" class="-mx-2 mt-2 space-y-1">
                    <?php if (in_array($role, ['super_admin', 'admin'])): ?>
                        <?php $active = is_active_admin_nav('/admin/forms', $current_path, false); ?>
                        <li>
                            <a href="/admin/forms" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                                <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                </span>
                                Forms
                            </a>
                        </li>
                        <?php $active = is_active_admin_nav('/admin/programs', $current_path, false); ?>
                        <li>
                            <a href="/admin/programs" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                                <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                                </span>
                                Programs
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php $active = is_active_admin_nav('/admin/review-queue', $current_path, false); ?>
                    <li>
                        <a href="/admin/review-queue" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                            <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </span>
                            Review Queue
                        </a>
                    </li>
                    <?php if (in_array($role, ['super_admin', 'admin'])): ?>
                        <?php $active = is_active_admin_nav('/admin/submissions', $current_path, false); ?>
                        <li>
                            <a href="/admin/submissions" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                                <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" /></svg>
                                </span>
                                All Submissions
                            </a>
                        </li>
                        <?php $active = is_active_admin_nav('/admin/reports', $current_path, false); ?>
                        <li>
                            <a href="/admin/reports" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                                <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                                </span>
                                Reports
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <!-- System Management -->
            <?php if (in_array($role, ['super_admin', 'admin'])): ?>
            <li>
                <div class="text-xs font-semibold leading-6 text-primary-200 uppercase tracking-wider">System Management</div>
                <ul role="list" class="-mx-2 mt-2 space-y-1">
                    <?php $active = is_active_admin_nav('/admin/users', $current_path, false); ?>
                    <li>
                        <a href="/admin/users" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                            <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                            </span>
                            Users
                        </a>
                    </li>
                    <?php if ($role === 'super_admin'): ?>
                        <?php $active = is_active_admin_nav('/admin/mfa', $current_path, false); ?>
                        <li>
                            <a href="/admin/mfa/settings" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                                <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                                </span>
                                MFA Settings
                            </a>
                        </li>
                        <?php $active = is_active_admin_nav('/admin/email-templates', $current_path, false); ?>
                        <li>
                            <a href="/admin/email-templates" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                                <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                                </span>
                                Email Templates
                            </a>
                        </li>
                        <?php $active = is_active_admin_nav('/admin/branding', $current_path, false); ?>
                        <li>
                            <a href="/admin/branding" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                                <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42" /></svg>
                                </span>
                                Branding
                            </a>
                        </li>
                        <?php $active = is_active_admin_nav('/admin/languages', $current_path, false); ?>
                        <li>
                            <a href="/admin/languages" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                                <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802" /></svg>
                                </span>
                                Languages
                            </a>
                        </li>
                        <?php $active = is_active_admin_nav('/admin/webhooks', $current_path, false); ?>
                        <li>
                            <a href="/admin/webhooks" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                                <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                                </span>
                                Webhooks
                            </a>
                        </li>
                        <?php $active = is_active_admin_nav('/admin/ai-settings', $current_path, false); ?>
                        <li>
                            <a href="/admin/ai-settings" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                                <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" /></svg>
                                </span>
                                AI Settings
                            </a>
                        </li>
                        <?php $active = is_active_admin_nav('/admin/audit-log', $current_path, false); ?>
                        <li>
                            <a href="/admin/audit-log" class="group nav-link <?php echo $active ? 'bg-primary-700 text-white' : 'text-primary-100 hover:bg-primary-700 hover:text-white'; ?>">
                                <span class="flex-shrink-0 <?php echo $active ? 'text-white' : 'text-primary-200 group-hover:text-white'; ?>">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
                                </span>
                                Audit Log
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Pinned bottom: user info, settings, back to portal -->
    <div class="shrink-0 border-t border-primary-500 px-4 py-4">
        <div class="flex items-center gap-x-3 px-3 py-2 mb-1 -mx-2">
            <div class="h-8 w-8 rounded-full bg-white flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-medium text-primary-600"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-white truncate"><?php echo sanitize($user['name']); ?></p>
                <p class="text-xs text-primary-200 truncate"><?php echo sanitize($user['email']); ?></p>
            </div>
        </div>

        <ul class="-mx-2 space-y-1">
            <li>
                <a href="/settings" class="group nav-link text-primary-100 hover:bg-primary-700 hover:text-white">
                    <span class="flex-shrink-0 text-primary-200 group-hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </span>
                    Account Settings
                </a>
            </li>
            <li>
                <a href="/dashboard" class="group nav-link text-primary-100 hover:bg-primary-700 hover:text-white">
                    <span class="flex-shrink-0 text-primary-200 group-hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                    </span>
                    Back to User Portal
                </a>
            </li>
            <li>
                <form method="POST" action="/logout">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="group nav-link w-full text-primary-100 hover:bg-red-700 hover:text-white">
                        <span class="flex-shrink-0 text-primary-200 group-hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                        </span>
                        Sign Out
                    </button>
                </form>
            </li>
        </ul>
    </div>
</nav>
