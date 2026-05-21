<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('run_campaigns');
$userProjects = getUserProjects($currentUser['id']);
$pageTitle = 'Automations – ' . APP_NAME;

Auth::start();
if (isset($_GET['project_id'])) $_SESSION['current_project_id'] = (int)$_GET['project_id'];
$projectId = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save' && $projectId) {
        $data = [
            'project_id'    => $projectId,
            'name'          => trim($_POST['name'] ?? 'New Automation'),
            'description'   => trim($_POST['description'] ?? ''),
            'trigger_type'  => $_POST['trigger_type'] ?? 'feedback_created',
            'trigger_config'=> json_encode(['value' => trim($_POST['trigger_value'] ?? '')]),
            'action_type'   => $_POST['action_type'] ?? 'send_email',
            'action_config' => json_encode([
                'to'      => trim($_POST['action_to'] ?? ''),
                'subject' => trim($_POST['action_subject'] ?? ''),
                'body'    => trim($_POST['action_body'] ?? ''),
                'url'     => trim($_POST['action_url'] ?? ''),
            ]),
            'is_active'     => isset($_POST['is_active']) ? 1 : 0,
            'created_by'    => $currentUser['id'],
        ];
        if (!empty($_POST['id'])) {
            unset($data['created_by']);
            DB::update('ff_automations', $data, 'id = ? AND project_id = ?', [(int)$_POST['id'], $projectId]);
            flash('success', 'Automation updated.');
        } else {
            DB::insert('ff_automations', $data);
            flash('success', 'Automation created.');
        }
    }

    if ($action === 'toggle' && isset($_POST['id'])) {
        $auto = DB::fetch("SELECT is_active FROM ff_automations WHERE id = ?", [(int)$_POST['id']]);
        if ($auto) DB::update('ff_automations', ['is_active' => $auto['is_active'] ? 0 : 1], 'id = ?', [(int)$_POST['id']]);
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        DB::delete('ff_automations', 'id = ? AND project_id = ?', [(int)$_POST['id'], $projectId]);
        flash('success', 'Automation deleted.');
    }

    redirect(APP_URL . '/admin/automations.php');
}

$automations = $projectId ? DB::fetchAll("SELECT * FROM ff_automations WHERE project_id = ? ORDER BY created_at DESC", [$projectId]) : [];
$flash = getFlash();

