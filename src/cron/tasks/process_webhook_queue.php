#!/usr/bin/env php
<?php

/**
 * Webhook Queue Processor
 * 
 * Processes pending webhooks from the webhook_log table.
 * Managed by the cron router — runs every minute.
 */

// Bootstrap application
require_once __DIR__ . '/../../core/cron_bootstrap.php';
require_once __DIR__ . '/../../modules/webhooks/processor.php';
require_once __DIR__ . '/../../modules/notifications/models.php';

// Configuration
$LOCK_FILE = __DIR__ . '/../../../storage/webhook.lock';
$MAX_RUNTIME = 300; // 5 minutes
$BATCH_SIZE = 10; // Process 10 webhooks at a time
$REQUEST_DELAY_MS = 500; // Milliseconds between each webhook fire to avoid flooding endpoints
$RETRY_ATTEMPTS = 3;

// Lock handling to prevent concurrent runs
$lockFile = fopen($LOCK_FILE, 'c');
if (!$lockFile) {
    error_log('[WEBHOOK] Failed to open lock file');
    exit(1);
}

if (!flock($lockFile, LOCK_EX | LOCK_NB)) {
    // Another instance is already running
    fclose($lockFile);
    exit(0);
}

// Log start
echo "[" . date('Y-m-d H:i:s') . "] Webhook queue processor started\n";

$startTime = time();
$processedCount = 0;
$successCount = 0;
$failedCount = 0;

