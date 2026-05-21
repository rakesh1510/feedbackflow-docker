<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('view_intelligence');
$userProjects = getUserProjects($currentUser['id']);
$pageTitle = 'Reports & Metrics – ' . APP_NAME;

Auth::start();
if (isset($_GET['project_id'])) $_SESSION['current_project_id'] = (int)$_GET['project_id'];
$projectId = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;

$period = $_GET['period'] ?? '30';
$periodDays = max(7, min(365, (int)$period));

if (!$projectId) {
    include dirname(__DIR__) . '/includes/header.php';
    echo '<div class="flex h-full">';
    include dirname(__DIR__) . '/includes/sidebar.php';
    echo '<main class="ml-64 flex-1 p-10 text-center"><p class="text-gray-500 mt-20">Please select a project to view reports.</p></main></div>';
    include dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// Core metrics
$totalFeedback = DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$projectId, $periodDays]);
$prevTotal     = DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL ? DAY) AND DATE_SUB(NOW(), INTERVAL ? DAY)", [$projectId, $periodDays * 2, $periodDays]);
$avgRating     = DB::count("SELECT COALESCE(AVG(rating),0) FROM ff_feedback WHERE project_id = ? AND rating IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$projectId, $periodDays]);
$positivePct   = $totalFeedback > 0 ? round(DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_sentiment='positive' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$projectId, $periodDays]) / $totalFeedback * 100) : 0;
$resolvedPct   = $totalFeedback > 0 ? round(DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND status='done' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$projectId, $periodDays]) / $totalFeedback * 100) : 0;

$change = $prevTotal > 0 ? round(($totalFeedback - $prevTotal) / $prevTotal * 100) : 0;

// Time series
$byDay = DB::fetchAll("SELECT DATE(created_at) as day, COUNT(*) as total, SUM(ai_sentiment='positive') as positive, SUM(ai_sentiment='negative') as negative, AVG(rating) as avg_rating FROM ff_feedback WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY DATE(created_at) ORDER BY day", [$projectId, $periodDays]);

// By source
$bySources = DB::fetchAll("SELECT COALESCE(source,'direct') as src, COUNT(*) as cnt FROM ff_feedback WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY src ORDER BY cnt DESC LIMIT 10", [$projectId, $periodDays]);

// By status
$byStatus = DB::fetchAll("SELECT status, COUNT(*) as cnt FROM ff_feedback WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY status ORDER BY cnt DESC", [$projectId, $periodDays]);

// By category
$byCategory = DB::fetchAll("SELECT c.name, c.color, COUNT(f.id) as cnt FROM ff_feedback f JOIN ff_categories c ON c.id = f.category_id WHERE f.project_id = ? AND f.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY c.id ORDER BY cnt DESC LIMIT 8", [$projectId, $periodDays]);