$triggerLabels = [
    'feedback_created' => 'New feedback submitted',
    'feedback_updated' => 'Feedback status changes',
    'rating_low'       => 'Low rating received (1–2 stars)',
    'rating_high'      => 'High rating received (4–5 stars)',
    'keyword_match'    => 'Keyword found in feedback',
    'daily'            => 'Daily (scheduled)',
    'weekly'           => 'Weekly (scheduled)',
];
$actionLabels = [
    'send_email'   => 'Send email notification',
    'send_webhook' => 'Send webhook (HTTP POST)',
    'create_task'  => 'Create a task',
    'add_tag'      => 'Add tag to feedback',
    'change_status'=> 'Change feedback status',
    'notify_slack' => 'Notify Slack channel',
    'send_sms'     => 'Send SMS alert',
];

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">

  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Automation Workflows</h1>
      <p class="text-sm text-gray-500 mt-0.5">Trigger actions automatically based on feedback events (Module 12)</p>
    </div>
    <button onclick="document.getElementById('autoModal').classList.remove('hidden')"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
      <i class="fas fa-plus"></i> New Automation
    </button>
  </header>

  <div class="p-6 space-y-5">
    <?php foreach ($flash as $f): ?>
      <div class="rounded-xl px-4 py-3 text-sm font-medium <?= $f['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
        <?= h($f['msg']) ?>
      </div>
    <?php endforeach; ?>

    <?php if (!$currentProject): ?>
    <div class="ff-card p-12 text-center"><p class="text-gray-500">Select a project first.</p></div>
    <?php elseif (empty($automations)): ?>
    <div class="ff-card p-12 text-center">
      <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-bolt text-purple-600 text-2xl"></i>
      </div>
      <h2 class="text-lg font-bold text-gray-900 mb-2">No automations yet</h2>
      <p class="text-gray-500 text-sm mb-5">Create workflows that run automatically when feedback events happen.</p>
      <button onclick="document.getElementById('autoModal').classList.remove('hidden')"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition">
        <i class="fas fa-plus"></i> Create First Automation
      </button>
    </div>
    <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($automations as $a): ?>
      <?php $tc = json_decode($a['trigger_config'], true) ?? []; $ac = json_decode($a['action_config'], true) ?? []; ?>
      <div class="ff-card p-5">
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 rounded-xl <?= $a['is_active'] ? 'bg-purple-100' : 'bg-gray-100' ?> flex items-center justify-center flex-shrink-0">
            <i class="fas fa-bolt <?= $a['is_active'] ? 'text-purple-600' : 'text-gray-400' ?>"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="font-semibold text-gray-900"><?= h($a['name']) ?></h3>
              <span class="badge <?= $a['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                <?= $a['is_active'] ? 'Active' : 'Paused' ?>
              </span>
            </div>
            <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
              <span class="badge bg-blue-50 text-blue-700">
                <i class="fas fa-play-circle mr-1"></i><?= $triggerLabels[$a['trigger_type']] ?? $a['trigger_type'] ?>
              </span>
              <i class="fas fa-arrow-right text-gray-300"></i>
              <span class="badge bg-indigo-50 text-indigo-700">
                <i class="fas fa-bolt mr-1"></i><?= $actionLabels[$a['action_type']] ?? $a['action_type'] ?>
              </span>
            </div>
            <?php if ($a['description']): ?>
            <p class="text-xs text-gray-400 mt-1"><?= h($a['description']) ?></p>
            <?php endif; ?>
          </div>
          <div class="flex items-center gap-3 flex-shrink-0">
            <div class="text-right text-xs">
              <p class="font-semibold text-gray-700"><?= number_format($a['run_count']) ?></p>
              <p class="text-gray-400">runs</p>
            </div>
            <form method="post">
              <?= csrfInput() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $a['id'] ?>">
              <button class="w-10 h-6 rounded-full transition-colors flex items-center px-1 <?= $a['is_active'] ? 'bg-indigo-600' : 'bg-gray-200' ?>">
                <span class="w-4 h-4 bg-white rounded-full shadow transition-transform <?= $a['is_active'] ? 'translate-x-4' : '' ?>"></span>
              </button>
            </form>
            <form method="post">
              <?= csrfInput() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $a['id'] ?>">
              <button onclick="return confirm('Delete this automation?')" class="text-red-400 hover:text-red-600 p-1 transition">
                <i class="fas fa-trash" style="font-size:12px"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>

<!-- Modal -->
<div id="autoModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl mx-4 p-6 max-h-screen overflow-y-auto">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-lg font-bold text-gray-900">New Automation</h2>
      <button onclick="document.getElementById('autoModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
    <form method="post" class="space-y-4">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="save">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Name *</label>
        <input type="text" name="name" required placeholder="e.g. Alert on low rating"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
        <input type="text" name="description" placeholder="Optional description"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
      </div>
      <div class="p-4 bg-blue-50 rounded-xl space-y-3">
        <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide"><i class="fas fa-play-circle mr-1"></i> When (Trigger)</p>
        <select name="trigger_type" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <?php foreach ($triggerLabels as $val => $label): ?>
            <option value="<?= $val ?>"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="trigger_value" placeholder="Keyword (for keyword match trigger)"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
      </div>
      <div class="p-4 bg-indigo-50 rounded-xl space-y-3">
        <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide"><i class="fas fa-bolt mr-1"></i> Then (Action)</p>
        <select name="action_type" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <?php foreach ($actionLabels as $val => $label): ?>
            <option value="<?= $val ?>"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="action_to" placeholder="Email address or Slack webhook URL"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
        <input type="text" name="action_subject" placeholder="Email subject"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
        <textarea name="action_body" rows="2" placeholder="Email body / webhook payload"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
      </div>
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_active" value="1" checked class="rounded">
        <span class="text-sm font-medium text-gray-700">Enable this automation</span>
      </label>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="document.getElementById('autoModal').classList.add('hidden')"
                class="flex-1 border border-gray-200 text-gray-700 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition">Cancel</button>
        <button class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-sm font-semibold transition">Create Automation</button>
      </div>
    </form>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
