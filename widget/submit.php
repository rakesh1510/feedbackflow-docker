<?php
// Widget submission endpoint
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/db-manager.php';
require_once dirname(__DIR__) . '/includes/functions.php';


$key = $_POST['key'] ?? $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($key) {
    $resolvedCompanyId = DBManager::findCompanyIdByWidgetKey($key);
    if ($resolvedCompanyId) {
        DB::useTenantForCompany($resolvedCompanyId);
    }
}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'Method not allowed']); exit; }

$key = $_POST['key'] ?? '';
if (empty($key)) { echo json_encode(['ok' => false, 'error' => 'No key']); exit; }

$project = DB::fetch("SELECT * FROM ff_projects WHERE widget_key = ?", [$key]);
if (!$project) { echo json_encode(['ok' => false, 'error' => 'Invalid key']); exit; }

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!rateLimit('widget_submit_' . $project['id'], 5, 600)) {
    echo json_encode(['ok' => false, 'error' => 'Too many submissions. Please wait.']);
    exit;
}

$title = sanitize($_POST['title'] ?? '');
$desc = sanitize($_POST['description'] ?? '');
$email = trim($_POST['email'] ?? '');
$type = $_POST['type'] ?? 'feedback';
$emoji = $_POST['emoji'] ?? '👍';
$pageUrl = filter_var($_POST['page_url'] ?? '', FILTER_VALIDATE_URL) ? $_POST['page_url'] : null;
$browser = sanitize($_POST['browser'] ?? '');

if (strlen($title) < 3) { echo json_encode(['ok' => false, 'error' => 'Title too short']); exit; }
if ($email && !isValidEmail($email)) { echo json_encode(['ok' => false, 'error' => 'Invalid email']); exit; }

// Map type to category
$catSlugMap = ['bug' => 'bug-report', 'idea' => 'feature-request', 'feedback' => 'improvement'];
$catSlug = $catSlugMap[$type] ?? null;
$cat = $catSlug ? DB::fetch("SELECT id FROM ff_categories WHERE project_id = ? AND slug = ?", [$project['id'], $catSlug]) : null;

$feedbackId = DB::insert('ff_feedback', [
    'project_id'     => $project['id'],
    'category_id'    => $cat['id'] ?? null,
    'title'          => $title,
    'description'    => $desc,
    'status'         => 'new',
    'priority'       => 'medium',
    'submitter_email'=> $email ?: null,
    'submitter_name' => $email ? explode('@', $email)[0] : 'Anonymous',
    'is_public'      => $project['allow_anonymous'] ? 1 : 1,
    'page_url'       => $pageUrl,
    'browser_info'   => $browser,
]);

// Fire webhooks & notifications
triggerWebhooks($project['id'], 'feedback.created', ['id' => $feedbackId, 'title' => $title, 'type' => $type]);

// Slack notification
$slackWebhook = DB::fetch("SELECT value FROM ff_settings WHERE project_id = ? AND `key` = 'slack_webhook'", [$project['id']]);
if ($slackWebhook && $slackWebhook['value']) {
    $payload = json_encode(['text' => "📬 New *{$type}* from " . ($email ?: 'Anonymous') . ":\n*{$title}*\n" . ($desc ? "_" . mb_strimwidth($desc, 0, 200, '...') . "_" : '')]);
    $ch = curl_init($slackWebhook['value']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 5]);
    curl_exec($ch);
    curl_close($ch);
}

// Optional: AI analysis (async-ish by running after response)
echo json_encode(['ok' => true, 'id' => $feedbackId]);

// Run AI in background if enabled
if (AI_ENABLED) {
    $fb = DB::fetch("SELECT * FROM ff_feedback WHERE id = ?", [$feedbackId]);
    $ai = aiAnalyzeFeedback($fb);
    if ($ai) {
        DB::update('ff_feedback', [
            'ai_sentiment'       => $ai['sentiment'] ?? null,
            'ai_sentiment_score' => $ai['sentiment_score'] ?? null,
            'ai_summary'         => $ai['summary'] ?? null,
            'ai_priority_score'  => $ai['priority_score'] ?? null,
        ], 'id = ?', [$feedbackId]);
    }
}