<?php

/**
 * Email Sending Functions
 * PHPMailer integration for transactional emails
 */

require_once __DIR__ . '/models.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email using PHPMailer
 */
function send_email(string $to, string $subject, string $body, bool $is_html = true, ?string $alt_body = null): bool {
    global $config;
    
    $mail = new PHPMailer(true);
    
    try {
        if (isset($config['mail']['smtp']) && $config['mail']['smtp']) {
            $mail->isSMTP();
            $mail->Host = $config['mail']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['mail']['username'];
            $mail->Password = $config['mail']['password'];
            $mail->SMTPSecure = $config['mail']['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config['mail']['port'] ?? 587;
        }
        
        $mail->setFrom($config['mail']['from_address'], $config['mail']['from_name']);
        $mail->addAddress($to);
        $mail->addReplyTo($config['mail']['reply_to'] ?? $config['mail']['from_address']);
        
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        
        if ($is_html) {
            $mail->Body    = email_html_wrapper($body);
            $mail->AltBody = $alt_body ?? strip_tags($body);
        } else {
            $mail->Body = $body;
        }
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        $logFile = dirname(__DIR__, 3) . '/storage/logs/email_errors.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = sprintf(
            "[%s] SMTP Error to %s: %s\n",
            date('Y-m-d H:i:s'),
            $to,
            $mail->ErrorInfo
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        error_log("Email send failed to {$to}: {$mail->ErrorInfo}");
        
        return false;
    }
}

/**
 * Send email from a global template
 * @param array<string,mixed> $user
 * @param array<string,mixed> $vars
 */
function send_from_template(string $trigger, array $user, array $vars = []): bool {
    $template = get_email_template($trigger);
    
    if (!$template) {
        return false;
    }
    
    $placeholders = build_placeholders($user, $vars);
    $escaped      = array_map(fn($v) => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), array_values($placeholders));

    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $template['subject']);
    $plain   = str_replace(array_keys($placeholders), array_values($placeholders), $template['body']);
    $body    = nl2br(str_replace(array_keys($placeholders), $escaped, $template['body']));

    return send_email($user['email'], $subject, $body, true, $plain);
}

/**
 * Queue email for later sending
 */
function queue_email(string $to, string $subject, string $body, int $delay_minutes = 0): bool {
    $run_at = datetime_add(now(), "+{$delay_minutes} minutes");
    
    return (bool) db_insert('job_queue', [
        'handler' => 'send_email',
        'payload' => json_encode([
            'to' => $to,
            'subject' => $subject,
            'body' => $body
        ]),
        'run_at' => $run_at
    ]);
}

/**
 * Queue email from a global template
 * @param array<string,mixed> $user
 * @param array<string,mixed> $vars
 * @param array<string,mixed> $meta
 */
function queue_email_from_template(string $trigger, array $user, array $vars = [], int $delay_minutes = 0, array $meta = []): bool {
    $template = get_email_template($trigger);
    
    if (!$template) {
        return false;
    }
    
    $placeholders = build_placeholders($user, $vars);
    $escaped      = array_map(fn($v) => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), array_values($placeholders));

    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $template['subject']);
    $plain   = str_replace(array_keys($placeholders), array_values($placeholders), $template['body']);
    $body    = nl2br(str_replace(array_keys($placeholders), $escaped, $template['body']));

    $run_at = datetime_add(now(), "+{$delay_minutes} minutes");

    $payload = [
        'to'       => $user['email'],
        'subject'  => $subject,
        'body'     => $body,
        'alt_body' => $plain,
    ];
    
    if (!empty($meta)) {
        $payload['meta'] = $meta;
    }
    
    return (bool) db_insert('job_queue', [
        'handler' => 'send_email',
        'payload' => json_encode($payload),
        'run_at' => $run_at
    ]);
}

/**
 * Build placeholder replacement array
 * @param array<string,mixed> $user
 * @param array<string,mixed> $vars
 * @return array<string,string>
 */
function build_placeholders(array $user, array $vars = []): array {
    global $config;

    $defaults = [
        '{user_name}'      => $user['name'] ?? $user['email'],
        '{user_email}'     => $user['email'],
        '{dashboard_link}' => base_url('/dashboard'),
        '{base_url}'       => rtrim($config['app']['base_url'], '/'),
    ];

    foreach ($vars as $key => $value) {
        $defaults["{{$key}}"] = (string) $value;
    }

    return $defaults;
}

