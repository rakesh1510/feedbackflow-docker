<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/db-manager.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';


$slug = $_GET['slug'] ?? '';
if ($slug) {
    $resolvedCompanyId = DBManager::findCompanyIdByProjectSlug($slug);
    if ($resolvedCompanyId) {
        DB::useTenantForCompany($resolvedCompanyId);
    }
}
$slug = $_GET['slug'] ?? '';
$project = $slug ? DB::fetch("SELECT * FROM ff_projects WHERE slug = ? AND is_public = 1", [$slug]) : null;
if (!$project) { http_response_code(404); die('<h1>Board not found</h1>'); }
$projectId = $project['id'];

// Handle vote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote'])) {
    Auth::start();
    $feedbackId = (int)$_POST['feedback_id'];
    $voterIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $voterEmail = trim($_POST['voter_email'] ?? '');
    if (!rateLimit('vote_' . $feedbackId, 5, 3600)) { http_response_code(429); exit; }
    $exists = DB::fetch("SELECT id FROM ff_votes WHERE feedback_id = ? AND voter_ip = ?", [$feedbackId, $voterIp]);
    if (!$exists) {
        DB::insert('ff_votes', ['feedback_id' => $feedbackId, 'voter_ip' => $voterIp, 'voter_email' => $voterEmail ?: null, 'emoji' => '👍']);
        DB::query("UPDATE ff_feedback SET vote_count = vote_count + 1 WHERE id = ?", [$feedbackId]);
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; }
    redirect($_SERVER['REQUEST_URI']);
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    if (!rateLimit('submit_feedback', 3, 600)) { $submitError = 'Too many submissions. Please wait.'; }
    else {
        $title = sanitize($_POST['title'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $catId = (int)($_POST['category_id'] ?? 0) ?: null;
        $name = sanitize($_POST['submitter_name'] ?? '');
        $email = trim($_POST['submitter_email'] ?? '');
        if (strlen($title) < 5) { $submitError = 'Title must be at least 5 characters.'; }
        elseif ($email && !isValidEmail($email)) { $submitError = 'Invalid email address.'; }
        else {
            $id = DB::insert('ff_feedback', ['project_id' => $projectId, 'category_id' => $catId, 'title' => $title, 'description' => $desc, 'submitter_name' => $name ?: 'Anonymous', 'submitter_email' => $email ?: null, 'status' => 'new', 'priority' => 'medium', 'is_public' => 1]);
            triggerWebhooks($projectId, 'feedback.created', ['id' => $id, 'title' => $title]);
            $submitSuccess = true;
        }
    }
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterCategory = (int)($_GET['category'] ?? 0);
$search = sanitize($_GET['q'] ?? '');
$sortBy = in_array($_GET['sort'] ?? '', ['vote_count','created_at','comment_count']) ? $_GET['sort'] : 'vote_count';
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;

$where = ['f.project_id = ?', 'f.is_public = 1'];
$params = [$projectId];
if ($filterStatus) { $where[] = 'f.status = ?'; $params[] = $filterStatus; }
if ($filterCategory) { $where[] = 'f.category_id = ?'; $params[] = $filterCategory; }
if ($search) { $where[] = '(f.title LIKE ? OR f.description LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereStr = implode(' AND ', $where);
$total = DB::count("SELECT COUNT(*) FROM ff_feedback f WHERE $whereStr", $params);
$feedbacks = DB::fetchAll("SELECT f.*, c.name as cat_name, c.color as cat_color FROM ff_feedback f LEFT JOIN ff_categories c ON c.id = f.category_id WHERE $whereStr ORDER BY f.$sortBy DESC LIMIT $perPage OFFSET " . (($page-1)*$perPage), $params);
$categories = DB::fetchAll("SELECT * FROM ff_categories WHERE project_id = ? ORDER BY sort_order", [$projectId]);
$stats = [
    'total' => DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND is_public = 1", [$projectId]),
    'planned' => DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND status='planned'", [$projectId]),
    'done' => DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND status='done'", [$projectId]),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($project['name']) ?> – Feedback Board</title>
<meta name="description" content="Share and vote on feedback for <?= h($project['name']) ?>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif}[x-cloak]{display:none!important}</style>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50 min-h-screen">
<!-- Header -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-40">
  <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <?php if ($project['logo']): ?><img src="<?= h(UPLOAD_URL . 'avatars/' . $project['logo']) ?>" class="w-8 h-8 rounded-lg object-cover"><?php else: ?>
        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm" style="background: <?= h($project['widget_color']) ?>"><?= strtoupper(substr($project['name'],0,1)) ?></div>
      <?php endif; ?>
      <div>
        <h1 class="font-bold text-gray-900"><?= h($project['name']) ?></h1>
        <p class="text-xs text-gray-400">Feedback Board</p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <a href="<?= APP_URL ?>/public/roadmap.php?slug=<?= h($slug) ?>" class="text-sm text-gray-500 hover:text-gray-900"><i class="fas fa-map mr-1"></i>Roadmap</a>
      <a href="<?= APP_URL ?>/public/changelog.php?slug=<?= h($slug) ?>" class="text-sm text-gray-500 hover:text-gray-900"><i class="fas fa-scroll mr-1"></i>Changelog</a>
    </div>
  </div>
</header>

<div class="max-w-4xl mx-auto px-4 py-8">
  <!-- Stats -->
  <div class="grid grid-cols-3 gap-4 mb-8">
    <?php foreach ([['Total Feedback',$stats['total'],'comments','indigo'],['Planned',$stats['planned'],'map','purple'],['Shipped',$stats['done'],'check-circle','green']] as [$l,$v,$i,$c]): ?>
      <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-gray-900"><?= $v ?></p>
        <p class="text-sm text-gray-500 mt-0.5"><i class="fas fa-<?= $i ?> mr-1 text-<?= $c ?>-500"></i><?= $l ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Submit Feedback -->
  <?php if (!empty($submitSuccess)): ?>
    <div class="bg-green-50 border border-green-200 rounded-2xl p-5 mb-6 text-center">
      <i class="fas fa-check-circle text-green-600 text-3xl block mb-2"></i>
      <p class="font-semibold text-green-800">Thanks for your feedback!</p>
      <p class="text-sm text-green-600 mt-1">We've received it and will review it soon.</p>
    </div>
  <?php else: ?>
  <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6" x-data="{ open: false }">
    <button @click="open = !open" class="w-full flex items-center justify-between text-left group">
      <div>
        <h2 class="font-bold text-gray-900 group-hover:text-indigo-600 transition">Share your feedback</h2>
        <p class="text-sm text-gray-500 mt-0.5">Feature requests, bug reports, ideas — all welcome</p>
      </div>
      <div class="w-10 h-10 rounded-xl flex items-center justify-center transition" :class="open ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-600'">
        <i class="fas" :class="open ? 'fa-times' : 'fa-plus'"></i>
      </div>
    </button>
    <div x-show="open" x-cloak class="mt-5 border-t border-gray-100 pt-5">
      <?php if (isset($submitError)): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm"><?= h($submitError) ?></div><?php endif; ?>
      <form method="POST" class="space-y-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">What's your feedback? <span class="text-red-500">*</span></label>
          <input type="text" name="title" required maxlength="200" placeholder="e.g. Add dark mode support" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea name="description" rows="3" placeholder="Tell us more about your idea or issue..." class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select name="category_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">— Select —</option>
              <?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Your Email (optional)</label>
            <input type="email" name="submitter_email" placeholder="for updates on your feedback" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
        </div>
        <button type="submit" name="submit_feedback" value="1" class="w-full text-white font-semibold py-3 px-4 rounded-xl transition hover:opacity-90" style="background: <?= h($project['widget_color']) ?>">
          <i class="fas fa-paper-plane mr-2"></i> Submit Feedback
        </button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="flex gap-3 mb-6 flex-wrap">
    <form method="GET" class="flex gap-2 flex-wrap flex-1 min-w-0">
      <input type="hidden" name="slug" value="<?= h($slug) ?>">
      <div class="relative flex-1 min-w-40">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search..." class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2.5 text-sm bg-white outline-none focus:ring-2 focus:ring-indigo-500">
      </div>
      <select name="status" onchange="this.form.submit()" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white outline-none">
        <option value="">All Statuses</option>
        <?php foreach (['new','under_review','planned','in_progress','done'] as $s): ?>
          <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="category" onchange="this.form.submit()" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white outline-none">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>" <?= $filterCategory==$cat['id']?'selected':'' ?>><?= h($cat['name']) ?></option><?php endforeach; ?>
      </select>
      <select name="sort" onchange="this.form.submit()" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white outline-none">
        <option value="vote_count" <?= $sortBy==='vote_count'?'selected':'' ?>>Most Voted</option>
        <option value="created_at" <?= $sortBy==='created_at'?'selected':'' ?>>Newest</option>
        <option value="comment_count" <?= $sortBy==='comment_count'?'selected':'' ?>>Most Discussed</option>
      </select>
    </form>
  </div>

  <!-- Feedback List -->
  <div class="space-y-3">
    <?php if (empty($feedbacks)): ?>
      <div class="bg-white rounded-2xl border border-gray-200 py-16 text-center text-gray-400">
        <i class="fas fa-inbox text-4xl block mb-3"></i>
        <p class="font-medium">No feedback yet</p>
        <p class="text-sm mt-1">Be the first to share your thoughts!</p>
      </div>
    <?php else: foreach ($feedbacks as $fb): ?>
      <div class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-md transition flex gap-4">
        <!-- Vote Button -->
        <form method="POST" class="flex-shrink-0">
          <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
          <button type="submit" name="vote" value="1"
                  class="flex flex-col items-center justify-center w-14 h-14 rounded-xl border-2 border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition text-gray-600 hover:text-indigo-600 group">
            <i class="fas fa-chevron-up text-lg group-hover:text-indigo-600"></i>
            <span class="text-sm font-bold"><?= $fb['vote_count'] ?></span>
          </button>
        </form>
        <!-- Content -->
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <?php if ($fb['cat_name']): ?>
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background:<?= h($fb['cat_color']) ?>20;color:<?= h($fb['cat_color']) ?>"><?= h($fb['cat_name']) ?></span>
                <?php endif; ?>
                <?= statusBadge($fb['status']) ?>
              </div>
              <h3 class="font-semibold text-gray-900 leading-snug"><?= h($fb['title']) ?></h3>
              <?php if ($fb['description']): ?><p class="text-sm text-gray-500 mt-1 line-clamp-2"><?= h($fb['description']) ?></p><?php endif; ?>
            </div>
          </div>
          <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
            <span><i class="fas fa-comment mr-1"></i><?= $fb['comment_count'] ?></span>
            <span><?= timeAgo($fb['created_at']) ?></span>
          </div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Pagination -->
  <?php $totalPages = ceil($total / $perPage); if ($totalPages > 1): ?>
    <div class="flex justify-center gap-1 mt-8">
      <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <a href="?slug=<?= h($slug) ?>&p=<?= $i ?>&status=<?= urlencode($filterStatus) ?>&category=<?= $filterCategory ?>&sort=<?= $sortBy ?>&q=<?= urlencode($search) ?>"
           class="w-9 h-9 flex items-center justify-center rounded-xl text-sm <?= $i===$page?'bg-indigo-600 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<footer class="max-w-4xl mx-auto px-4 py-8 text-center text-xs text-gray-300 border-t border-gray-100 mt-8">
  Powered by <a href="#" class="hover:text-gray-500">FeedbackFlow</a> &middot; <?= h($project['name']) ?>
</footer>
</body>
</html>