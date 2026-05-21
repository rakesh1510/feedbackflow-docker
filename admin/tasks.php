<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('view_tasks');
$userProjects = getUserProjects($currentUser['id']);
$pageTitle = 'Tasks – ' . APP_NAME;

Auth::start();
if (isset($_GET['project_id'])) $_SESSION['current_project_id'] = (int)$_GET['project_id'];
$projectId = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;

$teamMembers = $projectId ? DB::fetchAll("SELECT u.id, u.name FROM ff_project_members pm JOIN ff_users u ON u.id = pm.user_id WHERE pm.project_id = ? UNION SELECT id, name FROM ff_users WHERE id = ?", [$projectId, $currentUser['id']]) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create' && $projectId) {
        DB::insert('ff_tasks', [
            'project_id'  => $projectId,
            'feedback_id' => !empty($_POST['feedback_id']) ? (int)$_POST['feedback_id'] : null,
            'title'       => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'status'      => 'open',
            'priority'    => $_POST['priority'] ?? 'medium',
            'assigned_to' => !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null,
            'due_date'    => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            'created_by'  => $currentUser['id'],
        ]);
        flash('success', 'Task created.');
    }
    if ($action === 'status_change' && isset($_POST['id'])) {
        $newStatus = in_array($_POST['status'] ?? '', ['open','in_progress','done','cancelled']) ? $_POST['status'] : 'open';
        DB::update('ff_tasks', ['status' => $newStatus], 'id = ? AND project_id = ?', [(int)$_POST['id'], $projectId]);
    }
    if ($action === 'delete' && isset($_POST['id'])) {
        DB::delete('ff_tasks', 'id = ? AND project_id = ?', [(int)$_POST['id'], $projectId]);
        flash('success', 'Task deleted.');
    }
    redirect(APP_URL . '/admin/tasks.php');
}

$statusFilter = $_GET['status'] ?? '';
$where = $projectId ? "WHERE t.project_id = ?" : "WHERE 1=0";
$params = [$projectId];
if ($statusFilter) { $where .= " AND t.status = ?"; $params[] = $statusFilter; }

$tasks = DB::fetchAll("SELECT t.*, u.name as assignee_name, f.title as feedback_title FROM ff_tasks t LEFT JOIN ff_users u ON u.id = t.assigned_to LEFT JOIN ff_feedback f ON f.id = t.feedback_id $where ORDER BY t.created_at DESC", $params);

$statusColors = ['open'=>'blue','in_progress'=>'amber','done'=>'green','cancelled'=>'gray'];
$priorityColors = ['critical'=>'red','high'=>'orange','medium'=>'yellow','low'=>'gray'];

$flash = getFlash();
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">

  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Tasks</h1>
      <p class="text-sm text-gray-500 mt-0.5">Track work items linked to feedback (Module 08)</p>
    </div>
    <button onclick="document.getElementById('taskModal').classList.remove('hidden')"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
      <i class="fas fa-plus"></i> New Task
    </button>
  </header>

  <div class="p-6 space-y-4">
    <?php foreach ($flash as $f): ?>
      <div class="rounded-xl px-4 py-3 text-sm font-medium <?= $f['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
        <?= h($f['msg']) ?>
      </div>
    <?php endforeach; ?>

    <!-- Status filter tabs -->
    <div class="flex gap-1">
      <?php foreach (['' => 'All', 'open' => 'Open', 'in_progress' => 'In Progress', 'done' => 'Done', 'cancelled' => 'Cancelled'] as $k => $label): ?>
      <a href="?status=<?= $k ?>"
         class="px-4 py-2 text-sm font-medium rounded-xl transition <?= $statusFilter === $k ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if (!$currentProject): ?>
    <div class="ff-card p-12 text-center"><p class="text-gray-500">Select a project to view tasks.</p></div>
    <?php elseif (empty($tasks)): ?>
    <div class="ff-card p-12 text-center">
      <i class="fas fa-tasks text-4xl text-gray-200 mb-3"></i>
      <p class="text-gray-400 font-medium">No tasks found</p>
    </div>
    <?php else: ?>
    <div class="space-y-2">
      <?php foreach ($tasks as $task): ?>
      <?php $sc = $statusColors[$task['status']] ?? 'gray'; $pc = $priorityColors[$task['priority']] ?? 'gray'; ?>
      <div class="ff-card p-4">
        <div class="flex items-start gap-4">
          <!-- Quick status toggle -->
          <form method="post" class="mt-1">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="status_change">
            <input type="hidden" name="id" value="<?= $task['id'] ?>">
            <input type="hidden" name="status" value="<?= $task['status'] === 'done' ? 'open' : 'done' ?>">
            <button class="w-5 h-5 rounded-md border-2 <?= $task['status'] === 'done' ? 'bg-green-500 border-green-500' : 'border-gray-300 hover:border-indigo-400' ?> flex items-center justify-center transition" title="Toggle done">
              <?php if ($task['status'] === 'done'): ?><i class="fas fa-check text-white" style="font-size:9px"></i><?php endif; ?>
            </button>
          </form>
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-3">
              <h3 class="text-sm font-semibold text-gray-900 <?= $task['status'] === 'done' ? 'line-through text-gray-400' : '' ?>"><?= h($task['title']) ?></h3>
              <div class="flex items-center gap-1.5 flex-shrink-0">
                <span class="badge bg-<?= $sc ?>-100 text-<?= $sc ?>-700 capitalize"><?= str_replace('_',' ',$task['status']) ?></span>
                <span class="badge bg-<?= $pc ?>-100 text-<?= $pc ?>-700 capitalize"><?= $task['priority'] ?></span>
              </div>
            </div>
            <?php if ($task['description']): ?>
            <p class="text-xs text-gray-500 mt-0.5"><?= h($task['description']) ?></p>
            <?php endif; ?>
            <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
              <?php if ($task['assignee_name']): ?>
              <span><i class="fas fa-user mr-1"></i><?= h($task['assignee_name']) ?></span>
              <?php endif; ?>
              <?php if ($task['due_date']): ?>
              <span class="<?= strtotime($task['due_date']) < time() && $task['status'] !== 'done' ? 'text-red-500 font-medium' : '' ?>">
                <i class="fas fa-calendar mr-1"></i><?= formatDate($task['due_date']) ?>
              </span>
              <?php endif; ?>
              <?php if ($task['feedback_title']): ?>
              <span><i class="fas fa-comment-dots mr-1"></i><?= h(mb_substr($task['feedback_title'], 0, 40)) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <form method="post" class="flex-shrink-0">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $task['id'] ?>">
            <button onclick="return confirm('Delete task?')" class="text-gray-300 hover:text-red-400 transition p-1">
              <i class="fas fa-trash" style="font-size:11px"></i>
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>

<!-- Task Modal -->
<div id="taskModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-6">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-lg font-bold text-gray-900">New Task</h2>
      <button onclick="document.getElementById('taskModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
    <form method="post" class="space-y-4">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="create">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Task Title *</label>
        <input type="text" name="title" required placeholder="What needs to be done?"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
        <textarea name="description" rows="2" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Priority</label>
          <select name="priority" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
            <option value="low">Low</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Due Date</label>
          <input type="date" name="due_date" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Assign To</label>
        <select name="assigned_to" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <option value="">Unassigned</option>
          <?php foreach ($teamMembers as $member): ?>
          <option value="<?= $member['id'] ?>"><?= h($member['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="document.getElementById('taskModal').classList.add('hidden')"
                class="flex-1 border border-gray-200 text-gray-700 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition">Cancel</button>
        <button class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-sm font-semibold transition">Create Task</button>
      </div>
    </form>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
