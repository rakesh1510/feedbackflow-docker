<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('project_settings');
$userProjects = getUserProjects($currentUser['id']);
Auth::start();
if (isset($_GET['project_id'])) $_SESSION['current_project_id'] = (int)$_GET['project_id'];
$projectId = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;
if (!$currentProject && !empty($userProjects)) { $currentProject = $userProjects[0]; $projectId = $currentProject['id']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    if ($_POST['_action'] === 'regenerate_key') {
        DB::update('ff_projects', ['widget_key' => randomKey(32)], 'id = ?', [$projectId]);
        flash('success', 'Widget key regenerated!');
    }
    redirect(APP_URL . '/admin/widget.php');
}

$categories = $projectId ? DB::fetchAll("SELECT * FROM ff_categories WHERE project_id = ? ORDER BY sort_order", [$projectId]) : [];
$pageTitle = 'Widget – ' . APP_NAME;
$embedCode = $currentProject ? '<script src="' . APP_URL . '/widget/widget.js" data-key="' . $currentProject['widget_key'] . '" defer></script>' : '';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4">
    <h1 class="text-xl font-bold text-gray-900">Widget Installation</h1>
    <p class="text-sm text-gray-500 mt-0.5">Embed the feedback widget on your website</p>
  </header>

  <?php foreach (getFlash() as $f): ?>
    <div data-flash class="mx-6 mt-4 px-4 py-3 rounded-xl text-sm <?= $f['type']==='success'?'bg-green-50 text-green-700 border border-green-200':'bg-red-50 text-red-700 border border-red-200' ?>"><?= h($f['msg']) ?></div>
  <?php endforeach; ?>

  <div class="p-6 space-y-6">
    <?php if (!$currentProject): ?>
      <div class="bg-white rounded-2xl border border-gray-200 py-16 text-center text-gray-400">
        <p>Select a project to get the widget code.</p>
      </div>
    <?php else: ?>

    <!-- Embed Code -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="font-bold text-gray-900 mb-2">Embed Code</h2>
      <p class="text-sm text-gray-500 mb-4">Add this script tag to your website's <code class="bg-gray-100 px-1 rounded">&lt;head&gt;</code> or just before <code class="bg-gray-100 px-1 rounded">&lt;/body&gt;</code>:</p>
      <div class="relative bg-gray-900 rounded-xl p-4">
        <pre id="embedCodeDisplay" class="text-green-400 text-sm font-mono whitespace-pre-wrap break-all">&lt;script src="<?= h(APP_URL) ?>/widget/widget.js" data-key="<?= h($currentProject['widget_key']) ?>" defer&gt;&lt;/script&gt;</pre>
        <button id="copyEmbedBtn"
                data-code="<?= htmlspecialchars($embedCode, ENT_QUOTES) ?>"
                class="absolute top-3 right-3 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
          <i class="fas fa-copy"></i> <span>Copy</span>
        </button>
      </div>
      <div class="mt-4 flex items-center gap-3">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="_action" value="regenerate_key">
          <button type="submit" onclick="return confirm('Regenerate key? Old embeds will stop working!')" class="text-sm text-orange-600 hover:underline"><i class="fas fa-sync-alt mr-1"></i>Regenerate Key</button>
        </form>
        <span class="text-gray-300">|</span>
        <span class="text-sm text-gray-400">Key: <code class="bg-gray-100 px-2 py-0.5 rounded font-mono text-xs"><?= h($currentProject['widget_key']) ?></code></span>
      </div>
    </div>

    <!-- Widget Preview -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="font-bold text-gray-900 mb-4">Current Configuration</h2>
        <div class="space-y-3 text-sm">
          <?php $rows = [
            ['Color', "<span class='inline-flex items-center gap-2'><span class='w-4 h-4 rounded inline-block' style='background:" . h($currentProject['widget_color']) . "'></span>" . h($currentProject['widget_color']) . "</span>"],
            ['Position', h($currentProject['widget_position'] ?? 'bottom-right')],
            ['Theme', h($currentProject['widget_theme'] ?? 'light')],
            ['Title', h($currentProject['widget_title'] ?? '')],
            ['Placeholder', h($currentProject['widget_placeholder'] ?? '')],
            ['Anonymous?', $currentProject['allow_anonymous'] ? '<span class="text-green-600">Yes</span>' : '<span class="text-red-600">No</span>'],
          ]; foreach ($rows as [$label, $val]): ?>
            <div class="flex items-center gap-4 py-2 border-b border-gray-50">
              <span class="text-gray-500 w-28 flex-shrink-0"><?= $label ?></span>
              <span class="text-gray-900"><?= $val ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <a href="<?= APP_URL ?>/admin/projects.php?edit=<?= $currentProject['id'] ?>" class="mt-4 inline-flex items-center gap-2 text-sm text-indigo-600 hover:underline"><i class="fas fa-cog"></i> Change in Project Settings</a>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="font-bold text-gray-900 mb-4">Widget Preview</h2>
        <div class="relative bg-gray-100 rounded-xl overflow-hidden" style="height: 300px;">
          <div class="absolute inset-0 flex items-center justify-center text-gray-400 text-sm">
            <div class="text-center">
              <i class="fas fa-desktop text-3xl block mb-2 text-gray-300"></i>
              Your website content here
            </div>
          </div>
          <!-- Simulated widget button -->
          <div class="absolute <?= $currentProject['widget_position'] === 'bottom-left' ? 'bottom-4 left-4' : ($currentProject['widget_position'] === 'top-right' ? 'top-4 right-4' : ($currentProject['widget_position'] === 'top-left' ? 'top-4 left-4' : 'bottom-4 right-4')) ?>">
            <button class="flex items-center gap-2 text-white text-sm font-medium px-4 py-2.5 rounded-full shadow-lg" style="background: <?= h($currentProject['widget_color']) ?>">
              <i class="fas fa-comments"></i> Feedback
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Installation Instructions -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="font-bold text-gray-900 mb-4">Installation Guide</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php $steps = [
          ['1', 'Copy the code', 'Copy the script tag above and paste it into your HTML.', 'copy'],
          ['2', 'Customize widget', 'Go to Project Settings to change colors, position, and theme.', 'palette'],
          ['3', 'Start collecting', 'Users will see the feedback button and can submit instantly.', 'rocket'],
        ]; foreach ($steps as [$num, $title, $desc, $icon]): ?>
          <div class="flex items-start gap-4">
            <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center text-white font-bold flex-shrink-0 text-lg"><?= $num ?></div>
            <div>
              <p class="font-semibold text-gray-900"><?= $title ?></p>
              <p class="text-sm text-gray-500 mt-1"><?= $desc ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Advanced Options -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="font-bold text-gray-900 mb-4">Advanced Usage</h2>
      <p class="text-sm text-gray-500 mb-4">You can also use JavaScript to control the widget programmatically:</p>
      <div class="bg-gray-900 rounded-xl p-4 text-sm font-mono space-y-2">
        <p class="text-gray-400">// Open the widget programmatically</p>
        <p class="text-green-400">FeedbackFlow.open();</p>
        <p class="text-gray-400 mt-2">// Pre-fill user info</p>
        <p class="text-green-400">FeedbackFlow.identify('user@email.com', 'John Doe');</p>
        <p class="text-gray-400 mt-2">// Open on button click</p>
        <p class="text-green-400">&lt;button onclick="FeedbackFlow.open()"&gt;Give Feedback&lt;/button&gt;</p>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>
<script>
document.getElementById('copyEmbedBtn')?.addEventListener('click', function() {
  var code = this.getAttribute('data-code');
  var span = this.querySelector('span');
  navigator.clipboard.writeText(code).then(function() {
    span.textContent = 'Copied!';
    setTimeout(function() { span.textContent = 'Copy'; }, 2000);
  }).catch(function() {
    var ta = document.createElement('textarea');
    ta.value = code;
    ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select(); document.execCommand('copy');
    document.body.removeChild(ta);
    span.textContent = 'Copied!';
    setTimeout(function() { span.textContent = 'Copy'; }, 2000);
  });
});
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