// Top feedback this period
$topFeedback = DB::fetchAll("SELECT f.*, c.name as category_name FROM ff_feedback f LEFT JOIN ff_categories c ON c.id = f.category_id WHERE f.project_id = ? AND f.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY f.vote_count DESC, f.created_at DESC LIMIT 10", [$projectId, $periodDays]);

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">

  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Reports & Metrics</h1>
      <p class="text-sm text-gray-500 mt-0.5"><?= $currentProject ? h($currentProject['name']) : '' ?> · Comprehensive analytics (Module 24)</p>
    </div>
    <div class="flex items-center gap-3">
      <form method="get" class="flex items-center gap-2">
        <label class="text-sm text-gray-500">Period:</label>
        <select name="period" onchange="this.form.submit()" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <?php foreach (['7'=>'Last 7 days','14'=>'Last 14 days','30'=>'Last 30 days','90'=>'Last 90 days','180'=>'Last 6 months','365'=>'Last year'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= $period == $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <a href="<?= APP_URL ?>/admin/export.php?download=analytics&project_id=<?= $projectId ?>"
         class="inline-flex items-center gap-2 border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-xl text-sm font-medium transition">
        <i class="fas fa-download"></i> Export
      </a>
    </div>
  </header>

  <div class="p-6 space-y-6">
    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <?php foreach ([
        ['Total Feedback','fas fa-comments','indigo', $totalFeedback, ($change >= 0 ? '+':'').$change.'%', $change >= 0 ? 'text-green-600':'text-red-500'],
        ['Avg. Rating','fas fa-star','amber', number_format((float)$avgRating, 1) . ' / 5', '', ''],
        ['Positive Sentiment','fas fa-smile','green', $positivePct . '%', '', ''],
        ['Resolution Rate','fas fa-check-circle','purple', $resolvedPct . '%', '', ''],
      ] as [$label, $icon, $color, $val, $change_str, $change_cls]): ?>
      <div class="ff-card p-5 stat-card">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm text-gray-500"><?= $label ?></span>
          <div class="w-8 h-8 rounded-lg bg-<?= $color ?>-100 flex items-center justify-center">
            <i class="<?= $icon ?> text-<?= $color ?>-600" style="font-size:13px"></i>
          </div>
        </div>
        <p class="text-2xl font-bold text-gray-900"><?= $val ?></p>
        <?php if ($change_str): ?>
        <p class="text-xs <?= $change_cls ?> mt-1 font-medium"><?= $change_str ?> vs prev period</p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Feedback over time chart -->
    <div class="ff-card p-6">
      <h2 class="text-base font-bold text-gray-900 mb-4">Feedback Volume</h2>
      <?php if (empty($byDay)): ?>
      <div class="h-40 flex items-center justify-center text-gray-400 text-sm">No data for this period.</div>
      <?php else: ?>
      <canvas id="volumeChart" height="120"></canvas>
      <script>
        new Chart(document.getElementById('volumeChart').getContext('2d'), {
          type: 'line',
          data: {
            labels: <?= json_encode(array_column($byDay, 'day')) ?>,
            datasets: [
              { label: 'Total', data: <?= json_encode(array_column($byDay, 'total')) ?>, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.08)', fill: true, tension: 0.3, pointRadius: 3 },
              { label: 'Positive', data: <?= json_encode(array_column($byDay, 'positive')) ?>, borderColor: '#22c55e', backgroundColor: 'transparent', tension: 0.3, pointRadius: 2 },
              { label: 'Negative', data: <?= json_encode(array_column($byDay, 'negative')) ?>, borderColor: '#ef4444', backgroundColor: 'transparent', tension: 0.3, pointRadius: 2 },
            ]
          },
          options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, interaction: { intersect: false, mode: 'index' } }
        });
      </script>
      <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- By Source -->
      <div class="ff-card p-6">
        <h2 class="text-base font-bold text-gray-900 mb-4">By Source</h2>
        <?php if (empty($bySources)): ?>
        <p class="text-gray-400 text-sm text-center py-4">No data</p>
        <?php else: ?>
        <?php $maxSrc = max(array_column($bySources, 'cnt')); ?>
        <div class="space-y-2">
          <?php foreach ($bySources as $src): ?>
          <div>
            <div class="flex justify-between text-xs mb-0.5">
              <span class="text-gray-600 capitalize"><?= h($src['src']) ?></span>
              <span class="font-semibold"><?= number_format($src['cnt']) ?></span>
            </div>
            <div class="bg-gray-100 rounded-full h-1.5">
              <div class="bg-indigo-500 h-1.5 rounded-full" style="width:<?= round($src['cnt']/$maxSrc*100) ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- By Status -->
      <div class="ff-card p-6">
        <h2 class="text-base font-bold text-gray-900 mb-4">By Status</h2>
        <canvas id="statusChart" height="180"></canvas>
        <script>
          new Chart(document.getElementById('statusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
              labels: <?= json_encode(array_map(fn($r) => ucwords(str_replace('_',' ',$r['status'])), $byStatus)) ?>,
              datasets: [{ data: <?= json_encode(array_column($byStatus, 'cnt')) ?>, backgroundColor: ['#6366f1','#f59e0b','#8b5cf6','#22c55e','#ef4444','#94a3b8','#06b6d4'] }]
            },
            options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }, cutout: '60%' }
          });
        </script>
      </div>

      <!-- By Category -->
      <div class="ff-card p-6">
        <h2 class="text-base font-bold text-gray-900 mb-4">By Category</h2>
        <?php if (empty($byCategory)): ?>
        <p class="text-gray-400 text-sm text-center py-4">No categories used</p>
        <?php else: ?>
        <?php $maxCat = max(array_column($byCategory, 'cnt')); ?>
        <div class="space-y-2">
          <?php foreach ($byCategory as $cat): ?>
          <div class="flex items-center gap-2">
            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= h($cat['color']) ?>"></div>
            <span class="text-xs text-gray-600 flex-1 truncate"><?= h($cat['name']) ?></span>
            <div class="w-16 bg-gray-100 rounded-full h-1.5">
              <div class="h-1.5 rounded-full" style="width:<?= round($cat['cnt']/$maxCat*100) ?>%;background:<?= h($cat['color']) ?>"></div>
            </div>
            <span class="text-xs font-semibold text-gray-700 w-6 text-right"><?= $cat['cnt'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Top Feedback -->
    <div class="ff-card p-6">
      <h2 class="text-base font-bold text-gray-900 mb-4">Top Feedback (by votes)</h2>
      <?php if (empty($topFeedback)): ?>
      <p class="text-gray-400 text-sm text-center py-4">No feedback in this period.</p>
      <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($topFeedback as $i => $fb): ?>
        <div class="flex items-center gap-4 py-2.5 border-b border-gray-50 last:border-0">
          <span class="w-6 text-center text-sm font-bold text-gray-300"><?= $i + 1 ?></span>
          <div class="flex-1 min-w-0">
            <a href="<?= APP_URL ?>/admin/feedback.php?id=<?= $fb['id'] ?>" class="text-sm font-medium text-gray-800 hover:text-indigo-600 truncate block"><?= h($fb['title']) ?></a>
            <div class="flex gap-2 mt-0.5">
              <?= statusBadge($fb['status']) ?>
              <?php if ($fb['category_name']): ?><span class="badge bg-gray-100 text-gray-500"><?= h($fb['category_name']) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="text-right">
            <p class="text-sm font-bold text-gray-800"><?= $fb['vote_count'] ?></p>
            <p class="text-xs text-gray-400">votes</p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
