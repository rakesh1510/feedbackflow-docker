<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('run_campaigns');
$userProjects  = getUserProjects($currentUser['id']);
Auth::start();
if (isset($_GET['project_id'])) $_SESSION['current_project_id'] = (int)$_GET['project_id'];
$projectId     = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;
if (!$currentProject && !empty($userProjects)) { $currentProject = $userProjects[0]; $projectId = $currentProject['id']; }

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['_action'] ?? '';

    // Create feedback link (QR / WhatsApp / SMS / Direct)
    if ($action === 'create_link') {
        $source = sanitize($_POST['link_source'] ?? 'direct');
        $title  = sanitize($_POST['link_title'] ?? ucfirst($source) . ' Link');
        $rq     = sanitize($_POST['rating_question'] ?? 'How was your experience?');
        DB::insert('ff_feedback_links', [
            'project_id'      => $projectId,
            'title'           => $title,
            'source'          => $source,
            'token'           => randomKey(32),
            'rating_question' => $rq,
        ]);
        flash('success', 'Feedback link created!');
    }

    if ($action === 'delete_link') {
        DB::delete('ff_feedback_links', 'id = ? AND project_id = ?', [(int)$_POST['link_id'], $projectId]);
        flash('success', 'Link deleted.');
    }

    redirect(APP_URL . '/admin/channels.php');
}

$tab = $_GET['tab'] ?? 'overview';

// Load data
$links = $projectId ? DB::fetchAll(
    "SELECT * FROM ff_feedback_links WHERE project_id = ? ORDER BY created_at DESC", [$projectId]
) : [];

$campaigns = $projectId ? DB::fetchAll(
    "SELECT * FROM ff_email_campaigns WHERE project_id = ? ORDER BY created_at DESC", [$projectId]
) : [];

// Source stats
$sourceStats = $projectId ? DB::fetchAll(
    "SELECT source, COUNT(*) as cnt FROM ff_feedback WHERE project_id = ? GROUP BY source ORDER BY cnt DESC",
    [$projectId]
) : [];
$sourceMap = [];
foreach ($sourceStats as $s) $sourceMap[$s['source']] = $s['cnt'];
$totalFeedback = array_sum(array_column($sourceStats, 'cnt')) ?: 0;

