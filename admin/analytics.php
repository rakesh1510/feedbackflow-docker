<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('view_intelligence');
$userProjects = getUserProjects($currentUser['id']);
Auth::start();
if (isset($_GET['project_id'])) $_SESSION['current_project_id'] = (int)$_GET['project_id'];
$projectId = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;
if (!$currentProject && !empty($userProjects)) { $currentProject = $userProjects[0]; $projectId = $currentProject['id']; }

$range = $_GET['range'] ?? '30';
$rangeLabel = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last year'][$range] ?? 'Last 30 days';

// Analytics data
$feedbackOverTime = DB::fetchAll(
    "SELECT DATE(created_at) as day, COUNT(*) as count FROM ff_feedback WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL $range DAY) GROUP BY DATE(created_at) ORDER BY day",
    [$projectId]
);
$statusDist = DB::fetchAll("SELECT status, COUNT(*) as count FROM ff_feedback WHERE project_id = ? GROUP BY status ORDER BY count DESC", [$projectId]);
$sentimentDist = DB::fetchAll("SELECT ai_sentiment, COUNT(*) as count FROM ff_feedback WHERE project_id = ? AND ai_sentiment IS NOT NULL GROUP BY ai_sentiment", [$projectId]);
$categoryDist = DB::fetchAll("SELECT c.name, c.color, COUNT(f.id) as count FROM ff_feedback f JOIN ff_categories c ON c.id = f.category_id WHERE f.project_id = ? GROUP BY c.id ORDER BY count DESC", [$projectId]);
$priorityDist = DB::fetchAll("SELECT priority, COUNT(*) as count FROM ff_feedback WHERE project_id = ? GROUP BY priority ORDER BY FIELD(priority,'critical','high','medium','low')", [$projectId]);
$topVoted = DB::fetchAll("SELECT f.*, c.name as cat_name FROM ff_feedback f LEFT JOIN ff_categories c ON c.id = f.category_id WHERE f.project_id = ? ORDER BY f.vote_count DESC LIMIT 10", [$projectId]);
$responseTime = DB::fetch("SELECT AVG(TIMESTAMPDIFF(HOUR, f.created_at, cm.created_at)) as avg_hours FROM ff_feedback f JOIN ff_comments cm ON cm.feedback_id = f.id AND cm.is_admin_reply = 1 WHERE f.project_id = ? AND f.created_at >= DATE_SUB(NOW(), INTERVAL $range DAY)", [$projectId]);

// Totals
$totals = [
    'total'    => DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL $range DAY)", [$projectId]),
    'votes'    => DB::count("SELECT COALESCE(SUM(vote_count),0) FROM ff_feedback WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL $range DAY)", [$projectId]),
    'done'     => DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND status='done' AND created_at >= DATE_SUB(NOW(), INTERVAL $range DAY)", [$projectId]),
    'avg_resp' => $responseTime ? round($responseTime['avg_hours'] ?? 0) : 0,
];

