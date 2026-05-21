<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('run_campaigns');
$userProjects = getUserProjects($currentUser['id']);
$pageTitle = 'Review Booster – ' . APP_NAME;

Auth::start();
if (isset($_GET['project_id'])) $_SESSION['current_project_id'] = (int)$_GET['project_id'];
$projectId = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save' && $projectId) {
        $data = [
            'project_id'       => $projectId,
            'name'             => trim($_POST['name'] ?? 'Review Booster'),
            'platform'         => in_array($_POST['platform'] ?? '', ['google','yelp','tripadvisor','trustpilot','facebook','custom']) ? $_POST['platform'] : 'google',
            'review_url'       => trim($_POST['review_url'] ?? ''),
            'min_rating'       => max(1, min(5, (int)($_POST['min_rating'] ?? 4))),
            'message_template' => trim($_POST['message_template'] ?? ''),
            'is_active'        => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($data['review_url']) {
            if (!empty($_POST['id'])) {
                DB::update('ff_review_boosters', $data, 'id = ? AND project_id = ?', [(int)$_POST['id'], $projectId]);
                flash('success', 'Review booster updated.');
            } else {
                DB::insert('ff_review_boosters', $data);
                flash('success', 'Review booster created.');
            }
        }
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        DB::delete('ff_review_boosters', 'id = ? AND project_id = ?', [(int)$_POST['id'], $projectId]);
        flash('success', 'Booster deleted.');
    }

    redirect(APP_URL . '/admin/review-booster.php');
}

$boosters = $projectId ? DB::fetchAll("SELECT * FROM ff_review_boosters WHERE project_id = ? ORDER BY created_at DESC", [$projectId]) : [];
$edit = null;
if (isset($_GET['edit'])) {
    $edit = DB::fetch("SELECT * FROM ff_review_boosters WHERE id = ? AND project_id = ?", [(int)$_GET['edit'], $projectId]);
}

$flash = getFlash();
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">

  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Review Booster</h1>
      <p class="text-sm text-gray-500 mt-0.5">Automatically redirect happy customers to leave public reviews (Module 11)</p>
    </div>
    <button onclick="document.getElementById('formModal').classList.remove('hidden')"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
      <i class="fas fa-plus"></i> New Booster
    </button>
  </header>

  <div class="p-6 space-y-5">
    <?php foreach ($flash as $f): ?>
      <div class="rounded-xl px-4 py-3 text-sm font-medium <?= $f['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
        <?= h($f['msg']) ?>
      </div>
    <?php endforeach; ?>

    <?php if (!$currentProject): ?>
    <div class="ff-card p-12 text-center">
      <i class="fas fa-star text-4xl text-gray-200 mb-3"></i>
      <p class="text-gray-500 font-medium">Select a project to manage review boosters.</p>
    </div>
    <?php elseif (empty($boosters)): ?>
    <div class="ff-card p-12 text-center">
      <div class="w-16 h-16 bg-yellow-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-star text-yellow-500 text-2xl"></i>
      </div>
      <h2 class="text-lg font-bold text-gray-900 mb-2">No review boosters yet</h2>
      <p class="text-gray-500 text-sm mb-5">When a customer gives you 4–5 stars, automatically invite them to leave a public review on Google, Yelp, and more.</p>
      <button onclick="document.getElementById('formModal').classList.remove('hidden')"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition">
        <i class="fas fa-plus"></i> Create First Booster
      </button>
    </div>
    <?php else: ?>

    <!-- How it works banner -->
    <div class="bg-gradient-to-r from-yellow-50 to-amber-50 border border-amber-100 rounded-2xl p-5 flex items-start gap-4">
      <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
        <i class="fas fa-lightbulb text-amber-600"></i>
      </div>
      <div>
        <p class="text-sm font-semibold text-gray-900">How Review Booster Works</p>
        <p class="text-sm text-gray-600 mt-0.5">When a customer rates their experience at or above your minimum rating, FeedbackFlow shows them a prompt to leave a public review on the platform of your choice. This converts your happiest customers into brand advocates.</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <?php foreach ($boosters as $b): ?>
      <?php
        $platform_icons = ['google'=>'fab fa-google','yelp'=>'fab fa-yelp','tripadvisor'=>'fab fa-tripadvisor','trustpilot'=>'fas fa-check-circle','facebook'=>'fab fa-facebook','custom'=>'fas fa-external-link-alt'];
        $platform_colors = ['google'=>'red','yelp'=>'red','tripadvisor'=>'green','trustpilot'=>'green','facebook'=>'blue','custom'=>'indigo'];
        $icon = $platform_icons[$b['platform']] ?? 'fas fa-star';
        $color = $platform_colors[$b['platform']] ?? 'indigo';
        $conversion = $b['requests_sent'] > 0 ? round($b['requests_clicked'] / $b['requests_sent'] * 100, 1) : 0;
      ?>
      <div class="ff-card p-6">
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-<?= $color ?>-100 flex items-center justify-center">
              <i class="<?= $icon ?> text-<?= $color ?>-600"></i>
            </div>
            <div>
              <h3 class="font-semibold text-gray-900"><?= h($b['name']) ?></h3>
              <p class="text-xs text-gray-400 capitalize"><?= h($b['platform']) ?> · Min <?= $b['min_rating'] ?> stars</p>
            </div>
          </div>
          <span class="badge <?= $b['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
            <?= $b['is_active'] ? 'Active' : 'Inactive' ?>
          </span>
        </div>

        <div class="grid grid-cols-3 gap-3 mb-4">
          <div class="bg-gray-50 rounded-xl p-3 text-center">
            <p class="text-xl font-bold text-gray-900"><?= number_format($b['requests_sent']) ?></p>
            <p class="text-xs text-gray-400">Sent</p>
          </div>
          <div class="bg-gray-50 rounded-xl p-3 text-center">
            <p class="text-xl font-bold text-gray-900"><?= number_format($b['requests_clicked']) ?></p>
            <p class="text-xs text-gray-400">Clicked</p>
          </div>
          <div class="bg-gray-50 rounded-xl p-3 text-center">
            <p class="text-xl font-bold text-<?= $conversion >= 20 ? 'green' : 'gray' ?>-<?= $conversion >= 20 ? '700' : '900' ?>"><?= $conversion ?>%</p>
            <p class="text-xs text-gray-400">Conversion</p>
          </div>
        </div>

        <div class="text-xs text-gray-400 mb-4 bg-gray-50 rounded-xl p-3 truncate">
          <i class="fas fa-link text-gray-300 mr-1"></i><?= h($b['review_url']) ?>
        </div>

        <div class="flex gap-2">
          <a href="?edit=<?= $b['id'] ?>" onclick="showEditModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>); return false;"
             class="flex-1 text-center border border-gray-200 hover:bg-gray-50 text-gray-700 py-2 rounded-xl text-sm font-medium transition">
            <i class="fas fa-edit mr-1"></i>Edit
          </a>
          <form method="post" class="flex-none">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $b['id'] ?>">
            <button onclick="return confirm('Delete this booster?')"
                    class="border border-red-200 hover:bg-red-50 text-red-500 py-2 px-4 rounded-xl text-sm font-medium transition">
              <i class="fas fa-trash"></i>
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>

