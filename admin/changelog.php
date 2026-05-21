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
    if (!verifyCsrf()) { flash('error','CSRF failed.'); redirect($_SERVER['REQUEST_URI']); }
    $pAction = $_POST['_action'] ?? '';
    if ($pAction === 'create') {
        $id = DB::insert('ff_changelog', [
            'project_id'   => $projectId,
            'title'        => sanitize($_POST['title'] ?? ''),
            'content'      => $_POST['content'] ?? '',
            'type'         => $_POST['type'] ?? 'new',
            'version'      => sanitize($_POST['version'] ?? ''),
            'is_published' => isset($_POST['publish']) ? 1 : 0,
            'published_at' => isset($_POST['publish']) ? date('Y-m-d H:i:s') : null,
            'author_id'    => $currentUser['id'],
        ]);
        flash('success', isset($_POST['publish']) ? 'Entry published!' : 'Draft saved!');
    } elseif ($pAction === 'delete') {
        DB::delete('ff_changelog', 'id = ? AND project_id = ?', [(int)$_POST['entry_id'], $projectId]);
        flash('success', 'Entry deleted.');
    } elseif ($pAction === 'publish') {
        DB::update('ff_changelog', ['is_published' => 1, 'published_at' => date('Y-m-d H:i:s')], 'id = ? AND project_id = ?', [(int)$_POST['entry_id'], $projectId]);
        flash('success', 'Published!');
    } elseif ($pAction === 'unpublish') {
        DB::update('ff_changelog', ['is_published' => 0, 'published_at' => null], 'id = ? AND project_id = ?', [(int)$_POST['entry_id'], $projectId]);
        flash('success', 'Unpublished.');
    }
    redirect(APP_URL . '/admin/changelog.php');
}

$entries = DB::fetchAll("SELECT cl.*, u.name as author_name FROM ff_changelog cl LEFT JOIN ff_users u ON u.id = cl.author_id WHERE cl.project_id = ? ORDER BY cl.created_at DESC", [$projectId]);
$pageTitle = 'Changelog – ' . APP_NAME;
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Changelog</h1>
      <?php if ($currentProject): ?>
        <a href="<?= APP_URL ?>/public/changelog.php?slug=<?= h($currentProject['slug']) ?>" target="_blank" class="text-xs text-indigo-600 hover:underline"><i class="fas fa-external-link-alt mr-1"></i>View public changelog</a>
      <?php endif; ?>
    </div>
    <button onclick="document.getElementById('newEntryModal').classList.remove('hidden')" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
      <i class="fas fa-plus"></i> New Entry
    </button>
  </header>

  <?php foreach (getFlash() as $f): ?>
    <div data-flash class="mx-6 mt-4 px-4 py-3 rounded-xl text-sm <?= $f['type']==='success'?'bg-green-50 text-green-700 border border-green-200':'bg-red-50 text-red-700 border border-red-200' ?>"><?= h($f['msg']) ?></div>
  <?php endforeach; ?>

  <div class="p-6">
    <?php $typeColors = ['new'=>'bg-blue-100 text-blue-700','improvement'=>'bg-indigo-100 text-indigo-700','bugfix'=>'bg-red-100 text-red-700','breaking'=>'bg-orange-100 text-orange-700'];
    $typeIcons = ['new'=>'star','improvement'=>'arrow-up','bugfix'=>'bug','breaking'=>'exclamation-triangle']; ?>
    <?php if (empty($entries)): ?>
      <div class="bg-white rounded-2xl border border-gray-200 py-16 text-center text-gray-400">
        <i class="fas fa-scroll text-4xl block mb-3"></i>
        <p class="font-medium">No changelog entries yet</p>
        <p class="text-sm mt-1">Create your first entry to keep users informed</p>
      </div>
    <?php else: foreach ($entries as $entry): ?>
      <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-4 <?= !$entry['is_published'] ? 'opacity-70' : '' ?>">
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-start gap-4 flex-1 min-w-0">
            <div class="flex-shrink-0 mt-1">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold <?= $typeColors[$entry['type']] ?? 'bg-gray-100 text-gray-600' ?>">
                <i class="fas fa-<?= $typeIcons[$entry['type']] ?? 'tag' ?>"></i> <?= ucfirst($entry['type']) ?>
              </span>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3 flex-wrap">
                <h3 class="font-bold text-gray-900 text-lg"><?= h($entry['title']) ?></h3>
                <?php if ($entry['version']): ?><span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-xs font-mono"><?= h($entry['version']) ?></span><?php endif; ?>
                <?php if (!$entry['is_published']): ?><span class="bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded-full font-medium">Draft</span><?php endif; ?>
              </div>
              <p class="text-xs text-gray-400 mt-1">
                By <?= h($entry['author_name'] ?? 'Unknown') ?> &middot;
                <?= $entry['is_published'] && $entry['published_at'] ? 'Published ' . formatDate($entry['published_at']) : 'Created ' . formatDate($entry['created_at']) ?>
              </p>
              <div class="mt-3 prose prose-sm text-gray-700 max-w-none">
                <?= nl2br(h($entry['content'])) ?>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <?php if (!$entry['is_published']): ?>
              <form method="POST"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="_action" value="publish"><input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">Publish</button></form>
            <?php else: ?>
              <form method="POST"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="_action" value="unpublish"><input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium px-3 py-1.5 rounded-lg transition">Unpublish</button></form>
            <?php endif; ?>
            <form method="POST" onsubmit="return confirm('Delete this entry?')"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
              <button type="submit" class="text-red-400 hover:text-red-600 text-sm px-2 py-1"><i class="fas fa-trash"></i></button></form>
          </div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</main>
</div>

<!-- New Entry Modal -->
<div id="newEntryModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 max-h-screen overflow-y-auto">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-lg font-bold">New Changelog Entry</h3>
      <button onclick="document.getElementById('newEntryModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="_csrf" value="<?= csrf() ?>">
      <input type="hidden" name="_action" value="create">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
          <input type="text" name="title" required placeholder="What changed?" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
          <select name="type" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none">
            <option value="new">✨ New Feature</option>
            <option value="improvement">📈 Improvement</option>
            <option value="bugfix">🐛 Bug Fix</option>
            <option value="breaking">⚠️ Breaking Change</option>
          </select></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Version (optional)</label>
          <input type="text" name="version" placeholder="v2.1.0" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
      </div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Content <span class="text-red-500">*</span></label>
        <textarea name="content" rows="6" required placeholder="Describe the change in detail. You can use plain text." class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea></div>
      <div class="flex gap-3 pt-2">
        <button type="submit" name="publish" value="1" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl transition"><i class="fas fa-globe mr-1"></i> Publish Now</button>
        <button type="submit" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl transition"><i class="fas fa-save mr-1"></i> Save Draft</button>
      </div>
    </form>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
