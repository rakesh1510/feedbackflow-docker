<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::require();
if (!Auth::isAdmin($currentUser)) { redirect(APP_URL . '/admin/index.php'); }
$userProjects = getUserProjects($currentUser['id']);
$pageTitle = 'Audit Logs – ' . APP_NAME;

// Filters
$search  = trim($_GET['search'] ?? '');
$action  = trim($_GET['action_filter'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$wheres = [];
$params = [];
if ($search) { $wheres[] = "(user_name LIKE ? OR user_email LIKE ? OR resource_type LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($action) { $wheres[] = "action = ?"; $params[] = $action; }
$where = $wheres ? "WHERE " . implode(' AND ', $wheres) : "";

$total = DB::count("SELECT COUNT(*) FROM ff_audit_logs $where", $params);
$logs  = DB::fetchAll("SELECT * FROM ff_audit_logs $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);
$pages = max(1, ceil($total / $perPage));
$actions = DB::fetchAll("SELECT DISTINCT action FROM ff_audit_logs ORDER BY action");

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">

  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Audit Logs</h1>
      <p class="text-sm text-gray-500 mt-0.5">Complete record of all actions taken in the system (Module 26)</p>
    </div>
    <a href="<?= APP_URL ?>/admin/export.php?type=audit_logs" class="inline-flex items-center gap-2 border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-xl text-sm font-medium transition">
      <i class="fas fa-download"></i> Export CSV
    </a>
  </header>

  <div class="p-6 space-y-5">
    <!-- Filters -->
    <div class="ff-card p-4">
      <form method="get" class="flex gap-3 flex-wrap">
        <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search by user, action, resource…"
               class="flex-1 min-w-48 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
        <select name="action_filter" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <option value="">All Actions</option>
          <?php foreach ($actions as $a): ?>
          <option value="<?= h($a['action']) ?>" <?= $action === $a['action'] ? 'selected' : '' ?>><?= h($a['action']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition">Filter</button>
        <a href="?" class="border border-gray-200 hover:bg-gray-50 text-gray-600 px-4 py-2.5 rounded-xl text-sm font-medium transition">Reset</a>
      </form>
    </div>

    <div class="ff-card overflow-hidden">
      <?php if (empty($logs)): ?>
      <div class="text-center py-16">
        <i class="fas fa-list-alt text-4xl text-gray-200 mb-3"></i>
        <p class="text-gray-400 font-medium">No audit log entries found.</p>
      </div>
      <?php else: ?>
      <table class="w-full text-sm ff-table">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">When</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Action</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Resource</th>
            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">IP Address</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($logs as $log): ?>
          <?php
            $actionColor = match(true) {
              str_contains($log['action'], 'delete') || str_contains($log['action'], 'remove') => 'bg-red-100 text-red-700',
              str_contains($log['action'], 'create') || str_contains($log['action'], 'add') => 'bg-green-100 text-green-700',
              str_contains($log['action'], 'login') => 'bg-blue-100 text-blue-700',
              default => 'bg-gray-100 text-gray-600',
            };
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="py-3 px-4 text-xs text-gray-400 whitespace-nowrap">
              <?= timeAgo($log['created_at']) ?>
              <p class="text-gray-300 mt-0.5"><?= formatDate($log['created_at'], 'M j, Y H:i') ?></p>
            </td>
            <td class="py-3 px-4">
              <p class="font-medium text-gray-800 text-xs"><?= h($log['user_name'] ?? '—') ?></p>
              <p class="text-xs text-gray-400"><?= h($log['user_email'] ?? '') ?></p>
            </td>
            <td class="py-3 px-4">
              <span class="badge <?= $actionColor ?> text-xs"><?= h($log['action']) ?></span>
            </td>
            <td class="py-3 px-4 text-xs text-gray-600">
              <?php if ($log['resource_type']): ?>
                <span class="font-medium"><?= h($log['resource_type']) ?></span>
                <?php if ($log['resource_id']): ?> <span class="text-gray-400">#<?= $log['resource_id'] ?></span><?php endif; ?>
              <?php else: ?>
                <span class="text-gray-400">—</span>
              <?php endif; ?>
            </td>
            <td class="py-3 px-4 text-xs text-gray-400 font-mono"><?= h($log['ip_address'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
      <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
        <p class="text-xs text-gray-400">Showing <?= count($logs) ?> of <?= number_format($total) ?> entries</p>
        <div class="flex gap-1">
          <?php for ($i = 1; $i <= min($pages, 10); $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&action_filter=<?= urlencode($action) ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-medium transition <?= $i === $page ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
