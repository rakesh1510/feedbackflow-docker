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
$slug = $_GET['slug'] ?? '';
$project = $slug ? DB::fetch("SELECT * FROM ff_projects WHERE slug = ? AND is_public = 1", [$slug]) : null;
if (!$project) { http_response_code(404); die('<h1>Not found</h1>'); }
$projectId = $project['id'];

$roadmapItems = DB::fetchAll("SELECT * FROM ff_roadmap WHERE project_id = ? AND is_public = 1 ORDER BY FIELD(status,'in_progress','planned','done'), sort_order, created_at", [$projectId]);
$grouped = ['in_progress' => [], 'planned' => [], 'done' => []];
foreach ($roadmapItems as $item) $grouped[$item['status']][] = $item;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($project['name']) ?> – Public Roadmap</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="bg-gray-50 min-h-screen">
<header class="bg-white border-b border-gray-200 sticky top-0 z-40">
  <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm" style="background: <?= h($project['widget_color']) ?>"><?= strtoupper(substr($project['name'],0,1)) ?></div>
      <h1 class="font-bold text-gray-900"><?= h($project['name']) ?></h1>
      <span class="text-gray-300">|</span>
      <span class="text-sm text-gray-500">Public Roadmap</span>
    </div>
    <div class="flex gap-4">
      <a href="<?= APP_URL ?>/public/board.php?slug=<?= h($slug) ?>" class="text-sm text-gray-500 hover:text-gray-900"><i class="fas fa-comments mr-1"></i>Feedback</a>
      <a href="<?= APP_URL ?>/public/changelog.php?slug=<?= h($slug) ?>" class="text-sm text-gray-500 hover:text-gray-900"><i class="fas fa-scroll mr-1"></i>Changelog</a>
    </div>
  </div>
</header>

<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="text-center mb-10">
    <h2 class="text-3xl font-bold text-gray-900">What we're building</h2>
    <p class="text-gray-500 mt-2">Track what's planned, in progress, and recently shipped</p>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <?php $cols = [
      'in_progress' => ['In Progress', 'bg-orange-100 text-orange-700', 'bg-orange-500', 'fas fa-spinner'],
      'planned'     => ['Planned', 'bg-indigo-100 text-indigo-700', 'bg-indigo-500', 'fas fa-calendar'],
      'done'        => ['Done', 'bg-green-100 text-green-700', 'bg-green-500', 'fas fa-check-circle'],
    ];
    foreach ($cols as $status => [$label, $badge, $dot, $icon]): ?>
      <div>
        <div class="flex items-center gap-2 mb-4">
          <i class="<?= $icon ?> <?= str_replace('text-','text-',explode(' ',$badge)[1]) ?> text-sm"></i>
          <h3 class="font-bold text-gray-900 text-lg"><?= $label ?></h3>
          <span class="ml-auto <?= $badge ?> text-xs font-bold px-2 py-0.5 rounded-full"><?= count($grouped[$status]) ?></span>
        </div>
        <?php if (empty($grouped[$status])): ?>
          <div class="bg-white border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center text-gray-400 text-sm">Nothing here yet</div>
        <?php else: foreach ($grouped[$status] as $item): ?>
          <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-3 hover:shadow-sm transition">
            <h4 class="font-semibold text-gray-900 mb-1"><?= h($item['title']) ?></h4>
            <?php if ($item['description']): ?><p class="text-sm text-gray-500 leading-relaxed"><?= h($item['description']) ?></p><?php endif; ?>
            <div class="flex items-center gap-3 mt-3 text-xs text-gray-400">
              <?php if ($item['quarter']): ?><span><i class="fas fa-calendar mr-1"></i><?= h($item['quarter']) ?></span><?php endif; ?>
              <?php if ($item['target_date']): ?><span><i class="fas fa-flag mr-1"></i><?= formatDate($item['target_date']) ?></span><?php endif; ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<footer class="max-w-5xl mx-auto px-4 py-6 text-center text-xs text-gray-300 mt-8">
  Powered by FeedbackFlow
</footer>
</body>
</html>