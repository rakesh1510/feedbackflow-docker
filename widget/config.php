<?php
// Widget config endpoint - returns project widget settings
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/db-manager.php';


$key = $_POST['key'] ?? $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($key) {
    $resolvedCompanyId = DBManager::findCompanyIdByWidgetKey($key);
    if ($resolvedCompanyId) {
        DB::useTenantForCompany($resolvedCompanyId);
    }
}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=3600');

$key = $_GET['key'] ?? '';
if (empty($key)) { echo json_encode(['error' => 'No key']); exit; }

$project = DB::fetch("SELECT widget_color, widget_position, widget_theme, widget_title, widget_placeholder FROM ff_projects WHERE widget_key = ? AND is_public = 1", [$key]);
if (!$project) { echo json_encode(['error' => 'Invalid key']); exit; }

echo json_encode($project);