$pageTitle = 'Collection Channels – ' . APP_NAME;
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto min-h-screen bg-gray-50">

  <!-- Header -->
  <header class="sticky top-0 z-40 bg-white/80 backdrop-blur border-b border-gray-100 px-8 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Collection Channels</h1>
      <p class="text-xs text-gray-400 mt-0.5">Collect feedback from every touchpoint</p>
    </div>
    <a href="<?= APP_URL ?>/admin/email-campaigns.php"
       class="inline-flex items-center gap-2 text-white text-sm font-semibold px-5 py-2.5 rounded-xl hover:opacity-90 transition shadow-sm"
       style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
      <i class="fas fa-envelope"></i> Email Campaign
    </a>
  </header>

  <?php foreach (getFlash() as $f): ?>
    <div class="mx-8 mt-5 flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $f['type']==='success'?'bg-green-50 text-green-700 border border-green-200':'bg-red-50 text-red-700 border border-red-200' ?>">
      <i class="fas <?= $f['type']==='success'?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= h($f['msg']) ?>
    </div>
  <?php endforeach; ?>

  <?php if (!$currentProject): ?>
    <div class="m-8 bg-white rounded-2xl border border-gray-100 p-16 text-center text-gray-400">
      <i class="fas fa-folder-open text-4xl mb-3 block"></i>
      <p>Select a project first.</p>
    </div>
  <?php else: ?>

  <!-- Tabs -->
  <div class="px-8 pt-6">
    <div class="flex gap-1 bg-gray-100 p-1 rounded-xl w-fit">
      <?php foreach (['overview'=>'Overview','links'=>'Shareable Links','qr'=>'QR Code','email'=>'Email Campaigns','embed'=>'Embedded Form'] as $k=>$v): ?>
        <a href="?tab=<?= $k ?>"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-all <?= $tab===$k?'bg-white text-gray-900 shadow-sm':'text-gray-500 hover:text-gray-700' ?>">
          <?= $v ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="p-8">

  <!-- ── OVERVIEW ─────────────────────────────────────────── -->
  <?php if ($tab === 'overview'): ?>

    <!-- Source breakdown cards -->
    <?php
    $channels = [
      ['widget',   'Website Widget',    'fas fa-code',          '#6366f1', 'Floating button on your website'],
      ['email',    'Email Campaign',    'fas fa-envelope',      '#ec4899', 'Branded email with rating buttons'],
      ['qr',       'QR Code',           'fas fa-qrcode',        '#10b981', 'Printable code for offline use'],
      ['whatsapp', 'WhatsApp',          'fab fa-whatsapp',      '#25d366', 'Link shared via WhatsApp message'],
      ['sms',      'SMS',               'fas fa-sms',           '#f59e0b', 'Short link sent via SMS'],
      ['embedded', 'Embedded Form',     'fas fa-window-restore','#3b82f6', 'Inline form placed on any page'],
      ['public',   'Public Board',      'fas fa-globe',         '#8b5cf6', 'Users submit on your public board'],
      ['in-app',   'In-App',            'fas fa-mobile-alt',    '#06b6d4', 'Popup inside your application'],
    ];
    ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <?php foreach ($channels as [$src, $label, $icon, $color, $desc]): ?>
        <?php $cnt = $sourceMap[$src] ?? 0; $pct = $totalFeedback > 0 ? round($cnt/$totalFeedback*100) : 0; ?>
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white mb-3" style="background:<?= $color ?>22">
            <i class="<?= $icon ?> text-base" style="color:<?= $color ?>"></i>
          </div>
          <p class="text-2xl font-bold text-gray-900"><?= number_format($cnt) ?></p>
          <p class="text-sm font-medium text-gray-600"><?= $label ?></p>
          <div class="mt-3 h-1.5 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
          </div>
          <p class="text-xs text-gray-400 mt-1"><?= $pct ?>% of total</p>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Quick start guide -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
      <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Quick Setup — All Channels</h3>
        <p class="text-sm text-gray-400 mt-0.5">Click a tab above to configure each channel</p>
      </div>
      <div class="divide-y divide-gray-50">
        <?php foreach ($channels as [$src, $label, $icon, $color, $desc]): ?>
        <div class="flex items-center gap-4 px-6 py-4">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" style="background:<?= $color ?>18">
            <i class="<?= $icon ?>" style="color:<?= $color ?>"></i>
          </div>
          <div class="flex-1">
            <p class="text-sm font-semibold text-gray-800"><?= $label ?></p>
            <p class="text-xs text-gray-400"><?= $desc ?></p>
          </div>
          <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                style="background:<?= $color ?>15;color:<?= $color ?>">
            <?= ($sourceMap[$src] ?? 0) ?> responses
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  <!-- ── SHAREABLE LINKS ───────────────────────────────────── -->
  <?php elseif ($tab === 'links'): ?>

    <div class="grid lg:grid-cols-2 gap-6">
      <!-- Create new link -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900 flex items-center gap-2">
            <i class="fas fa-link text-indigo-500 text-sm"></i> Create Feedback Link
          </h3>
        </div>
        <form method="POST" class="p-6 space-y-4">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="_action" value="create_link">
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Channel</label>
            <select name="link_source" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 outline-none focus:ring-2 focus:ring-indigo-400">
              <option value="direct">🔗 Direct Link</option>
              <option value="whatsapp">💚 WhatsApp</option>
              <option value="sms">💬 SMS</option>
              <option value="qr">📷 QR Code</option>
              <option value="in-app">📱 In-App</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Link Title</label>
            <input type="text" name="link_title" placeholder="e.g. Post-purchase Survey" required
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 outline-none focus:ring-2 focus:ring-indigo-400">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Rating Question</label>
            <input type="text" name="rating_question" value="How was your experience?" 
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 outline-none focus:ring-2 focus:ring-indigo-400">
          </div>
          <button type="submit" class="w-full py-2.5 rounded-xl text-white text-sm font-semibold hover:opacity-90 transition"
                  style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
            Generate Link
          </button>
        </form>
      </div>

      <!-- WhatsApp / SMS quick generator -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900 flex items-center gap-2">
            <i class="fab fa-whatsapp text-green-500 text-sm"></i> WhatsApp & SMS Templates
          </h3>
        </div>
        <div class="p-6 space-y-4">
          <?php $boardUrl = APP_URL . '/public/feedback-link.php?slug=' . h($currentProject['slug']) . '&source=whatsapp'; ?>
          <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">WhatsApp Message</p>
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm text-gray-700 leading-relaxed">
              Hi [Name] 👋<br><br>
              We'd love to know about your experience with <strong><?= h($currentProject['name']) ?></strong>.<br><br>
              📝 Share your feedback here:<br>
              <?= APP_URL ?>/public/feedback-link.php?slug=<?= h($currentProject['slug']) ?>&source=whatsapp<br><br>
              It only takes 1 minute. Thank you! 🙏
            </div>
            <a href="https://wa.me/?text=<?= urlencode('Hi! 👋 We\'d love your feedback on ' . $currentProject['name'] . '. Share it here: ' . APP_URL . '/public/feedback-link.php?slug=' . $currentProject['slug'] . '&source=whatsapp') ?>"
               target="_blank"
               class="mt-2 inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
              <i class="fab fa-whatsapp"></i> Open in WhatsApp
            </a>
          </div>
          <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">SMS Message</p>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-sm text-gray-700 leading-relaxed">
              <?= h($currentProject['name']) ?>: Quick feedback? Tap here →
              <?= APP_URL ?>/public/feedback-link.php?slug=<?= h($currentProject['slug']) ?>&source=sms
              Reply STOP to opt out.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Existing links list -->
    <?php if (!empty($links)): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mt-6">
      <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Your Feedback Links</h3>
      </div>
      <div class="divide-y divide-gray-50">
        <?php
        $srcIcon = ['direct'=>'🔗','whatsapp'=>'💚','sms'=>'💬','qr'=>'📷','in-app'=>'📱'];
        foreach ($links as $lnk):
          $lUrl = APP_URL . '/public/feedback-link.php?token=' . $lnk['token'];
        ?>
        <div class="flex items-center gap-4 px-6 py-4">
          <div class="text-xl"><?= $srcIcon[$lnk['source']] ?? '🔗' ?></div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-gray-800"><?= h($lnk['title']) ?></p>
            <p class="text-xs text-gray-400 truncate"><?= $lUrl ?></p>
          </div>
          <div class="flex items-center gap-4 text-xs text-gray-500 flex-shrink-0">
            <span><strong class="text-gray-700"><?= $lnk['click_count'] ?></strong> clicks</span>
            <span><strong class="text-gray-700"><?= $lnk['submit_count'] ?></strong> submissions</span>
          </div>
          <div class="flex gap-2 flex-shrink-0">
            <button onclick="copyLink('<?= $lUrl ?>', this)"
                    class="text-xs bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg transition font-medium">
              <i class="fas fa-copy"></i> Copy
            </button>
            <a href="<?= $lUrl ?>" target="_blank"
               class="text-xs bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg transition font-medium">
              <i class="fas fa-external-link-alt"></i>
            </a>
            <form method="POST" class="inline" onsubmit="return confirm('Delete this link?')">
              <input type="hidden" name="_csrf" value="<?= csrf() ?>">
              <input type="hidden" name="_action" value="delete_link">
              <input type="hidden" name="link_id" value="<?= $lnk['id'] ?>">
              <button type="submit" class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1.5 rounded-lg transition">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  <!-- ── QR CODE ───────────────────────────────────────────── -->
  <?php elseif ($tab === 'qr'): ?>
    <?php
    $qrUrl  = APP_URL . '/public/feedback-link.php?slug=' . $currentProject['slug'] . '&source=qr';
    $qrImg  = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . urlencode($qrUrl);
    $qrLinks = array_filter($links, fn($l) => $l['source'] === 'qr');
    ?>
    <div class="grid lg:grid-cols-2 gap-6">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900 flex items-center gap-2">
            <i class="fas fa-qrcode text-indigo-500"></i> Your Feedback QR Code
          </h3>
        </div>
        <div class="p-8 text-center">
          <div class="inline-block p-4 bg-white border-2 border-gray-200 rounded-2xl shadow-sm mb-4">
            <img src="<?= $qrImg ?>" alt="QR Code" class="w-48 h-48 block">
          </div>
          <p class="text-sm font-semibold text-gray-800 mb-1"><?= h($currentProject['name']) ?></p>
          <p class="text-xs text-gray-400 mb-5">Scan to share feedback</p>
          <div class="flex gap-3 justify-center flex-wrap">
            <a href="<?= $qrImg ?>&format=png" download="feedbackflow-qr-<?= h($currentProject['slug']) ?>.png"
               class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition">
              <i class="fas fa-download"></i> Download PNG
            </a>
            <a href="<?= $qrUrl ?>" target="_blank"
               class="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold px-5 py-2.5 rounded-xl transition">
              <i class="fas fa-external-link-alt"></i> Preview
            </a>
          </div>
        </div>
      </div>
      <div class="space-y-4">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2"><i class="fas fa-lightbulb text-yellow-400"></i> Where to use QR codes</h4>
          <ul class="space-y-2.5 text-sm text-gray-600">
            <?php foreach (['🍽 Restaurants — table cards or receipts','🏨 Hotels — in-room cards or checkout','🏥 Clinics — waiting room posters','🎪 Events — badge inserts or banners','🛍 Retail — packaging or counter displays','📦 Delivery — parcel slips'] as $u): ?>
              <li class="flex items-start gap-2"><?= $u ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h4 class="font-semibold text-gray-900 mb-3">Print-ready tip</h4>
          <p class="text-sm text-gray-500">Download the PNG above and insert it into your Word, Canva, or Illustrator design. Minimum print size: <strong>3×3 cm</strong> for reliable scanning.</p>
        </div>
        <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-5">
          <p class="text-sm font-semibold text-indigo-700 mb-1">Custom QR for specific locations?</p>
          <p class="text-sm text-indigo-600">Go to the Shareable Links tab, create a new QR link per location (e.g. "Table 5 QR"), then generate its individual QR code to track responses by location.</p>
        </div>
      </div>
    </div>

  <!-- ── EMAIL CAMPAIGNS ───────────────────────────────────── -->
  <?php elseif ($tab === 'email'): ?>
    <div class="grid lg:grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm text-center">
        <p class="text-3xl font-bold text-gray-900"><?= count($campaigns) ?></p>
        <p class="text-sm text-gray-400 mt-1">Campaigns</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm text-center">
        <p class="text-3xl font-bold text-gray-900"><?= array_sum(array_column($campaigns,'sent_count')) ?></p>
        <p class="text-sm text-gray-400 mt-1">Emails Sent</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm text-center">
        <p class="text-3xl font-bold text-gray-900"><?= array_sum(array_column($campaigns,'submit_count')) ?></p>
        <p class="text-sm text-gray-400 mt-1">Responses</p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">Email Campaigns</h3>
        <a href="<?= APP_URL ?>/admin/email-campaigns.php"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
          <i class="fas fa-plus"></i> New Campaign
        </a>
      </div>
      <?php if (empty($campaigns)): ?>
        <div class="py-16 text-center text-gray-400">
          <i class="fas fa-envelope text-4xl block mb-3"></i>
          <p class="font-medium">No campaigns yet</p>
          <p class="text-sm mt-1">Create your first email campaign to collect feedback via email.</p>
          <a href="<?= APP_URL ?>/admin/email-campaigns.php" class="mt-4 inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-5 py-2.5 rounded-xl hover:bg-indigo-700 transition"><i class="fas fa-plus"></i> Create Campaign</a>
        </div>
      <?php else: ?>
        <div class="divide-y divide-gray-50">
          <?php foreach ($campaigns as $c):
            $rate = $c['sent_count'] > 0 ? round($c['submit_count']/$c['sent_count']*100) : 0;
            $statusColor = ['draft'=>'bg-gray-100 text-gray-600','sending'=>'bg-blue-100 text-blue-600','sent'=>'bg-green-100 text-green-600'][$c['status']] ?? '';
          ?>
          <div class="flex items-center gap-4 px-6 py-4">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-pink-50 flex-shrink-0">
              <i class="fas fa-envelope text-pink-500"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800"><?= h($c['name']) ?></p>
              <p class="text-xs text-gray-400"><?= h($c['subject']) ?></p>
            </div>
            <div class="flex items-center gap-6 text-xs text-gray-500 flex-shrink-0">
              <div class="text-center"><p class="text-base font-bold text-gray-800"><?= $c['sent_count'] ?></p><p>Sent</p></div>
              <div class="text-center"><p class="text-base font-bold text-gray-800"><?= $c['open_count'] ?></p><p>Opens</p></div>
              <div class="text-center"><p class="text-base font-bold text-gray-800"><?= $c['submit_count'] ?></p><p>Responses</p></div>
              <div class="text-center"><p class="text-base font-bold text-green-600"><?= $rate ?>%</p><p>Rate</p></div>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?= $statusColor ?>"><?= ucfirst($c['status']) ?></span>
            <a href="<?= APP_URL ?>/admin/email-campaigns.php?id=<?= $c['id'] ?>"
               class="text-xs bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg transition font-medium flex-shrink-0">
              <?= $c['status']==='draft' ? 'Edit' : 'View' ?>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  <!-- ── EMBEDDED FORM ─────────────────────────────────────── -->
  <?php elseif ($tab === 'embed'): ?>
    <?php
    $embedFormCode = '<div id="ff-embed-form"></div>
