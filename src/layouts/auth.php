<?php 
/**
 * Auth Layout - Centered card for login/register
 * @var string $content Content to be displayed in the layout
 */
$content = $content ?? '';
$_brand = get_branding();
?>
<!DOCTYPE html>
<html lang="<?= current_locale() ?>" dir="<?= current_direction() ?>" class="h-full bg-gray-50">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body class="h-full font-sans">
    <div class="min-h-full flex flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <?php if ($_brand['logo_path']): ?>
                    <div class="flex justify-center">
                        <img src="<?php echo sanitize($_brand['logo_path']); ?>" alt="<?php echo sanitize($_brand['brand_name']); ?>" class="h-12 max-w-[200px] object-contain">
                    </div>
                <?php else: ?>
                    <h1 class="text-4xl font-bold text-primary-600"><?php echo sanitize($_brand['brand_name']); ?></h1>
                <?php endif; ?>
            </div>
            
            <!-- Language Switcher -->
            <?php if (i18n_show_switcher()): ?>
            <div class="flex justify-center mb-4">
                <?php require __DIR__ . '/partials/language_switcher.php'; ?>
            </div>
            <?php endif; ?>
            
            <!-- Main Content -->
            <div class="card p-8">
                <?php require __DIR__ . '/partials/flash.php'; ?>
                <?php echo $content; ?>
            </div>
        </div>

        <?php require __DIR__ . '/partials/footer.php'; ?>
    </div>
</body>
</html>
