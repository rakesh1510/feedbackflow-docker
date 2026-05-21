<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('run_campaigns');
$userProjects = getUserProjects($currentUser['id']);
$pageTitle = 'Suppression List – ' . APP_NAME;

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $type  = in_array($_POST['type'] ?? '', ['email','phone','domain']) ? $_POST['type'] : 'email';
        $value = trim($_POST['value'] ?? '');
        $reason = in_array($_POST['reason'] ?? '', ['unsubscribe','bounce','complaint','manual','gdpr']) ? $_POST['reason'] : 'manual';
        $note  = trim($_POST['note'] ?? '');
        if ($value) {
            // Check for duplicate
            $exists = DB::fetch("SELECT id FROM ff_suppression WHERE value = ?", [$value]);
            if (!$exists) {
                DB::insert('ff_suppression', [
                    'type' => $type, 'value' => $value, 'reason' => $reason,
                    'added_by' => $currentUser['id'], 'note' => $note,
                ]);
                logAudit($currentUser, 'suppression.add', 'suppression', null, null, ['value' => $value, 'type' => $type]);
                flash('success', "Added $value to suppression list.");
            } else {
                flash('warning', "$value already exists in the suppression list.");
            }
        }
    }

    if ($action === 'bulk_import') {
        $raw  = trim($_POST['bulk_emails'] ?? '');
        $lines = preg_split('/[\r\n,;]+/', $raw);
        $added = 0;
        foreach ($lines as $line) {
            $val = trim($line);
            if (!$val) continue;
            $exists = DB::fetch("SELECT id FROM ff_suppression WHERE value = ?", [$val]);
            if (!$exists) {
                DB::insert('ff_suppression', ['type' => 'email', 'value' => $val, 'reason' => 'manual', 'added_by' => $currentUser['id']]);
                $added++;
            }
        }
        flash('success', "Imported $added new entries.");
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        DB::delete('ff_suppression', 'id = ?', [(int)$_POST['id']]);
        flash('success', 'Entry removed from suppression list.');
    }

    if ($action === 'clear_all') {
        DB::query("DELETE FROM ff_suppression WHERE added_by = ?", [$currentUser['id']]);
        flash('success', 'Suppression list cleared.');
    }

    redirect(APP_URL . '/admin/suppression.php');
}

// Pagination & search
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = $search ? "WHERE value LIKE ?" : "";
$params = $search ? ["%$search%"] : [];
$total = DB::count("SELECT COUNT(*) FROM ff_suppression $where", $params);
$items = DB::fetchAll("SELECT s.*, u.name AS added_by_name FROM ff_suppression s LEFT JOIN ff_users u ON u.id = s.added_by $where ORDER BY s.created_at DESC LIMIT $perPage OFFSET $offset", $params);
$pages = max(1, ceil($total / $perPage));

