#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../core/cron_bootstrap.php';
require_once __DIR__ . '/../../modules/emails/sender.php';

$lockFile = __DIR__ . '/../../../storage/queue.lock';
$fp = fopen($lockFile, 'w');

if (!flock($fp, LOCK_EX | LOCK_NB)) {
    fclose($fp);
    exit(0);
}

$startTime = time();
$maxRuntime = $config['queue']['max_runtime'] ?? 300;
$batchSize = $config['queue']['batch_size'] ?? 20;
$processed = 0;
$failed = 0;

ini_set('memory_limit', '256M');
set_time_limit(0);

unlock_stale_jobs();

while ((time() - $startTime) < $maxRuntime) {
    $jobs = claim_pending_jobs($batchSize);
    
    if (empty($jobs)) {
        sleep(5);
        continue;
    }
    
    foreach ($jobs as $job) {
        try {
            if (process_queued_job($job)) {
                complete_job($job['id']);
                $processed++;
            } else {
                fail_job($job['id'], 'Processing failed', true);
                $failed++;
            }
        } catch (Exception $e) {
            fail_job($job['id'], $e->getMessage(), true);
            $failed++;
        }
        
        if ((time() - $startTime) >= $maxRuntime) {
            break;
        }
    }
}

fclose($fp);

if ($processed > 0 || $failed > 0) {
    error_log(sprintf(
        "[Queue Worker] Processed: %d, Failed: %d, Runtime: %ds",
        $processed,
        $failed,
        time() - $startTime
    ));
}

exit(0);
