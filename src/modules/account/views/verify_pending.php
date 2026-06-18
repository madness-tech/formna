<?php
$_brand = get_branding();
$title = t('verify_pending.title_page') . ' - ' . $_brand['brand_name'];
?>
<!DOCTYPE html>
<html lang="<?= current_locale() ?>" dir="<?= current_direction() ?>">
<head>
    <?php require __DIR__ . '/../../../layouts/partials/head.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-12">
    <div class="max-w-lg w-full mx-auto px-4">
        <div class="card p-8 sm:p-10">
            <!-- Icon -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h1 class="page-title mb-2"><?= t('verify_pending.check_email') ?></h1>
                <p class="text-gray-600"><?= t('verify_pending.sent_verification') ?></p>
            </div>

            <?php require __DIR__ . '/../../../layouts/partials/flash.php'; ?>

            <!-- Instructions -->
            <div class="space-y-5 mb-8">
                <p class="text-sm text-gray-700 leading-relaxed">
                    <?= t('verify_pending.instructions') ?>
                </p>
                
                <div class="p-5 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm font-semibold text-blue-900 mb-3"><?= t('verify_pending.didnt_receive') ?></p>
                    <ul class="text-sm text-blue-800 space-y-2 leading-relaxed">
                        <li class="flex items-start">
                            <span class="mr-2">•</span>
                            <span><?= t('verify_pending.check_spam') ?></span>
                        </li>
                        <li class="flex items-start">
                            <span class="mr-2">•</span>
                            <span><?= t('verify_pending.verify_correct_email') ?></span>
                        </li>
                        <li class="flex items-start">
                            <span class="mr-2">•</span>
                            <span><?= t('verify_pending.wait_delivery') ?></span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <?php if (!empty($pending_email)): ?>
                <form method="POST" action="/verify-pending/resend">
                    <?php echo csrf_field(); ?>
                    <button
                        type="submit"
                        class="btn btn-primary btn-full text-center"
                    >
                        <?= t('verify_pending.resend') ?>
                    </button>
                </form>
                <?php endif; ?>
                
                <a
                    href="/login"
                    class="btn btn-full py-3 <?php echo !empty($pending_email) ? 'btn-secondary' : 'btn-primary'; ?>"
                >
                    <?= t('verify_pending.back_to_login') ?>
                </a>
            </div>
            
            <p class="help-text text-center mt-6">
                <?= t('verify_pending.link_expires') ?>
            </p>
        </div>
    </div>
</body>
</html>
