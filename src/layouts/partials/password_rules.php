<?php
/**
 * Password complexity guide partial
 *
 * Renders the 4-rule grid (length, uppercase, lowercase, digit) with dot/check indicators.
 *
 * Optional variables (set before including):
 *   $pw_prefix  — ID prefix for rule elements (default: 'pw')
 *   $pw_labels  — associative array with keys: length, uppercase, lowercase, digit
 *                  Defaults to English strings. User-facing pages should pass t() values.
 */

$pw_prefix = $pw_prefix ?? 'pw';
$pw_labels = $pw_labels ?? [];

$_pw_label_length    = sanitize($pw_labels['length']    ?? 'At least 8 characters');
$_pw_label_uppercase = sanitize($pw_labels['uppercase'] ?? 'One uppercase letter');
$_pw_label_lowercase = sanitize($pw_labels['lowercase'] ?? 'One lowercase letter');
$_pw_label_digit     = sanitize($pw_labels['digit']     ?? 'One number');
?>
<div class="mt-3 rounded-lg bg-gray-50 border border-gray-100 p-3" id="<?= $pw_prefix ?>-rules">
    <div class="grid grid-cols-2 gap-x-4 gap-y-2">
        <div class="pw-rule flex items-center gap-2 text-xs" id="<?= $pw_prefix ?>-length">
            <span class="pw-dot h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300 transition-all"></span>
            <svg class="pw-check h-3.5 w-3.5 shrink-0 text-green-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span class="text-gray-500 transition-colors"><?= $_pw_label_length ?></span>
        </div>
        <div class="pw-rule flex items-center gap-2 text-xs" id="<?= $pw_prefix ?>-upper">
            <span class="pw-dot h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300 transition-all"></span>
            <svg class="pw-check h-3.5 w-3.5 shrink-0 text-green-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span class="text-gray-500 transition-colors"><?= $_pw_label_uppercase ?></span>
        </div>
        <div class="pw-rule flex items-center gap-2 text-xs" id="<?= $pw_prefix ?>-lower">
            <span class="pw-dot h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300 transition-all"></span>
            <svg class="pw-check h-3.5 w-3.5 shrink-0 text-green-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span class="text-gray-500 transition-colors"><?= $_pw_label_lowercase ?></span>
        </div>
        <div class="pw-rule flex items-center gap-2 text-xs" id="<?= $pw_prefix ?>-digit">
            <span class="pw-dot h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300 transition-all"></span>
            <svg class="pw-check h-3.5 w-3.5 shrink-0 text-green-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span class="text-gray-500 transition-colors"><?= $_pw_label_digit ?></span>
        </div>
    </div>
</div>
