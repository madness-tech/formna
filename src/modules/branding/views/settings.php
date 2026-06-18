<?php
/**
 * Branding Settings View
 *
 * @var array{brand_name: string, brand_color: string, logo_path: ?string, logo_path_dark: ?string} $settings
 * @var bool $has_override
 */
$brand_name      = sanitize($settings['brand_name']);
$brand_color     = sanitize($settings['brand_color']);
$logo_path       = logo_url($settings['logo_path']);
$logo_path_dark  = logo_url($settings['logo_path_dark']);
$palette         = generate_color_palette($settings['brand_color']);
?>

<!-- Header -->
<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="page-title">Branding</h1>
        <p class="mt-2 text-sm text-gray-700">Customize your platform's brand name, color scheme, and logo.</p>
    </div>
</div>

<?php if ($has_override): ?>
    <!-- CSS Override Active Notice -->
    <div class="mt-6 bg-gradient-to-r from-purple-50 to-primary-50 rounded-lg shadow-sm border-2 border-primary-200 p-8">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <svg class="w-12 h-12 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Custom CSS Override Detected</h2>
                    <p class="text-sm text-gray-700 mb-4">
                        The branding module has been automatically disabled because a custom CSS override file was detected at
                        <code class="px-2 py-0.5 bg-white/80 rounded text-xs font-mono text-primary-700 border border-primary-200">/uploads/branding/custom.css</code>
                    </p>
                    <div class="bg-white/60 rounded-lg p-4 mb-4 border border-primary-100">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">What does this mean?</h3>
                        <ul class="text-xs text-gray-600 space-y-1.5 list-disc list-inside">
                            <li>All branding controls on this page are disabled</li>
                            <li>The platform uses default CSS classes (indigo/primary palette)</li>
                            <li>Your <code class="px-1 py-0.5 bg-gray-100 rounded font-mono">custom.css</code> file has full control over styling</li>
                            <li>You can override any color, font, layout, or component style</li>
                        </ul>
                    </div>
                    <div class="bg-amber-50 rounded-lg p-4 border border-amber-200 mb-4">
                        <h3 class="text-sm font-semibold text-amber-900 mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Re-enabling the Branding Module
                        </h3>
                        <p class="text-xs text-amber-800">
                            To re-enable this branding interface, delete or rename the
                            <code class="px-1 py-0.5 bg-amber-100 rounded font-mono">custom.css</code> file from
                            <code class="px-1 py-0.5 bg-amber-100 rounded font-mono">app/public/uploads/branding/</code>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="badge badge-green border border-green-200">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Advanced CSS Mode Active
                        </span>
                        <span class="help-text">Full styling control enabled</span>
                    </div>
                </div>
        </div>
    </div>
<?php else: ?>

<!-- Brand Name & Color -->
<div class="mt-6">
    <form method="POST" action="/admin/branding">
        <?php echo csrf_field(); ?>

        <div class="card p-6">
            <h2 class="section-title mb-4">Brand Identity</h2>

            <!-- Brand Name -->
            <div class="mb-6">
                <label for="brand_name">Brand Name</label>
                <input type="text"
                       id="brand_name"
                       name="brand_name"
                       value="<?php echo $brand_name; ?>"
                       maxlength="100"
                       required
                       placeholder="e.g. FORMNA"
                       class="max-w-sm">
                <p class="mt-1 text-xs text-gray-400">Shown in the sidebar, login page, browser tab, and footer.</p>
            </div>

            <!-- Brand Color -->
            <div class="mb-6">
                <label for="brand_color">Brand Color</label>
                <div class="flex items-center gap-3">
                    <input type="color"
                           id="brand_color_picker"
                           value="<?php echo $brand_color; ?>"
                           class="h-10 w-14 rounded cursor-pointer p-0.5">
                    <input type="text"
                           id="brand_color"
                           name="brand_color"
                           value="<?php echo $brand_color; ?>"
                           pattern="^#[0-9a-fA-F]{6}$"
                           required
                           maxlength="7"
                           class="w-32 input-mono">
                    <button type="button"
                            id="reset-color-btn"
                            class="text-xs text-gray-400 hover:text-gray-600 underline">
                        Reset to default
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-400">A full color palette is automatically generated from this color.</p>
            </div>

            <!-- Live Palette Preview -->
            <div class="mb-6">
                <label>Generated Palette Preview</label>
                <div class="flex rounded-lg overflow-hidden shadow-sm border border-gray-200" id="palette-preview">
                    <?php foreach ($palette as $shade => $hex): ?>
                        <div class="flex-1 group relative" style="background-color: <?php echo $hex; ?>; height: 56px;" data-shade="<?php echo $shade; ?>">
                            <div class="absolute inset-x-0 bottom-0 bg-black/50 text-white text-center text-[9px] leading-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                <?php echo $shade; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="flex mt-1" id="palette-labels">
                    <?php foreach ($palette as $shade => $hex): ?>
                        <div class="flex-1 text-center text-[9px] text-gray-400 font-mono" data-shade="<?php echo $shade; ?>"><?php echo $hex; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="btn btn-primary">
                    Save Changes
                </button>
                <span class="text-xs text-gray-400">Changes take effect immediately after saving.</span>
            </div>
        </div>
    </form>
