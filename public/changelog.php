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

$entries = DB::fetchAll("SELECT cl.*, u.name as author_name FROM ff_changelog cl LEFT JOIN ff_users u ON u.id = cl.author_id WHERE cl.project_id = ? AND cl.is_published = 1 ORDER BY cl.published_at DESC", [$projectId]);

$typeConfig = [
    'new'         => ['✨', 'New', 'bg-blue-100 text-blue-700'],
    'improvement' => ['📈', 'Improvement', 'bg-indigo-100 text-indigo-700'],
    'bugfix'      => ['🐛', 'Bug Fix', 'bg-red-100 text-red-700'],
    'breaking'    => ['⚠️', 'Breaking', 'bg-orange-100 text-orange-700'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($project['name']) ?> – Changelog</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="bg-gray-50 min-h-screen">
<header class="bg-white border-b border-gray-200 sticky top-0 z-40">
  <div class="max-w-3xl mx-auto px-4 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold" style="background: <?= h($project['widget_color']) ?>"><?= strtoupper(substr($project['name'],0,1)) ?></div>
      <h1 class="font-bold text-gray-900"><?= h($project['name']) ?></h1>
      <span class="text-gray-300">|</span>
      <span class="text-sm text-gray-500">Changelog</span>
    </div>
    <div class="flex gap-4">
      <a href="<?= APP_URL ?>/public/board.php?slug=<?= h($slug) ?>" class="text-sm text-gray-500 hover:text-gray-900"><i class="fas fa-comments mr-1"></i>Feedback</a>
      <a href="<?= APP_URL ?>/public/roadmap.php?slug=<?= h($slug) ?>" class="text-sm text-gray-500 hover:text-gray-900"><i class="fas fa-map mr-1"></i>Roadmap</a>
    </div>
  </div>
</header>

<div class="max-w-3xl mx-auto px-4 py-10">
  <div class="text-center mb-12">
    <h2 class="text-3xl font-bold text-gray-900">What's New</h2>
    <p class="text-gray-500 mt-2">All the improvements and updates for <?= h($project['name']) ?></p>
  </div>

  <?php if (empty($entries)): ?>
    <div class="bg-white rounded-2xl border border-gray-200 py-16 text-center text-gray-400">
      <i class="fas fa-scroll text-4xl block mb-3"></i>
      <p class="font-medium">No changelog entries yet</p>
    </div>
  <?php else: ?>
    <div class="relative">
      <!-- Timeline line -->
      <div class="absolute left-8 top-0 bottom-0 w-0.5 bg-gray-200"></div>
      <div class="space-y-8">
        <?php foreach ($entries as $entry):
          [$emoji, $typeLabel, $typeCls] = $typeConfig[$entry['type']] ?? ['📝', 'Update', 'bg-gray-100 text-gray-600']; ?>
          <div class="relative pl-20">
            <!-- Timeline dot -->
            <div class="absolute left-5 top-4 w-7 h-7 bg-white border-2 border-gray-200 rounded-full flex items-center justify-center text-base z-10">
              <?= $emoji ?>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
              <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold <?= $typeCls ?>"><?= $typeLabel ?></span>
                  <?php if ($entry['version']): ?><code class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-xs"><?= h($entry['version']) ?></code><?php endif; ?>
                  <h3 class="font-bold text-gray-900 text-lg"><?= h($entry['title']) ?></h3>
                </div>
                <div class="text-right flex-shrink-0">
                  <p class="text-xs text-gray-400"><?= formatDate($entry['published_at'], 'M j, Y') ?></p>
                  <?php if ($entry['author_name']): ?><p class="text-xs text-gray-300 mt-0.5">by <?= h($entry['author_name']) ?></p><?php endif; ?>
                </div>
              </div>
              <div class="text-gray-700 leading-relaxed text-sm whitespace-pre-wrap"><?= h($entry['content']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<footer class="max-w-3xl mx-auto px-4 py-8 text-center text-xs text-gray-300 mt-8">
  Powered by FeedbackFlow
</footer>
</body>
</html>