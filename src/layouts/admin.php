<?php
/**
 * Admin Layout - Sidebar navigation with top bar (Dark purple theme)
 * @var string $content Content to be displayed in the layout
 */
$content = $content ?? '';
$_brand = get_branding();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full bg-gray-50">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body class="h-full font-sans">
    <div class="min-h-full flex">
        <!-- Mobile sidebar overlay -->
        <div class="fixed inset-0 z-50 bg-gray-900/80 overlay-transition opacity-0 pointer-events-none lg:hidden" id="sidebar-overlay"></div>

        <!-- Mobile sidebar -->
        <div class="fixed inset-y-0 left-0 z-50 w-72 sidebar-transition -translate-x-full lg:hidden" id="mobile-sidebar">
            <div class="flex h-full flex-col min-h-0 bg-primary-600 border-r border-primary-700">
                <div class="flex h-14 shrink-0 items-center justify-between px-6 border-b border-primary-500">
                    <a href="/admin/dashboard" class="flex items-center">
                        <?php if ($_brand['logo_path_dark']): ?>
                            <img src="<?php echo sanitize($_brand['logo_path_dark']); ?>" alt="<?php echo sanitize($_brand['brand_name']); ?>" class="h-8 max-w-[160px] object-contain">
                        <?php elseif ($_brand['logo_path']): ?>
                            <img src="<?php echo sanitize($_brand['logo_path']); ?>" alt="<?php echo sanitize($_brand['brand_name']); ?>" class="h-8 max-w-[160px] object-contain brightness-0 invert">
                        <?php else: ?>
                            <span class="text-white text-xl font-bold"><?php echo sanitize($_brand['brand_name']); ?></span>
                        <?php endif; ?>
                    </a>
                    <button type="button" class="text-primary-200 hover:text-white" id="close-sidebar-btn">
                        <span class="sr-only">Close sidebar</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex-1 flex flex-col min-h-0">
                    <?php require __DIR__ . '/partials/nav_admin.php'; ?>
                </div>
            </div>
        </div>

        <!-- Desktop sidebar -->
        <div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-64 lg:flex-col">
            <div class="flex flex-1 flex-col min-h-0 border-r border-primary-700 bg-primary-600">
                <div class="flex h-14 shrink-0 items-center px-6 border-b border-primary-500">
                    <a href="/admin/dashboard" class="flex items-center">
                        <?php if ($_brand['logo_path_dark']): ?>
                            <img src="<?php echo sanitize($_brand['logo_path_dark']); ?>" alt="<?php echo sanitize($_brand['brand_name']); ?>" class="h-8 max-w-[160px] object-contain">
                        <?php elseif ($_brand['logo_path']): ?>
                            <img src="<?php echo sanitize($_brand['logo_path']); ?>" alt="<?php echo sanitize($_brand['brand_name']); ?>" class="h-8 max-w-[160px] object-contain brightness-0 invert">
                        <?php else: ?>
                            <span class="text-white text-xl font-bold"><?php echo sanitize($_brand['brand_name']); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="flex-1 flex flex-col min-h-0">
                    <?php require __DIR__ . '/partials/nav_admin.php'; ?>
                </div>
            </div>
        </div>

        <!-- Main content area -->
        <div class="flex-1 lg:pl-64 flex flex-col min-h-screen">
            <!-- Top bar -->
            <div class="sticky top-0 z-40 flex h-12 items-center gap-x-4 bg-white border-b border-gray-200 px-4 lg:px-8">
                <div class="flex items-center gap-x-2 lg:hidden">
                    <button type="button" class="-m-2 p-2 text-gray-700" id="mobile-menu-button">
                        <span class="sr-only">Open sidebar</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                </div>
                <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
                <nav class="hidden lg:flex items-center gap-x-2 text-sm text-gray-500" aria-label="Breadcrumb">
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                        <?php if ($index > 0): ?>
                            <svg class="h-4 w-4 text-gray-300 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                            </svg>
                        <?php endif; ?>
                        <?php if (isset($crumb['url'])): ?>
                            <a href="<?php echo sanitize($crumb['url']); ?>" class="hover:text-gray-700"><?php echo sanitize($crumb['label']); ?></a>
                        <?php else: ?>
                            <span class="text-gray-900 font-medium"><?php echo sanitize($crumb['label']); ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
                <?php endif; ?>
                <div class="ml-auto flex items-center gap-3">
                    <?php require __DIR__ . '/partials/notifications_bell.php'; ?>
                </div>
            </div>

            <!-- Page content -->
            <main class="flex-1 py-8">
                <div class="px-4 sm:px-6 lg:px-8">
                    <?php require __DIR__ . '/partials/flash.php'; ?>
                    <?php echo $content; ?>
                </div>
            </main>

            <?php require __DIR__ . '/partials/footer.php'; ?>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-button');
        const closeSidebarBtn = document.getElementById('close-sidebar-btn');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        function openSidebar() {
            mobileSidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.remove('opacity-0', 'pointer-events-none');
        }
        function closeSidebar() {
            mobileSidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
        }

        if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', openSidebar);
        if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
    </script>
</body>
</html>
