#!/usr/bin/env php
<?php

/**
 * Clean up expired verification tokens.
 *
 * Removes all verification_tokens rows where expires_at < NOW().
 * Safe to run frequently — idempotent and fast.
 *
 * Managed by the cron router — runs hourly.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';
require_once __DIR__ . '/../../core/verification.php';

echo "[" . date('Y-m-d H:i:s') . "] Cleaning up expired verification tokens...\n";

$deleted = cleanup_expired_tokens();

echo "[" . date('Y-m-d H:i:s') . "] Deleted {$deleted} expired token(s)\n";