</div>

<!-- Logos -->
<div class="card mt-6 p-6">
        <h2 class="section-title mb-1">Logos</h2>
        <p class="text-sm text-gray-500 mb-5">Upload logos for light and dark backgrounds. When no logo is set, the brand name is shown as text.</p>

        <!-- Live Preview -->
        <div class="mb-6 p-4 rounded-lg border border-gray-100 bg-gray-50">
            <label class="text-xs text-gray-500 uppercase tracking-wider mb-3">Preview</label>
            <div class="flex items-center gap-6">
                <!-- Admin sidebar preview (dark bg) -->
                <div>
                    <p class="text-[10px] text-gray-400 mb-1.5 text-center">Admin Sidebar</p>
                    <div class="rounded-lg overflow-hidden shadow-sm" style="background-color: <?php echo $brand_color; ?>;">
                        <div class="flex items-center h-14 px-5">
                            <?php if ($logo_path_dark): ?>
                                <img src="<?php echo sanitize($logo_path_dark); ?>" alt="Logo (dark bg)" class="h-8 max-w-[160px] object-contain">
                            <?php elseif ($logo_path): ?>
                                <img src="<?php echo sanitize($logo_path); ?>" alt="Logo" class="h-8 max-w-[160px] object-contain brightness-0 invert">
                            <?php else: ?>
                                <span class="text-white text-lg font-bold tracking-tight"><?php echo $brand_name; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- User sidebar preview (light bg) -->
                <div>
                    <p class="text-[10px] text-gray-400 mb-1.5 text-center">User Sidebar</p>
                    <div class="card">
                        <div class="flex items-center h-14 px-5">
                            <?php if ($logo_path): ?>
                                <img src="<?php echo sanitize($logo_path); ?>" alt="Logo" class="h-8 max-w-[160px] object-contain">
                            <?php else: ?>
                                <span class="text-lg font-bold tracking-tight" style="color: <?php echo $brand_color; ?>;"><?php echo $brand_name; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Two-column logo upload grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Light Background Logo -->
            <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Light Background</h3>
                <p class="text-xs text-gray-400 mb-3">Used on white/light pages (user sidebar, login, auth).</p>

                <?php if ($logo_path): ?>
                    <div class="flex items-center gap-3 mb-3 p-3 rounded-lg bg-green-50 border border-green-200">
                        <img src="<?php echo sanitize($logo_path); ?>" alt="Current logo" class="h-8 max-w-[100px] object-contain">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-green-800">Active</p>
                            <p class="text-[10px] text-green-600 truncate"><?php echo sanitize(basename($logo_path)); ?></p>
                        </div>
                        <form method="POST" action="/admin/branding/logo/delete" class="flex-shrink-0"
                              onsubmit="return confirm('Remove this logo?')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="variant" value="light">
                            <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">Remove</button>
                        </form>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/admin/branding/logo" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="variant" value="light">
                    <input type="file"
                           name="logo"
                           accept="image/png,image/jpeg,image/webp"
                           required
                           class="help-text w-full file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 file:cursor-pointer">
                    <button type="submit"
                            class="btn btn-primary btn-full btn-sm mt-3">
                        <?php echo $logo_path ? 'Replace' : 'Upload'; ?>
                    </button>
                </form>
            </div>

            <!-- Dark Background Logo -->
            <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Dark Background</h3>
                <p class="text-xs text-gray-400 mb-3">Ideally a white or light-colored version.</p>

                <?php if ($logo_path_dark): ?>
                    <div class="flex items-center gap-3 mb-3 p-3 rounded-lg bg-green-50 border border-green-200">
                        <div class="p-1 rounded" style="background-color: <?php echo $brand_color; ?>;">
                            <img src="<?php echo sanitize($logo_path_dark); ?>" alt="Current logo (dark)" class="h-6 max-w-[80px] object-contain">
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-green-800">Active</p>
                            <p class="text-[10px] text-green-600 truncate"><?php echo sanitize(basename($logo_path_dark)); ?></p>
                        </div>
                        <form method="POST" action="/admin/branding/logo/delete" class="flex-shrink-0"
                              onsubmit="return confirm('Remove this logo?')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="variant" value="dark">
                            <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">Remove</button>
                        </form>
                    </div>
                <?php elseif ($logo_path): ?>
                    <div class="mb-3 p-3 rounded-lg bg-blue-50 border border-blue-200">
                        <p class="text-xs text-blue-700">No dark variant uploaded. The light logo will be shown with an auto-invert filter.</p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/admin/branding/logo" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="variant" value="dark">
                    <input type="file"
                           name="logo"
                           accept="image/png,image/jpeg,image/webp"
                           required
                           class="help-text w-full file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 file:cursor-pointer">
                    <button type="submit"
                            class="btn btn-primary btn-full btn-sm mt-3">
                        <?php echo $logo_path_dark ? 'Replace' : 'Upload'; ?>
                    </button>
                </form>
            </div>
        </div>

    <p class="mt-3 text-xs text-gray-400">PNG, JPG, or WebP. Max 2 MB each. Transparent backgrounds recommended.</p>
