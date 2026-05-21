<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('project_settings');
$userProjects = getUserProjects($currentUser['id']);
$pageTitle = 'Export & Import – ' . APP_NAME;

Auth::start();
if (isset($_GET['project_id'])) $_SESSION['current_project_id'] = (int)$_GET['project_id'];
$projectId = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;

// Handle direct CSV download
if (isset($_GET['download']) && $projectId) {
    $type = $_GET['download'];
    $filename = 'feedbackflow-' . $type . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');

    if ($type === 'feedback') {
        fputcsv($out, ['ID','Title','Description','Status','Priority','Sentiment','Rating','Source','Submitter','Email','Tags','Created At']);
        $rows = DB::fetchAll("SELECT * FROM ff_feedback WHERE project_id = ? ORDER BY created_at DESC", [$projectId]);
        foreach ($rows as $r) {
            fputcsv($out, [$r['id'],$r['title'],$r['description'],$r['status'],$r['priority'],$r['ai_sentiment'] ?? '',$r['rating'] ?? '',$r['source'] ?? '',$r['submitter_name'] ?? '',$r['submitter_email'] ?? '',$r['tags'] ?? '',formatDate($r['created_at'],'Y-m-d H:i:s')]);
        }
    } elseif ($type === 'contacts') {
        fputcsv($out, ['ID','Name','Email','Phone','Source','Created At']);
        $rows = DB::fetchAll("SELECT DISTINCT submitter_name as name, submitter_email as email, submitter_phone as phone, source, MIN(created_at) as created_at FROM ff_feedback WHERE project_id = ? AND submitter_email IS NOT NULL GROUP BY submitter_email ORDER BY created_at DESC", [$projectId]);
        foreach ($rows as $r) { fputcsv($out, ['',$r['name'],$r['email'],$r['phone'],$r['source'],formatDate($r['created_at'],'Y-m-d')]); }
    } elseif ($type === 'analytics') {
        fputcsv($out, ['Date','Feedback Count','Avg Rating','Positive','Neutral','Negative']);
        $rows = DB::fetchAll("SELECT DATE(created_at) as day, COUNT(*) as total, AVG(rating) as avg_rating, SUM(ai_sentiment='positive') as pos, SUM(ai_sentiment='neutral') as neu, SUM(ai_sentiment='negative') as neg FROM ff_feedback WHERE project_id = ? GROUP BY DATE(created_at) ORDER BY day DESC", [$projectId]);
        foreach ($rows as $r) { fputcsv($out, [$r['day'],$r['total'],round((float)$r['avg_rating'],1),$r['pos'],$r['neu'],$r['neg']]); }
    } elseif ($type === 'audit_logs') {
        fputcsv($out, ['ID','Action','User','Email','Resource','IP','When']);
        $rows = DB::fetchAll("SELECT * FROM ff_audit_logs ORDER BY created_at DESC LIMIT 10000");
        foreach ($rows as $r) { fputcsv($out, [$r['id'],$r['action'],$r['user_name'],$r['user_email'],$r['resource_type'],$r['ip_address'],formatDate($r['created_at'],'Y-m-d H:i:s')]); }
    }
    fclose($out);
    exit;
}

