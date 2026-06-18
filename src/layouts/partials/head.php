<?php
$_has_css_override = has_css_override();

// Use default indigo palette when CSS override is active
if ($_has_css_override) {
    $_brand = [
        'brand_name'    => 'FORMNA',
        'brand_color'   => '#4f46e5',
        'logo_path'     => null,
        'logo_path_dark' => null,
    ];
    $_palette = generate_color_palette('#4f46e5');
} else {
    $_brand  = get_branding();
    $_palette = $_brand['palette'];
}

?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <title><?php echo sanitize($title ?? $_brand['brand_name']); ?></title>

    <!-- Compiled CSS (Tailwind v4 + design system) -->
    <link rel="stylesheet" href="<?= asset('/css/app.css') ?>">

    <!-- Preload shared JS module to eliminate import waterfall -->
    <link rel="modulepreload" href="<?= asset('/js/common.js') ?>">

    <!-- Brand color overrides — must come AFTER app.css so vars cascade correctly -->
    <style>
        :root {
            --color-primary-50:  <?php echo $_palette[50]; ?>;
            --color-primary-100: <?php echo $_palette[100]; ?>;
            --color-primary-200: <?php echo $_palette[200]; ?>;
            --color-primary-300: <?php echo $_palette[300]; ?>;
            --color-primary-400: <?php echo $_palette[400]; ?>;
            --color-primary-500: <?php echo $_palette[500]; ?>;
            --color-primary-600: <?php echo $_palette[600]; ?>;
            --color-primary-700: <?php echo $_palette[700]; ?>;
            --color-primary-800: <?php echo $_palette[800]; ?>;
            --color-primary-900: <?php echo $_palette[900]; ?>;
        }
    </style>

    <?php if ($_has_css_override): ?>
    <!-- Custom CSS Override (loads last — can override any CSS variable or class) -->
    <link rel="stylesheet" href="<?= asset(get_css_override_path()) ?>">
    <?php endif; ?>
