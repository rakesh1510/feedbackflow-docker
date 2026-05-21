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
    $pAction = $_POST['_action'] ?? '';
    if ($pAction === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $currentUser['password'])) { flash('error', 'Current password is incorrect.'); }
        elseif (strlen($new) < 8) { flash('error', 'New password must be at least 8 characters.'); }
        elseif ($new !== $confirm) { flash('error', 'New passwords do not match.'); }
        else {
            DB::update('ff_users', ['password' => password_hash($new, PASSWORD_BCRYPT)], 'id = ?', [$currentUser['id']]);
            flash('success', 'Password changed successfully!');
        }
    } elseif ($pAction === 'update_profile') {
        DB::update('ff_users', ['name' => sanitize($_POST['name']??'')], 'id = ?', [$currentUser['id']]);
        flash('success', 'Profile updated!');
    } elseif ($pAction === 'test_smtp') {
        $testTo = sanitize($_POST['test_email'] ?? $currentUser['email']);
        $err = '';
        $ok  = ffSendMail($testTo, 'Test', 'FeedbackFlow SMTP Test', '
            <div style="font-family:sans-serif;padding:24px;max-width:480px">
                <h2 style="color:#6366f1">✓ SMTP is working!</h2>
                <p>Your FeedbackFlow email settings are configured correctly.</p>
                <p style="color:#6b7280;font-size:13px">Sent from: <strong>' . SMTP_FROM . '</strong> via <strong>' . SMTP_HOST . ':' . SMTP_PORT . '</strong></p>
            </div>
        ', $err);
        if ($ok)  flash('success', "Test email sent to {$testTo}. Check your inbox!");
        else      flash('error',   "SMTP test failed: {$err}");
        redirect(APP_URL . '/admin/settings.php?tab=smtp');
    } elseif ($pAction === 'gdpr_export') {
        // Export user data
        $data = ['user' => $currentUser, 'feedback' => DB::fetchAll("SELECT * FROM ff_feedback WHERE submitter_id = ?", [$currentUser['id']]), 'comments' => DB::fetchAll("SELECT * FROM ff_comments WHERE user_id = ?", [$currentUser['id']])];
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="feedbackflow-data-export.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    redirect(APP_URL . '/admin/settings.php');
}

$pageTitle = 'Settings – ' . APP_NAME;
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4">
    <h1 class="text-xl font-bold text-gray-900">Settings</h1>
  </header>

  <?php foreach (getFlash() as $f): ?>
    <div data-flash class="mx-6 mt-4 px-4 py-3 rounded-xl text-sm <?= $f['type']==='success'?'bg-green-50 text-green-700 border border-green-200':'bg-red-50 text-red-700 border border-red-200' ?>"><?= h($f['msg']) ?></div>
  <?php endforeach; ?>

  <div class="p-6 space-y-6 max-w-2xl">
    <!-- Profile -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="font-bold text-gray-900 mb-5">Profile</h2>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="_action" value="update_profile">
        <div class="flex items-center gap-4 mb-4">
          <div class="w-16 h-16 bg-indigo-600 rounded-full flex items-center justify-center text-white text-2xl font-bold">
            <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
          </div>
          <div>
            <p class="font-semibold text-gray-900"><?= h($currentUser['name']) ?></p>
            <p class="text-sm text-gray-500"><?= h($currentUser['email']) ?></p>
            <p class="text-xs text-gray-400 capitalize"><?= $currentUser['role'] ?></p>
          </div>
        </div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
          <input type="text" name="name" value="<?= h($currentUser['name']) ?>" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input type="email" value="<?= h($currentUser['email']) ?>" disabled class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-2.5 text-sm text-gray-400 cursor-not-allowed"></div>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition">Update Profile</button>
      </form>
    </div>

    <!-- Change Password -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="font-bold text-gray-900 mb-5">Change Password</h2>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="_action" value="change_password">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
          <input type="password" name="current_password" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
          <input type="password" name="new_password" required minlength="8" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
          <input type="password" name="confirm_password" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
        <button type="submit" class="bg-gray-900 hover:bg-gray-700 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition">Change Password</button>
      </form>
    </div>

    <!-- GDPR -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="font-bold text-gray-900 mb-2">GDPR & Privacy</h2>
      <p class="text-sm text-gray-500 mb-4">Your data rights under GDPR. You can export or delete your personal data at any time.</p>
      <div class="flex gap-3">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="_action" value="gdpr_export">
          <button type="submit" class="inline-flex items-center gap-2 bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium px-4 py-2.5 rounded-xl text-sm border border-blue-200 transition">
            <i class="fas fa-download"></i> Export My Data
          </button>
        </form>
      </div>
    </div>

    <!-- SMTP Settings -->
    <?php
    $smtpOk = defined('SMTP_HOST') && SMTP_HOST !== '' && defined('SMTP_USER') && SMTP_USER !== '';
    ?>
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="font-bold text-gray-900">Email / SMTP Settings</h2>
          <p class="text-sm text-gray-500 mt-0.5">These are set inside <code class="bg-gray-100 px-1 rounded text-xs">config.php</code> on your server.</p>
        </div>
        <?php if ($smtpOk): ?>
          <span class="inline-flex items-center gap-1.5 text-xs font-semibold bg-green-50 text-green-700 border border-green-200 px-3 py-1 rounded-full"><i class="fas fa-check-circle"></i> SMTP Configured</span>
        <?php else: ?>
          <span class="inline-flex items-center gap-1.5 text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1 rounded-full"><i class="fas fa-exclamation-triangle"></i> Not Configured</span>
        <?php endif; ?>
      </div>

      <?php if (!$smtpOk): ?>
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 text-sm text-amber-800">
        <p class="font-semibold mb-2">&#9888; Emails are not being sent — SMTP is not set up yet.</p>
        <p class="mb-2">Open <code class="bg-amber-100 px-1 rounded">config.php</code> on your server and fill in these values:</p>
        <pre class="bg-amber-100 rounded-lg p-3 text-xs overflow-x-auto mt-2">define('SMTP_HOST', 'smtp.gmail.com');    // Your SMTP server
define('SMTP_PORT', 587);                 // 587 = TLS (recommended), 465 = SSL
define('SMTP_USER', 'you@gmail.com');     // Your email login
define('SMTP_PASS', 'your-app-password'); // App password (not your normal password)
define('SMTP_FROM', 'you@gmail.com');     // Sender address shown in emails
define('SMTP_FROM_NAME', 'FeedbackFlow'); // Sender name shown in emails
define('SMTP_SECURE', 'tls');             // 'tls' or 'ssl'</pre>
        <p class="mt-3 font-semibold">Quick provider settings:</p>
        <table class="mt-2 w-full text-xs border-collapse">
          <tr class="border-b border-amber-200"><th class="text-left py-1 pr-3">Provider</th><th class="text-left py-1 pr-3">SMTP_HOST</th><th class="text-left py-1">PORT</th></tr>
          <tr class="border-b border-amber-100"><td class="py-1 pr-3 font-medium">Gmail</td><td class="py-1 pr-3 font-mono">smtp.gmail.com</td><td class="py-1">587</td></tr>
          <tr class="border-b border-amber-100"><td class="py-1 pr-3 font-medium">Outlook / Office 365</td><td class="py-1 pr-3 font-mono">smtp-mail.outlook.com</td><td class="py-1">587</td></tr>
          <tr class="border-b border-amber-100"><td class="py-1 pr-3 font-medium">Yahoo</td><td class="py-1 pr-3 font-mono">smtp.mail.yahoo.com</td><td class="py-1">587</td></tr>
          <tr class="border-b border-amber-100"><td class="py-1 pr-3 font-medium">SendGrid</td><td class="py-1 pr-3 font-mono">smtp.sendgrid.net</td><td class="py-1">587</td></tr>
          <tr><td class="py-1 pr-3 font-medium">Mailgun</td><td class="py-1 pr-3 font-mono">smtp.mailgun.org</td><td class="py-1">587</td></tr>
        </table>
        <p class="mt-3 text-xs"><strong>Gmail note:</strong> You must use an <strong>App Password</strong>, not your normal Gmail password. Enable 2FA on your Google account, then go to <em>Google Account → Security → App Passwords</em> to generate one.</p>
      </div>
      <?php else: ?>
      <div class="space-y-2 text-sm mb-5">
        <?php $smtpInfo = [['Host', SMTP_HOST . ':' . SMTP_PORT . ' (' . strtoupper(SMTP_SECURE) . ')'],['Username', SMTP_USER],['From address', SMTP_FROM . ' — ' . SMTP_FROM_NAME]]; ?>
        <?php foreach ($smtpInfo as [$l, $v]): ?>
          <div class="flex gap-4 py-1.5 border-b border-gray-50">
            <span class="text-gray-500 w-32 flex-shrink-0"><?= $l ?></span>
            <span class="text-gray-900 font-mono text-xs"><?= h($v) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Send Test Email -->
      <form method="POST" class="flex items-end gap-3">
        <input type="hidden" name="_csrf"   value="<?= csrf() ?>">
        <input type="hidden" name="_action" value="test_smtp">
        <div class="flex-1">
          <label class="block text-sm font-medium text-gray-700 mb-1">Send test email to</label>
          <input type="email" name="test_email" value="<?= h($currentUser['email']) ?>" required
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition whitespace-nowrap">
          <i class="fas fa-paper-plane mr-1"></i> Send Test Email
        </button>
      </form>
    </div>

    <!-- System Info -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="font-bold text-gray-900 mb-4">System Information</h2>
      <div class="space-y-2 text-sm">
        <?php $sysInfo = [['Version', APP_VERSION],['PHP Version', PHP_VERSION],['Database', DB_NAME . '@' . DB_HOST],['AI Features', AI_ENABLED ? '<span class="text-green-600 font-medium">Enabled ✓</span>' : '<span class="text-gray-400">Disabled (no API key)</span>'],['Upload Limit', ini_get('upload_max_filesize')],['Environment', DEBUG_MODE ? 'Development' : 'Production']];
        foreach ($sysInfo as [$label, $val]): ?>
          <div class="flex items-center gap-4 py-2 border-b border-gray-50">
            <span class="text-gray-500 w-40 flex-shrink-0"><?= $label ?></span>
            <span class="text-gray-900"><?= $val ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