$flash = getFlash();
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">

  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4">
    <h1 class="text-xl font-bold text-gray-900">Export & Import</h1>
    <p class="text-sm text-gray-500 mt-0.5">Download your data or import contacts (Module 27)</p>
  </header>

  <div class="p-6 space-y-6">
    <?php foreach ($flash as $f): ?>
      <div class="rounded-xl px-4 py-3 text-sm font-medium <?= $f['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
        <?= h($f['msg']) ?>
      </div>
    <?php endforeach; ?>

    <!-- Export Section -->
    <div class="ff-card p-6">
      <h2 class="text-base font-bold text-gray-900 mb-1">Export Data</h2>
      <p class="text-sm text-gray-500 mb-5">Download your data in CSV format for use in Excel, Google Sheets, or other tools.</p>

      <?php if (!$currentProject): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-700">Please select a project to export data.</div>
      <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php
          $exports = [
            ['feedback',   'Feedback',   'All feedback submitted',     'fas fa-comments','indigo'],
            ['contacts',   'Contacts',   'Unique customers with emails','fas fa-address-book','blue'],
            ['analytics',  'Analytics',  'Daily stats & sentiment',    'fas fa-chart-bar','purple'],
            ['audit_logs', 'Audit Logs', 'All system audit entries',   'fas fa-list-alt','green'],
          ];
          $counts = [
            'feedback'   => DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ?", [$projectId]),
            'contacts'   => DB::count("SELECT COUNT(DISTINCT submitter_email) FROM ff_feedback WHERE project_id = ? AND submitter_email IS NOT NULL", [$projectId]),
            'analytics'  => DB::count("SELECT COUNT(DISTINCT DATE(created_at)) FROM ff_feedback WHERE project_id = ?", [$projectId]),
            'audit_logs' => DB::count("SELECT COUNT(*) FROM ff_audit_logs"),
          ];
        ?>
        <?php foreach ($exports as [$key, $label, $desc, $icon, $color]): ?>
        <div class="border border-gray-200 rounded-2xl p-5 hover:border-<?= $color ?>-300 hover:shadow-sm transition">
          <div class="w-10 h-10 rounded-xl bg-<?= $color ?>-100 flex items-center justify-center mb-3">
            <i class="<?= $icon ?> text-<?= $color ?>-600"></i>
          </div>
          <h3 class="font-semibold text-gray-900 mb-0.5"><?= $label ?></h3>
          <p class="text-xs text-gray-400 mb-2"><?= $desc ?></p>
          <p class="text-lg font-bold text-gray-800 mb-3"><?= number_format($counts[$key]) ?> <span class="text-xs font-normal text-gray-400">records</span></p>
          <a href="?download=<?= $key ?>&project_id=<?= $projectId ?>"
             class="inline-flex items-center gap-2 w-full justify-center border border-gray-200 hover:bg-<?= $color ?>-50 hover:border-<?= $color ?>-200 text-gray-700 py-2 rounded-xl text-sm font-medium transition">
            <i class="fas fa-download text-xs"></i> Download CSV
          </a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Import Section -->
    <div class="ff-card p-6">
      <h2 class="text-base font-bold text-gray-900 mb-1">Import Contacts</h2>
      <p class="text-sm text-gray-500 mb-5">Upload a CSV file to import email addresses for campaign recipients or review requests.</p>

      <form method="post" enctype="multipart/form-data" class="space-y-4 max-w-lg">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="import_csv">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">CSV File *</label>
          <div class="border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center hover:border-indigo-300 transition cursor-pointer" onclick="document.getElementById('csvFile').click()">
            <i class="fas fa-file-csv text-3xl text-gray-300 mb-2"></i>
            <p class="text-sm text-gray-500">Click to upload or drag & drop</p>
            <p class="text-xs text-gray-400 mt-1">CSV with columns: name, email, phone (header row required)</p>
            <input type="file" name="csv_file" id="csvFile" accept=".csv" class="hidden" onchange="document.getElementById('fileName').textContent = this.files[0]?.name || 'No file chosen'">
          </div>
          <p id="fileName" class="text-xs text-gray-400 mt-2 text-center">No file chosen</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Import to Campaign (optional)</label>
          <select name="campaign_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">— Save to contacts only —</option>
            <?php $campaigns = $projectId ? DB::fetchAll("SELECT id, name FROM ff_campaigns WHERE project_id = ? AND status = 'draft'", [$projectId]) : []; ?>
            <?php foreach ($campaigns as $camp): ?>
            <option value="<?= $camp['id'] ?>"><?= h($camp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition">
          <i class="fas fa-upload mr-2"></i> Import
        </button>
      </form>
    </div>

    <!-- GDPR / Data Deletion -->
    <div class="ff-card p-6">
      <h2 class="text-base font-bold text-gray-900 mb-1">GDPR – Right to Erasure</h2>
      <p class="text-sm text-gray-500 mb-4">Delete all data associated with a specific email address to comply with GDPR erasure requests.</p>
      <form method="post" class="flex gap-3 max-w-lg" onsubmit="return confirm('This will permanently delete all data for this email. This cannot be undone. Continue?')">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="gdpr_erase">
        <input type="email" name="erase_email" required placeholder="customer@example.com"
               class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <button class="bg-red-600 hover:bg-red-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">
          <i class="fas fa-user-slash mr-2"></i> Erase Data
        </button>
      </form>
    </div>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
