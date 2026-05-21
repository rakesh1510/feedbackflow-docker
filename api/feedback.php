<?php
// Public Feedback API (JSON)
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
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// Check API key (widget key used as auth)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['key'] ?? '';
$project = $apiKey ? DB::fetch("SELECT * FROM ff_projects WHERE widget_key = ?", [$apiKey]) : null;
if (!$project) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
$projectId = $project['id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    if ($action === 'list') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $status = $_GET['status'] ?? '';
        $where = ['project_id = ?', 'is_public = 1'];
        $params = [$projectId];
        if ($status) { $where[] = 'status = ?'; $params[] = $status; }
        $whereStr = implode(' AND ', $where);
        $total = DB::count("SELECT COUNT(*) FROM ff_feedback WHERE $whereStr", $params);
        $feedbacks = DB::fetchAll("SELECT id, title, description, status, priority, vote_count, comment_count, created_at FROM ff_feedback WHERE $whereStr ORDER BY vote_count DESC LIMIT $limit OFFSET " . (($page-1)*$limit), $params);
        echo json_encode(['data' => $feedbacks, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    } elseif ($action === 'single') {
        $id = (int)($_GET['id'] ?? 0);
        $fb = DB::fetch("SELECT f.*, c.name as category FROM ff_feedback f LEFT JOIN ff_categories c ON c.id = f.category_id WHERE f.id = ? AND f.project_id = ? AND f.is_public = 1", [$id, $projectId]);
        echo json_encode($fb ?: ['error' => 'Not found']);
    }
} elseif ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $title = sanitize($body['title'] ?? '');
    $desc  = sanitize($body['description'] ?? '');
    $email = trim($body['email'] ?? '');
    if (strlen($title) < 3) { http_response_code(422); echo json_encode(['error' => 'Title too short']); exit; }
    if (!rateLimit('api_submit_' . $projectId, 10, 3600)) { http_response_code(429); echo json_encode(['error' => 'Rate limit exceeded']); exit; }
    $id = DB::insert('ff_feedback', ['project_id' => $projectId, 'title' => $title, 'description' => $desc, 'submitter_email' => $email ?: null, 'submitter_name' => $email ? explode('@',$email)[0] : 'API', 'status' => 'new', 'priority' => 'medium', 'is_public' => 1]);
    echo json_encode(['ok' => true, 'id' => $id]);
}