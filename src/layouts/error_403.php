<?php
$_brand = get_branding();
$title = 'Access Denied - ' . $_brand['brand_name'];
?>
<!DOCTYPE html>
<html lang="<?= current_locale() ?>" dir="<?= current_direction() ?>" class="h-full bg-gray-50">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body class="h-full font-sans">
    <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <!-- Logo/Brand -->
            <div class="text-center mb-8">
                <a href="<?php echo is_logged_in() ? '/dashboard' : '/login'; ?>" class="flex justify-center">
                    <?php if ($_brand['logo_path']): ?>
                        <img src="<?php echo sanitize($_brand['logo_path']); ?>" alt="<?php echo sanitize($_brand['brand_name']); ?>" class="h-10 max-w-[180px] object-contain">
                    <?php else: ?>
                        <span class="text-primary-600 text-3xl font-bold"><?php echo sanitize($_brand['brand_name']); ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- Error Card -->
            <div class="bg-white py-8 px-4 shadow-sm border border-gray-200 sm:rounded-lg sm:px-10">
                <div class="text-center">
                    <!-- Error Icon -->
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                        <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                    </div>
                    
                    <!-- Error Message -->
                    <h1 class="page-title mb-2"><?= t('error_403.title') ?></h1>
                    <p class="text-gray-600 mb-8">
                        <?= t('error_403.message') ?>
                    </p>
                    
                    <!-- Action Buttons -->
                    <div class="space-y-3">
                        <?php if (is_logged_in()): ?>
                            <?php $user = current_user(); ?>
                            <?php if (in_array($user['role'], ['admin', 'super_admin'])): ?>
                                <a href="/admin/forms" class="btn btn-primary btn-full">
                                    <?= t('error_403.go_admin') ?>
                                </a>
                            <?php elseif ($user['role'] === 'reviewer'): ?>
                                <a href="/admin/review-queue" class="btn btn-primary btn-full">
                                    <?= t('error_403.go_review_queue') ?>
                                </a>
                            <?php else: ?>
                                <a href="/dashboard" class="btn btn-primary btn-full">
                                    <?= t('error_403.go_dashboard') ?>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="/login" class="btn btn-primary btn-full">
                                <?= t('error_403.go_login') ?>
                            </a>
                        <?php endif; ?>
                        
                        <button onclick="window.history.back()" class="btn btn-secondary btn-full">
                            <?= t('common.back') ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 text-center">
                <p class="text-xs text-gray-400">Error Code: 403 &mdash; Forbidden</p>
            </div>
        </div>
    </div>
</body>
</html>