/**
 * HTML email wrapper template
 */
function email_html_wrapper(string $content): string {
    global $config;
    $app_name = $config['app']['name'] ?? 'FORMNA';
    $brand = get_branding();
    $brand_color = $brand['brand_color'] ?: '#4f46e5';
    $year = date('Y');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background-color: {$brand_color};
            padding: 30px;
            text-align: center;
        }
        .email-header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-body {
            padding: 40px 30px;
        }
        .email-body p {
            margin: 0 0 15px 0;
        }
        .email-button {
            display: inline-block;
            padding: 12px 30px;
            background-color: {$brand_color};
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 500;
        }
        .email-footer {
            background-color: #f9fafb;
            padding: 20px 30px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>{$app_name}</h1>
        </div>
        <div class="email-body">
            {$content}
        </div>
        <div class="email-footer">
            <p>&copy; {$year} {$app_name}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Send test email to verify SMTP configuration
 */
function send_test_email(string $to): bool {
    global $config;
    $app_name = $config['app']['name'] ?? 'Form Builder';
    
    return send_email(
        $to,
        "Test Email from {$app_name}",
        '<p>This is a test email to verify email configuration.</p><p>If you received this, your email settings are working correctly!</p>'
    );
}

/**
 * Atomically claim up to $limit pending jobs.
 * Uses SELECT ... FOR UPDATE SKIP LOCKED inside a transaction so concurrent
 * workers never compete for the same rows.
 * @return list<array<string, mixed>>
 */
function claim_pending_jobs(int $limit = 20): array {
    return db_transaction(function () use ($limit) {
        $candidates = db_query("
            SELECT * FROM job_queue
            WHERE run_at <= UTC_TIMESTAMP()
            AND locked_at IS NULL
            AND failed_at IS NULL
            ORDER BY run_at ASC
            LIMIT ?
            FOR UPDATE SKIP LOCKED
        ", [$limit]);

        if (empty($candidates)) {
            return [];
        }

        $ids = array_column($candidates, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        db_exec("
            UPDATE job_queue
            SET locked_at = UTC_TIMESTAMP()
            WHERE id IN ({$placeholders})
        ", $ids);

        return $candidates;
    });
}

/** @param array<string, mixed> $job */
function process_queued_job(array $job): bool {
    $payload = json_decode($job['payload'], true);
    
    if (!$payload || !isset($payload['to'], $payload['subject'], $payload['body'])) {
        return false;
    }
    
    return send_email(
        $payload['to'],
        $payload['subject'],
        $payload['body'],
        $payload['is_html'] ?? true,
        $payload['alt_body'] ?? null
    );
}

function complete_job(int $job_id): bool {
    return (bool) db_exec("DELETE FROM job_queue WHERE id = ?", [$job_id]);
}

function fail_job(int $job_id, string $error, bool $retry = true): bool {
    global $config;
    
    $job = db_one("SELECT * FROM job_queue WHERE id = ?", [$job_id]);
    
    if (!$job) {
        return false;
    }
    
    $attempts = $job['attempts'] + 1;
    $maxAttempts = $job['max_attempts'];
    
    if (!$retry || $attempts >= $maxAttempts) {
        return (bool) db_update('job_queue', [
            'attempts' => $attempts,
            'failed_at' => now(),
            'error' => substr($error, 0, 1000),
            'locked_at' => null
        ], 'id = ?', [$job_id]);
    }
    
    $retryDelays = $config['queue']['retry_delays'] ?? [300, 1800, 7200];
    $delayIndex = min($attempts - 1, count($retryDelays) - 1);
    $delaySeconds = $retryDelays[$delayIndex];
    $runAt = gmdate('Y-m-d H:i:s', time() + $delaySeconds);
    
    return (bool) db_update('job_queue', [
        'attempts' => $attempts,
        'run_at' => $runAt,
        'locked_at' => null,
        'error' => substr($error, 0, 1000)
    ], 'id = ?', [$job_id]);
}

function unlock_stale_jobs(): int {
    $staleMinutes = 10;
    $staleTime = gmdate('Y-m-d H:i:s', time() - ($staleMinutes * 60));
    
    return db_exec("
        UPDATE job_queue
        SET locked_at = NULL
        WHERE locked_at < ?
        AND failed_at IS NULL
    ", [$staleTime]);
}
