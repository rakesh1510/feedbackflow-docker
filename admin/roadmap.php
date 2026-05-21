<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('manage_roadmap');
$userProjects = getUserProjects($currentUser['id']);
Auth::start();
if (isset($_GET['project_id'])) $_SESSION['current_project_id'] = (int)$_GET['project_id'];
$projectId = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;
if (!$currentProject && !empty($userProjects)) { $currentProject = $userProjects[0]; $projectId = $currentProject['id']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { flash('error', 'Security check failed.'); redirect($_SERVER['REQUEST_URI']); }
    $postAction = $_POST['_action'] ?? '';
    if ($postAction === 'create') {
        DB::insert('ff_roadmap', [
            'project_id'  => $projectId,
            'feedback_id' => (int)($_POST['feedback_id'] ?? 0) ?: null,
            'title'       => sanitize($_POST['title'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'status'      => $_POST['status'] ?? 'planned',
            'quarter'     => sanitize($_POST['quarter'] ?? ''),
            'target_date' => $_POST['target_date'] ?: null,
            'is_public'   => isset($_POST['is_public']) ? 1 : 0,
        ]);
        flash('success', 'Roadmap item added!');
    } elseif ($postAction === 'update') {
        DB::update('ff_roadmap', [
            'status'      => $_POST['status'] ?? 'planned',
            'title'       => sanitize($_POST['title'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'quarter'     => sanitize($_POST['quarter'] ?? ''),
            'target_date' => $_POST['target_date'] ?: null,
            'is_public'   => isset($_POST['is_public']) ? 1 : 0,
        ], 'id = ? AND project_id = ?', [(int)$_POST['item_id'], $projectId]);
        flash('success', 'Updated!');
    } elseif ($postAction === 'delete') {
        DB::delete('ff_roadmap', 'id = ? AND project_id = ?', [(int)$_POST['item_id'], $projectId]);
        flash('success', 'Deleted.');
    }
    redirect(APP_URL . '/admin/roadmap.php');
}

$roadmapItems = DB::fetchAll(
    "SELECT r.*, f.title as feedback_title FROM ff_roadmap r LEFT JOIN ff_feedback f ON f.id = r.feedback_id WHERE r.project_id = ? ORDER BY FIELD(r.status,'in_progress','planned','done'), r.sort_order, r.created_at",
    [$projectId]
);
$grouped = ['planned' => [], 'in_progress' => [], 'done' => []];
foreach ($roadmapItems as $item) $grouped[$item['status']][] = $item;
$feedbackList = DB::fetchAll("SELECT id, title FROM ff_feedback WHERE project_id = ? AND status IN ('planned','in_progress') ORDER BY vote_count DESC LIMIT 50", [$projectId]);
$pageTitle = 'Roadmap – ' . APP_NAME;
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Roadmap</h1>
      <?php if ($currentProject): ?>
        <a href="<?= APP_URL ?>/public/roadmap.php?slug=<?= h($currentProject['slug']) ?>" target="_blank" class="text-xs text-indigo-600 hover:underline"><i class="fas fa-external-link-alt mr-1"></i>View public roadmap</a>
      <?php endif; ?>
    </div>
    <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
      <i class="fas fa-plus"></i> Add Item
    </button>
  </header>

  <?php foreach (getFlash() as $f): ?>
    <div data-flash class="mx-6 mt-4 px-4 py-3 rounded-xl text-sm font-medium <?= $f['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>"><?= h($f['msg']) ?></div>
  <?php endforeach; ?>

  <div class="p-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <?php $cols = ['planned' => ['Planned', 'bg-indigo-50 border-indigo-200', 'text-indigo-700', 'bg-indigo-600'], 'in_progress' => ['In Progress', 'bg-orange-50 border-orange-200', 'text-orange-700', 'bg-orange-600'], 'done' => ['Done', 'bg-green-50 border-green-200', 'text-green-700', 'bg-green-600']];
      foreach ($cols as $status => [$label, $bgCls, $textCls, $dotCls]): ?>
        <div class="space-y-3">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full <?= $dotCls ?>"></span>
            <h2 class="font-semibold text-gray-900"><?= $label ?></h2>
            <span class="ml-auto text-sm text-gray-400"><?= count($grouped[$status]) ?></span>
          </div>
          <?php if (empty($grouped[$status])): ?>
            <div class="border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center text-gray-400 text-sm">No items</div>
          <?php else: foreach ($grouped[$status] as $item): ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-md transition group" x-data="{ open: false }">
              <div class="flex items-start justify-between gap-2">
                <h3 class="font-semibold text-gray-900 leading-tight flex-1"><?= h($item['title']) ?></h3>
                <button @click="open = !open" class="text-gray-400 hover:text-gray-600 opacity-0 group-hover:opacity-100"><i class="fas fa-ellipsis-v"></i></button>
              </div>
              <?php if ($item['description']): ?><p class="text-sm text-gray-500 mt-1.5 leading-relaxed"><?= h($item['description']) ?></p><?php endif; ?>
              <?php if ($item['quarter']): ?><p class="text-xs text-gray-400 mt-2"><i class="fas fa-calendar mr-1"></i><?= h($item['quarter']) ?></p><?php endif; ?>
              <?php if ($item['target_date']): ?><p class="text-xs text-gray-400 mt-1"><i class="fas fa-flag mr-1"></i>Target: <?= formatDate($item['target_date']) ?></p><?php endif; ?>
              <?php if ($item['feedback_title']): ?><p class="text-xs text-indigo-600 mt-2"><i class="fas fa-link mr-1"></i><?= h($item['feedback_title']) ?></p><?php endif; ?>
              <?php if (!$item['is_public']): ?><span class="text-xs text-gray-400"><i class="fas fa-lock mr-1"></i>Private</span><?php endif; ?>
              <div x-show="open" x-cloak class="mt-3 flex gap-2">
                <form method="POST" class="flex gap-2">
                  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                  <input type="hidden" name="_action" value="update">
                  <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                  <select name="status" onchange="this.form.submit()" class="text-xs border border-gray-200 rounded-lg px-2 py-1 outline-none">
                    <?php foreach (['planned','in_progress','done'] as $s): ?>
                      <option value="<?= $s ?>" <?= $item['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input type="hidden" name="title" value="<?= h($item['title']) ?>">
                  <input type="hidden" name="description" value="<?= h($item['description'] ?? '') ?>">
                  <input type="hidden" name="quarter" value="<?= h($item['quarter'] ?? '') ?>">
                  <input type="hidden" name="is_public" value="<?= $item['is_public'] ?>">
                </form>
                <form method="POST" onsubmit="return confirm('Delete this item?')">
                  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                  <input type="hidden" name="_action" value="delete">
                  <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                  <button type="submit" class="text-xs text-red-500 hover:text-red-700 border border-red-200 rounded-lg px-2 py-1">Delete</button>
                </form>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</main>
</div>

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-lg font-bold text-gray-900">Add Roadmap Item</h3>
      <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="_csrf" value="<?= csrf() ?>">
      <input type="hidden" name="_action" value="create">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
        <input type="text" name="title" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <textarea name="description" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select name="status" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="planned">Planned</option>
            <option value="in_progress">In Progress</option>
            <option value="done">Done</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Quarter</label>
          <input type="text" name="quarter" placeholder="Q1 2025" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Target Date</label>
          <input type="date" name="target_date" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Link to Feedback</label>
          <select name="feedback_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">— None —</option>
            <?php foreach ($feedbackList as $fb): ?><option value="<?= $fb['id'] ?>"><?= h(mb_strimwidth($fb['title'], 0, 40, '...')) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
        <input type="checkbox" name="is_public" checked class="rounded text-indigo-600"> Visible on public roadmap
      </label>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl transition">Add Item</button>
        <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
