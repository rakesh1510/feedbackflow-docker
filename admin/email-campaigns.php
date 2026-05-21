<?php
require_once dirname(__DIR__) . '/includes/db-manager.php';
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

$campaignId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$campaign   = $campaignId ? DB::fetch("SELECT * FROM ff_email_campaigns WHERE id = ? AND project_id = ?", [$campaignId, $projectId]) : null;
$recipients = $campaign ? DB::fetchAll("SELECT * FROM ff_campaign_recipients WHERE campaign_id = ? ORDER BY id", [$campaign['id']]) : [];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['_action'] ?? '';

    // Save/create campaign
    if ($action === 'save_campaign') {
        $data = [
            'project_id'      => $projectId,
            'name'            => sanitize($_POST['name'] ?? ''),
            'subject'         => sanitize($_POST['subject'] ?? ''),
            'intro_text'      => sanitize($_POST['intro_text'] ?? ''),
            'rating_question' => sanitize($_POST['rating_question'] ?? 'How was your experience?'),
            'show_category'   => isset($_POST['show_category']) ? 1 : 0,
            'show_message'    => isset($_POST['show_message']) ? 1 : 0,
        ];
        if ($campaign) {
            DB::update('ff_email_campaigns', $data, 'id = ?', [$campaign['id']]);
            $campaignId = $campaign['id'];
        } else {
            $campaignId = DB::insert('ff_email_campaigns', $data);
        }
        flash('success', 'Campaign saved!');
        redirect(APP_URL . '/admin/email-campaigns.php?id=' . $campaignId);
    }

    // Add recipients
    if ($action === 'add_recipients' && $campaign) {
        $lines = explode("\n", $_POST['recipients'] ?? '');
        $added = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) continue;
            // Accept "Name,email" or just "email"
            $parts = array_map('trim', explode(',', $line));
            $email = filter_var(end($parts), FILTER_VALIDATE_EMAIL);
            $name  = count($parts) > 1 ? $parts[0] : null;
            if (!$email) continue;
            // Check not already added
            $exists = DB::fetch("SELECT id FROM ff_campaign_recipients WHERE campaign_id = ? AND email = ?", [$campaign['id'], $email]);
            if (!$exists) {
    $recipientToken = randomKey(48);

    $recipientId = DB::insert('ff_campaign_recipients', [
        'campaign_id' => $campaign['id'],
        'email'       => $email,
        'name'        => $name,
        'token'       => $recipientToken,
    ]);

    DBManager::registerPublicToken(
        (int)$currentUser['company_id'],
        $recipientToken,
        'campaign_recipient',
        (int)$campaign['project_id'],
        (int)$recipientId
    );

    $added++;
}
        }
        DB::update('ff_email_campaigns', ['sent_count' => DB::count("SELECT COUNT(*) FROM ff_campaign_recipients WHERE campaign_id = ?", [$campaign['id']])], 'id = ?', [$campaign['id']]);
        flash('success', $added . ' recipient(s) added.');
        redirect(APP_URL . '/admin/email-campaigns.php?id=' . $campaign['id'] . '&tab=recipients');
    }

    // Remove recipient
    if ($action === 'remove_recipient' && $campaign) {
        DB::delete('ff_campaign_recipients', 'id = ? AND campaign_id = ?', [(int)$_POST['recipient_id'], $campaign['id']]);
        flash('success', 'Recipient removed.');
        redirect(APP_URL . '/admin/email-campaigns.php?id=' . $campaign['id'] . '&tab=recipients');
    }

    // Send campaign
    if ($action === 'send_campaign' && $campaign) {
        if ($campaign['status'] === 'sent') {
            flash('error', 'This campaign was already sent.');
            redirect(APP_URL . '/admin/email-campaigns.php?id=' . $campaign['id']);
        }

        $recipientsToSend = DB::fetchAll("SELECT * FROM ff_campaign_recipients WHERE campaign_id = ? AND submitted_at IS NULL", [$campaign['id']]);
        if (empty($recipientsToSend)) {
            flash('error', 'No recipients to send to.');
            redirect(APP_URL . '/admin/email-campaigns.php?id=' . $campaign['id']);
        }

        DB::update('ff_email_campaigns', ['status' => 'sending'], 'id = ?', [$campaign['id']]);

        $sent = 0; $failed = 0; $lastError = '';
        foreach ($recipientsToSend as $r) {
            $link  = APP_URL . '/public/feedback-link.php?token=' . $r['token'];
            $html  = buildEmailHtml($campaign, $r, $link, $currentProject);
            $err   = '';
            if (sendMail($r['email'], $r['name'] ?? '', $campaign['subject'], $html, $err)) {
                $sent++;
            } else {
                $failed++;
                $lastError = $err;
            }
        }

        DB::update('ff_email_campaigns', [
            'status'     => 'sent',
            'sent_count' => $sent,
            'sent_at'    => date('Y-m-d H:i:s'),
        ], 'id = ?', [$campaign['id']]);

        if ($sent > 0 && $failed === 0) {
            flash('success', "Campaign sent successfully to {$sent} recipient(s)!");
        } elseif ($sent > 0) {
            flash('success', "{$sent} email(s) sent. {$failed} failed. Last error: {$lastError}");
        } else {
            flash('error', "All emails failed to send. Error: {$lastError} — Please check your SMTP settings in config.php.");
        }
        redirect(APP_URL . '/admin/email-campaigns.php?id=' . $campaign['id']);
    }
}

