<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/db-manager.php';
require_once dirname(__DIR__) . '/includes/functions.php';


$slug = $_GET['slug'] ?? '';
if ($slug) {
    $resolvedCompanyId = DBManager::findCompanyIdByProjectSlug($slug);
    if ($resolvedCompanyId) {
        DB::useTenantForCompany($resolvedCompanyId);
    }
}
$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    // Show first public status page
    $page = DB::fetch("SELECT * FROM ff_status_pages WHERE is_public = 1 LIMIT 1");
} else {
    $page = DB::fetch("SELECT * FROM ff_status_pages WHERE slug = ? AND is_public = 1", [$slug]);
}

$incidents = [];
if ($page) {
    $incidents = DB::fetchAll("SELECT * FROM ff_status_incidents WHERE page_id = ? ORDER BY created_at DESC LIMIT 30", [$page['id']]);
}

$statusMap = [
    'operational'    => ['All Systems Operational', 'bg-green-500', 'fas fa-check-circle', 'bg-green-50 border-green-200 text-green-800'],
    'degraded'       => ['Degraded Performance', 'bg-yellow-500', 'fas fa-exclamation-circle', 'bg-yellow-50 border-yellow-200 text-yellow-800'],
    'partial_outage' => ['Partial Outage', 'bg-orange-500', 'fas fa-minus-circle', 'bg-orange-50 border-orange-200 text-orange-800'],
    'major_outage'   => ['Major Outage', 'bg-red-500', 'fas fa-times-circle', 'bg-red-50 border-red-200 text-red-800'],
    'maintenance'    => ['Under Maintenance', 'bg-blue-500', 'fas fa-wrench', 'bg-blue-50 border-blue-200 text-blue-800'],
];

$overallStatus = $page ? ($page['overall_status'] ?? 'operational') : 'operational';
[$statusText, $statusBg, $statusIcon, $bannerClass] = $statusMap[$overallStatus] ?? $statusMap['operational'];

$incidentSeverityColors = ['minor'=>'yellow','major'=>'orange','critical'=>'red','maintenance'=>'blue'];
$incidentStatusColors = ['investigating'=>'red','identified'=>'orange','monitoring'=>'blue','resolved'=>'green'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page ? h($page['name']) : 'System Status' ?> – <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif; }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">

  <!-- Top nav -->
  <nav class="bg-white border-b border-gray-200">
    <div class="max-w-3xl mx-auto px-4 py-4 flex items-center justify-between">
      <a href="<?= APP_URL ?>" class="flex items-center gap-2.5 font-bold text-gray-900">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs font-bold" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
          <i class="fas fa-comments"></i>
        </div>
        <?= APP_NAME ?>
      </a>
      <span class="text-sm text-gray-400">Status Page</span>
    </div>
  </nav>

  <main class="max-w-3xl mx-auto px-4 py-10">
    <?php if (!$page): ?>
    <div class="text-center py-20">
      <i class="fas fa-exclamation-circle text-4xl text-gray-300 mb-4"></i>
      <h1 class="text-2xl font-bold text-gray-700">Status page not found</h1>
    </div>
    <?php else: ?>

    <!-- Overall status banner -->
    <div class="<?= $bannerClass ?> border rounded-2xl p-6 mb-8 flex items-center gap-4">
      <div class="w-14 h-14 <?= $statusBg ?> rounded-xl flex items-center justify-center flex-shrink-0">
        <i class="<?= $statusIcon ?> text-white text-2xl"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold"><?= $statusText ?></h1>
        <p class="text-sm opacity-75 mt-0.5">Updated <?= date('M j, Y \a\t H:i') ?> UTC</p>
      </div>
    </div>

    <?php if ($page['description']): ?>
    <p class="text-gray-600 mb-8"><?= nl2br(h($page['description'])) ?></p>
    <?php endif; ?>

    <!-- Incidents -->
    <h2 class="text-xl font-bold text-gray-900 mb-4">
      <?php
        $openCount = count(array_filter($incidents, fn($i) => $i['status'] !== 'resolved'));
        echo $openCount > 0 ? "Active Incidents ($openCount)" : 'Incidents';
      ?>
    </h2>

    <?php if (empty($incidents)): ?>
    <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center mb-6">
      <i class="fas fa-check-circle text-3xl text-green-400 mb-3"></i>
      <p class="text-gray-600 font-medium">No incidents reported.</p>
      <p class="text-sm text-gray-400 mt-1">We'll post updates here whenever there are issues.</p>
    </div>
    <?php else: ?>
    <div class="space-y-4 mb-8">
      <?php foreach ($incidents as $inc): ?>
      <?php
        $sc = $incidentSeverityColors[$inc['severity']] ?? 'gray';
        $stc = $incidentStatusColors[$inc['status']] ?? 'gray';
        $resolved = $inc['status'] === 'resolved';
      ?>
      <div class="bg-white rounded-2xl border <?= $resolved ? 'border-gray-200' : 'border-' . $sc . '-200' ?> p-5 <?= $resolved ? 'opacity-70' : '' ?>">
        <div class="flex items-start justify-between gap-3 mb-3">
          <h3 class="font-semibold text-gray-900"><?= h($inc['title']) ?></h3>
          <div class="flex gap-1.5 flex-shrink-0">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-<?= $sc ?>-100 text-<?= $sc ?>-700 capitalize"><?= $inc['severity'] ?></span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-<?= $stc ?>-100 text-<?= $stc ?>-700 capitalize"><?= str_replace('_', ' ', $inc['status']) ?></span>
          </div>
        </div>
        <?php if ($inc['description']): ?>
        <p class="text-sm text-gray-600 mb-3"><?= nl2br(h($inc['description'])) ?></p>
        <?php endif; ?>
        <div class="flex items-center gap-4 text-xs text-gray-400">
          <span><i class="fas fa-clock mr-1"></i><?= timeAgo($inc['created_at']) ?></span>
          <?php if ($resolved && $inc['resolved_at']): ?>
          <span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Resolved <?= timeAgo($inc['resolved_at']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Subscribe / footer -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6 text-center">
      <h3 class="font-semibold text-gray-900 mb-1">Stay Updated</h3>
      <p class="text-sm text-gray-500 mb-4">Subscribe to receive status updates via email when incidents are reported or resolved.</p>
      <form class="flex gap-2 max-w-sm mx-auto">
        <input type="email" placeholder="your@email.com" class="flex-1 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
        <button type="button" onclick="alert('Email notification subscriptions require email configuration. See config.php.')"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-sm font-semibold transition">
          Subscribe
        </button>
      </form>
    </div>

    <?php endif; ?>
  </main>

  <footer class="text-center py-8 text-xs text-gray-400">
    Powered by <a href="<?= APP_URL ?>" class="text-indigo-500 hover:underline"><?= APP_NAME ?></a>
  </footer>
</body>
</html>