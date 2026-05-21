<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('manage_roadmap');
$userProjects = getUserProjects($currentUser['id']);
$pageTitle = 'Status Page – ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_page') {
        $name = trim($_POST['name'] ?? 'Status Page');
        $slug = slug($name) . '-' . randomKey(4);
        DB::insert('ff_status_pages', [
            'name'            => $name,
            'slug'            => $slug,
            'description'     => trim($_POST['description'] ?? ''),
            'is_public'       => 1,
            'overall_status'  => 'operational',
        ]);
        flash('success', "Status page \"$name\" created.");
    }

    if ($action === 'update_status' && isset($_POST['page_id'])) {
        $validStatuses = ['operational','degraded','partial_outage','major_outage','maintenance'];
        $newStatus = in_array($_POST['overall_status'] ?? '', $validStatuses) ? $_POST['overall_status'] : 'operational';
        DB::update('ff_status_pages', ['overall_status' => $newStatus], 'id = ?', [(int)$_POST['page_id']]);
        flash('success', 'Status updated.');
    }

    if ($action === 'create_incident') {
        DB::insert('ff_status_incidents', [
            'page_id'     => (int)$_POST['page_id'],
            'title'       => trim($_POST['title'] ?? 'Incident'),
            'description' => trim($_POST['description'] ?? ''),
            'severity'    => $_POST['severity'] ?? 'minor',
            'status'      => 'investigating',
        ]);
        flash('success', 'Incident created.');
    }

    if ($action === 'resolve_incident' && isset($_POST['incident_id'])) {
        DB::update('ff_status_incidents', ['status' => 'resolved', 'resolved_at' => date('Y-m-d H:i:s')], 'id = ?', [(int)$_POST['incident_id']]);
        flash('success', 'Incident resolved.');
    }

    if ($action === 'delete_page' && isset($_POST['page_id'])) {
        DB::delete('ff_status_pages', 'id = ?', [(int)$_POST['page_id']]);
        flash('success', 'Status page deleted.');
    }

    redirect(APP_URL . '/admin/status.php');
}

$statusPages = DB::fetchAll("SELECT sp.*, (SELECT COUNT(*) FROM ff_status_incidents WHERE page_id = sp.id AND status != 'resolved') as open_incidents FROM ff_status_pages ORDER BY sp.created_at DESC");

$selectedPage = null;
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $selectedPage = DB::fetch("SELECT * FROM ff_status_pages WHERE id = ?", [(int)$_GET['page']]);
}
if (!$selectedPage && !empty($statusPages)) $selectedPage = $statusPages[0];

$incidents = $selectedPage ? DB::fetchAll("SELECT * FROM ff_status_incidents WHERE page_id = ? ORDER BY created_at DESC LIMIT 20", [$selectedPage['id']]) : [];

$statusLabels = [
    'operational'    => ['Operational', 'bg-green-100 text-green-700', 'fas fa-check-circle text-green-500'],
    'degraded'       => ['Degraded Performance', 'bg-yellow-100 text-yellow-700', 'fas fa-exclamation-circle text-yellow-500'],
    'partial_outage' => ['Partial Outage', 'bg-orange-100 text-orange-700', 'fas fa-minus-circle text-orange-500'],
    'major_outage'   => ['Major Outage', 'bg-red-100 text-red-700', 'fas fa-times-circle text-red-500'],
    'maintenance'    => ['Under Maintenance', 'bg-blue-100 text-blue-700', 'fas fa-wrench text-blue-500'],
];

