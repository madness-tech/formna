<?php
/**
 * Reusable Pagination Partial
 * Always renders (shows disabled state when ≤1 page so users know that's all there is).
 *
 * Required variables (set by controller before including):
 *   $pagination   - array{page: int, pages: int, total: int, per_page: int}
 *   $base_url     - string, base path for links (e.g. '/submissions')
 *   $query_params - array<string, string>, query params to preserve (e.g. ['status' => 'approved'])
 *   $page_param   - string (optional), query param name for the page number (default: 'page')
 */
/** @var array{page: int, pages: int, total: int, per_page: int} $pagination */
$pagination   = $pagination ?? ['page' => 1, 'pages' => 1, 'total' => 0, 'per_page' => 15];
/** @var string $base_url */
$base_url     = $base_url ?? '';
/** @var array<string, string> $query_params */
$query_params = $query_params ?? [];

/** @var string $page_param */
$page_param   = $page_param ?? 'page';

$current_page = (int)$pagination['page'];
$total_pages  = max(1, (int)$pagination['pages']);
$total        = (int)$pagination['total'];
$per_page     = (int)$pagination['per_page'];

$from = $total > 0 ? (($current_page - 1) * $per_page) + 1 : 0;
$to   = min($current_page * $per_page, $total);

$is_single_page = $total_pages <= 1;

// Build URL helper
$build_page_url = function (int $page) use ($base_url, $query_params, $page_param): string {
    $params = array_merge($query_params, [$page_param => $page]);
    $params = array_filter($params, fn($v) => $v !== '');
    return $base_url . '?' . http_build_query($params);
};

// Compute visible page window (max 7 page buttons)
$window = 7;
if ($total_pages <= $window) {
    $page_range = range(1, $total_pages);
} else {
    $half = (int)floor($window / 2);
    $start = max(1, $current_page - $half);
    $end   = min($total_pages, $start + $window - 1);
    if ($end - $start < $window - 1) {
        $start = max(1, $end - $window + 1);
    }
    $page_range = range($start, $end);
}

$_pag_path   = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$_pag_admin  = str_starts_with($_pag_path, '/admin') || str_starts_with($_pag_path, '/api/admin');

// Admin is English-only and forced LTR. User portal continues to use translations.
if ($_pag_admin) {
    $_pag_previous   = 'Previous';
    $_pag_next       = 'Next';
    $_pag_showing    = 'Showing';
    $_pag_to         = 'to';
    $_pag_of         = 'of';
    $_pag_results    = 'results';
    $_pag_no_results = 'No results';
    $_pag_rtl        = false;
} else {
    $_pag_previous   = t('pagination.previous');
    $_pag_next       = t('pagination.next');
    $_pag_showing    = t('common.showing');
    $_pag_to         = t('common.to');
    $_pag_of         = t('common.of');
    $_pag_results    = t('common.results');
    $_pag_no_results = t('common.no_results');
    $_pag_rtl        = is_rtl();
}

$link_base   = 'relative inline-flex items-center px-3 py-2 text-sm font-medium border';
$link_active = 'z-10 bg-primary-50 border-primary-500 text-primary-600';
$link_normal = 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50';
$link_disabled = 'bg-gray-50 border-gray-200 text-gray-300 cursor-not-allowed';
?>