<script>
  window.FFConfig = {
    key: "' . $currentProject['widget_key'] . '",
    baseUrl: "' . APP_URL . '",
    theme: "light",
    showRating: true,
    ratingQuestion: "How was your experience?",
    source: "embedded"
  };
</script>
<script src="' . APP_URL . '/widget/embed-form.js" defer></script>';
    ?>
    <div class="grid lg:grid-cols-2 gap-6">
      <div class="space-y-6">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
          <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900 flex items-center gap-2">
              <i class="fas fa-window-restore text-blue-500 text-sm"></i> Embed Code
            </h3>
          </div>
          <div class="p-6">
            <p class="text-sm text-gray-500 mb-4">Paste this snippet wherever you want the form to appear on your page — support page, checkout, thank-you page, etc.</p>
            <div class="relative bg-gray-900 rounded-xl p-4">
              <pre class="text-green-400 text-xs font-mono whitespace-pre-wrap break-all"><?= htmlspecialchars($embedFormCode) ?></pre>
              <button data-code="<?= htmlspecialchars($embedFormCode, ENT_QUOTES) ?>"
                      onclick="copyCode(this)"
                      class="absolute top-3 right-3 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                <i class="fas fa-copy"></i> <span>Copy</span>
              </button>
            </div>
            <div class="mt-4 bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-700">
              <strong>Difference from widget:</strong> The floating widget button appears in the corner of every page. The embedded form appears inline exactly where you paste the code — great for dedicated feedback pages.
            </div>
          </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h4 class="font-semibold text-gray-900 mb-3">Best places to embed</h4>
          <ul class="space-y-2 text-sm text-gray-600">
            <?php foreach (['📞 Contact / Support page','✅ Thank-you / order confirmation page','🏠 Dashboard sidebar or footer','📧 End of email newsletters','🛒 After checkout','📖 Help docs pages'] as $p): ?>
              <li><?= $p ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <!-- Live preview mockup -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900">Preview</h3>
        </div>
        <div class="p-6">
          <div class="border-2 border-dashed border-gray-200 rounded-2xl p-6 bg-gray-50">
            <p class="text-xs text-gray-400 text-center mb-4">— Your page content above —</p>

            <!-- Simulated embedded form -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
              <p class="text-sm font-semibold text-gray-700 mb-3">How was your experience?</p>
              <div class="flex gap-1 mb-4">
                <?php for ($i=1;$i<=5;$i++): ?>
                  <span class="text-2xl" style="color:<?= $i<=4?'#f59e0b':'#e5e7eb' ?>">★</span>
                <?php endfor; ?>
              </div>
              <div class="mb-3">
                <select class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs text-gray-500 bg-gray-50">
                  <option>— Select category —</option>
                </select>
              </div>
              <textarea class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs text-gray-500 bg-gray-50 resize-none" rows="3" placeholder="Tell us what you think..."></textarea>
              <button class="mt-3 w-full py-2 rounded-xl text-white text-xs font-semibold"
                      style="background:<?= $currentProject['widget_color'] ?>">
                Submit Feedback
              </button>
            </div>

            <p class="text-xs text-gray-400 text-center mt-4">— Your page content below —</p>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
  </div>

  <?php endif; ?>
</main>
</div>

<script>
function copyLink(url, btn) {
  navigator.clipboard.writeText(url).then(() => {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    setTimeout(() => btn.innerHTML = orig, 2000);
  });
}
function copyCode(btn) {
  const code = btn.getAttribute('data-code');
  const span = btn.querySelector('span');
  navigator.clipboard.writeText(code).then(() => {
    span.textContent = 'Copied!';
    setTimeout(() => span.textContent = 'Copy', 2000);
  });
}
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
