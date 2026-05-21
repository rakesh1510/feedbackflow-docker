<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('view_notifications');
$userProjects = getUserProjects($currentUser['id']);
$pageTitle = 'Notifications – ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read' && isset($_POST['id'])) {
        DB::update('ff_notifications', ['is_read' => 1], 'id = ? AND user_id = ?', [(int)$_POST['id'], $currentUser['id']]);
    }
    if ($action === 'mark_all_read') {
        DB::query("UPDATE ff_notifications SET is_read = 1 WHERE user_id = ?", [$currentUser['id']]);
        flash('success', 'All notifications marked as read.');
    }
    if ($action === 'delete' && isset($_POST['id'])) {
        DB::delete('ff_notifications', 'id = ? AND user_id = ?', [(int)$_POST['id'], $currentUser['id']]);
    }
    if ($action === 'delete_all_read') {
        DB::query("DELETE FROM ff_notifications WHERE user_id = ? AND is_read = 1", [$currentUser['id']]);
        flash('success', 'Read notifications cleared.');
    }
    redirect(APP_URL . '/admin/notifications.php');
}

$filter = $_GET['filter'] ?? 'all';
$where = $filter === 'unread' ? "AND is_read = 0" : ($filter === 'read' ? "AND is_read = 1" : "");
$notifications = DB::fetchAll("SELECT * FROM ff_notifications WHERE user_id = ? $where ORDER BY created_at DESC LIMIT 100", [$currentUser['id']]);
$unreadCount   = DB::count("SELECT COUNT(*) FROM ff_notifications WHERE user_id = ? AND is_read = 0", [$currentUser['id']]);

$typeIcons = [
    'new_feedback'       => ['fas fa-comment-dots','indigo'],
    'feedback_reply'     => ['fas fa-reply','blue'],
    'mention'            => ['fas fa-at','purple'],
    'campaign_sent'      => ['fas fa-paper-plane','green'],
    'limit_warning'      => ['fas fa-exclamation-triangle','amber'],
    'billing'            => ['fas fa-credit-card','red'],
    'workflow_triggered' => ['fas fa-bolt','yellow'],
    'team_invite'        => ['fas fa-user-plus','teal'],
    'system'             => ['fas fa-cog','gray'],
];

$flash = getFlash();
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">

  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <h1 class="text-xl font-bold text-gray-900">Notifications</h1>
      <?php if ($unreadCount > 0): ?>
      <span class="badge bg-red-100 text-red-700"><?= $unreadCount ?> unread</span>
      <?php endif; ?>
    </div>
    <div class="flex gap-2">
      <?php if ($unreadCount > 0): ?>
      <form method="post">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button class="inline-flex items-center gap-2 border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-xl text-sm font-medium transition">
          <i class="fas fa-check-double"></i> Mark All Read
        </button>
      </form>
      <?php endif; ?>
      <form method="post">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="delete_all_read">
        <button class="inline-flex items-center gap-2 border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-xl text-sm font-medium transition">
          <i class="fas fa-broom"></i> Clear Read
        </button>
      </form>
    </div>
  </header>

  <div class="p-6 space-y-4">
    <?php foreach ($flash as $f): ?>
      <div class="rounded-xl px-4 py-3 text-sm font-medium <?= $f['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
        <?= h($f['msg']) ?>
      </div>
    <?php endforeach; ?>

    <!-- Filter tabs -->
    <div class="flex gap-1 border-b border-gray-200">
      <?php foreach (['all'=>'All','unread'=>'Unread','read'=>'Read'] as $k => $label): ?>
      <a href="?filter=<?= $k ?>"
         class="px-4 py-2.5 text-sm font-medium border-b-2 transition <?= $filter === $k ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($notifications)): ?>
    <div class="ff-card p-16 text-center">
      <i class="fas fa-bell text-4xl text-gray-200 mb-3"></i>
      <p class="text-gray-400 font-medium">No notifications</p>
      <p class="text-sm text-gray-300 mt-1">You're all caught up! 🎉</p>
    </div>
    <?php else: ?>
    <div class="ff-card divide-y divide-gray-50 overflow-hidden">
      <?php foreach ($notifications as $notif): ?>
      <?php [$icon, $color] = $typeIcons[$notif['type']] ?? ['fas fa-bell', 'gray']; ?>
      <div class="flex items-start gap-4 p-4 <?= !$notif['is_read'] ? 'bg-indigo-50/30' : 'hover:bg-gray-50' ?> transition-colors">
        <div class="w-9 h-9 rounded-xl bg-<?= $color ?>-100 flex items-center justify-center flex-shrink-0 mt-0.5">
          <i class="<?= $icon ?> text-<?= $color ?>-600" style="font-size:13px"></i>
        </div>
        <div class="flex-1 min-w-0">
          <?php if (!$notif['is_read']): ?>
          <div class="flex items-center gap-1.5 mb-0.5">
            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 flex-shrink-0"></span>
            <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wide">New</span>
          </div>
          <?php endif; ?>
          <p class="text-sm text-gray-800 <?= !$notif['is_read'] ? 'font-semibold' : '' ?>"><?= h($notif['message']) ?></p>
          <p class="text-xs text-gray-400 mt-0.5"><?= timeAgo($notif['created_at']) ?></p>
        </div>
        <div class="flex gap-1 flex-shrink-0">
          <?php if (!$notif['is_read']): ?>
          <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="mark_read">
            <input type="hidden" name="id" value="<?= $notif['id'] ?>">
            <button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Mark read">
              <i class="fas fa-check" style="font-size:11px"></i>
            </button>
          </form>
          <?php endif; ?>
          <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $notif['id'] ?>">
            <button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Delete">
              <i class="fas fa-times" style="font-size:11px"></i>
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
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
