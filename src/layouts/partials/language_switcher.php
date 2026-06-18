<?php
/**
 * Language Switcher Component
 *
 * Shows a dropdown to switch between active languages.
 * Only renders when multiple languages are active.
 */
if (!i18n_show_switcher()) {
    return;
}

$_active_langs = i18n_active_languages();
$_current = current_locale();
?>
<div class="relative z-10">
    <button type="button"
            class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-gray-900 transition relative"
            id="lang-switcher-btn">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802" />
        </svg>
        <span id="lang-switcher-label" class="inline-block whitespace-nowrap">
            <?php foreach ($_active_langs as $al): ?>
                <?php if ($al['code'] === $_current): ?>
                    <?= sanitize($al['native_name']) ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </span>
        <svg class="h-3 w-3 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div class="hidden absolute right-0 z-50 mt-2 w-44 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black/5 py-1"
         id="lang-switcher-menu"
         role="menu">
        <?php foreach ($_active_langs as $al): ?>
            <?php
            $is_selected = ($al['code'] === $_current);
            $url = '?lang=' . urlencode($al['code']);
            ?>
            <a href="<?= $url ?>"
               class="flex items-center justify-between px-4 py-2 text-sm <?= $is_selected ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-700 hover:bg-gray-50' ?>"
               role="menuitem">
                <span><?= sanitize($al['native_name']) ?></span>
                <?php if ($is_selected): ?>
                    <svg class="h-4 w-4 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function() {
    const btn = document.getElementById('lang-switcher-btn');
    const menu = document.getElementById('lang-switcher-menu');
    if (!btn || !menu) return;

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        menu.classList.toggle('hidden');
    });

    document.addEventListener('click', function() {
        menu.classList.add('hidden');
    });
})();
</script>