$flash = getFlash();
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">

  <!-- Top Bar -->
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Suppression List</h1>
      <p class="text-sm text-gray-500 mt-0.5">Manage email/phone addresses excluded from outreach (Module 07 – Compliance)</p>
    </div>
    <button onclick="document.getElementById('addModal').classList.remove('hidden')"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
      <i class="fas fa-plus"></i> Add Entry
    </button>
  </header>

  <div class="p-6 space-y-5">
    <?php foreach ($flash as $f): ?>
      <div class="rounded-xl px-4 py-3 text-sm font-medium <?= $f['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
        <?= h($f['msg']) ?>
      </div>
    <?php endforeach; ?>

    <!-- Stats row -->
    <div class="grid grid-cols-3 gap-4">
      <?php
        $counts = [
          'email'  => DB::count("SELECT COUNT(*) FROM ff_suppression WHERE type='email'"),
          'phone'  => DB::count("SELECT COUNT(*) FROM ff_suppression WHERE type='phone'"),
          'domain' => DB::count("SELECT COUNT(*) FROM ff_suppression WHERE type='domain'"),
        ];
        $icons = ['email'=>'envelope','phone'=>'phone','domain'=>'globe'];
        $colors = ['email'=>'indigo','phone'=>'emerald','domain'=>'amber'];
      ?>
      <?php foreach ($counts as $type => $cnt): ?>
      <div class="ff-card p-5">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-<?= $colors[$type] ?>-100 flex items-center justify-center">
            <i class="fas fa-<?= $icons[$type] ?> text-<?= $colors[$type] ?>-600"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-900"><?= number_format($cnt) ?></p>
            <p class="text-xs text-gray-500 capitalize"><?= $type ?>s suppressed</p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Search + Bulk Import -->
    <div class="ff-card p-5">
      <div class="flex items-center gap-4 mb-4">
        <form method="get" class="flex-1 flex gap-2">
          <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search emails, phones, domains…"
                 class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <button class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2.5 rounded-xl text-sm font-medium transition">
            <i class="fas fa-search"></i>
          </button>
        </form>
        <button onclick="document.getElementById('importModal').classList.remove('hidden')"
                class="inline-flex items-center gap-2 border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-xl text-sm font-medium transition">
          <i class="fas fa-file-import"></i> Bulk Import
        </button>
      </div>

      <?php if (empty($items)): ?>
      <div class="text-center py-16">
        <i class="fas fa-shield-alt text-4xl text-gray-200 mb-3"></i>
        <p class="text-gray-400 font-medium">No entries in suppression list</p>
        <p class="text-sm text-gray-300 mt-1">Add emails or phones to stop them from receiving outreach.</p>
      </div>
      <?php else: ?>
      <table class="w-full text-sm ff-table">
        <thead>
          <tr class="border-b border-gray-100">
            <th class="text-left py-3 px-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Value</th>
            <th class="text-left py-3 px-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
            <th class="text-left py-3 px-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Reason</th>
            <th class="text-left py-3 px-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Added By</th>
            <th class="text-left py-3 px-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
            <th class="w-12"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($items as $item): ?>
          <tr>
            <td class="py-3 px-2 font-mono text-sm text-gray-800"><?= h($item['value']) ?></td>
            <td class="py-3 px-2">
              <span class="badge <?= $item['type'] === 'email' ? 'bg-indigo-100 text-indigo-700' : ($item['type'] === 'phone' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700') ?>">
                <?= ucfirst($item['type']) ?>
              </span>
            </td>
            <td class="py-3 px-2">
              <span class="badge <?= $item['reason'] === 'gdpr' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' ?>">
                <?= ucfirst(str_replace('_', ' ', $item['reason'])) ?>
              </span>
            </td>
            <td class="py-3 px-2 text-gray-600"><?= h($item['added_by_name'] ?? '—') ?></td>
            <td class="py-3 px-2 text-gray-400 text-xs"><?= timeAgo($item['created_at']) ?></td>
            <td class="py-3 px-2">
              <form method="post">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                <button class="text-red-400 hover:text-red-600 transition" title="Remove">
                  <i class="fas fa-trash" style="font-size:12px"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
      <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
        <p class="text-xs text-gray-400">Showing <?= count($items) ?> of <?= $total ?> entries</p>
        <div class="flex gap-1">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition <?= $i === $page ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
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

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-lg font-bold text-gray-900">Add to Suppression List</h2>
      <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
    <form method="post" class="space-y-4">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="add">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Type</label>
        <select name="type" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <option value="email">Email</option>
          <option value="phone">Phone</option>
          <option value="domain">Domain</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Value *</label>
        <input type="text" name="value" required placeholder="e.g. user@example.com"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Reason</label>
        <select name="reason" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <option value="manual">Manual</option>
          <option value="unsubscribe">Unsubscribe</option>
          <option value="bounce">Bounce</option>
          <option value="complaint">Complaint</option>
          <option value="gdpr">GDPR Request</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Note</label>
        <input type="text" name="note" placeholder="Optional note"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                class="flex-1 border border-gray-200 text-gray-700 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition">Cancel</button>
        <button class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-sm font-semibold transition">Add Entry</button>
      </div>
    </form>
  </div>
</div>

<!-- Bulk Import Modal -->
<div id="importModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-lg font-bold text-gray-900">Bulk Import Emails</h2>
      <button onclick="document.getElementById('importModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
    <form method="post" class="space-y-4">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="bulk_import">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Addresses</label>
        <textarea name="bulk_emails" rows="8" required placeholder="One email per line, or comma-separated"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
        <p class="text-xs text-gray-400 mt-1">Duplicates will be skipped automatically.</p>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="document.getElementById('importModal').classList.add('hidden')"
                class="flex-1 border border-gray-200 text-gray-700 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition">Cancel</button>
        <button class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-sm font-semibold transition">Import</button>
      </div>
    </form>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