<!-- Create/Edit Modal -->
<div id="formModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-6 max-h-screen overflow-y-auto">
    <div class="flex items-center justify-between mb-5">
      <h2 id="modalTitle" class="text-lg font-bold text-gray-900">New Review Booster</h2>
      <button onclick="document.getElementById('formModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
    <form method="post" class="space-y-4" id="boosterForm">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="boosterId" value="">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Booster Name *</label>
        <input type="text" name="name" id="boosterName" required placeholder="e.g. Google Review Request"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Platform *</label>
        <select name="platform" id="boosterPlatform" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <option value="google">Google</option>
          <option value="yelp">Yelp</option>
          <option value="tripadvisor">TripAdvisor</option>
          <option value="trustpilot">Trustpilot</option>
          <option value="facebook">Facebook</option>
          <option value="custom">Custom URL</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Review URL *</label>
        <input type="url" name="review_url" id="boosterUrl" required placeholder="https://g.page/r/..."
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Minimum Rating to Trigger</label>
        <select name="min_rating" id="boosterMinRating" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
          <option value="3">3 stars or more</option>
          <option value="4" selected>4 stars or more</option>
          <option value="5">5 stars only</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Custom Message (optional)</label>
        <textarea name="message_template" id="boosterMessage" rows="3" placeholder="Enjoying our service? Leave us a review!"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
      </div>
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_active" id="boosterActive" value="1" checked class="rounded">
        <span class="text-sm font-medium text-gray-700">Active</span>
      </label>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="document.getElementById('formModal').classList.add('hidden')"
                class="flex-1 border border-gray-200 text-gray-700 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition">Cancel</button>
        <button class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-sm font-semibold transition">Save Booster</button>
      </div>
    </form>
  </div>
</div>

<script>
function showEditModal(data) {
  document.getElementById('formModal').classList.remove('hidden');
  document.getElementById('modalTitle').textContent = 'Edit Review Booster';
  document.getElementById('boosterId').value = data.id;
  document.getElementById('boosterName').value = data.name;
  document.getElementById('boosterPlatform').value = data.platform;
  document.getElementById('boosterUrl').value = data.review_url;
  document.getElementById('boosterMinRating').value = data.min_rating;
  document.getElementById('boosterMessage').value = data.message_template || '';
  document.getElementById('boosterActive').checked = data.is_active == 1;
}
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
