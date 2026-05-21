<?php
// Public vote API endpoint
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/db-manager.php';
require_once dirname(__DIR__) . '/includes/functions.php';


$feedbackId = (int)($_POST['feedback_id'] ?? $_GET['feedback_id'] ?? 0);
if ($feedbackId > 0) {
    $resolvedCompanyId = DBManager::findCompanyIdByFeedbackId($feedbackId);
    if ($resolvedCompanyId) {
        DB::useTenantForCompany($resolvedCompanyId);
    }
}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$feedbackId = (int)($_POST['feedback_id'] ?? $_GET['feedback_id'] ?? 0);
$voterEmail = trim($_POST['email'] ?? '');
$emoji = $_POST['emoji'] ?? '👍';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!$feedbackId) { echo json_encode(['ok' => false, 'error' => 'Missing feedback_id']); exit; }

$fb = DB::fetch("SELECT f.*, p.is_public FROM ff_feedback f JOIN ff_projects p ON p.id = f.project_id WHERE f.id = ?", [$feedbackId]);
if (!$fb || !$fb['is_public']) { echo json_encode(['ok' => false, 'error' => 'Not found']); exit; }

if (!rateLimit('vote_' . $feedbackId . '_' . $ip, 3, 3600)) {
    echo json_encode(['ok' => false, 'error' => 'Already voted']); exit;
}

$exists = DB::fetch("SELECT id FROM ff_votes WHERE feedback_id = ? AND voter_ip = ?", [$feedbackId, $ip]);
if ($exists) { echo json_encode(['ok' => false, 'error' => 'Already voted', 'votes' => $fb['vote_count']]); exit; }

DB::insert('ff_votes', ['feedback_id' => $feedbackId, 'voter_ip' => $ip, 'voter_email' => $voterEmail ?: null, 'emoji' => $emoji]);
DB::query("UPDATE ff_feedback SET vote_count = vote_count + 1 WHERE id = ?", [$feedbackId]);

$newCount = DB::count("SELECT vote_count FROM ff_feedback WHERE id = ?", [$feedbackId]);
echo json_encode(['ok' => true, 'votes' => $newCount]);