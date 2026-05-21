<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('view_intelligence');
$userProjects = getUserProjects($currentUser['id']);
Auth::start();
if (isset($_GET['project_id'])) $_SESSION['current_project_id'] = (int)$_GET['project_id'];
$projectId      = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;
if (!$currentProject && !empty($userProjects)) { $currentProject = $userProjects[0]; $projectId = $currentProject['id']; }
if (!$currentProject) { redirect(APP_URL . '/admin/projects.php'); }

// Mark all insights as read
if ($projectId) DB::query("UPDATE ff_ai_insights SET is_read = 1 WHERE project_id = ?", [$projectId]);

// ── Data ────────────────────────────────────────────────────────────────────
$insights  = $projectId ? DB::fetchAll("SELECT * FROM ff_ai_insights WHERE project_id = ? ORDER BY generated_at DESC", [$projectId]) : [];
$clusters  = $projectId ? DB::fetchAll("SELECT * FROM ff_ai_clusters WHERE project_id = ? ORDER BY severity ASC, feedback_count DESC LIMIT 10", [$projectId]) : [];

// Sentiment breakdown this week vs last week
$thisWeek  = date('Y-m-d', strtotime('monday this week'));
$lastMonday = date('Y-m-d', strtotime('monday last week'));
$lastSunday = date('Y-m-d', strtotime('sunday last week'));

$sentNow  = $projectId ? DB::fetchAll("SELECT ai_sentiment, COUNT(*) as cnt FROM ff_feedback WHERE project_id = ? AND ai_sentiment IS NOT NULL AND created_at >= ? GROUP BY ai_sentiment", [$projectId, $thisWeek]) : [];
$sentPrev = $projectId ? DB::fetchAll("SELECT ai_sentiment, COUNT(*) as cnt FROM ff_feedback WHERE project_id = ? AND ai_sentiment IS NOT NULL AND created_at BETWEEN ? AND ? GROUP BY ai_sentiment", [$projectId, $lastMonday, $lastSunday]) : [];
$sentNowMap  = array_column($sentNow,  'cnt', 'ai_sentiment');
$sentPrevMap = array_column($sentPrev, 'cnt', 'ai_sentiment');

// Intent distribution
$intentDist = $projectId ? DB::fetchAll("SELECT ai_intent, COUNT(*) as cnt FROM ff_feedback WHERE project_id = ? AND ai_intent IS NOT NULL GROUP BY ai_intent ORDER BY cnt DESC", [$projectId]) : [];

// Top clusters by severity
$critClusters = array_filter($clusters, fn($c) => $c['severity'] === 'critical');

