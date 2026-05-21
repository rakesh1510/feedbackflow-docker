<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/db-manager.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::require();
// Only super admins
if (empty($currentUser['is_super_admin'])) {
    flash('error', 'Access denied. Super admin required.');
    redirect(APP_URL . '/admin/index.php');
}
$userProjects = getUserProjects($currentUser['id']);
$pageTitle = 'Super Admin – ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_user' && isset($_POST['uid'])) {
        $uid = (int)$_POST['uid'];
        $u = DB::fetch("SELECT is_active FROM ff_users WHERE id = ?", [$uid]);
        if ($u) {
            DB::update('ff_users', ['is_active' => $u['is_active'] ? 0 : 1], 'id = ?', [$uid]);
            DBManager::logSuperAdminAction($currentUser['id'], 'toggle_user', null, $uid, ['is_active' => $u['is_active'] ? 0 : 1]);
        }
    }
    if ($action === 'delete_user' && isset($_POST['uid'])) {
        DBManager::logSuperAdminAction($currentUser['id'], 'delete_user', null, (int)$_POST['uid']);
        DB::delete('ff_users', 'id = ? AND is_super_admin = 0', [(int)$_POST['uid']]);
        flash('success', 'User deleted.');
    }
    if ($action === 'impersonate' && isset($_POST['uid'])) {
        $impUser = DB::fetch("SELECT * FROM ff_users WHERE id = ?", [(int)$_POST['uid']]);
        if ($impUser) {
            DBManager::logSuperAdminAction($currentUser['id'], 'impersonate', $impUser['company_id'] ?? null, $impUser['id'], [
                'name' => $impUser['name'], 'email' => $impUser['email'],
            ]);
            Auth::start();
            $_SESSION['impersonate_original'] = $_SESSION['user_id'];
            $_SESSION['user_id'] = $impUser['id'];
        }
        redirect(APP_URL . '/admin/index.php');
    }
    redirect(APP_URL . '/admin/super-admin.php');
}

$tab = $_GET['tab'] ?? 'overview';

// Overview stats
$totalUsers     = DB::count("SELECT COUNT(*) FROM ff_users");
$totalProjects  = DB::count("SELECT COUNT(*) FROM ff_projects");
$totalFeedback  = DB::count("SELECT COUNT(*) FROM ff_feedback");
$totalCompanies = DB::count("SELECT COUNT(*) FROM ff_companies");
$activeUsers    = DB::count("SELECT COUNT(*) FROM ff_users WHERE is_active = 1");
$planCounts     = DB::fetchAll("SELECT plan, COUNT(*) as cnt FROM ff_users GROUP BY plan ORDER BY cnt DESC");