try {
    while ((time() - $startTime) < $MAX_RUNTIME) {
        // Get pending/retrying webhooks
        $stmt = db()->prepare("
            SELECT * FROM webhook_log
            WHERE status IN ('pending', 'retrying')
              AND (locked_at IS NULL OR locked_at < NOW() - INTERVAL 10 MINUTE)
              AND (retry_after IS NULL OR retry_after <= NOW())
            ORDER BY created_at ASC
            LIMIT ?
        ");
        
        $stmt->execute([$BATCH_SIZE]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($jobs)) {
            // No jobs to process, sleep and check again
            echo "[" . date('Y-m-d H:i:s') . "] No pending webhooks, sleeping...\n";
            sleep(5);
            continue;
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Found " . count($jobs) . " webhooks to process\n";
        
        foreach ($jobs as $job) {
            $processedCount++;
            
            // Lock this job to prevent duplicate processing
            $lockStmt = db()->prepare("
                UPDATE webhook_log
                SET locked_at = NOW()
                WHERE id = ? AND (locked_at IS NULL OR locked_at < NOW() - INTERVAL 10 MINUTE)
            ");
            $lockStmt->execute([$job['id']]);
            
            // Check if we got the lock (if another worker grabbed it, skip)
            if ($lockStmt->rowCount() === 0) {
                echo "[" . date('Y-m-d H:i:s') . "] Job {$job['id']} already locked by another worker\n";
                continue;
            }
            
            // Delay between requests to avoid flooding endpoints
            if ($processedCount > 1) {
                usleep($REQUEST_DELAY_MS * 1000);
            }
            
            if ((int)$job['attempts'] >= $RETRY_ATTEMPTS) {
                $updateStmt = db()->prepare("
                    UPDATE webhook_log
                    SET status = 'failed',
                        response_body = CONCAT(COALESCE(response_body, ''), ' | Max retry attempts reached'),
                        locked_at = NULL
                    WHERE id = ?
                ");

                $updateStmt->execute([$job['id']]);
                $failedCount++;
                echo "[" . date('Y-m-d H:i:s') . "] SKIPPED: Max retry attempts reached for job {$job['id']} (attempt {$job['attempts']}/{$RETRY_ATTEMPTS})\n";
                continue;
            }

            // Fire the webhook
            echo "[" . date('Y-m-d H:i:s') . "] Firing webhook #{$job['webhook_id']} for submission #{$job['submission_id']}...\n";
            $result = fire_webhook($job);
            
            // Update webhook log based on result
            if ($result['success']) {
                // Success - mark as complete
                $updateStmt = db()->prepare("
                    UPDATE webhook_log
                    SET status = 'success',
                        response_status = ?,
                        response_body = ?,
                        attempts = attempts + 1,
                        locked_at = NULL
                    WHERE id = ?
                ");
                
                $updateStmt->execute([
                    $result['status'],
                    $result['body'],
                    $job['id']
                ]);
                
                $successCount++;
                echo "[" . date('Y-m-d H:i:s') . "] SUCCESS: HTTP {$result['status']}\n";
                
            } else {
                // Failed - check if we should retry
                $attempts = (int)$job['attempts'] + 1;
                
                if ($attempts >= $RETRY_ATTEMPTS) {
                    // Max retries reached - mark as failed permanently
                    $updateStmt = db()->prepare("
                        UPDATE webhook_log
                        SET status = 'failed',
                            response_status = ?,
                            response_body = ?,
                            attempts = ?,
                            locked_at = NULL
                        WHERE id = ?
                    ");
                    
                    $updateStmt->execute([
                        $result['status'],
                        $result['error'] . ' | ' . ($result['body'] ?? ''),
                        $attempts,
                        $job['id']
                    ]);
                    
                    $failedCount++;
                    echo "[" . date('Y-m-d H:i:s') . "] FAILED PERMANENTLY: {$result['error']} (attempt {$attempts}/{$RETRY_ATTEMPTS})\n";
                    
                    // Notify webhook creator about the permanent failure
                    try {
                        $webhookStmt = db()->prepare("SELECT name, url, created_by FROM webhooks WHERE id = ?");
                        $webhookStmt->execute([$job['webhook_id']]);
                        $webhook = $webhookStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($webhook && $webhook['created_by']) {
                            create_notification(
                                (int)$webhook['created_by'],
                                'webhook_failed',
                                [
                                    'webhook_id' => $job['webhook_id'],
                                    'webhook_name' => $webhook['name'],
                                    'webhook_url' => $webhook['url'],
                                    'submission_id' => $job['submission_id'],
                                    'error' => $result['error'],
                                    'http_status' => $result['status'],
                                    'log_id' => $job['id']
                                ]
                            );
                        }
                    } catch (Exception $notifyEx) {
                        error_log('[WEBHOOK] Failed to create notification: ' . $notifyEx->getMessage());
                    }
                    
                } else {
                    // Retry later with exponential backoff
                    // Attempt 1 failed → wait 2 min, Attempt 2 failed → wait 4 min
                    $backoffMinutes = (int) pow(2, $attempts);
                    $backoffMinutes = min($backoffMinutes, 60); // Cap at 60 minutes
                    $retryAfter = date('Y-m-d H:i:s', time() + ($backoffMinutes * 60));
                    
                    $updateStmt = db()->prepare("
                        UPDATE webhook_log
                        SET status = 'retrying',
                            response_status = ?,
                            response_body = ?,
                            attempts = ?,
                            locked_at = NULL,
                            retry_after = ?
                        WHERE id = ?
                    ");
                    
                    $updateStmt->execute([
                        $result['status'],
                        $result['error'] . ' | ' . ($result['body'] ?? ''),
                        $attempts,
                        $retryAfter,
                        $job['id']
                    ]);
                    
                    echo "[" . date('Y-m-d H:i:s') . "] RETRY SCHEDULED in {$backoffMinutes}min: {$result['error']} (attempt {$attempts}/{$RETRY_ATTEMPTS})\n";
                }
            }
        }
        
        // Delay between batches to prevent overwhelming the database and endpoints
        sleep(2);
    }
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    error_log('[WEBHOOK] Fatal error: ' . $e->getMessage());
} finally {
    // Release lock
    flock($lockFile, LOCK_UN);
    fclose($lockFile);
}

// Log summary
$runtime = time() - $startTime;
echo "[" . date('Y-m-d H:i:s') . "] Webhook queue processor finished\n";
echo "[" . date('Y-m-d H:i:s') . "] Runtime: {$runtime}s | Processed: {$processedCount} | Success: {$successCount} | Failed: {$failedCount}\n";

exit(0);