// Release impact
$releases = $projectId ? DB::fetchAll("SELECT * FROM ff_changelog WHERE project_id = ? AND published_at IS NOT NULL ORDER BY published_at DESC LIMIT 5", [$projectId]) : [];
$releaseImpact = [];
foreach ($releases as $rel) {
    $pub   = $rel['published_at'];
    $start = date('Y-m-d H:i:s', strtotime($pub . ' -7 days'));
    $end   = date('Y-m-d H:i:s', strtotime($pub . ' +7 days'));
    $negBefore = (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_sentiment='negative' AND created_at BETWEEN ? AND ?", [$projectId, $start, $pub]);
    $negAfter  = (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_sentiment='negative' AND created_at BETWEEN ? AND ?", [$projectId, $pub, $end]);
    $posBefore = (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_sentiment='positive' AND created_at BETWEEN ? AND ?", [$projectId, $start, $pub]);
    $posAfter  = (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_sentiment='positive' AND created_at BETWEEN ? AND ?", [$projectId, $pub, $end]);
    $releaseImpact[] = ['release' => $rel, 'neg_before' => $negBefore, 'neg_after' => $negAfter, 'pos_before' => $posBefore, 'pos_after' => $posAfter];
}

$totalFeedback = $projectId ? (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ?", [$projectId]) : 0;
$analyzedCount = $projectId ? (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_intent IS NOT NULL", [$projectId]) : 0;
$hasData       = !empty($insights) || !empty($clusters);

$pageTitle = 'AI Insights – ' . APP_NAME;
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto bg-gray-50">

  <!-- Header -->
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">AI Insights</h1>
      <p class="text-sm text-gray-500 mt-0.5">CEO-level view — what's happening and what to do next</p>
    </div>
    <div class="flex items-center gap-3">
      <?php if ($analyzedCount < $totalFeedback): ?>
        <span class="text-xs text-amber-600 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-lg">
          <?= $totalFeedback - $analyzedCount ?> items need analysis — run AI Copilot first
        </span>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/admin/ai-copilot.php"
         class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-xl text-sm transition">
        <i class="fas fa-robot"></i> Open AI Copilot
      </a>
    </div>
  </header>

  <?php foreach (getFlash() as $f): ?>
    <div data-flash class="mx-6 mt-4 px-4 py-3 rounded-xl text-sm <?= $f['type']==='success'?'bg-green-50 text-green-700 border border-green-200':'bg-red-50 text-red-700 border border-red-200' ?>"><?= h($f['msg']) ?></div>
  <?php endforeach; ?>

  <div class="p-6 space-y-6">

    <?php if (!$hasData): ?>
    <!-- Empty state -->
    <div class="bg-white rounded-2xl border border-gray-200 p-16 text-center">
      <div class="w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-5" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
        <i class="fas fa-brain text-3xl text-white"></i>
      </div>
      <h2 class="text-xl font-bold text-gray-900 mb-2">No insights yet</h2>
      <p class="text-gray-500 mb-6 max-w-md mx-auto">Run the AI Copilot analysis first to automatically group feedback, detect trends, and generate executive-level insights.</p>
      <a href="<?= APP_URL ?>/admin/ai-copilot.php" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-3 rounded-xl transition">
        <i class="fas fa-play"></i> Run AI Analysis Now
      </a>
      <?php if (!AI_ENABLED): ?>
        <p class="text-xs text-gray-400 mt-4">Tip: Add your OpenAI API key to config.php for smarter insights. Works without it too.</p>
      <?php endif; ?>
    </div>
    <?php else: ?>

    <!-- Insight Cards -->
    <?php if (!empty($insights)): ?>
    <div>
      <h2 class="text-base font-bold text-gray-900 mb-3">📋 Key Insights</h2>
      <div class="grid grid-cols-1 gap-4">
        <?php foreach ($insights as $ins):
          $typeColors = [
            'trending'       => 'border-red-200 bg-red-50',
            'sentiment'      => 'border-amber-200 bg-amber-50',
            'release_impact' => 'border-blue-200 bg-blue-50',
            'praise'         => 'border-green-200 bg-green-50',
            'warning'        => 'border-orange-200 bg-orange-50',
          ];
          $cls = $typeColors[$ins['type']] ?? 'border-gray-200 bg-white';
        ?>
        <div class="rounded-2xl border p-5 flex items-start gap-4 <?= $cls ?>">
          <span class="text-2xl flex-shrink-0 mt-0.5"><?= $ins['icon'] ?: '📊' ?></span>
          <div class="flex-1">
            <p class="font-semibold text-gray-900"><?= h($ins['title']) ?></p>
            <?php if ($ins['body']): ?>
              <p class="text-sm text-gray-600 mt-1"><?= h($ins['body']) ?></p>
            <?php endif; ?>
          </div>
          <?php if ($ins['metric']): ?>
            <span class="flex-shrink-0 text-sm font-bold px-3 py-1 rounded-full bg-white border border-gray-200 text-gray-700"><?= h($ins['metric']) ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- 2-col grid: Sentiment + Intent -->
    <div class="grid grid-cols-2 gap-6">

      <!-- Sentiment This Week vs Last Week -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="font-bold text-gray-900 mb-4">Sentiment This Week vs Last</h2>
        <?php foreach (['positive' => ['text-green-600','bg-green-500','😊'], 'neutral' => ['text-gray-500','bg-gray-400','😐'], 'negative' => ['text-red-600','bg-red-500','😞']] as $sent => [$tc, $bc, $emoji]): ?>
          <?php $now = (int)($sentNowMap[$sent] ?? 0); $prev = (int)($sentPrevMap[$sent] ?? 0); $max = max($now, $prev, 1); ?>
          <div class="mb-4">
            <div class="flex items-center justify-between text-sm mb-1.5">
              <span class="font-medium text-gray-700"><?= $emoji ?> <?= ucfirst($sent) ?></span>
              <span class="<?= $tc ?> font-semibold">
                <?= $now ?> this week
                <?php if ($prev > 0): ?>
                  <span class="text-gray-400 font-normal">(<?= $prev ?> last)</span>
                <?php endif; ?>
              </span>
            </div>
            <div class="relative h-2 bg-gray-100 rounded-full overflow-hidden">
              <div class="absolute left-0 top-0 h-full <?= $bc ?> rounded-full opacity-30" style="width:<?= min(100, (int)($prev/$max*100)) ?>%"></div>
              <div class="absolute left-0 top-0 h-full <?= $bc ?> rounded-full" style="width:<?= min(100, (int)($now/$max*100)) ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Intent Distribution -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="font-bold text-gray-900 mb-4">Feedback by Type</h2>
        <?php
        $intentMeta = [
          'bug'         => ['🐛','bg-red-500','text-red-700','bg-red-50'],
          'feature'     => ['💡','bg-indigo-500','text-indigo-700','bg-indigo-50'],
          'ux'          => ['🎨','bg-purple-500','text-purple-700','bg-purple-50'],
          'performance' => ['⚡','bg-yellow-500','text-yellow-700','bg-yellow-50'],
          'pricing'     => ['💰','bg-green-500','text-green-700','bg-green-50'],
          'praise'      => ['🎉','bg-teal-500','text-teal-700','bg-teal-50'],
          'other'       => ['📝','bg-gray-400','text-gray-600','bg-gray-50'],
        ];
        $totalIntent = array_sum(array_column($intentDist, 'cnt')) ?: 1;
        ?>
        <?php if (empty($intentDist)): ?>
          <p class="text-sm text-gray-400 text-center mt-8">No data yet — run AI Copilot to analyze.</p>
        <?php else: ?>
          <?php foreach ($intentDist as $row):
            $meta = $intentMeta[$row['ai_intent']] ?? $intentMeta['other'];
            $pct  = (int)($row['cnt'] / $totalIntent * 100);
          ?>
          <div class="flex items-center gap-3 mb-2.5">
            <span class="text-base w-6 text-center"><?= $meta[0] ?></span>
            <div class="flex-1">
              <div class="flex justify-between text-xs text-gray-600 mb-1">
                <span class="font-medium"><?= ucfirst($row['ai_intent']) ?></span>
                <span><?= $row['cnt'] ?> (<?= $pct ?>%)</span>
              </div>
              <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full <?= $meta[1] ?> rounded-full" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Top Issue Clusters -->
    <?php if (!empty($clusters)): ?>
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-5">
        <h2 class="font-bold text-gray-900">Top Issue Clusters</h2>
        <a href="<?= APP_URL ?>/admin/ai-copilot.php" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">View all + take action →</a>
      </div>
      <div class="space-y-3">
        <?php foreach ($clusters as $cl):
          $sevColor = ['critical' => 'bg-red-100 text-red-700 border-red-200', 'high' => 'bg-orange-100 text-orange-700 border-orange-200', 'medium' => 'bg-yellow-100 text-yellow-700 border-yellow-200', 'low' => 'bg-gray-100 text-gray-600 border-gray-200'][$cl['severity']] ?? 'bg-gray-100 text-gray-600';
          $sentIcon = ['positive' => '😊', 'neutral' => '😐', 'negative' => '😞'][$cl['avg_sentiment']] ?? '😐';
          $trendIcon = ['rising' => '↑', 'falling' => '↓', 'stable' => '→'][$cl['trend']] ?? '→';
          $trendColor = ['rising' => 'text-red-600', 'falling' => 'text-green-600', 'stable' => 'text-gray-400'][$cl['trend']] ?? 'text-gray-400';
          $intentIcons = ['bug'=>'🐛','feature'=>'💡','ux'=>'🎨','performance'=>'⚡','pricing'=>'💰','praise'=>'🎉','other'=>'📝'];
        ?>
        <div class="flex items-center gap-4 p-4 rounded-xl border border-gray-100 hover:bg-gray-50 transition">
          <span class="text-xl flex-shrink-0"><?= $intentIcons[$cl['intent']] ?? '📝' ?></span>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-gray-900 text-sm truncate"><?= h($cl['title']) ?></p>
            <p class="text-xs text-gray-500 mt-0.5"><?= $cl['feedback_count'] ?> reports · <?= $sentIcon ?> <?= ucfirst($cl['avg_sentiment'] ?? 'neutral') ?></p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <span class="text-sm font-bold <?= $trendColor ?>"><?= $trendIcon ?> <?= $cl['trend_pct'] ?>%</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border <?= $sevColor ?>"><?= ucfirst($cl['severity']) ?></span>
            <a href="<?= APP_URL ?>/admin/ai-copilot.php#cluster-<?= $cl['id'] ?>" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium px-2 py-1 rounded-lg hover:bg-indigo-50">Act →</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Release Impact -->
    <?php if (!empty($releaseImpact)): ?>
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="font-bold text-gray-900 mb-5">📦 Release Impact Tracker</h2>
      <p class="text-sm text-gray-500 mb-4">Feedback sentiment 7 days before vs after each release.</p>
      <div class="space-y-4">
        <?php foreach ($releaseImpact as $ri):
          $negDiff = $ri['neg_after'] - $ri['neg_before'];
          $posDiff = $ri['pos_after'] - $ri['pos_before'];
          $impact = ($negDiff < 0) ? 'positive' : (($negDiff > 0) ? 'negative' : 'neutral');
          $impactColor = ['positive' => 'bg-green-50 border-green-200', 'negative' => 'bg-red-50 border-red-200', 'neutral' => 'bg-gray-50 border-gray-200'][$impact];
        ?>
        <div class="rounded-xl border p-4 <?= $impactColor ?>">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="font-semibold text-gray-900 text-sm"><?= h($ri['release']['title']) ?></p>
              <p class="text-xs text-gray-500 mt-0.5">Released <?= formatDate($ri['release']['published_at']) ?><?= $ri['release']['version'] ? ' · v' . h($ri['release']['version']) : '' ?></p>
            </div>
            <?php if ($negDiff < 0): ?>
              <span class="text-green-700 font-bold text-sm flex-shrink-0">↓ <?= abs($negDiff) ?> fewer complaints</span>
            <?php elseif ($negDiff > 0): ?>
              <span class="text-red-700 font-bold text-sm flex-shrink-0">↑ <?= $negDiff ?> more complaints</span>
            <?php else: ?>
              <span class="text-gray-500 font-medium text-sm flex-shrink-0">No change</span>
            <?php endif; ?>
          </div>
          <div class="grid grid-cols-2 gap-4 mt-3 text-xs">
            <div class="flex gap-6">
              <span class="text-gray-500">😞 Negative: <strong><?= $ri['neg_before'] ?></strong> before / <strong><?= $ri['neg_after'] ?></strong> after</span>
            </div>
            <div>
              <span class="text-gray-500">😊 Positive: <strong><?= $ri['pos_before'] ?></strong> before / <strong><?= $ri['pos_after'] ?></strong> after</span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php endif; // hasData ?>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
