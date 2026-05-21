
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

DB::query("USE `abdddd_company_1779095233467_256148_feedbackflow`");

$email = 'playwright_1779095233467_256148@example.com';

$user = DB::fetch("SELECT * FROM ff_users WHERE email = ?", [$email]);
if (!$user) die("User not found in tenant DB\n");

$userId = (int)$user['id'];
echo "User ID: $userId\n";

function tableColumns($table) {
    $rows = DB::fetchAll("SHOW COLUMNS FROM `$table`");
    return array_column($rows, 'Field');
}

function filterDataByColumns($data, $columns) {
    return array_intersect_key($data, array_flip($columns));
}

$projectColumns = tableColumns('ff_projects');
$feedbackColumns = tableColumns('ff_feedback');

// Create projects up to 5
$projects = DB::fetchAll("SELECT id FROM ff_projects WHERE owner_id = ? ORDER BY id ASC", [$userId]);
$needProjects = max(0, 5 - count($projects));

for ($i = 1; $i <= $needProjects; $i++) {
    $name = "Limit Seed Project " . time() . " " . $i;
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));

    $projectData = [
        'name' => $name,
        'slug' => $slug,
        'description' => 'Created automatically for billing limit test',
        'website' => 'https://trustnovatech.de',
        'owner_id' => $userId,
        'widget_key' => bin2hex(random_bytes(16)),
        'is_public' => 1,
        'allow_anonymous' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'status' => 'active'
    ];

    DB::insert('ff_projects', filterDataByColumns($projectData, $projectColumns));
    echo "Created project: $name\n";
}

$projects = DB::fetchAll("SELECT id FROM ff_projects WHERE owner_id = ? ORDER BY id ASC", [$userId]);
$projectIds = array_column($projects, 'id');

if (empty($projectIds)) {
    die("No projects found. Cannot create feedback.\n");
}

$currentFeedback = DB::count("
    SELECT COUNT(*)
    FROM ff_feedback f
    JOIN ff_projects p ON p.id = f.project_id
    WHERE p.owner_id = ?
", [$userId]);

$needFeedback = max(0, 2000 - $currentFeedback);

echo "Current projects: " . count($projectIds) . "\n";
echo "Current feedback: $currentFeedback\n";
echo "Need feedback: $needFeedback\n";

for ($i = 1; $i <= $needFeedback; $i++) {
    $projectId = $projectIds[$i % count($projectIds)];

    $feedbackData = [
        'project_id' => $projectId,
        'title' => 'Auto limit feedback ' . $i,
        'description' => 'Automatic feedback created for billing usage limit test',
        'message' => 'Automatic feedback created for billing usage limit test',
        'feedback' => 'Automatic feedback created for billing usage limit test',
        'content' => 'Automatic feedback created for billing usage limit test',
        'status' => 'new',
        'priority' => 'medium',
        'name' => 'Auto User ' . $i,
        'email' => 'auto' . $i . '@test.com',
        'customer_name' => 'Auto User ' . $i,
        'customer_email' => 'auto' . $i . '@test.com',
        'source' => 'seed-test',
        'votes' => 0,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    DB::insert('ff_feedback', filterDataByColumns($feedbackData, $feedbackColumns));

    if ($i % 100 === 0) {
        echo "Inserted feedback: $i\n";
    }
}

echo "DONE. Now open admin/billing.php\n";
