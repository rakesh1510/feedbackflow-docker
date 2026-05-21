<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/billing.php';
require_once dirname(__DIR__) . '/includes/limit-check.php';

$currentUser = Auth::requirePermission('view_projects');
$userProjects = getUserProjects($currentUser['id']);
Auth::start();
$projectId = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { flash('error','CSRF failed.'); redirect($_SERVER['REQUEST_URI']); }
    $pAction = $_POST['_action'] ?? '';
    if ($pAction === 'create') {
        $name = sanitize($_POST['name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $website = trim($_POST['website'] ?? '');
        if (empty($name)) { flash('error','Name is required.'); redirect($_SERVER['REQUEST_URI']); }
        // ── Limit check: max_projects ─────────────────────────────────
        $__co = BillingService::getCompany($currentUser['id']);
        $__cid = $__co ? (int)$__co['id'] : (int)$currentUser['id'];
        $__projCount = DB::count("SELECT COUNT(*) FROM ff_projects WHERE owner_id = ?", [$currentUser['id']]);
        LimitCheck::gateNumeric($__cid, 'max_projects', $__projCount, 'project', true, APP_URL . '/admin/billing.php');
        // ─────────────────────────────────────────────────────────────
        $s = slug($name);
        // Ensure unique slug
        $i = 0;
        $baseSlug = $s;
        while (DB::fetch("SELECT id FROM ff_projects WHERE slug = ?", [$s])) { $s = $baseSlug . '-' . ++$i; }
        $pid = DB::insert('ff_projects', ['name' => $name, 'slug' => $s, 'description' => $desc, 'website' => $website ?: null, 'owner_id' => $currentUser['id'], 'widget_key' => randomKey(32)]);
        // Default categories
        $defaultCats = [['Bug Report','bug-report','#ef4444'],['Feature Request','feature-request','#6366f1'],['Improvement','improvement','#10b981'],['Question','question','#f59e0b'],['Other','other','#6b7280']];
        foreach ($defaultCats as $i => [$cn,$cs,$cc]) DB::insert('ff_categories',['project_id'=>$pid,'name'=>$cn,'slug'=>$cs,'color'=>$cc,'sort_order'=>$i]);
        Auth::start(); $_SESSION['current_project_id'] = $pid;
        flash('success','Project created!'); redirect(APP_URL.'/admin/index.php');
    }
    if ($pAction === 'update') {
        $pid = (int)$_POST['project_id'];
        if (getProject($pid, $currentUser['id'])) {
            DB::update('ff_projects', ['name'=>sanitize($_POST['name']??''),'description'=>sanitize($_POST['description']??''),'website'=>trim($_POST['website']??'')?:null,'widget_color'=>$_POST['widget_color']??'#6366f1','widget_position'=>$_POST['widget_position']??'bottom-right','widget_theme'=>$_POST['widget_theme']??'light','widget_title'=>sanitize($_POST['widget_title']??''),'widget_placeholder'=>sanitize($_POST['widget_placeholder']??''),'is_public'=>isset($_POST['is_public'])?1:0,'allow_anonymous'=>isset($_POST['allow_anonymous'])?1:0], 'id = ?', [$pid]);
            flash('success','Project updated!');
        }
        redirect(APP_URL.'/admin/projects.php');
    }
    if ($pAction === 'delete') {
        $pid = (int)$_POST['project_id'];
        if (getProject($pid, $currentUser['id'])) { DB::delete('ff_projects','id = ?',[$pid]); flash('success','Project deleted.'); }
        redirect(APP_URL.'/admin/projects.php');
    }
    // Category management
    if ($pAction === 'add_category') {
        $pid = (int)$_POST['project_id'];
        $cname = sanitize($_POST['cat_name']??'');
        $ccolor = $_POST['cat_color']??'#6366f1';
        if ($cname && getProject($pid,$currentUser['id'])) DB::insert('ff_categories',['project_id'=>$pid,'name'=>$cname,'slug'=>slug($cname),'color'=>$ccolor]);
        flash('success','Category added!');
        redirect(APP_URL.'/admin/projects.php?edit='.$pid);
    }
    if ($pAction === 'delete_category') {
        DB::delete('ff_categories','id = ?',[(int)$_POST['category_id']]);
        flash('success','Category deleted.');
        redirect(APP_URL.'/admin/projects.php?edit='.$_POST['project_id']);
    }
    // Tag management
    if ($pAction === 'add_tag') {
        $pid = (int)$_POST['project_id'];
        $tname = sanitize($_POST['tag_name']??'');
        $tcolor = $_POST['tag_color']??'#94a3b8';
        if ($tname && getProject($pid,$currentUser['id'])) DB::insert('ff_tags',['project_id'=>$pid,'name'=>$tname,'color'=>$tcolor]);
        flash('success','Tag added!'); redirect(APP_URL.'/admin/projects.php?edit='.$pid);
    }
}

$editProject = isset($_GET['edit']) ? getProject((int)$_GET['edit'], $currentUser['id']) : null;
$pageTitle = 'Projects – ' . APP_NAME;
$projectColors = ['#6366f1','#8b5cf6','#ec4899','#10b981','#f59e0b','#3b82f6','#ef4444','#06b6d4'];
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto min-h-screen bg-gray-50">

  <!-- Top Bar -->
  <header class="sticky top-0 z-40 bg-white/80 backdrop-blur border-b border-gray-100 px-8 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Projects</h1>
      <p class="text-xs text-gray-400 mt-0.5"><?= count($userProjects) ?> project<?= count($userProjects) !== 1 ? 's' : '' ?></p>
    </div>
    <button onclick="document.getElementById('newProjectModal').classList.remove('hidden')"
            class="inline-flex items-center gap-2 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-all hover:opacity-90 active:scale-95 shadow-sm"
            style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
      <i class="fas fa-plus text-xs"></i> New Project
    </button>
  </header>

  <?php foreach (getFlash() as $f): ?>
    <div class="mx-8 mt-5 flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $f['type']==='success'?'bg-green-50 text-green-700 border border-green-200':'bg-red-50 text-red-700 border border-red-200' ?>">
      <i class="fas <?= $f['type']==='success'?'fa-check-circle':'fa-exclamation-circle' ?>"></i>
      <?= h($f['msg']) ?>
    </div>
  <?php endforeach; ?>

  <?php if ($editProject): ?>
  <!-- ── Edit Project ─────────────────────────────────────────────────── -->
  <div class="p-8 space-y-6 max-w-5xl">
    <a href="<?= APP_URL ?>/admin/projects.php"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 transition-colors font-medium">
      <i class="fas fa-arrow-left text-xs"></i> Back to Projects
    </a>

    <!-- Project header banner -->
    <div class="rounded-2xl p-6 flex items-center gap-5 text-white"
         style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
      <div class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center text-2xl font-black">
        <?= strtoupper(substr($editProject['name'], 0, 1)) ?>
      </div>
      <div class="flex-1">
        <h2 class="text-xl font-bold"><?= h($editProject['name']) ?></h2>
        <p class="text-indigo-200 text-sm mt-0.5"><?= h($editProject['description'] ?? 'No description') ?></p>
      </div>
      <a href="<?= APP_URL ?>/public/board.php?slug=<?= h($editProject['slug']) ?>" target="_blank"
         class="flex items-center gap-2 bg-white/20 hover:bg-white/30 px-4 py-2 rounded-xl text-sm font-medium transition-colors">
        <i class="fas fa-external-link-alt text-xs"></i> Public Board
      </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- General Settings -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900 flex items-center gap-2">
            <i class="fas fa-sliders-h text-indigo-500 text-sm"></i> General Settings
          </h3>
        </div>
        <form method="POST" class="p-6 space-y-4">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="_action" value="update">
          <input type="hidden" name="project_id" value="<?= $editProject['id'] ?>">
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Project Name</label>
            <input type="text" name="name" value="<?= h($editProject['name']) ?>" required
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Description</label>
            <textarea name="description" rows="3"
                      class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition resize-none"><?= h($editProject['description']??'') ?></textarea>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Website URL</label>
            <input type="text" name="website" value="<?= h($editProject['website']??'') ?>" placeholder="https://example.com"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
          </div>
          <div class="bg-gray-50 rounded-xl p-4 space-y-3">
            <label class="flex items-center gap-3 cursor-pointer">
              <div class="relative">
                <input type="checkbox" name="is_public" <?= $editProject['is_public']?'checked':'' ?> class="sr-only peer">
                <div class="w-9 h-5 rounded-full bg-gray-200 peer-checked:bg-indigo-500 transition-colors"></div>
                <div class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow peer-checked:translate-x-4 transition-transform"></div>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-700">Public feedback board</p>
                <p class="text-xs text-gray-400">Anyone can view and vote on feedback</p>
              </div>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
              <div class="relative">
                <input type="checkbox" name="allow_anonymous" <?= $editProject['allow_anonymous']?'checked':'' ?> class="sr-only peer">
                <div class="w-9 h-5 rounded-full bg-gray-200 peer-checked:bg-indigo-500 transition-colors"></div>
                <div class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow peer-checked:translate-x-4 transition-transform"></div>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-700">Allow anonymous feedback</p>
                <p class="text-xs text-gray-400">Users can submit without signing in</p>
              </div>
            </label>
          </div>
          <button type="submit"
                  class="w-full py-2.5 rounded-xl text-white text-sm font-semibold transition hover:opacity-90"
                  style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
            Save Settings
          </button>
        </form>
      </div>

      <!-- Widget Settings -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900 flex items-center gap-2">
            <i class="fas fa-code text-indigo-500 text-sm"></i> Widget Settings
          </h3>
        </div>
        <form method="POST" class="p-6 space-y-4">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="_action" value="update">
          <input type="hidden" name="project_id" value="<?= $editProject['id'] ?>">
          <input type="hidden" name="name" value="<?= h($editProject['name']) ?>">
          <input type="hidden" name="description" value="<?= h($editProject['description']??'') ?>">
          <input type="hidden" name="website" value="<?= h($editProject['website']??'') ?>">
          <input type="hidden" name="is_public" value="<?= $editProject['is_public'] ?>">
          <input type="hidden" name="allow_anonymous" value="<?= $editProject['allow_anonymous'] ?>">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Brand Color</label>
              <input type="color" name="widget_color" value="<?= h($editProject['widget_color'] ?? '#6366f1') ?>"
                     class="w-full h-11 border border-gray-200 rounded-xl p-1.5 cursor-pointer bg-gray-50">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Position</label>
              <select name="widget_position"
                      class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 outline-none focus:ring-2 focus:ring-indigo-400 transition">
                <?php foreach (['bottom-right','bottom-left','top-right','top-left'] as $pos): ?>
                  <option <?= ($editProject['widget_position']??'bottom-right')===$pos?'selected':'' ?>><?= $pos ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Theme</label>
            <div class="grid grid-cols-3 gap-2">
              <?php foreach (['light'=>'Light','dark'=>'Dark','auto'=>'Auto'] as $val => $label): ?>
                <label class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border-2 text-sm font-medium cursor-pointer transition-all
                  <?= ($editProject['widget_theme']??'light')===$val ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300' ?>">
                  <input type="radio" name="widget_theme" value="<?= $val ?>" <?= ($editProject['widget_theme']??'light')===$val?'checked':'' ?> class="hidden">
                  <?= $label ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Widget Title</label>
            <input type="text" name="widget_title" value="<?= h($editProject['widget_title']??'Share feedback') ?>" placeholder="Share feedback"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 transition">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Placeholder Text</label>
            <input type="text" name="widget_placeholder" value="<?= h($editProject['widget_placeholder']??'Tell us what you think...') ?>" placeholder="Tell us what you think..."
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 transition">
          </div>
          <button type="submit"
                  class="w-full py-2.5 rounded-xl text-white text-sm font-semibold transition hover:opacity-90"
                  style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
            Save Widget Settings
          </button>
        </form>
      </div>
    </div>

    <!-- Categories -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
      <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900 flex items-center gap-2">
          <i class="fas fa-tags text-indigo-500 text-sm"></i> Categories
        </h3>
      </div>
      <div class="p-6">
        <div class="flex flex-wrap gap-2 mb-5">
          <?php $cats = DB::fetchAll("SELECT * FROM ff_categories WHERE project_id = ? ORDER BY sort_order", [$editProject['id']]);
          foreach ($cats as $cat): ?>
            <div class="flex items-center gap-2 pl-3 pr-2 py-1.5 rounded-xl border text-sm font-medium transition"
                 style="border-color:<?= h($cat['color']) ?>33;background:<?= h($cat['color']) ?>12;color:<?= h($cat['color']) ?>">
              <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:<?= h($cat['color']) ?>"></span>
              <?= h($cat['name']) ?>
              <form method="POST" class="inline-flex ml-1">
                <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                <input type="hidden" name="_action" value="delete_category">
                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                <input type="hidden" name="project_id" value="<?= $editProject['id'] ?>">
                <button type="submit" class="opacity-40 hover:opacity-100 transition" title="Remove">
                  <i class="fas fa-times text-xs"></i>
                </button>
              </form>
            </div>
          <?php endforeach; ?>
          <?php if (empty($cats)): ?>
            <p class="text-sm text-gray-400 italic">No categories yet.</p>
          <?php endif; ?>
        </div>
        <form method="POST" class="flex gap-3 items-center">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="_action" value="add_category">
          <input type="hidden" name="project_id" value="<?= $editProject['id'] ?>">
          <input type="text" name="cat_name" placeholder="New category name" required
                 class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 transition">
          <input type="color" name="cat_color" value="#6366f1"
                 class="w-11 h-11 border border-gray-200 rounded-xl p-1.5 cursor-pointer bg-gray-50">
          <button type="submit"
                  class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">
            Add
          </button>
        </form>
      </div>
    </div>

    <!-- Danger Zone -->
    <div class="bg-white rounded-2xl border border-red-100 shadow-sm">
      <div class="px-6 py-4 border-b border-red-100">
        <h3 class="font-semibold text-red-600 flex items-center gap-2">
          <i class="fas fa-exclamation-triangle text-sm"></i> Danger Zone
        </h3>
      </div>
      <div class="p-6 flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-700">Delete this project</p>
          <p class="text-xs text-gray-400 mt-0.5">This will permanently remove all feedback, comments, and data. This cannot be undone.</p>
        </div>
        <form method="POST" onsubmit="return confirm('Delete this project and ALL its data permanently? This cannot be undone.')">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="project_id" value="<?= $editProject['id'] ?>">
          <button type="submit"
                  class="flex-shrink-0 ml-6 border border-red-300 hover:bg-red-50 text-red-600 font-medium px-5 py-2.5 rounded-xl text-sm transition">
            Delete Project
          </button>
        </form>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- ── Project List ─────────────────────────────────────────────────── -->
  <div class="p-8">
    <?php if (empty($userProjects)): ?>
      <!-- Empty state -->
      <div class="flex flex-col items-center justify-center py-24 text-center">
        <div class="w-20 h-20 rounded-3xl flex items-center justify-center mb-5 text-white text-3xl"
             style="background:linear-gradient(135deg,#e0e7ff,#c7d2fe)">
          <i class="fas fa-folder-open" style="color:#6366f1"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-800 mb-2">No projects yet</h3>
        <p class="text-sm text-gray-400 mb-6 max-w-xs">Create your first project to start collecting and managing product feedback.</p>
        <button onclick="document.getElementById('newProjectModal').classList.remove('hidden')"
                class="inline-flex items-center gap-2 text-white text-sm font-semibold px-6 py-3 rounded-xl transition hover:opacity-90"
                style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
          <i class="fas fa-plus"></i> Create your first project
        </button>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <?php foreach ($userProjects as $idx => $proj):
          $color = $projectColors[$idx % count($projectColors)];
          $isActive = ($currentProject['id'] ?? 0) == $proj['id'];
        ?>
        <div class="group bg-white rounded-2xl border <?= $isActive ? 'border-indigo-200 shadow-md shadow-indigo-100/50' : 'border-gray-100 shadow-sm' ?> hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 overflow-hidden flex flex-col">
          <!-- Card header -->
          <div class="p-5 pb-4">
            <div class="flex items-start justify-between mb-4">
              <div class="w-11 h-11 rounded-2xl flex items-center justify-center text-white text-lg font-black shadow-sm"
                   style="background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>99)">
                <?= strtoupper(substr($proj['name'], 0, 1)) ?>
              </div>
              <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <a href="?edit=<?= $proj['id'] ?>"
                   class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors text-sm" title="Settings">
                  <i class="fas fa-cog"></i>
                </a>
                <a href="<?= APP_URL ?>/public/board.php?slug=<?= h($proj['slug']) ?>" target="_blank"
                   class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors text-sm" title="Public board">
                  <i class="fas fa-external-link-alt"></i>
                </a>
              </div>
            </div>

            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <h3 class="font-bold text-gray-900 text-base truncate"><?= h($proj['name']) ?></h3>
                <p class="text-sm text-gray-400 mt-0.5 line-clamp-2 leading-relaxed">
                  <?= h($proj['description'] ?: 'No description') ?>
                </p>
              </div>
              <?php if ($isActive): ?>
                <span class="flex-shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full text-indigo-600 bg-indigo-50 border border-indigo-200">Active</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Stats bar -->
          <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center gap-4 text-xs text-gray-500">
            <span class="flex items-center gap-1.5">
              <i class="fas fa-comments text-gray-300"></i>
              <strong class="text-gray-700 font-semibold"><?= number_format($proj['feedback_count'] ?? 0) ?></strong> feedback
            </span>
            <?php if (!empty($proj['new_count']) && $proj['new_count'] > 0): ?>
              <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                <strong class="text-blue-600 font-semibold"><?= $proj['new_count'] ?></strong> new
              </span>
            <?php endif; ?>
            <?php if ($proj['is_public']): ?>
              <span class="flex items-center gap-1.5 ml-auto">
                <i class="fas fa-globe text-green-400"></i> Public
              </span>
            <?php else: ?>
              <span class="flex items-center gap-1.5 ml-auto">
                <i class="fas fa-lock text-gray-300"></i> Private
              </span>
            <?php endif; ?>
          </div>

          <!-- CTA -->
          <div class="px-5 pb-5 pt-3">
            <a href="<?= APP_URL ?>/admin/index.php?project_id=<?= $proj['id'] ?>"
               class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl text-sm font-semibold transition-all hover:opacity-90 text-white"
               style="background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>cc)">
              Open Dashboard <i class="fas fa-arrow-right text-xs"></i>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</main>
</div>

<!-- ── New Project Modal ────────────────────────────────────────────────── -->
<div id="newProjectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,0.4);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl flex items-center justify-center text-white text-sm"
             style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
          <i class="fas fa-folder-plus"></i>
        </div>
        <h3 class="text-base font-bold text-gray-900">Create New Project</h3>
      </div>
      <button onclick="document.getElementById('newProjectModal').classList.add('hidden')"
              class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="_csrf" value="<?= csrf() ?>">
      <input type="hidden" name="_action" value="create">
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
          Project Name <span class="text-red-400">*</span>
        </label>
        <input type="text" name="name" required placeholder="My Awesome App"
               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Description</label>
        <textarea name="description" rows="2" placeholder="What does this project do?"
                  class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition resize-none"></textarea>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Website URL</label>
        <input type="text" name="website" placeholder="www.yourapp.com"
               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
      </div>
      <div class="flex gap-3 pt-1">
        <button type="submit"
                class="flex-1 text-white font-semibold py-2.5 rounded-xl transition hover:opacity-90"
                style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
          Create Project
        </button>
        <button type="button" onclick="document.getElementById('newProjectModal').classList.add('hidden')"
                class="px-5 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50 transition">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Radio button theme selector visual feedback
document.querySelectorAll('input[name="widget_theme"]').forEach(radio => {
  radio.addEventListener('change', () => {
    document.querySelectorAll('input[name="widget_theme"]').forEach(r => {
      const label = r.closest('label');
      label.classList.toggle('border-indigo-500', r.checked);
      label.classList.toggle('bg-indigo-50', r.checked);
      label.classList.toggle('text-indigo-700', r.checked);
      label.classList.toggle('border-gray-200', !r.checked);
      label.classList.toggle('text-gray-600', !r.checked);
    });
  });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