$flash = getFlash();
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">

  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">System Status Pages</h1>
      <p class="text-sm text-gray-500 mt-0.5">Public status pages & incident management (Module 31)</p>
    </div>
    <button onclick="document.getElementById('createPageModal').classList.remove('hidden')"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
      <i class="fas fa-plus"></i> New Status Page
    </button>
  </header>

  <div class="p-6 space-y-5">
    <?php foreach ($flash as $f): ?>
      <div class="rounded-xl px-4 py-3 text-sm font-medium <?= $f['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
        <?= h($f['msg']) ?>
      </div>
    <?php endforeach; ?>

    <?php if (empty($statusPages)): ?>
    <div class="ff-card p-12 text-center">
      <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-heartbeat text-green-600 text-2xl"></i>
      </div>
      <h2 class="text-lg font-bold text-gray-900 mb-2">No status pages yet</h2>
      <p class="text-gray-500 text-sm mb-5">Create a public status page to communicate system health to your users.</p>
      <button onclick="document.getElementById('createPageModal').classList.remove('hidden')"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition">
        <i class="fas fa-plus"></i> Create Status Page
      </button>
    </div>
    <?php else: ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">
      <!-- Sidebar: page list -->
      <div class="space-y-2">
        <?php foreach ($statusPages as $sp): ?>
        <?php [$slabel, $sbadge, $sicon] = $statusLabels[$sp['overall_status']] ?? $statusLabels['operational']; ?>
        <a href="?page=<?= $sp['id'] ?>"
           class="block ff-card p-4 hover:shadow-sm transition <?= $selectedPage && $selectedPage['id'] == $sp['id'] ? 'ring-2 ring-indigo-500' : '' ?>">
          <div class="flex items-start gap-2">
            <i class="<?= $sicon ?> mt-0.5 flex-shrink-0"></i>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800 truncate"><?= h($sp['name']) ?></p>
              <p class="text-xs text-gray-400"><?= $slabel ?></p>
              <?php if ($sp['open_incidents'] > 0): ?>
              <span class="badge bg-red-100 text-red-700 text-xs mt-1"><?= $sp['open_incidents'] ?> active</span>
              <?php endif; ?>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Main content for selected page -->
      <?php if ($selectedPage): ?>
      <?php [$slabel, $sbadge, $sicon] = $statusLabels[$selectedPage['overall_status']] ?? $statusLabels['operational']; ?>
      <div class="lg:col-span-3 space-y-4">
        <!-- Page header -->
        <div class="ff-card p-5">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="font-bold text-gray-900 text-lg"><?= h($selectedPage['name']) ?></h2>
              <a href="<?= APP_URL ?>/public/status.php?slug=<?= h($selectedPage['slug']) ?>" target="_blank"
                 class="text-xs text-indigo-600 hover:underline"><i class="fas fa-external-link-alt mr-1"></i>Public URL</a>
            </div>
            <span class="badge <?= $sbadge ?> text-sm px-3 py-1"><i class="<?= $sicon ?> mr-1.5"></i><?= $slabel ?></span>
          </div>
          <!-- Status picker -->
          <form method="post" class="flex gap-2 flex-wrap">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="page_id" value="<?= $selectedPage['id'] ?>">
            <?php foreach ($statusLabels as $sk => [$slt, $sbt, $sit]): ?>
            <button name="overall_status" value="<?= $sk ?>"
                    class="px-3 py-1.5 text-xs rounded-xl border transition <?= $selectedPage['overall_status'] === $sk ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
              <?= $slt ?>
            </button>
            <?php endforeach; ?>
          </form>
        </div>

        <!-- Incidents -->
        <div class="ff-card p-5">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-900">Incidents</h3>
            <button onclick="document.getElementById('incidentModal').classList.remove('hidden');document.getElementById('incidentPageId').value=<?= $selectedPage['id'] ?>"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 transition">
              <i class="fas fa-plus" style="font-size:11px"></i> Add Incident
            </button>
          </div>

          <?php if (empty($incidents)): ?>
          <div class="text-center py-8">
            <i class="fas fa-check-circle text-3xl text-green-300 mb-2"></i>
            <p class="text-gray-400 text-sm">All systems operational — no incidents.</p>
          </div>
          <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($incidents as $inc): ?>
            <?php
              $sevColors = ['minor'=>'yellow','major'=>'orange','critical'=>'red','maintenance'=>'blue'];
              $statColors = ['investigating'=>'red','identified'=>'orange','monitoring'=>'blue','resolved'=>'green'];
              $sc = $sevColors[$inc['severity']] ?? 'gray';
              $stc = $statColors[$inc['status']] ?? 'gray';
            ?>
            <div class="border border-gray-100 rounded-xl p-4">
              <div class="flex items-start justify-between gap-3">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-1">
                    <h4 class="font-semibold text-gray-900 text-sm"><?= h($inc['title']) ?></h4>
                    <span class="badge bg-<?= $sc ?>-100 text-<?= $sc ?>-700 capitalize"><?= $inc['severity'] ?></span>
                    <span class="badge bg-<?= $stc ?>-100 text-<?= $stc ?>-700 capitalize"><?= str_replace('_',' ',$inc['status']) ?></span>
                  </div>
                  <?php if ($inc['description']): ?>
                  <p class="text-xs text-gray-500"><?= h($inc['description']) ?></p>
                  <?php endif; ?>
                  <p class="text-xs text-gray-400 mt-1"><?= timeAgo($inc['created_at']) ?></p>
                </div>
                <?php if ($inc['status'] !== 'resolved'): ?>
                <form method="post">
                  <?= csrfInput() ?>
                  <input type="hidden" name="action" value="resolve_incident">
                  <input type="hidden" name="incident_id" value="<?= $inc['id'] ?>">
                  <button class="px-3 py-1.5 text-xs bg-green-600 hover:bg-green-700 text-white rounded-xl font-medium transition">Resolve</button>
                </form>
                <?php else: ?>
                <span class="text-xs text-green-600 font-medium"><i class="fas fa-check-circle mr-1"></i>Resolved</span>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Delete page -->
        <form method="post" onsubmit="return confirm('Delete this status page?')">
          <?= csrfInput() ?>
          <input type="hidden" name="action" value="delete_page">
          <input type="hidden" name="page_id" value="<?= $selectedPage['id'] ?>">
          <button class="text-xs text-red-500 hover:text-red-700 transition"><i class="fas fa-trash mr-1"></i>Delete this status page</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>

<!-- Create Page Modal -->
<div id="createPageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-lg font-bold text-gray-900">New Status Page</h2>
      <button onclick="document.getElementById('createPageModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
    <form method="post" class="space-y-4">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="create_page">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Page Name *</label>
        <input type="text" name="name" required placeholder="e.g. FeedbackFlow Status"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
        <textarea name="description" rows="2" placeholder="This page shows the current status of our systems."
                  class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="document.getElementById('createPageModal').classList.add('hidden')"
                class="flex-1 border border-gray-200 text-gray-700 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition">Cancel</button>
        <button class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-sm font-semibold transition">Create Page</button>
      </div>
    </form>
  </div>
</div>

<!-- Incident Modal -->
<div id="incidentModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-lg font-bold text-gray-900">Report Incident</h2>
      <button onclick="document.getElementById('incidentModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
    <form method="post" class="space-y-4">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="create_incident">
      <input type="hidden" name="page_id" id="incidentPageId" value="">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Title *</label>
        <input type="text" name="title" required placeholder="e.g. API response delays"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
        <textarea name="description" rows="3"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Severity</label>
        <select name="severity" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <option value="minor">Minor</option>
          <option value="major">Major</option>
          <option value="critical">Critical</option>
          <option value="maintenance">Maintenance</option>
        </select>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="document.getElementById('incidentModal').classList.add('hidden')"
                class="flex-1 border border-gray-200 text-gray-700 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition">Cancel</button>
        <button class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-xl text-sm font-semibold transition">Report Incident</button>
      </div>
    </form>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
