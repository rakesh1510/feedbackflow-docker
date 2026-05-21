<?php
/**
 * FeedbackFlow Background Job Worker (Module 28)
 * Run via cron: * * * * * php /path/to/feedbackflow/jobs/worker.php >> /var/log/ff-worker.log 2>&1
 * Or run manually: php jobs/worker.php
 */
define('FF_WORKER', true);

$dir = dirname(__DIR__);
require_once $dir . '/config.php';
require_once $dir . '/includes/db.php';
require_once $dir . '/includes/functions.php';

$logger = function(string $msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
};

$logger("FeedbackFlow Worker started (PID: " . getmypid() . ")");

// Claim a batch of pending jobs
$jobs = DB::fetchAll(
    "SELECT * FROM ff_jobs 
     WHERE status = 'pending' 
       AND available_at <= NOW() 
       AND attempts < max_attempts 
     ORDER BY available_at ASC 
     LIMIT 10 FOR UPDATE"
);

if (empty($jobs)) {
    $logger("No pending jobs. Exiting.");
    exit(0);
}

foreach ($jobs as $job) {
    // Mark as running
    DB::update('ff_jobs', [
        'status'     => 'running',
        'started_at' => date('Y-m-d H:i:s'),
        'attempts'   => $job['attempts'] + 1,
    ], 'id = ?', [$job['id']]);

    $logger("Processing job #{$job['id']} type={$job['type']}");
    $payload = json_decode($job['payload'] ?? '{}', true) ?? [];
    $error = null;

    try {
        switch ($job['type']) {

            // -------------------------------------------------------
            // Send campaign emails
            // -------------------------------------------------------
            case 'send_campaign_email':
                $recipientId = $payload['recipient_id'] ?? null;
                if (!$recipientId) throw new \Exception("Missing recipient_id");

                $recipient = DB::fetch("SELECT cr.*, c.subject, c.body, c.from_name, c.from_email FROM ff_campaign_recipients cr JOIN ff_campaigns c ON c.id = cr.campaign_id WHERE cr.id = ?", [$recipientId]);
                if (!$recipient) throw new \Exception("Recipient not found");

                // Build email body (replace placeholders)
                $body = str_replace(
                    ['{{name}}', '{{email}}', '{{unsubscribe_url}}'],
                    [$recipient['name'] ?? 'there', $recipient['email'] ?? '', APP_URL . '/unsubscribe?token=' . ($recipient['token'] ?? '')],
                    $recipient['body']
                );

                $sent = sendEmail($recipient['email'], $recipient['subject'], $body, $recipient['from_name'] ?? APP_NAME, $recipient['from_email'] ?? MAIL_FROM);

                if ($sent) {
                    DB::update('ff_campaign_recipients', ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s')], 'id = ?', [$recipientId]);
                    DB::query("UPDATE ff_campaigns SET sent_count = sent_count + 1 WHERE id = ?", [$recipient['campaign_id']]);
                    $logger("  → Email sent to {$recipient['email']}");
                } else {
                    throw new \Exception("Mail delivery failed");
                }
                break;

            // -------------------------------------------------------
            // Trigger automation action
            // -------------------------------------------------------
            case 'trigger_automation':
                $automationId = $payload['automation_id'] ?? null;
                $feedbackId   = $payload['feedback_id'] ?? null;
                if (!$automationId) throw new \Exception("Missing automation_id");

                $automation = DB::fetch("SELECT * FROM ff_automations WHERE id = ? AND is_active = 1", [$automationId]);
                if (!$automation) { $logger("  → Automation not found or inactive"); break; }

                $actionConfig = json_decode($automation['action_config'] ?? '{}', true) ?? [];

                match($automation['action_type']) {
                    'send_email' => (function() use ($actionConfig, $feedbackId) {
                        $to = $actionConfig['to'] ?? '';
                        if ($to) sendEmail($to, $actionConfig['subject'] ?? 'Automation Alert', $actionConfig['body'] ?? 'A feedback event was triggered.');
                    })(),
                    'send_webhook' => (function() use ($actionConfig, $feedbackId) {
                        $url = $actionConfig['url'] ?? $actionConfig['to'] ?? '';
                        if ($url) {
                            $ch = curl_init($url);
                            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode(['feedback_id'=>$feedbackId]), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>10]);
                            curl_exec($ch); curl_close($ch);
                        }
                    })(),
                    default => null,
                };

                DB::update('ff_automations', ['run_count' => (int)$automation['run_count'] + 1, 'last_run_at' => date('Y-m-d H:i:s')], 'id = ?', [$automationId]);
                $logger("  → Automation #{$automationId} executed ({$automation['action_type']})");
                break;

            // -------------------------------------------------------
            // Generate AI report
            // -------------------------------------------------------
            case 'generate_ai_report':
                $projectId = $payload['project_id'] ?? null;
                if (!$projectId) throw new \Exception("Missing project_id");

                $feedback = DB::fetchAll("SELECT title, description, ai_sentiment, ai_intent FROM ff_feedback WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 100", [$projectId]);
                $count = count($feedback);
                $logger("  → Generating report for project #$projectId ($count feedback items)");

                $positive = count(array_filter($feedback, fn($f) => $f['ai_sentiment'] === 'positive'));
                $negative = count(array_filter($feedback, fn($f) => $f['ai_sentiment'] === 'negative'));

                $content = "# Weekly Feedback Report\n\n";
                $content .= "**Total feedback:** $count\n";
                $content .= "**Positive sentiment:** $positive (" . ($count > 0 ? round($positive/$count*100) : 0) . "%)\n";
                $content .= "**Negative sentiment:** $negative (" . ($count > 0 ? round($negative/$count*100) : 0) . "%)\n\n";

                DB::query("INSERT INTO ff_ai_reports (project_id, report_date, content) VALUES (?,?,?) ON DUPLICATE KEY UPDATE content = VALUES(content)", [$projectId, date('Y-m-d'), $content]);
                $logger("  → Report generated");
                break;

            // -------------------------------------------------------
            // Process export request
            // -------------------------------------------------------
            case 'process_export':
                $exportId = $payload['export_id'] ?? null;
                if (!$exportId) throw new \Exception("Missing export_id");

                DB::update('ff_export_requests', ['status' => 'processing'], 'id = ?', [$exportId]);
                $export = DB::fetch("SELECT * FROM ff_export_requests WHERE id = ?", [$exportId]);
                if (!$export) throw new \Exception("Export not found");

                // Simulate export processing
                $filename = 'exports/export-' . $exportId . '-' . time() . '.csv';
                DB::update('ff_export_requests', ['status' => 'ready', 'file_path' => $filename], 'id = ?', [$exportId]);
                $logger("  → Export #{$exportId} completed: $filename");
                break;

            // -------------------------------------------------------
            // Send review booster request
            // -------------------------------------------------------
            case 'send_review_booster':
                $boosterId   = $payload['booster_id'] ?? null;
                $email       = $payload['email'] ?? null;
                $name        = $payload['name'] ?? 'there';
                if (!$boosterId || !$email) throw new \Exception("Missing booster_id or email");

                $booster = DB::fetch("SELECT * FROM ff_review_boosters WHERE id = ? AND is_active = 1", [$boosterId]);
                if (!$booster) throw new \Exception("Booster not found");

                $message = $booster['message_template'] ?: "Hi $name! We'd love your feedback on {$booster['platform']}. Please leave us a review:";
                $body = $message . "\n\n" . $booster['review_url'];

                sendEmail($email, "Would you leave us a review?", $body);
                DB::query("UPDATE ff_review_boosters SET requests_sent = requests_sent + 1 WHERE id = ?", [$boosterId]);
                $logger("  → Review request sent to $email");
                break;

            default:
                throw new \Exception("Unknown job type: {$job['type']}");
        }

        // Mark done
        DB::update('ff_jobs', ['status' => 'done', 'finished_at' => date('Y-m-d H:i:s'), 'error' => null], 'id = ?', [$job['id']]);
        $logger("  ✓ Job #{$job['id']} completed");

    } catch (\Throwable $e) {
        $error = $e->getMessage();
        $logger("  ✗ Job #{$job['id']} failed: $error");

        $status = ($job['attempts'] + 1) >= $job['max_attempts'] ? 'failed' : 'pending';
        $retryAt = date('Y-m-d H:i:s', time() + (60 * pow(2, $job['attempts']))); // Exponential backoff

        DB::update('ff_jobs', [
            'status'       => $status,
            'error'        => $error,
            'finished_at'  => $status === 'failed' ? date('Y-m-d H:i:s') : null,
            'available_at' => $status === 'pending' ? $retryAt : date('Y-m-d H:i:s'),
        ], 'id = ?', [$job['id']]);
    }
}

$logger("Worker finished. Processed " . count($jobs) . " jobs.");
exit(0);

// ---------------------------------------------------------------------------
// Helper: send email via PHP mail or SMTP (basic)
// ---------------------------------------------------------------------------
function sendEmail(string $to, string $subject, string $body, string $fromName = '', string $fromEmail = ''): bool {
    if (!$fromEmail) $fromEmail = MAIL_FROM;
    if (!$fromName)  $fromName  = APP_NAME;

    if (SMTP_HOST && function_exists('mail')) {
        // For production, wire in PHPMailer or SwiftMailer here
    }

    $headers = "From: $fromName <$fromEmail>\r\n";
    $headers .= "Reply-To: $fromEmail\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: FeedbackFlow/1.0\r\n";

    return @mail($to, $subject, $body, $headers);
}
