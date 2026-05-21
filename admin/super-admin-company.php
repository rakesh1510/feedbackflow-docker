<?php
/**
 * Super Admin — Company Detail Page
 *
 * Reads company info from the MASTER database.
 * Dynamically switches to the TENANT database to show:
 *  - Users, Projects, Feedback counts, Usage stats
 * Supports: activate/deactivate, impersonate admin, export data.
 * All actions are logged to ff_super_admin_log.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/db-manager.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/billing.php';

$currentUser = Auth::require();
if (empty($currentUser['is_super_admin'])) {
    flash('error', 'Super admin access required.');
    redirect(APP_URL . '/admin/index.php');
}

$companyId = (int)($_GET['id'] ?? 0);
if (!$companyId) {
    redirect(APP_URL . '/admin/super-admin.php?tab=companies');
}

// ── Load company from MASTER DB ──────────────────────────────────────────
$company = DB::fetch("SELECT * FROM ff_companies WHERE id = ?", [$companyId]);
if (!$company) {
    flash('error', "Company #$companyId not found.");
    redirect(APP_URL . '/admin/super-admin.php?tab=companies');
}

// ── Tenant DB status ─────────────────────────────────────────────────────
$tenantDbInfo = DB::fetch(
    "SELECT * FROM ff_company_databases WHERE company_id = ?", [$companyId]
);
$hasTenantDb = $tenantDbInfo && $tenantDbInfo['db_status'] === 'active';

// ── POST actions ─────────────────────────────────────────────────────────
$messages = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_company') {
        $newActive = $company['is_active'] ? 0 : 1;
        try {
            DB::update('ff_companies', ['is_active' => $newActive], 'id = ?', [$companyId]);
            DBManager::logSuperAdminAction($currentUser['id'], 'toggle_company', $companyId, null, [
                'is_active' => $newActive,
            ]);
            $label = $newActive ? 'activated' : 'deactivated';
            $messages[] = ['type' => 'success', 'msg' => "Company has been $label."];
            $company['is_active'] = $newActive;
        } catch (\Throwable $e) {
            $messages[] = ['type' => 'error', 'msg' => 'Failed to update company status: ' . $e->getMessage()];
        }
    }

    if ($action === 'impersonate') {
        $targetUserId = (int)($_POST['uid'] ?? 0);
        if ($targetUserId) {
            $targetUser = DB::fetch("SELECT * FROM ff_users WHERE id = ? AND company_id = ?", [$targetUserId, $companyId]);
            if ($targetUser) {
                Auth::start();
                $_SESSION['impersonate_original']         = $currentUser['id'];
                $_SESSION['impersonate_original_company'] = null;
                $_SESSION['user_id']   = $targetUser['id'];
                $_SESSION['user_role'] = $targetUser['role'];
                DBManager::logSuperAdminAction($currentUser['id'], 'impersonate', $companyId, $targetUserId, [
                    'user_name'  => $targetUser['name'],
                    'user_email' => $targetUser['email'],
                ]);
                redirect(APP_URL . '/admin/index.php');
            }
        }
        $messages[] = ['type' => 'error', 'msg' => 'User not found.'];
    }

    if ($action === 'export_data') {
        require_once dirname(__DIR__) . '/includes/tenant-provisioner.php';
        DBManager::logSuperAdminAction($currentUser['id'], 'export_data', $companyId, null);

        if ($hasTenantDb) {
            $data = TenantProvisioner::exportData($companyId);
        } else {
            // Fall back to master DB export
            $data = [
                'company_id'  => $companyId,
                'exported_at' => date('c'),
                'source'      => 'master_db',
                'tables'      => [
                    'ff_users'    => DB::fetchAll("SELECT id,name,email,role,is_active,created_at FROM ff_users WHERE company_id = ?", [$companyId]),
                    'ff_projects' => DB::fetchAll("SELECT id,name,slug,description,created_at FROM ff_projects WHERE company_id = ?", [$companyId]),
                    'ff_feedback' => DB::fetchAll("SELECT id,project_id,type,content,status,created_at FROM ff_feedback WHERE project_id IN (SELECT id FROM ff_projects WHERE company_id = ?) LIMIT 1000", [$companyId]),
                ],
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="company-' . $companyId . '-export-' . date('Ymd-His') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'provision_now') {
        require_once dirname(__DIR__) . '/includes/tenant-provisioner.php';
        $result = TenantProvisioner::provision(
            $companyId,
            $company['name'],
            $company['slug'],
            0,  // no owner to seed
            []
        );
        DBManager::logSuperAdminAction($currentUser['id'], 'provision_db', $companyId, null, $result);
        if ($result['status'] === 'success') {
            $messages[] = ['type' => 'success', 'msg' => "Tenant database provisioned: {$result['db_name']}"];
            $tenantDbInfo = DB::fetch("SELECT * FROM ff_company_databases WHERE company_id = ?", [$companyId]);
            $hasTenantDb  = $tenantDbInfo && $tenantDbInfo['db_status'] === 'active';
        } elseif ($result['status'] === 'skipped') {
            $messages[] = ['type' => 'warning', 'msg' => 'Already provisioned.'];
        } else {
            $messages[] = ['type' => 'error', 'msg' => "Provisioning failed: {$result['error']}"];
        }
    }

    if ($action === 'run_migration') {
        $sql = trim($_POST['migration_sql'] ?? '');
        if ($hasTenantDb && $sql) {
            require_once dirname(__DIR__) . '/includes/tenant-provisioner.php';
            [$ok, $err] = TenantProvisioner::runMigration($companyId, $sql);
            DBManager::logSuperAdminAction($currentUser['id'], 'run_migration', $companyId, null, ['sql_length' => strlen($sql)]);
            $messages[] = $ok
                ? ['type' => 'success', 'msg' => 'Migration applied successfully.']
                : ['type' => 'error',   'msg' => "Migration failed: $err"];
        }
    }
}

// ── Load data from MASTER DB ──────────────────────────────────────────────
$masterUsers    = DB::fetchAll("SELECT * FROM ff_users WHERE company_id = ? ORDER BY created_at DESC", [$companyId]);
$masterProjects = DB::fetchAll("SELECT * FROM ff_projects WHERE company_id = ? ORDER BY created_at DESC", [$companyId]);
$provLogs       = DBManager::getProvisioningLogs($companyId, 20);
$superAdminLogs = [];
try {
    $stmt = DBManager::master()->prepare(
        "SELECT l.*, u.name AS admin_name FROM ff_super_admin_log l
         LEFT JOIN ff_users u ON u.id = l.admin_id
         WHERE l.target_company_id = ? ORDER BY l.created_at DESC LIMIT 30"
    );
    $stmt->execute([$companyId]);
    $superAdminLogs = $stmt->fetchAll();
} catch (\Throwable $e) { }

// ── Load data from TENANT DB (if provisioned) ─────────────────────────────
$tenantUsers    = [];
$tenantProjects = [];
$tenantFeedback = 0;
$tenantUsage    = null;
$tenantError    = null;

if ($hasTenantDb) {
    try {
        DBManager::logSuperAdminAction($currentUser['id'], 'view_company', $companyId, null);
        $tenantUsers    = DBManager::tenantFetchAll($companyId, "SELECT id, name, email, role, status, is_active, created_at FROM ff_users ORDER BY created_at DESC LIMIT 50");
        $tenantProjects = DBManager::tenantFetchAll($companyId, "SELECT id, name, slug, is_public, created_at FROM ff_projects ORDER BY created_at DESC LIMIT 20");
        $tenantFeedback = DBManager::tenantCount($companyId, "SELECT COUNT(*) FROM ff_feedback");
        $tenantUsage    = DBManager::tenantFetch($companyId,
            "SELECT * FROM ff_billing_usage WHERE year_month = ? LIMIT 1", [date('Y-m')]
        );
    } catch (\Throwable $e) {
        $tenantError = $e->getMessage();
    }
}

$plan    = DB::fetch("SELECT * FROM ff_billing_plans WHERE slug = ?", [$company['plan'] ?? 'free']);
$planLabel = $plan ? $plan['name'] : ucfirst($company['plan'] ?? 'Free');

$pageTitle = 'Company Detail – ' . h($company['name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?> – <?= APP_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { font-family: 'Inter', sans-serif; }
  .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; }
  .badge { display:inline-flex; align-items:center; gap:4px; padding:2px 10px; border-radius:9999px; font-size:11px; font-weight:600; }
  .tab-active { border-bottom: 2px solid #6366f1; color: #6366f1; }
  .tab-inactive { border-bottom: 2px solid transparent; color: #6b7280; }
  .db-badge-active { background:#d1fae5; color:#065f46; }
  .db-badge-pending { background:#fef3c7; color:#92400e; }
  .db-badge-failed  { background:#fee2e2; color:#991b1b; }
</style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Back nav -->
<nav class="bg-white border-b border-gray-200 sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-6 h-14 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <a href="<?= APP_URL ?>/admin/super-admin.php?tab=companies"
         class="text-gray-400 hover:text-gray-700 text-sm flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> Companies
      </a>
      <span class="text-gray-300">/</span>
      <span class="font-semibold text-gray-900 text-sm"><?= h($company['name']) ?></span>
    </div>
    <div class="flex items-center gap-2">
      <span class="text-xs text-gray-400">Logged in as <strong><?= h($currentUser['name']) ?></strong> (Super Admin)</span>
    </div>
  </div>
</nav>

<div class="max-w-7xl mx-auto px-6 py-8 space-y-6">

  <!-- Flash messages -->
  <?php foreach ($messages as $msg): ?>
    <div class="px-4 py-3 rounded-xl text-sm font-medium
      <?= $msg['type'] === 'success' ? 'bg-green-50 border border-green-200 text-green-800' :
         ($msg['type'] === 'warning' ? 'bg-yellow-50 border border-yellow-200 text-yellow-800' :
                                       'bg-red-50 border border-red-200 text-red-800') ?>">
      <i class="fas fa-<?= $msg['type'] === 'success' ? 'check-circle' : ($msg['type'] === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?> mr-2"></i>
      <?= h($msg['msg']) ?>
    </div>
  <?php endforeach; ?>

  <!-- Header -->
  <div class="card p-6 flex flex-col sm:flex-row sm:items-center gap-5">
    <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-white text-2xl font-black flex-shrink-0"
         style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
      <?= strtoupper(substr($company['name'], 0, 1)) ?>
    </div>
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-3 flex-wrap mb-1">
        <h1 class="text-xl font-bold text-gray-900"><?= h($company['name']) ?></h1>
        <span class="badge bg-indigo-100 text-indigo-700"><?= $planLabel ?></span>
        <span class="badge <?= $company['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
          <?= $company['is_active'] ? 'Active' : 'Suspended' ?>
        </span>
        <?php if ($hasTenantDb): ?>
          <span class="badge db-badge-active"><i class="fas fa-database"></i> Tenant DB Active</span>
        <?php elseif ($tenantDbInfo && $tenantDbInfo['db_status'] === 'failed'): ?>
          <span class="badge db-badge-failed"><i class="fas fa-exclamation-triangle"></i> Provision Failed</span>
        <?php else: ?>
          <span class="badge db-badge-pending"><i class="fas fa-clock"></i> No Tenant DB</span>
        <?php endif; ?>
      </div>
      <p class="text-sm text-gray-500 truncate">
        <span class="font-mono"><?= h($company['slug']) ?></span>
        &nbsp;·&nbsp; <?= h($company['email'] ?? '') ?>
        &nbsp;·&nbsp; ID #<?= $companyId ?>
      </p>
      <?php if ($hasTenantDb): ?>
        <p class="text-xs text-gray-400 mt-1">
          <i class="fas fa-server mr-1"></i>
          Tenant DB: <code class="font-mono text-indigo-600"><?= h($tenantDbInfo['db_name']) ?></code>
          · <?= h($tenantDbInfo['db_host']) ?>:<?= $tenantDbInfo['db_port'] ?>
        </p>
      <?php endif; ?>
    </div>

    <!-- Action buttons -->
    <div class="flex flex-wrap gap-2">
      <!-- Toggle activate/deactivate -->
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="toggle_company">
        <button class="px-4 py-2 text-sm font-semibold rounded-xl border-2 transition
          <?= $company['is_active'] ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-green-200 text-green-700 hover:bg-green-50' ?>">
          <i class="fas fa-<?= $company['is_active'] ? 'ban' : 'check-circle' ?> mr-1"></i>
          <?= $company['is_active'] ? 'Suspend' : 'Activate' ?>
        </button>
      </form>

      <!-- Export data -->
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="export_data">
        <button class="px-4 py-2 text-sm font-semibold rounded-xl border-2 border-blue-200 text-blue-600 hover:bg-blue-50 transition">
          <i class="fas fa-download mr-1"></i> Export JSON
        </button>
      </form>

      <!-- Provision DB (if not yet done) -->
      <?php if (!$hasTenantDb): ?>
      <form method="POST" onsubmit="return confirm('Provision a new tenant database for this company?');">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="provision_now">
        <button class="px-4 py-2 text-sm font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition">
          <i class="fas fa-database mr-1"></i> Provision DB
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats row -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <?php foreach ([
      ['Users (master)',    count($masterUsers),    'users',    'indigo'],
      ['Projects (master)', count($masterProjects),  'folder',   'blue'],
      ['Feedback (tenant)', $hasTenantDb ? $tenantFeedback : '–', 'comments', 'purple'],
      ['Plan',              $planLabel,              'star',     'yellow'],
    ] as [$label, $val, $icon, $color]): ?>
    <div class="card p-4">
      <div class="w-9 h-9 rounded-xl bg-<?= $color ?>-100 flex items-center justify-center mb-3">
        <i class="fas fa-<?= $icon ?> text-<?= $color ?>-600 text-sm"></i>
      </div>
      <p class="text-xl font-bold text-gray-900"><?= is_int($val) ? number_format($val) : $val ?></p>
      <p class="text-xs text-gray-500"><?= $label ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Tabs -->
  <?php
  $tab = $_GET['tab'] ?? 'tenant';
  $tabs = [
    'tenant'    => ['label' => 'Tenant DB', 'icon' => 'fa-database'],
    'users'     => ['label' => 'Users',     'icon' => 'fa-users'],
    'projects'  => ['label' => 'Projects',  'icon' => 'fa-folder'],
    'provision' => ['label' => 'Provision', 'icon' => 'fa-server'],
    'logs'      => ['label' => 'Audit Logs','icon' => 'fa-history'],
  ];
  ?>
  <div class="border-b border-gray-200 bg-white rounded-t-xl px-4">
    <div class="flex gap-1 overflow-x-auto">
      <?php foreach ($tabs as $k => $t): ?>
        <a href="?id=<?= $companyId ?>&tab=<?= $k ?>"
           class="px-4 py-3 text-sm font-medium whitespace-nowrap transition
             <?= $tab === $k ? 'tab-active' : 'tab-inactive hover:text-gray-700' ?>">
          <i class="fas <?= $t['icon'] ?> mr-1 text-xs"></i> <?= $t['label'] ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card p-6">

    <!-- ── TENANT DB TAB ──────────────────────────────────────────────── -->
    <?php if ($tab === 'tenant'): ?>
      <?php if (!$hasTenantDb): ?>
        <div class="text-center py-10">
          <div class="text-5xl mb-4">🗄️</div>
          <h3 class="font-bold text-gray-900 mb-2">No Tenant Database Yet</h3>
          <p class="text-gray-500 text-sm mb-6">
            This company doesn't have a dedicated tenant database provisioned.
            <?php if ($tenantDbInfo && $tenantDbInfo['db_status'] === 'failed'): ?>
              <br><span class="text-red-600">Last attempt failed: <?= h($tenantDbInfo['error_msg'] ?? 'Unknown error') ?></span>
            <?php endif; ?>
          </p>
          <form method="POST" onsubmit="return confirm('Provision tenant database now?');">
            <input type="hidden" name="_csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="provision_now">
            <button class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl transition">
              <i class="fas fa-database mr-2"></i> Provision Tenant Database Now
            </button>
          </form>
        </div>
      <?php else: ?>
        <?php if ($tenantError): ?>
          <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-6 text-sm">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Failed to connect to tenant DB: <code><?= h($tenantError) ?></code>
          </div>
        <?php else: ?>
          <!-- Tenant DB info -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <?php foreach ([
              ['Database Name',  $tenantDbInfo['db_name']],
              ['Host:Port',      $tenantDbInfo['db_host'] . ':' . $tenantDbInfo['db_port']],
              ['Provisioned',    $tenantDbInfo['provisioned_at'] ? date('d M Y H:i', strtotime($tenantDbInfo['provisioned_at'])) : '–'],
            ] as [$k, $v]): ?>
            <div class="bg-gray-50 rounded-xl px-4 py-3">
              <p class="text-xs text-gray-400 mb-0.5"><?= $k ?></p>
              <p class="text-sm font-mono font-semibold text-gray-800 truncate"><?= h($v) ?></p>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Tenant users quick view -->
          <?php if ($tenantUsers): ?>
          <h3 class="font-bold text-gray-900 mb-3">Tenant DB — Users (<?= count($tenantUsers) ?>)</h3>
          <div class="overflow-x-auto rounded-xl border border-gray-100 mb-6">
            <table class="w-full text-sm">
              <thead><tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Name</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Email</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Role</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Status</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Action</th>
              </tr></thead>
              <tbody class="divide-y divide-gray-50">
                <?php foreach ($tenantUsers as $tu): ?>
                <tr class="hover:bg-gray-50">
                  <td class="px-4 py-3 font-medium text-gray-800"><?= h($tu['name']) ?></td>
                  <td class="px-4 py-3 text-gray-500 text-xs"><?= h($tu['email']) ?></td>
                  <td class="px-4 py-3">
                    <span class="badge bg-indigo-100 text-indigo-700 capitalize"><?= h($tu['role']) ?></span>
                  </td>
                  <td class="px-4 py-3">
                    <span class="badge <?= $tu['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                      <?= $tu['is_active'] ? 'Active' : 'Disabled' ?>
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <?php
                    // Find matching user in master DB to get the actual user_id for impersonation
                    $masterMatch = DB::fetch("SELECT id FROM ff_users WHERE email = ? AND company_id = ?", [$tu['email'], $companyId]);
                    if ($masterMatch && $masterMatch['id'] !== $currentUser['id']): ?>
                    <form method="POST" class="inline">
                      <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                      <input type="hidden" name="action" value="impersonate">
                      <input type="hidden" name="uid" value="<?= $masterMatch['id'] ?>">
                      <button class="px-3 py-1 text-xs border border-blue-200 text-blue-600 rounded-lg hover:bg-blue-50 transition"
                              title="Impersonate this user">
                        <i class="fas fa-user-secret"></i> Impersonate
                      </button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>

          <!-- Usage this month -->
          <?php if ($tenantUsage): ?>
          <h3 class="font-bold text-gray-900 mb-3">Usage — <?= date('F Y') ?></h3>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <?php foreach ([
              ['Feedback',   $tenantUsage['feedback_count']  ?? 0],
              ['Campaigns',  $tenantUsage['campaign_count']  ?? 0],
              ['Emails Sent',$tenantUsage['email_count']     ?? 0],
              ['AI Tokens',  $tenantUsage['ai_tokens_used']  ?? 0],
            ] as [$lbl, $val]): ?>
            <div class="bg-gray-50 rounded-xl px-4 py-3 text-center">
              <p class="text-xl font-bold text-gray-900"><?= number_format($val) ?></p>
              <p class="text-xs text-gray-500"><?= $lbl ?></p>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>

    <!-- ── USERS TAB (master DB) ──────────────────────────────────────── -->
    <?php elseif ($tab === 'users'): ?>
      <h3 class="font-bold text-gray-900 mb-4">Users from Master DB (<?= count($masterUsers) ?>)</h3>
      <?php if ($masterUsers): ?>
      <div class="overflow-x-auto rounded-xl border border-gray-100">
        <table class="w-full text-sm">
          <thead><tr class="bg-gray-50 border-b border-gray-100">
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">User</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Role</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Status</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Joined</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Action</th>
          </tr></thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($masterUsers as $u): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <p class="font-medium text-gray-800"><?= h($u['name']) ?></p>
                <p class="text-xs text-gray-400"><?= h($u['email']) ?></p>
              </td>
              <td class="px-4 py-3"><span class="badge bg-indigo-100 text-indigo-700 capitalize"><?= h($u['role']) ?></span></td>
              <td class="px-4 py-3">
                <span class="badge <?= $u['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                  <?= $u['is_active'] ? 'Active' : 'Suspended' ?>
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-gray-400"><?= timeAgo($u['created_at']) ?></td>
              <td class="px-4 py-3">
                <?php if ($u['id'] !== $currentUser['id']): ?>
                <form method="POST" class="inline">
                  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                  <input type="hidden" name="action" value="impersonate">
                  <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                  <button class="px-3 py-1 text-xs border border-blue-200 text-blue-600 rounded-lg hover:bg-blue-50 transition">
                    <i class="fas fa-user-secret"></i> Impersonate
                  </button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p class="text-gray-400 text-sm text-center py-6">No users linked to this company in master DB.</p>
      <?php endif; ?>

    <!-- ── PROJECTS TAB ───────────────────────────────────────────────── -->
    <?php elseif ($tab === 'projects'): ?>
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-gray-900">Projects</h3>
        <?php if ($hasTenantDb && !$tenantError): ?>
          <span class="text-xs text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">Source: Tenant DB</span>
        <?php else: ?>
          <span class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full">Source: Master DB</span>
        <?php endif; ?>
      </div>
      <?php $displayProjects = ($hasTenantDb && !$tenantError) ? $tenantProjects : $masterProjects; ?>
      <?php if ($displayProjects): ?>
      <div class="space-y-2">
        <?php foreach ($displayProjects as $p): ?>
        <div class="flex items-center gap-4 bg-gray-50 rounded-xl px-4 py-3">
          <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <i class="fas fa-folder text-indigo-600 text-sm"></i>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-800 text-sm"><?= h($p['name']) ?></p>
            <p class="text-xs text-gray-400 font-mono">/<?= h($p['slug']) ?></p>
          </div>
          <span class="badge <?= ($p['is_public'] ?? 1) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>">
            <?= ($p['is_public'] ?? 1) ? 'Public' : 'Private' ?>
          </span>
          <span class="text-xs text-gray-400"><?= timeAgo($p['created_at']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <p class="text-gray-400 text-sm text-center py-6">No projects found.</p>
      <?php endif; ?>

    <!-- ── PROVISION TAB ──────────────────────────────────────────────── -->
    <?php elseif ($tab === 'provision'): ?>
      <div class="space-y-6">
        <!-- Current DB status -->
        <div>
          <h3 class="font-bold text-gray-900 mb-3">Tenant Database Status</h3>
          <?php if ($tenantDbInfo): ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <?php foreach ([
                ['DB Name',      $tenantDbInfo['db_name'] ?: '–'],
                ['Host',         $tenantDbInfo['db_host'] . ':' . $tenantDbInfo['db_port']],
                ['DB User',      $tenantDbInfo['db_user']  ?: '–'],
                ['Status',       strtoupper($tenantDbInfo['db_status'])],
                ['Provisioned',  $tenantDbInfo['provisioned_at'] ?? '–'],
                ['Error',        $tenantDbInfo['error_msg']  ?: 'None'],
              ] as [$k,$v]): ?>
              <div class="bg-gray-50 rounded-xl px-4 py-3">
                <p class="text-xs text-gray-400 mb-0.5"><?= $k ?></p>
                <p class="text-xs font-mono text-gray-800 break-all"><?= h((string)$v) ?></p>
              </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-sm text-gray-500">No provisioning record found in master DB.</p>
          <?php endif; ?>
        </div>

        <!-- Run migration -->
        <?php if ($hasTenantDb): ?>
        <div>
          <h3 class="font-bold text-gray-900 mb-3">Run Tenant Migration</h3>
          <p class="text-sm text-gray-500 mb-3">Apply raw SQL to this company's tenant database. Use for schema updates, data fixes, or seeding.</p>
          <form method="POST" onsubmit="return confirm('Run this SQL on the tenant database?');">
            <input type="hidden" name="_csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="run_migration">
            <textarea name="migration_sql" rows="8" placeholder="-- Enter SQL statements here...&#10;ALTER TABLE ff_feedback ADD COLUMN example_col VARCHAR(100) DEFAULT NULL;"
                      class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition resize-y mb-3"></textarea>
            <button type="submit" class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl text-sm transition">
              <i class="fas fa-play mr-1"></i> Execute Migration
            </button>
          </form>
        </div>
        <?php endif; ?>
      </div>

    <!-- ── AUDIT LOGS TAB ─────────────────────────────────────────────── -->
    <?php elseif ($tab === 'logs'): ?>
      <div class="space-y-6">
        <!-- Provisioning logs -->
        <div>
          <h3 class="font-bold text-gray-900 mb-3">Provisioning Log</h3>
          <?php if ($provLogs): ?>
          <div class="overflow-x-auto rounded-xl border border-gray-100">
            <table class="w-full text-sm">
              <thead><tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Action</th>
                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Status</th>
                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Detail</th>
                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Time</th>
              </tr></thead>
              <tbody class="divide-y divide-gray-50">
                <?php foreach ($provLogs as $log): ?>
                <tr>
                  <td class="px-4 py-2.5 font-mono text-xs text-gray-700"><?= h($log['action']) ?></td>
                  <td class="px-4 py-2.5">
                    <span class="badge <?= match($log['status']) {
                      'success' => 'bg-green-100 text-green-700',
                      'failed'  => 'bg-red-100 text-red-700',
                      'warning' => 'bg-yellow-100 text-yellow-700',
                      'skipped' => 'bg-gray-100 text-gray-500',
                      default   => 'bg-blue-100 text-blue-700',
                    } ?>"><?= h($log['status']) ?></span>
                  </td>
                  <td class="px-4 py-2.5 text-xs text-gray-500 max-w-xs truncate"><?= h($log['detail'] ?? '') ?></td>
                  <td class="px-4 py-2.5 text-xs text-gray-400"><?= timeAgo($log['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
            <p class="text-sm text-gray-400">No provisioning logs yet.</p>
          <?php endif; ?>
        </div>

        <!-- Super admin action logs -->
        <div>
          <h3 class="font-bold text-gray-900 mb-3">Super Admin Actions</h3>
          <?php if ($superAdminLogs): ?>
          <div class="overflow-x-auto rounded-xl border border-gray-100">
            <table class="w-full text-sm">
              <thead><tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Admin</th>
                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Action</th>
                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Target User</th>
                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">IP</th>
                <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Time</th>
              </tr></thead>
              <tbody class="divide-y divide-gray-50">
                <?php foreach ($superAdminLogs as $log): ?>
                <tr>
                  <td class="px-4 py-2.5 text-xs font-medium text-gray-700"><?= h($log['admin_name'] ?? 'Admin #' . $log['admin_id']) ?></td>
                  <td class="px-4 py-2.5 font-mono text-xs text-indigo-600"><?= h($log['action']) ?></td>
                  <td class="px-4 py-2.5 text-xs text-gray-400"><?= $log['target_user_id'] ? '#' . $log['target_user_id'] : '–' ?></td>
                  <td class="px-4 py-2.5 text-xs text-gray-400 font-mono"><?= h($log['ip'] ?? '–') ?></td>
                  <td class="px-4 py-2.5 text-xs text-gray-400"><?= timeAgo($log['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
            <p class="text-sm text-gray-400">No super admin actions recorded yet.</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

  </div><!-- /card -->
</div><!-- /container -->
</body>
</html>