// Build HTML email
function buildEmailHtml($campaign, $recipient, $link, $project) {
    $color   = $project['widget_color'] ?? '#6366f1';
    $name    = $recipient['name'] ? ('Hi ' . htmlspecialchars($recipient['name']) . ',') : 'Hi there,';
    $intro   = htmlspecialchars($campaign['intro_text'] ?: 'We\'d love to hear about your experience. It takes less than a minute and helps us improve.');
    $rq      = htmlspecialchars($campaign['rating_question']);
    $pname   = htmlspecialchars($project['name']);

    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $rLink = $link . '&r=' . $i;
        $stars .= '<a href="' . $rLink . '" style="text-decoration:none;font-size:32px;margin:0 4px;color:#f59e0b">★</a>';
    }

    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Inter,-apple-system,BlinkMacSystemFont,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:40px 20px">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 4px 30px rgba(0,0,0,.08)">

  <!-- Header -->
  <tr><td style="background:linear-gradient(135deg,' . $color . ',' . $color . '99);padding:32px;text-align:center">
    <div style="width:56px;height:56px;background:rgba(255,255,255,.2);border-radius:18px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px">
      <span style="color:#fff;font-size:24px;font-weight:800">' . strtoupper(substr($project['name'],0,1)) . '</span>
    </div>
    <h1 style="color:#fff;font-size:22px;font-weight:700;margin:0">' . $pname . '</h1>
  </td></tr>

  <!-- Body -->
  <tr><td style="padding:36px 40px">
    <p style="font-size:16px;color:#374151;margin:0 0 8px">' . $name . '</p>
    <p style="font-size:15px;color:#6b7280;line-height:1.6;margin:0 0 28px">' . $intro . '</p>

    <!-- Rating -->
    <div style="background:#fafafa;border:1px solid #f3f4f6;border-radius:16px;padding:24px;text-align:center;margin-bottom:24px">
      <p style="font-size:15px;font-weight:600;color:#111827;margin:0 0 16px">' . $rq . '</p>
      <div>' . $stars . '</div>
      <p style="font-size:12px;color:#9ca3af;margin:12px 0 0">Click a star to rate and leave a comment</p>
    </div>

    <!-- CTA -->
    <div style="text-align:center;margin-bottom:28px">
      <a href="' . $link . '" style="display:inline-block;background:linear-gradient(135deg,' . $color . ',' . $color . 'cc);color:#fff;font-size:15px;font-weight:700;padding:14px 32px;border-radius:50px;text-decoration:none">
        Share Your Feedback →
      </a>
    </div>

    <p style="font-size:13px;color:#9ca3af;text-align:center;margin:0">
      This email was sent by ' . $pname . '. <a href="' . $link . '&unsubscribe=1" style="color:#9ca3af">Unsubscribe</a>
    </p>
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#f8fafc;padding:20px;text-align:center;border-top:1px solid #f3f4f6">
    <p style="font-size:11px;color:#d1d5db;margin:0">Powered by FeedbackFlow</p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>';
}

// Thin wrapper — actual sending is in includes/functions.php::ffSendMail()
function sendMail($toEmail, $toName, $subject, $htmlBody, &$error = '') {
    return ffSendMail($toEmail, $toName ?: '', $subject, $htmlBody, $error);
}

