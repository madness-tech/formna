<?php
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

if (!function_exists('is_active_user_nav')) {
    function is_active_user_nav(string $match, string $current_path, bool $exact): bool {
        if ($exact) {
            return $current_path === $match;
        }
        return str_starts_with($current_path, $match);
    }
}
?>

<nav class="flex flex-1 flex-col min-h-0">
    <!-- Scrollable nav items -->
    <div class="flex-1 overflow-y-auto px-4 py-4 sidebar-scroll">
        <ul role="list" class="flex flex-col gap-y-5">
            <!-- Overview -->
            <li>
                <ul role="list" class="-mx-2 space-y-1">
                    <?php $active = is_active_user_nav('/dashboard', $current_path, true); ?>
                    <li>
                        <a href="/dashboard" class="group nav-link <?php echo $active ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                            <span class="flex-shrink-0 <?php echo $active ? 'text-primary-600' : 'text-gray-400 group-hover:text-primary-600'; ?>">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                            </span>
                            <?= t('common.dashboard') ?>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- My Activity -->
            <li>
                <div class="text-xs font-semibold leading-6 text-gray-400 uppercase tracking-wider"><?= t('nav.my_activity') ?></div>
                <ul role="list" class="-mx-2 mt-2 space-y-1">
                    <?php $active = is_active_user_nav('/submissions', $current_path, false); ?>
                    <li>
                        <a href="/submissions" class="group nav-link <?php echo $active ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                            <span class="flex-shrink-0 <?php echo $active ? 'text-primary-600' : 'text-gray-400 group-hover:text-primary-600'; ?>">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            </span>
                            <?= t('nav.my_submissions') ?>
                        </a>
                    </li>
                    <?php $active = is_active_user_nav('/my-programs', $current_path, false); ?>
                    <li>
                        <a href="/my-programs" class="group nav-link <?php echo $active ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                            <span class="flex-shrink-0 <?php echo $active ? 'text-primary-600' : 'text-gray-400 group-hover:text-primary-600'; ?>">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" /></svg>
                            </span>
                            <?= t('nav.my_programs') ?>
                        </a>
                    </li>
                    <?php $active = is_active_user_nav('/requests', $current_path, false); ?>
                    <li>
                        <a href="/requests" class="group nav-link <?php echo $active ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                            <span class="flex-shrink-0 <?php echo $active ? 'text-primary-600' : 'text-gray-400 group-hover:text-primary-600'; ?>">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>
                            </span>
                            <?= t('nav.clarification_requests') ?>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Browse -->
            <li>
                <div class="text-xs font-semibold leading-6 text-gray-400 uppercase tracking-wider"><?= t('nav.browse') ?></div>
                <ul role="list" class="-mx-2 mt-2 space-y-1">
                    <?php $active = $current_path === '/forms' || (str_starts_with($current_path, '/forms/') && !str_contains($current_path, '/admin')); ?>
                    <li>
                        <a href="/forms" class="group nav-link <?php echo $active ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                            <span class="flex-shrink-0 <?php echo $active ? 'text-primary-600' : 'text-gray-400 group-hover:text-primary-600'; ?>">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z" /></svg>
                            </span>
                            <?= t('common.forms') ?>
                        </a>
                    </li>
                    <?php $active = $current_path === '/programs' || (str_starts_with($current_path, '/programs/') && !str_contains($current_path, '/admin')); ?>
                    <li>
                        <a href="/programs" class="group nav-link <?php echo $active ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                            <span class="flex-shrink-0 <?php echo $active ? 'text-primary-600' : 'text-gray-400 group-hover:text-primary-600'; ?>">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                            </span>
                            <?= t('common.programs') ?>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Help (only show if help content exists for current language) -->
            <?php if (i18n_help_exists(current_locale())): ?>
            <li>
                <div class="text-xs font-semibold leading-6 text-gray-400 uppercase tracking-wider"><?= t('nav.help') ?></div>
                <ul role="list" class="-mx-2 mt-2 space-y-1">
                    <?php $active = is_active_user_nav('/help', $current_path, false); ?>
                    <li>
                        <a href="/help" class="group nav-link <?php echo $active ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                            <span class="flex-shrink-0 <?php echo $active ? 'text-primary-600' : 'text-gray-400 group-hover:text-primary-600'; ?>">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>
                            </span>
                            <?= t('nav.help_center') ?>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

        </ul>
    </div>

    <!-- Pinned bottom: user info, settings, sign out -->
    <div class="shrink-0 border-t border-gray-100 px-4 py-4">
        <div class="flex items-center gap-x-3 px-3 py-2 mb-1 -mx-2">
            <div class="h-8 w-8 rounded-full bg-primary-600 flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-medium text-white"><?php echo strtoupper(substr(current_user()['name'], 0, 1)); ?></span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 truncate"><?php echo sanitize(current_user()['name']); ?></p>
                <p class="help-text truncate"><?php echo sanitize(current_user()['email']); ?></p>
            </div>
        </div>

        <ul class="-mx-2 space-y-1">
            <?php $active = is_active_user_nav('/settings', $current_path, false) || is_active_user_nav('/mfa/change', $current_path, false); ?>
            <li>
                <a href="/settings" class="group nav-link <?php echo $active ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                    <span class="flex-shrink-0 <?php echo $active ? 'text-primary-600' : 'text-gray-400 group-hover:text-primary-600'; ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </span>
                    <?= t('nav.account_settings') ?>
                </a>
            </li>
            <?php if (in_array(current_user()['role'], ['super_admin', 'admin', 'reviewer'])): ?>
            <li>
                <a href="/admin/dashboard" class="group nav-link text-gray-700 hover:bg-gray-50 hover:text-primary-600">
                    <span class="flex-shrink-0 text-gray-400 group-hover:text-primary-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" /></svg>
                    </span>
                    <?= t('nav.admin_panel') ?>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <form method="POST" action="/logout">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="group nav-link w-full text-gray-700 hover:bg-red-50 hover:text-red-600">
                        <span class="flex-shrink-0 text-gray-400 group-hover:text-red-500">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                        </span>
                        <?= t('auth.sign_out') ?>
                    </button>
                </form>
            </li>
        </ul>
    </div>
</nav>
