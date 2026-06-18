<?php
/**
 * Prerequisites Gate Page
 *
 * Shown when a user tries to access a form whose prerequisites are not yet met.
 * Displays the form name, description, and a checklist of prerequisite forms
 * with completion status and links.
 *
 * @var array<string, mixed> $form            The form the user is trying to access
 * @var array{met: bool, forms: list<array{id: int, name: string, uuid: string, completed: bool}>} $prerequisite_status
 */
$title = sanitize($form['name']);

ob_start();
?>

<div class="mb-6">
    <a href="/forms" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary-600 transition">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M8.25 4.5l7.5 7.5-7.5 7.5' : 'M15.75 19.5L8.25 12l7.5-7.5' ?>" />
        </svg>
        <?= t('form_fill.back_to_forms') ?>
    </a>
</div>

<div class="max-w-2xl mx-auto">
    <!-- Form Header -->
    <div class="card p-6 mb-6">
        <div class="flex <?= empty($form['description']) ? 'items-center' : 'items-start' ?> gap-4">
            <div class="flex-shrink-0">
                <div class="h-12 w-12 rounded-lg bg-amber-50 flex items-center justify-center">
                    <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-semibold text-gray-900"><?= sanitize($form['name']) ?></h1>
                <?php if (!empty($form['description'])): ?>
                    <p class="body-text mt-1"><?= sanitize($form['description']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Prerequisites Section -->
    <div class="card p-6">
        <div class="flex items-center gap-3 mb-4">
            <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <h2 class="text-sm font-semibold text-gray-900"><?= t('form_fill.prerequisites_heading') ?></h2>
        </div>
        <p class="body-text mb-5"><?= t('form_fill.prerequisites_message') ?></p>

        <div class="space-y-3">
            <?php foreach ($prerequisite_status['forms'] as $prereq): ?>
                <?php if ($prereq['completed']): ?>
                    <!-- Completed prerequisite -->
                    <div class="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                        <svg class="h-5 w-5 flex-shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="flex-1 text-sm font-medium text-green-800"><?= sanitize($prereq['name']) ?></span>
                        <span class="text-xs text-green-600 flex-shrink-0"><?= t('form_fill.prerequisite_completed') ?></span>
                    </div>
                <?php else: ?>
                    <!-- Incomplete prerequisite -->
                    <a href="/forms/<?= sanitize($prereq['uuid']) ?>"
                       class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 hover:bg-amber-100 transition group">
                        <svg class="h-5 w-5 flex-shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="flex-1 text-sm font-medium text-amber-800 group-hover:text-amber-900"><?= sanitize($prereq['name']) ?></span>
                        <span class="text-xs text-amber-600 flex-shrink-0 flex items-center gap-1 group-hover:text-amber-700">
                            <?= t('form_fill.prerequisite_not_completed') ?>
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="<?= is_rtl() ? 'M15.75 19.5L8.25 12l7.5-7.5' : 'M8.25 4.5l7.5 7.5-7.5 7.5' ?>" />
                            </svg>
                        </span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/user.php';