// User list
$search = trim($_GET['search'] ?? '');
$userWhere = $search ? "WHERE name LIKE ? OR email LIKE ?" : "";
$userParams = $search ? ["%$search%", "%$search%"] : [];
$users = DB::fetchAll("SELECT u.*, (SELECT COUNT(*) FROM ff_projects WHERE owner_id = u.id) as project_count FROM ff_users u $userWhere ORDER BY u.created_at DESC LIMIT 50", $userParams);

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">

  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center gap-3">
    <div class="w-8 h-8 rounded-xl bg-red-100 flex items-center justify-center">
      <i class="fas fa-shield-alt text-red-600" style="font-size:14px"></i>
    </div>
    <div>
      <h1 class="text-xl font-bold text-gray-900">Super Admin Panel</h1>
      <p class="text-sm text-red-500 font-medium">⚠ Super admin access — all actions are logged</p>
    </div>
  </header>

  <!-- Tabs -->
  <div class="border-b border-gray-200 bg-white px-6">
    <div class="flex gap-1">
      <?php foreach (['overview'=>'Overview','users'=>'Users','companies'=>'Companies','databases'=>'Databases','plans'=>'Plans','system'=>'System'] as $k=>$label): ?>
      <a href="?tab=<?= $k ?>"
         class="px-4 py-3 text-sm font-medium border-b-2 transition <?= $tab === $k ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="p-6 space-y-5">

    <?php if ($tab === 'overview'): ?>
    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <?php foreach ([
        ['Total Users', $totalUsers, 'users', 'indigo'],
        ['Active Users', $activeUsers, 'user-check', 'green'],
        ['Total Projects', $totalProjects, 'folder', 'blue'],
        ['Total Feedback', $totalFeedback, 'comments', 'purple'],
      ] as [$label, $val, $icon, $color]): ?>
      <div class="ff-card p-5 stat-card">
        <div class="w-10 h-10 rounded-xl bg-<?= $color ?>-100 flex items-center justify-center mb-3">
          <i class="fas fa-<?= $icon ?> text-<?= $color ?>-600"></i>
        </div>
        <p class="text-2xl font-bold text-gray-900"><?= number_format($val) ?></p>
        <p class="text-sm text-gray-500"><?= $label ?></p>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Plan Distribution -->
    <div class="ff-card p-6">
      <h2 class="text-base font-bold text-gray-900 mb-4">Plan Distribution</h2>
      <div class="space-y-3">
        <?php foreach ($planCounts as $pc): ?>
        <?php $pct = $totalUsers > 0 ? round($pc['cnt'] / $totalUsers * 100) : 0; ?>
        <div class="flex items-center gap-3">
          <span class="w-20 text-xs text-gray-500 capitalize"><?= h($pc['plan'] ?? 'free') ?></span>
          <div class="flex-1 bg-gray-100 rounded-full h-2">
            <div class="bg-indigo-500 h-2 rounded-full" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="text-xs font-semibold text-gray-700 w-12 text-right"><?= number_format($pc['cnt']) ?></span>
          <span class="text-xs text-gray-400 w-8"><?= $pct ?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Recent Signups -->
    <div class="ff-card p-6">
      <h2 class="text-base font-bold text-gray-900 mb-4">Recent Signups</h2>
      <div class="space-y-2">
        <?php $recentUsers = DB::fetchAll("SELECT * FROM ff_users ORDER BY created_at DESC LIMIT 8"); ?>
        <?php foreach ($recentUsers as $ru): ?>
        <div class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0">
          <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
               style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
            <?= strtoupper(substr($ru['name'], 0, 1)) ?>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-800 truncate"><?= h($ru['name']) ?></p>
            <p class="text-xs text-gray-400 truncate"><?= h($ru['email']) ?></p>
          </div>
          <span class="badge bg-gray-100 text-gray-600 capitalize"><?= h($ru['role']) ?></span>
          <span class="text-xs text-gray-400"><?= timeAgo($ru['created_at']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($tab === 'users'): ?>
    <!-- Users Tab -->
    <div class="ff-card overflow-hidden">
      <div class="p-4 border-b border-gray-100">
        <form method="get" class="flex gap-3">
          <input type="hidden" name="tab" value="users">
          <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search by name or email…"
                 class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition">Search</button>
        </form>
      </div>
      <table class="w-full text-sm ff-table">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">User</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Role</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Projects</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Joined</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($users as $u): ?>
          <tr>
            <td class="py-3 px-4">
              <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                     style="background:linear-gradient(135deg,#6366f1,#8b5cf6)"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                <div>
                  <p class="font-medium text-gray-800 text-xs"><?= h($u['name']) ?></p>
                  <p class="text-xs text-gray-400"><?= h($u['email']) ?></p>
                </div>
                <?php if ($u['is_super_admin']): ?>
                <span class="badge bg-red-100 text-red-700 text-xs">SA</span>
                <?php endif; ?>
              </div>
            </td>
            <td class="py-3 px-4"><span class="badge bg-indigo-100 text-indigo-700 capitalize"><?= h($u['role']) ?></span></td>
            <td class="py-3 px-4 text-center text-xs font-semibold text-gray-700"><?= $u['project_count'] ?></td>
            <td class="py-3 px-4"><span class="badge <?= $u['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>"><?= $u['is_active'] ? 'Active' : 'Suspended' ?></span></td>
            <td class="py-3 px-4 text-xs text-gray-400"><?= timeAgo($u['created_at']) ?></td>
            <td class="py-3 px-4">
              <div class="flex gap-1">
                <form method="post" class="inline">
                  <?= csrfInput() ?>
                  <input type="hidden" name="action" value="toggle_user">
                  <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                  <button class="px-2 py-1 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 transition text-gray-600">
                    <?= $u['is_active'] ? 'Suspend' : 'Activate' ?>
                  </button>
                </form>
                <?php if ($u['id'] !== $currentUser['id']): ?>
                <form method="post" class="inline">
                  <?= csrfInput() ?>
                  <input type="hidden" name="action" value="impersonate">
                  <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                  <button class="px-2 py-1 text-xs border border-blue-200 text-blue-600 rounded-lg hover:bg-blue-50 transition" title="Impersonate user">
                    <i class="fas fa-user-secret"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($tab === 'companies'): ?>
    <?php
    $companies = DB::fetchAll("
      SELECT c.*,
        (SELECT COUNT(*) FROM ff_users u WHERE u.company_id = c.id) AS user_count,
        (SELECT COUNT(*) FROM ff_projects p WHERE p.company_id = c.id) AS project_count,
        (SELECT cd.db_status FROM ff_company_databases cd WHERE cd.company_id = c.id LIMIT 1) AS db_status
      FROM ff_companies c ORDER BY c.created_at DESC LIMIT 100
    ");
    ?>
    <div class="ff-card overflow-hidden">
      <div class="p-4 border-b border-gray-100 flex items-center justify-between">
        <p class="text-sm font-semibold text-gray-700"><?= count($companies) ?> Companies</p>
        <span class="text-xs text-gray-400">Click a row to open company detail</span>
      </div>
      <table class="w-full text-sm ff-table">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Company</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Plan</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Users</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Tenant DB</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Created</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($companies as $co): ?>
          <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='<?= APP_URL ?>/admin/super-admin-company.php?id=<?= $co['id'] ?>'">
            <td class="py-3 px-4">
              <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                     style="background:linear-gradient(135deg,#6366f1,#8b5cf6)"><?= strtoupper(substr($co['name'],0,1)) ?></div>
                <div>
                  <p class="font-medium text-gray-800 text-sm"><?= h($co['name']) ?></p>
                  <p class="text-xs text-gray-400"><?= h($co['email'] ?? '') ?></p>
                </div>
              </div>
            </td>
            <td class="py-3 px-4"><span class="badge bg-indigo-100 text-indigo-700 capitalize"><?= h($co['plan'] ?: 'free') ?></span></td>
            <td class="py-3 px-4 text-center text-xs font-semibold text-gray-700">
              <?= $co['user_count'] ?> / <?= $co['project_count'] ?> proj
            </td>
            <td class="py-3 px-4">
              <?php $dbs = $co['db_status'] ?? null; ?>
              <?php if ($dbs === 'active'): ?>
                <span class="badge bg-emerald-100 text-emerald-700"><i class="fas fa-database text-xs"></i> Active</span>
              <?php elseif ($dbs === 'failed'): ?>
                <span class="badge bg-red-100 text-red-700"><i class="fas fa-exclamation-triangle text-xs"></i> Failed</span>
              <?php elseif ($dbs === 'pending'): ?>
                <span class="badge bg-yellow-100 text-yellow-700"><i class="fas fa-clock text-xs"></i> Pending</span>
              <?php else: ?>
                <span class="badge bg-gray-100 text-gray-400">–</span>
              <?php endif; ?>
            </td>
            <td class="py-3 px-4"><span class="badge <?= ($co['is_active'] ?? 1) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>"><?= ($co['is_active'] ?? 1) ? 'Active' : 'Suspended' ?></span></td>
            <td class="py-3 px-4 text-xs text-gray-400"><?= timeAgo($co['created_at']) ?></td>
            <td class="py-3 px-4" onclick="event.stopPropagation()">
              <a href="<?= APP_URL ?>/admin/super-admin-company.php?id=<?= $co['id'] ?>"
                 class="px-2 py-1 text-xs border border-indigo-200 text-indigo-600 rounded-lg hover:bg-indigo-50 transition">
                <i class="fas fa-eye mr-1"></i> View
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($tab === 'databases'): ?>
    <?php $allDbs = DBManager::listCompanyDatabases(); ?>
    <div class="ff-card overflow-hidden">
      <div class="p-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700">
          Tenant Database Registry — <?= count($allDbs) ?> entries
        </h2>
        <p class="text-xs text-gray-400 mt-0.5">All provisioned company databases. Credentials are AES-256 encrypted in the master DB.</p>
      </div>
      <table class="w-full text-sm ff-table">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Company</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Database Name</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Host</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">DB User</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Provisioned</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($allDbs as $db): ?>
          <tr class="hover:bg-gray-50">
            <td class="py-3 px-4">
              <a href="<?= APP_URL ?>/admin/super-admin-company.php?id=<?= $db['company_id'] ?>"
                 class="font-medium text-indigo-600 hover:underline text-sm">
                <?= h($db['company_name'] ?? 'Company #' . $db['company_id']) ?>
              </a>
            </td>
            <td class="py-3 px-4 font-mono text-xs text-gray-700"><?= h($db['db_name']) ?></td>
            <td class="py-3 px-4 text-xs text-gray-500"><?= h($db['db_host']) ?>:<?= $db['db_port'] ?></td>
            <td class="py-3 px-4 text-xs font-mono text-gray-600"><?= h($db['db_user']) ?></td>
            <td class="py-3 px-4">
              <span class="badge <?= match($db['db_status']) {
                'active'    => 'bg-green-100 text-green-700',
                'failed'    => 'bg-red-100 text-red-700',
                'suspended' => 'bg-orange-100 text-orange-700',
                default     => 'bg-yellow-100 text-yellow-700',
              } ?>"><?= h($db['db_status']) ?></span>
            </td>
            <td class="py-3 px-4 text-xs text-gray-400">
              <?= $db['provisioned_at'] ? timeAgo($db['provisioned_at']) : '–' ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$allDbs): ?>
          <tr><td colspan="6" class="py-8 text-center text-sm text-gray-400">
            No tenant databases provisioned yet. They are created automatically when companies sign up.
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($tab === 'plans'): ?>
    <?php $plans = DB::fetchAll("SELECT * FROM ff_billing_plans ORDER BY sort_order"); ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <?php foreach ($plans as $plan): ?>
      <div class="ff-card p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-bold text-gray-900"><?= h($plan['name']) ?></h3>
          <span class="badge bg-indigo-100 text-indigo-700">$<?= $plan['price_monthly'] ?>/mo</span>
        </div>
        <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
          <div>Max projects: <strong><?= $plan['max_projects'] >= 999 ? '∞' : $plan['max_projects'] ?></strong></div>
          <div>Max members: <strong><?= $plan['max_team_members'] >= 999 ? '∞' : $plan['max_team_members'] ?></strong></div>
          <div>Feedback/mo: <strong><?= $plan['max_feedback_per_month'] >= 999999 ? '∞' : number_format($plan['max_feedback_per_month']) ?></strong></div>
          <div>Campaigns/mo: <strong><?= $plan['max_campaigns_per_month'] >= 999 ? '∞' : $plan['max_campaigns_per_month'] ?></strong></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php elseif ($tab === 'system'): ?>
    <div class="ff-card p-6 space-y-4">
      <h2 class="text-base font-bold text-gray-900">System Information</h2>
      <?php $info = [
        'PHP Version' => PHP_VERSION,
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
        'App Version' => APP_VERSION,
        'App URL' => APP_URL,
        'Debug Mode' => DEBUG_MODE ? 'ENABLED ⚠️' : 'Disabled',
        'AI Enabled' => AI_ENABLED ? 'Yes (' . OPENAI_MODEL . ')' : 'No (API key missing)',
        'SMTP Host' => SMTP_HOST ?: 'Not configured',
        'Slack' => SLACK_WEBHOOK_URL ? 'Configured' : 'Not configured',
        'Upload Dir' => UPLOAD_DIR,
        'Max Upload Size' => round(MAX_FILE_SIZE / 1024 / 1024) . ' MB',
        'Session Lifetime' => SESSION_LIFETIME / 86400 . ' days',
      ]; ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <?php foreach ($info as $key => $val): ?>
        <div class="flex items-center gap-3 bg-gray-50 rounded-xl px-4 py-3">
          <span class="text-xs text-gray-500 w-36 flex-shrink-0"><?= $key ?></span>
          <span class="text-xs font-mono text-gray-800 break-all"><?= h((string)$val) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