$tab = $_GET['tab'] ?? 'compose';
$pageTitle = 'Email Campaigns – ' . APP_NAME;
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto min-h-screen bg-gray-50">

  <!-- Header -->
  <header class="sticky top-0 z-40 bg-white/80 backdrop-blur border-b border-gray-100 px-8 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <a href="<?= APP_URL ?>/admin/channels.php?tab=email" class="text-gray-400 hover:text-gray-600 transition">
        <i class="fas fa-arrow-left"></i>
      </a>
      <div>
        <h1 class="text-xl font-bold text-gray-900"><?= $campaign ? h($campaign['name']) : 'New Email Campaign' ?></h1>
        <p class="text-xs text-gray-400 mt-0.5">Email feedback collection</p>
      </div>
    </div>
    <?php if ($campaign && $campaign['status'] !== 'sent'): ?>
    <form method="POST" onsubmit="return confirm('Send this campaign to all recipients now?')">
      <input type="hidden" name="_csrf" value="<?= csrf() ?>">
      <input type="hidden" name="_action" value="send_campaign">
      <button type="submit" class="inline-flex items-center gap-2 text-white text-sm font-semibold px-5 py-2.5 rounded-xl hover:opacity-90 transition shadow-sm"
              style="background:linear-gradient(135deg,#ec4899,#f43f5e)">
        <i class="fas fa-paper-plane"></i>
        Send to <?= DB::count("SELECT COUNT(*) FROM ff_campaign_recipients WHERE campaign_id = ?", [$campaign['id']]) ?> recipients
      </button>
    </form>
    <?php elseif ($campaign && $campaign['status'] === 'sent'): ?>
      <span class="inline-flex items-center gap-2 text-sm font-semibold text-green-600 bg-green-50 px-4 py-2 rounded-xl border border-green-200">
        <i class="fas fa-check-circle"></i> Sent on <?= date('M j, Y', strtotime($campaign['sent_at'])) ?>
      </span>
    <?php endif; ?>
  </header>

  <?php foreach (getFlash() as $f): ?>
    <div class="mx-8 mt-5 flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $f['type']==='success'?'bg-green-50 text-green-700 border border-green-200':'bg-red-50 text-red-700 border border-red-200' ?>">
      <i class="fas <?= $f['type']==='success'?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= h($f['msg']) ?>
    </div>
  <?php endforeach; ?>

  <?php if ($campaign): ?>
  <!-- Tabs for existing campaign -->
  <div class="px-8 pt-6">
    <div class="flex gap-1 bg-gray-100 p-1 rounded-xl w-fit">
      <?php foreach (['compose'=>'Compose','recipients'=>'Recipients','preview'=>'Preview','results'=>'Results'] as $k=>$v): ?>
        <a href="?id=<?= $campaign['id'] ?>&tab=<?= $k ?>"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-all <?= $tab===$k?'bg-white text-gray-900 shadow-sm':'text-gray-500 hover:text-gray-700' ?>">
          <?= $v ?>
          <?php if ($k==='recipients'): ?>
            <span class="ml-1 bg-indigo-100 text-indigo-600 text-xs font-bold px-1.5 py-0.5 rounded-full">
              <?= count($recipients) ?>
            </span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="p-8 max-w-4xl">

  <?php if ($tab === 'compose'): ?>
  <!-- ── COMPOSE ─────────────────────────────────────────── -->
  <form method="POST" class="space-y-6">
    <input type="hidden" name="_csrf" value="<?= csrf() ?>">
    <input type="hidden" name="_action" value="save_campaign">

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
      <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900 flex items-center gap-2">
          <i class="fas fa-edit text-indigo-500 text-sm"></i> Campaign Details
        </h3>
      </div>
      <div class="p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Campaign Name <span class="text-red-400">*</span></label>
            <input type="text" name="name" value="<?= h($campaign['name'] ?? '') ?>" required placeholder="e.g. Post-purchase Survey Q1"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 transition">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Email Subject <span class="text-red-400">*</span></label>
            <input type="text" name="subject" value="<?= h($campaign['subject'] ?? 'We\'d love your feedback') ?>" required
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 transition">
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Rating Question</label>
          <input type="text" name="rating_question" value="<?= h($campaign['rating_question'] ?? 'How was your experience?') ?>"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 transition">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Intro Message</label>
          <textarea name="intro_text" rows="3" placeholder="We'd love to hear about your experience. It takes less than a minute and helps us improve."
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 transition resize-none"><?= h($campaign['intro_text'] ?? '') ?></textarea>
        </div>
        <div class="bg-gray-50 rounded-xl p-4 flex gap-6">
          <label class="flex items-center gap-2 cursor-pointer text-sm">
            <input type="checkbox" name="show_category" <?= ($campaign['show_category'] ?? 1) ? 'checked' : '' ?> class="rounded text-indigo-600">
            <span class="font-medium text-gray-700">Show category selector</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer text-sm">
            <input type="checkbox" name="show_message" <?= ($campaign['show_message'] ?? 1) ? 'checked' : '' ?> class="rounded text-indigo-600">
            <span class="font-medium text-gray-700">Show message field</span>
          </label>
        </div>
      </div>
    </div>

    <button type="submit" class="px-8 py-2.5 rounded-xl text-white text-sm font-semibold hover:opacity-90 transition"
            style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
      Save Campaign
    </button>
  </form>

  <?php elseif ($tab === 'recipients'): ?>
  <!-- ── RECIPIENTS ─────────────────────────────────────── -->
  <div class="space-y-6">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
      <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900 flex items-center gap-2">
          <i class="fas fa-users text-indigo-500 text-sm"></i> Add Recipients
        </h3>
      </div>
      <form method="POST" class="p-6 space-y-4">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="_action" value="add_recipients">
        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
            Email List — one per line, format: <code class="bg-gray-100 px-1 rounded text-xs normal-case">Name, email@example.com</code> or just email
          </label>
          <textarea name="recipients" rows="6" placeholder="John Doe, john@example.com&#10;jane@example.com&#10;Rakesh Prajapati, rakesh@company.de"
                    class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-400 transition resize-none font-mono"></textarea>
        </div>
        <button type="submit" class="px-6 py-2.5 rounded-xl text-white text-sm font-semibold hover:opacity-90 transition"
                style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
          <i class="fas fa-plus mr-2"></i>Add Recipients
        </button>
      </form>
    </div>

    <?php if (!empty($recipients)): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900"><?= count($recipients) ?> Recipients</h3>
        <div class="flex gap-4 text-xs text-gray-500">
          <span><strong class="text-green-600"><?= count(array_filter($recipients, fn($r)=>$r['submitted_at'])) ?></strong> responded</span>
          <span><strong class="text-blue-600"><?= count(array_filter($recipients, fn($r)=>$r['opened_at']&&!$r['submitted_at'])) ?></strong> opened</span>
          <span><strong class="text-gray-600"><?= count(array_filter($recipients, fn($r)=>!$r['opened_at'])) ?></strong> pending</span>
        </div>
      </div>
      <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
        <?php foreach ($recipients as $r): ?>
        <div class="flex items-center gap-4 px-6 py-3">
          <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
               style="background:<?= $r['submitted_at'] ? '#10b981' : ($r['opened_at'] ? '#6366f1' : '#94a3b8') ?>">
            <?= strtoupper(substr($r['name'] ?: $r['email'], 0, 1)) ?>
          </div>
          <div class="flex-1 min-w-0">
            <?php if ($r['name']): ?><p class="text-sm font-medium text-gray-800"><?= h($r['name']) ?></p><?php endif; ?>
            <p class="text-xs text-gray-400"><?= h($r['email']) ?></p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <?php if ($r['submitted_at']): ?>
              <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full">✓ Responded</span>
              <?php if ($r['pre_rating']): ?>
                <span class="text-xs text-yellow-500"><?= str_repeat('★',$r['pre_rating']) ?></span>
              <?php endif; ?>
            <?php elseif ($r['opened_at']): ?>
              <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">👁 Opened</span>
            <?php else: ?>
              <span class="text-xs font-semibold text-gray-500 bg-gray-100 px-2 py-1 rounded-full">Pending</span>
            <?php endif; ?>
            <?php if ($campaign['status'] !== 'sent'): ?>
            <form method="POST" class="inline">
              <input type="hidden" name="_csrf" value="<?= csrf() ?>">
              <input type="hidden" name="_action" value="remove_recipient">
              <input type="hidden" name="recipient_id" value="<?= $r['id'] ?>">
              <button type="submit" class="text-gray-300 hover:text-red-400 transition ml-1" title="Remove">
                <i class="fas fa-times text-xs"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php elseif ($tab === 'preview'): ?>
  <!-- ── EMAIL PREVIEW ──────────────────────────────────── -->
  <?php
    $previewRecipient = ['name' => 'John Doe', 'email' => 'john@example.com', 'token' => 'preview'];
    $previewLink = APP_URL . '/public/feedback-link.php?slug=' . $currentProject['slug'] . '&source=email';
    $previewHtml = buildEmailHtml($campaign, $previewRecipient, $previewLink, $currentProject);
  ?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <div>
        <h3 class="font-semibold text-gray-900">Email Preview</h3>
        <p class="text-xs text-gray-400 mt-0.5">Subject: <?= h($campaign['subject']) ?></p>
      </div>
      <span class="text-xs text-gray-400 bg-gray-100 px-3 py-1.5 rounded-lg">As seen by recipient</span>
    </div>
    <div class="p-4 bg-gray-100">
      <iframe srcdoc="<?= htmlspecialchars($previewHtml, ENT_QUOTES) ?>"
              class="w-full rounded-xl border border-gray-200 bg-white"
              style="height:600px"
              sandbox="allow-same-origin"></iframe>
    </div>
  </div>

  <?php elseif ($tab === 'results'): ?>
  <!-- ── RESULTS ─────────────────────────────────────────── -->
  <?php
    $totalR = count($recipients);
    $opens  = count(array_filter($recipients, fn($r)=>$r['opened_at']));
    $subs   = count(array_filter($recipients, fn($r)=>$r['submitted_at']));
    $openRate = $totalR > 0 ? round($opens/$totalR*100) : 0;
    $subRate  = $totalR > 0 ? round($subs/$totalR*100) : 0;
    $ratings  = array_filter(array_column($recipients, 'pre_rating'));
    $avgRating = count($ratings) > 0 ? round(array_sum($ratings)/count($ratings),1) : 0;
  ?>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php foreach ([
      ['Sent', $totalR, '#6366f1', 'fas fa-paper-plane'],
      ['Opened', $opens . ' (' . $openRate . '%)', '#3b82f6', 'fas fa-envelope-open'],
      ['Responded', $subs . ' (' . $subRate . '%)', '#10b981', 'fas fa-check-circle'],
      ['Avg Rating', $avgRating . ' / 5 ★', '#f59e0b', 'fas fa-star'],
    ] as [$label,$val,$color,$icon]): ?>
    <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
      <div class="flex items-center gap-3 mb-2">
        <i class="<?= $icon ?> text-sm" style="color:<?= $color ?>"></i>
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider"><?= $label ?></p>
      </div>
      <p class="text-2xl font-bold text-gray-900"><?= $val ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
    <div class="px-6 py-4 border-b border-gray-100">
      <h3 class="font-semibold text-gray-900">Feedback Received</h3>
    </div>
    <?php
    $fbIds = array_filter(array_column($recipients, 'feedback_id'));
    $feedbackItems = !empty($fbIds) ? DB::fetchAll("SELECT f.*, c.name as cat_name FROM ff_feedback f LEFT JOIN ff_categories c ON c.id = f.category_id WHERE f.id IN (" . implode(',', array_map('intval',$fbIds)) . ") ORDER BY f.created_at DESC") : [];
    ?>
    <?php if (empty($feedbackItems)): ?>
      <div class="py-12 text-center text-gray-400 text-sm">No feedback submitted yet.</div>
    <?php else: ?>
    <div class="divide-y divide-gray-50">
      <?php foreach ($feedbackItems as $fb): ?>
      <div class="flex items-start gap-4 px-6 py-4">
        <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-xs font-bold flex-shrink-0">
          <?= strtoupper(substr($fb['submitter_name'] ?: '?', 0, 1)) ?>
        </div>
        <div class="flex-1">
          <div class="flex items-center gap-2 mb-1">
            <p class="text-sm font-semibold text-gray-800"><?= h($fb['title']) ?></p>
            <?php if ($fb['rating']): ?>
              <span class="text-yellow-400 text-xs"><?= str_repeat('★',$fb['rating']) ?></span>
            <?php endif; ?>
          </div>
          <p class="text-sm text-gray-500"><?= h($fb['description']) ?></p>
        </div>
        <a href="<?= APP_URL ?>/admin/feedback.php?id=<?= $fb['id'] ?>"
           class="text-xs text-indigo-600 hover:underline flex-shrink-0">View →</a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php endif; ?>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