$pageTitle = 'Analytics – ' . APP_NAME;
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Analytics</h1>
      <p class="text-sm text-gray-500"><?= $rangeLabel ?></p>
    </div>
    <form method="GET" class="flex items-center gap-2">
      <input type="hidden" name="project_id" value="<?= $projectId ?>">
      <select name="range" onchange="this.form.submit()" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        <option value="7" <?= $range=='7'?'selected':'' ?>>Last 7 days</option>
        <option value="30" <?= $range=='30'?'selected':'' ?>>Last 30 days</option>
        <option value="90" <?= $range=='90'?'selected':'' ?>>Last 90 days</option>
        <option value="365" <?= $range=='365'?'selected':'' ?>>Last year</option>
      </select>
    </form>
  </header>

  <div class="p-6 space-y-6">
    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <?php $kpis = [
        ['Total Feedback', $totals['total'], 'comments', 'indigo', 'Submissions received'],
        ['Total Votes', $totals['votes'], 'thumbs-up', 'blue', 'Community engagement'],
        ['Resolved', $totals['done'], 'check-circle', 'green', 'Marked as done'],
        ['Avg Response', $totals['avg_resp'] . 'h', 'clock', 'orange', 'Average response time'],
      ]; foreach ($kpis as [$label, $val, $icon, $color, $sub]): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-medium text-gray-500"><?= $label ?></p>
            <div class="w-9 h-9 bg-<?= $color ?>-50 rounded-xl flex items-center justify-center">
              <i class="fas fa-<?= $icon ?> text-<?= $color ?>-600 text-sm"></i>
            </div>
          </div>
          <p class="text-3xl font-bold text-gray-900"><?= $val ?></p>
          <p class="text-xs text-gray-400 mt-1"><?= $sub ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Charts Row 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">Feedback Trend</h3>
        <canvas id="trendChart" height="100"></canvas>
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">Status Distribution</h3>
        <canvas id="statusChart" height="100"></canvas>
      </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">By Category</h3>
        <?php foreach ($categoryDist as $cat): ?>
          <div class="flex items-center gap-3 mb-3">
            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= h($cat['color']) ?>"></div>
            <span class="text-sm text-gray-600 flex-1"><?= h($cat['name']) ?></span>
            <span class="text-sm font-bold text-gray-900"><?= $cat['count'] ?></span>
          </div>
          <div class="mb-3 bg-gray-100 rounded-full h-1.5">
            <?php $max = max(array_column($categoryDist,'count')); ?>
            <div class="h-1.5 rounded-full" style="width:<?= $max ? round($cat['count']/$max*100) : 0 ?>%;background:<?= h($cat['color']) ?>"></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">By Priority</h3>
        <canvas id="priorityChart" height="200"></canvas>
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">AI Sentiment</h3>
        <?php if (empty($sentimentDist)): ?>
          <div class="text-center py-8 text-gray-400 text-sm">
            <i class="fas fa-robot text-3xl block mb-2"></i>
            Enable AI to see sentiment analysis
          </div>
        <?php else: ?>
          <canvas id="sentimentChart" height="200"></canvas>
        <?php endif; ?>
      </div>
    </div>

    <!-- Top Voted Feedback -->
    <div class="bg-white rounded-2xl border border-gray-200">
      <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Top Voted Feedback</h3>
      </div>
      <div class="divide-y divide-gray-50">
        <?php foreach ($topVoted as $i => $fb): ?>
          <a href="<?= APP_URL ?>/admin/feedback.php?id=<?= $fb['id'] ?>" class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 transition">
            <span class="w-8 h-8 flex items-center justify-center rounded-xl text-sm font-bold <?= $i < 3 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500' ?>"><?= $i+1 ?></span>
            <div class="flex-1 min-w-0">
              <p class="font-medium text-gray-900 truncate"><?= h($fb['title']) ?></p>
              <?php if ($fb['cat_name']): ?><p class="text-xs text-gray-400"><?= h($fb['cat_name']) ?></p><?php endif; ?>
            </div>
            <?= statusBadge($fb['status']) ?>
            <div class="flex items-center gap-1 text-sm font-semibold text-indigo-600 min-w-12">
              <i class="fas fa-thumbs-up text-sm"></i> <?= $fb['vote_count'] ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>
</div>

<?php
$trendDays = array_column($feedbackOverTime, 'day');
$trendCounts = array_column($feedbackOverTime, 'count');
$trendDaysFormatted = array_map(fn($d) => date('M j', strtotime($d)), $trendDays);
$statusColors = ['new'=>'#6366f1','under_review'=>'#f59e0b','planned'=>'#8b5cf6','in_progress'=>'#f97316','done'=>'#10b981','declined'=>'#ef4444','duplicate'=>'#6b7280'];
?>
<script>
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: { labels: <?= json_encode($trendDaysFormatted) ?>, datasets: [{ label: 'Feedback', data: <?= json_encode($trendCounts) ?>, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)', fill: true, tension: 0.4, pointRadius: 2 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
new Chart(document.getElementById('statusChart'), {
    type: 'bar',
    data: { labels: <?= json_encode(array_map(fn($s) => ucfirst(str_replace('_', ' ', $s['status'])), $statusDist)) ?>, datasets: [{ data: <?= json_encode(array_column($statusDist, 'count')) ?>, backgroundColor: <?= json_encode(array_map(fn($s) => $statusColors[$s['status']] ?? '#94a3b8', $statusDist)) ?>, borderRadius: 8 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
new Chart(document.getElementById('priorityChart'), {
    type: 'doughnut',
    data: { labels: <?= json_encode(array_map(fn($p) => ucfirst($p['priority']), $priorityDist)) ?>, datasets: [{ data: <?= json_encode(array_column($priorityDist,'count')) ?>, backgroundColor: ['#ef4444','#f97316','#f59e0b','#94a3b8'] }] },
    options: { responsive: true, cutout: '60%' }
});
<?php if (!empty($sentimentDist)): ?>
new Chart(document.getElementById('sentimentChart'), {
    type: 'doughnut',
    data: { labels: <?= json_encode(array_map(fn($s) => ucfirst($s['ai_sentiment']), $sentimentDist)) ?>, datasets: [{ data: <?= json_encode(array_column($sentimentDist,'count')) ?>, backgroundColor: ['#10b981','#94a3b8','#ef4444'] }] },
    options: { responsive: true, cutout: '60%' }
});
<?php endif; ?>
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
