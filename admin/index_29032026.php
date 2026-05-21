<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('view_dashboard');
$userProjects = getUserProjects($currentUser['id']);

// Project selection
if (isset($_GET['project_id'])) {
    Auth::start();
    $_SESSION['current_project_id'] = (int)$_GET['project_id'];
}
Auth::start();
$projectId = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;
if (!$currentProject && !empty($userProjects)) {
    $currentProject = $userProjects[0];
    $_SESSION['current_project_id'] = $currentProject['id'];
    $projectId = $currentProject['id'];
}

$pageTitle = 'Dashboard – ' . APP_NAME;

// Stats
$stats = [];
if ($projectId) {
    $stats['total'] = DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ?", [$projectId]);
    $stats['new'] = DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND status = 'new'", [$projectId]);
    $stats['planned'] = DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND status = 'planned'", [$projectId]);
    $stats['done'] = DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND status = 'done'", [$projectId]);
    $stats['votes'] = DB::count("SELECT COALESCE(SUM(vote_count),0) FROM ff_feedback WHERE project_id = ?", [$projectId]);
    $recentFeedback = DB::fetchAll(
        "SELECT f.*, c.name as category_name, c.color as category_color 
         FROM ff_feedback f LEFT JOIN ff_categories c ON c.id = f.category_id
         WHERE f.project_id = ? ORDER BY f.created_at DESC LIMIT 8",
        [$projectId]
    );
    // Chart data: last 30 days
    $chartData = DB::fetchAll(
        "SELECT DATE(created_at) as day, COUNT(*) as count FROM ff_feedback 
         WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY DATE(created_at) ORDER BY day",
        [$projectId]
    );
    // Status breakdown
    $statusBreakdown = DB::fetchAll(
        "SELECT status, COUNT(*) as count FROM ff_feedback WHERE project_id = ? GROUP BY status",
        [$projectId]
    );
    // Top categories
    $topCategories = DB::fetchAll(
        "SELECT c.name, c.color, COUNT(f.id) as count FROM ff_feedback f
         JOIN ff_categories c ON c.id = f.category_id WHERE f.project_id = ?
         GROUP BY c.id ORDER BY count DESC LIMIT 5",
        [$projectId]
    );
    // Recent activity
    $recentActivity = DB::fetchAll(
        "SELECT a.*, u.name as user_name FROM ff_activity a LEFT JOIN ff_users u ON u.id = a.user_id
         WHERE a.project_id = ? ORDER BY a.created_at DESC LIMIT 10",
        [$projectId]
    );
}

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">
  <!-- Top Bar -->
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Dashboard</h1>
      <?php if ($currentProject): ?>
        <p class="text-sm text-gray-500 mt-0.5"><?= h($currentProject['name']) ?></p>
      <?php endif; ?>
    </div>
    <div class="flex items-center gap-3">
      <?php if ($currentProject): ?>
        <a href="<?= APP_URL ?>/admin/feedback.php?action=new&project_id=<?= $projectId ?>" 
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
          <i class="fas fa-plus"></i> New Feedback
        </a>
      <?php endif; ?>
    </div>
  </header>

  <div class="p-6 space-y-6">
    <?php if (!$currentProject): ?>
      <!-- Empty state: no projects -->
      <div class="bg-white rounded-2xl border border-gray-200 p-16 text-center">
        <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-folder-open text-indigo-600 text-2xl"></i>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">Create your first project</h2>
        <p class="text-gray-500 mb-6">Get started by creating a project to collect and manage feedback.</p>
        <a href="<?= APP_URL ?>/admin/projects.php?action=new" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-3 rounded-xl transition">
          <i class="fas fa-plus"></i> Create Project
        </a>
      </div>
    <?php else: ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
      <?php $cards = [
        ['Total Feedback', $stats['total'], 'comments', 'indigo', ''],
        ['New', $stats['new'], 'star', 'blue', 'text-blue-600'],
        ['Planned', $stats['planned'], 'map', 'purple', 'text-purple-600'],
        ['Done', $stats['done'], 'check-circle', 'green', 'text-green-600'],
        ['Total Votes', $stats['votes'], 'thumbs-up', 'orange', 'text-orange-600'],
      ];
      foreach ($cards as [$label, $value, $icon, $color, $textColor]): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4">
          <div class="w-11 h-11 bg-<?= $color ?>-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-<?= $icon ?> text-<?= $color ?>-600"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-900"><?= number_format($value) ?></p>
            <p class="text-xs text-gray-500 mt-0.5"><?= $label ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Line Chart: Feedback over time -->
      <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">Feedback Over Time <span class="text-xs text-gray-400 font-normal ml-1">Last 30 days</span></h3>
        <canvas id="lineChart" height="80"></canvas>
      </div>
      <!-- Donut Chart: Status breakdown -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">Status Breakdown</h3>
        <canvas id="donutChart" height="160"></canvas>
        <div class="mt-4 space-y-1.5">
          <?php foreach ($statusBreakdown as $sb): ?>
            <div class="flex items-center justify-between text-sm">
              <span class="flex items-center gap-2 text-gray-600"><?= statusBadge($sb['status']) ?></span>
              <span class="font-semibold text-gray-900"><?= $sb['count'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Recent Feedback + Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Recent Feedback -->
      <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900">Recent Feedback</h3>
          <a href="<?= APP_URL ?>/admin/feedback.php" class="text-sm text-indigo-600 hover:underline">View all</a>
        </div>
        <div class="divide-y divide-gray-50">
          <?php if (empty($recentFeedback)): ?>
            <div class="px-6 py-12 text-center text-gray-400">
              <i class="fas fa-inbox text-3xl mb-2 block"></i>
              No feedback yet. Share your widget to start collecting!
            </div>
          <?php else: foreach ($recentFeedback as $fb): ?>
            <a href="<?= APP_URL ?>/admin/feedback.php?id=<?= $fb['id'] ?>" class="flex items-start gap-4 px-6 py-4 hover:bg-gray-50 transition group">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <?php if ($fb['category_name']): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background:<?= h($fb['category_color']) ?>20;color:<?= h($fb['category_color']) ?>"><?= h($fb['category_name']) ?></span>
                  <?php endif; ?>
                  <?= statusBadge($fb['status']) ?>
                </div>
                <p class="font-medium text-gray-900 mt-1 group-hover:text-indigo-600 truncate"><?= h($fb['title']) ?></p>
                <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-2">
                  <span><i class="fas fa-thumbs-up mr-1"></i><?= $fb['vote_count'] ?></span>
                  <span><i class="fas fa-comment mr-1"></i><?= $fb['comment_count'] ?></span>
                  <span><?= timeAgo($fb['created_at']) ?></span>
                </p>
              </div>
              <?= priorityBadge($fb['priority']) ?>
            </a>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Activity + Top Categories -->
      <div class="space-y-6">
        <!-- Top Categories -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Top Categories</h3>
          <?php if (empty($topCategories)): ?>
            <p class="text-sm text-gray-400">No data yet</p>
          <?php else: foreach ($topCategories as $cat): ?>
            <div class="flex items-center gap-3 mb-3">
              <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= h($cat['color']) ?>"></div>
              <span class="text-sm text-gray-700 flex-1 truncate"><?= h($cat['name']) ?></span>
              <span class="text-sm font-semibold text-gray-900"><?= $cat['count'] ?></span>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Recent Activity</h3>
          <div class="space-y-3">
            <?php if (empty($recentActivity)): ?>
              <p class="text-sm text-gray-400">No activity yet</p>
            <?php else: foreach (array_slice($recentActivity, 0, 5) as $act): ?>
              <div class="flex items-start gap-3">
                <div class="w-7 h-7 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                  <i class="fas fa-bolt text-indigo-600 text-xs"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs text-gray-600 leading-relaxed"><?= h($act['action']) ?></p>
                  <p class="text-xs text-gray-400 mt-0.5"><?= timeAgo($act['created_at']) ?></p>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php endif; ?>
  </div>
</main>
</div>

<?php
// Build chart data
$chartDays = [];
$chartCounts = [];
if (!empty($chartData)) {
    foreach ($chartData as $d) { $chartDays[] = date('M j', strtotime($d['day'])); $chartCounts[] = $d['count']; }
}
$statusColors = ['new' => '#6366f1','under_review' => '#f59e0b','planned' => '#8b5cf6','in_progress' => '#f97316','done' => '#10b981','declined' => '#ef4444','duplicate' => '#6b7280'];
$donutLabels = $donutData = $donutColors = [];
foreach ($statusBreakdown ?? [] as $s) {
    $donutLabels[] = ucfirst(str_replace('_', ' ', $s['status']));
    $donutData[] = $s['count'];
    $donutColors[] = $statusColors[$s['status']] ?? '#94a3b8';
}
?>
<script>
// Line chart
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartDays) ?>,
        datasets: [{
            label: 'Feedback',
            data: <?= json_encode($chartCounts) ?>,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.08)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#6366f1',
            pointRadius: 3,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
// Donut chart
<?php if (!empty($donutData)): ?>
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($donutLabels) ?>,
        datasets: [{ data: <?= json_encode($donutData) ?>, backgroundColor: <?= json_encode($donutColors) ?>, borderWidth: 2 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, cutout: '65%' }
});
<?php endif; ?>
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