<div class="flex items-center justify-between border-t border-gray-200 px-4 py-3 sm:px-6 rounded-b-lg">
    <!-- Mobile summary -->
    <div class="flex flex-1 justify-between sm:hidden">
        <?php if ($current_page > 1 && !$is_single_page): ?>
            <a href="<?php echo $build_page_url($current_page - 1); ?>" class="<?php echo $link_base; ?> <?php echo $link_normal; ?> rounded-md"><?= $_pag_previous ?></a>
       <?php else: ?>
            <span class="<?php echo $link_base; ?> <?php echo $link_disabled; ?> rounded-md"><?= $_pag_previous ?></span>
       <?php endif; ?>
       <?php if ($current_page < $total_pages && !$is_single_page): ?>
           <a href="<?php echo $build_page_url($current_page + 1); ?>" class="<?php echo $link_base; ?> <?php echo $link_normal; ?> rounded-md <?= $_pag_rtl ? 'mr-3' : 'ml-3' ?>"><?= $_pag_next ?></a>
       <?php else: ?>
           <span class="<?php echo $link_base; ?> <?php echo $link_disabled; ?> rounded-md <?= $_pag_rtl ? 'mr-3' : 'ml-3' ?>"><?= $_pag_next ?></span>
        <?php endif; ?>
    </div>

    <!-- Desktop -->
    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
        <p class="text-sm text-gray-700">
            <?php if ($total > 0): ?>
                <?= $_pag_showing ?> <span class="font-medium"><?php echo $from; ?></span> <?= $_pag_to ?> <span class="font-medium"><?php echo $to; ?></span> <?= $_pag_of ?> <span class="font-medium"><?php echo $total; ?></span> <?= $_pag_results ?>
            <?php else: ?>
                <?= $_pag_no_results ?>
            <?php endif; ?>
        </p>
        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
            <!-- Previous arrow -->
            <?php if ($current_page > 1 && !$is_single_page): ?>
                <a href="<?php echo $build_page_url($current_page - 1); ?>" class="<?php echo $link_base; ?> <?php echo $link_normal; ?> <?= $_pag_rtl ? 'rounded-r-md' : 'rounded-l-md' ?>">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="<?= $_pag_rtl ? 'M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z' : 'M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z' ?>" clip-rule="evenodd" /></svg>
                </a>
            <?php else: ?>
                <span class="<?php echo $link_base; ?> <?php echo $link_disabled; ?> <?= $_pag_rtl ? 'rounded-r-md' : 'rounded-l-md' ?>">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="<?= $_pag_rtl ? 'M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z' : 'M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z' ?>" clip-rule="evenodd" /></svg>
                </span>
            <?php endif; ?>

            <!-- Page numbers -->
            <?php if (!$is_single_page): ?>
                <?php if ($page_range[0] > 1): ?>
                    <a href="<?php echo $build_page_url(1); ?>" class="<?php echo $link_base; ?> <?php echo $link_normal; ?>">1</a>
                    <?php if ($page_range[0] > 2): ?>
                        <span class="<?php echo $link_base; ?> <?php echo $link_disabled; ?>">…</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php foreach ($page_range as $p): ?>
                    <?php if ($p === $current_page): ?>
                        <span class="<?php echo $link_base; ?> <?php echo $link_active; ?>"><?php echo $p; ?></span>
                    <?php else: ?>
                        <a href="<?php echo $build_page_url($p); ?>" class="<?php echo $link_base; ?> <?php echo $link_normal; ?>"><?php echo $p; ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (end($page_range) < $total_pages): ?>
                    <?php if (end($page_range) < $total_pages - 1): ?>
                        <span class="<?php echo $link_base; ?> <?php echo $link_disabled; ?>">…</span>
                    <?php endif; ?>
                    <a href="<?php echo $build_page_url($total_pages); ?>" class="<?php echo $link_base; ?> <?php echo $link_normal; ?>"><?php echo $total_pages; ?></a>
                <?php endif; ?>
            <?php else: ?>
                <span class="<?php echo $link_base; ?> <?php echo $link_active; ?>">1</span>
            <?php endif; ?>

            <!-- Next arrow -->
            <?php if ($current_page < $total_pages && !$is_single_page): ?>
                <a href="<?php echo $build_page_url($current_page + 1); ?>" class="<?php echo $link_base; ?> <?php echo $link_normal; ?> <?= $_pag_rtl ? 'rounded-l-md' : 'rounded-r-md' ?>">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="<?= $_pag_rtl ? 'M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z' : 'M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z' ?>" clip-rule="evenodd" /></svg>
                </a>
            <?php else: ?>
                <span class="<?php echo $link_base; ?> <?php echo $link_disabled; ?> <?= $_pag_rtl ? 'rounded-l-md' : 'rounded-r-md' ?>">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="<?= $_pag_rtl ? 'M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z' : 'M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z' ?>" clip-rule="evenodd" /></svg>
                </span>
            <?php endif; ?>
        </nav>
    </div>
</div>
