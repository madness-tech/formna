<?php $_footer_brand = get_branding(); ?>
    <footer class="mt-auto py-6 text-center text-gray-400 text-xs">
        <?php
        $_footer_path  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $_footer_admin = str_starts_with($_footer_path, '/admin') || str_starts_with($_footer_path, '/api/admin');
        ?>
        <p>
            <?php echo format_datetime(now(), 'year'); ?> &copy; <?php echo sanitize($_footer_brand['brand_name']); ?> |
            <?= $_footer_admin ? 'All rights reserved.' : t('common.all_rights_reserved') ?>
        </p>
    </footer>