</div>

<?php endif; // End of !$has_override ?>

<?php if (!$has_override): ?>
<script>
(function() {
    const DEFAULT_COLOR = '#4f46e5';
    const colorPicker = document.getElementById('brand_color_picker');
    const colorInput  = document.getElementById('brand_color');
    const resetBtn    = document.getElementById('reset-color-btn');
    const preview     = document.getElementById('palette-preview');
    const labels      = document.getElementById('palette-labels');

    // Sync color picker → text input + live preview
    colorPicker.addEventListener('input', function() {
        colorInput.value = this.value;
        updatePalettePreview(this.value);
    });

    // Sync text input → color picker + live preview
    colorInput.addEventListener('input', function() {
        const v = this.value;
        if (/^#[0-9a-fA-F]{6}$/.test(v)) {
            colorPicker.value = v;
            updatePalettePreview(v);
        }
    });

    // Reset button
    resetBtn.addEventListener('click', function() {
        colorPicker.value = DEFAULT_COLOR;
        colorInput.value  = DEFAULT_COLOR;
        updatePalettePreview(DEFAULT_COLOR);
    });

    /**
     * Generate a full color palette from a single hex color (client-side mirror
     * of the PHP generate_color_palette function).
     */
    function updatePalettePreview(hex) {
        const palette = generatePalette(hex);
        const shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900];

        shades.forEach(shade => {
            const color = palette[shade];
            const swatch = preview.querySelector(`[data-shade="${shade}"]`);
            const label  = labels.querySelector(`[data-shade="${shade}"]`);
            if (swatch) swatch.style.backgroundColor = color;
            if (label)  label.textContent = color;
        });
    }

    /** Convert hex to HSL (all values 0-1 for h, 0-100 for s/l) */
    function hexToHsl(hex) {
        let r = parseInt(hex.slice(1, 3), 16) / 255;
        let g = parseInt(hex.slice(3, 5), 16) / 255;
        let b = parseInt(hex.slice(5, 7), 16) / 255;

        const max = Math.max(r, g, b), min = Math.min(r, g, b);
        let h = 0, s = 0, l = (max + min) / 2;

        if (max !== min) {
            const d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            switch (max) {
                case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
                case g: h = ((b - r) / d + 2) / 6; break;
                case b: h = ((r - g) / d + 4) / 6; break;
            }
        }

        return [h * 360, s * 100, l * 100];
    }

    /** Convert HSL to hex string */
    function hslToHex(h, s, l) {
        h = ((h % 360) + 360) % 360;
        s = Math.max(0, Math.min(100, s)) / 100;
        l = Math.max(0, Math.min(100, l)) / 100;

        const c = (1 - Math.abs(2 * l - 1)) * s;
        const x = c * (1 - Math.abs((h / 60) % 2 - 1));
        const m = l - c / 2;

        let r = 0, g = 0, b = 0;
        if (h < 60)       { r = c; g = x; }
        else if (h < 120) { r = x; g = c; }
        else if (h < 180) { g = c; b = x; }
        else if (h < 240) { g = x; b = c; }
        else if (h < 300) { r = x; b = c; }
        else              { r = c; b = x; }

        const toHex = v => Math.round((v + m) * 255).toString(16).padStart(2, '0');
        return '#' + toHex(r) + toHex(g) + toHex(b);
    }

    /**
     * Generate a 10-shade palette from a single brand color.
     * The input maps to the 600 shade.
     */
    function generatePalette(hex) {
        const [h, s, l] = hexToHsl(hex);
        const lightRange = 97 - l;
        const darkFloor  = Math.max(l * 0.28, 8);
        const darkRange  = l - darkFloor;

        const lightSteps = [
            [50,  1.00, 0.50],
            [100, 0.92, 0.60],
            [200, 0.80, 0.72],
            [300, 0.65, 0.82],
            [400, 0.44, 0.91],
            [500, 0.22, 0.96],
        ];
        const darkSteps = [
            [700, 0.25, 0.93],
            [800, 0.50, 0.82],
            [900, 0.75, 0.70],
        ];

        const palette = {};

        lightSteps.forEach(([shade, lFrac, sFactor]) => {
            palette[shade] = hslToHex(h, s * sFactor, l + lightRange * lFrac);
        });

        palette[600] = hex;

        darkSteps.forEach(([shade, dFrac, sFactor]) => {
            palette[shade] = hslToHex(h, s * sFactor, Math.max(l - darkRange * dFrac, darkFloor));
        });

        return palette;
    }
})();
</script>
<?php endif; // End of !$has_override (for script) ?>
