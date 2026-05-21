<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::require();
$userProjects = getUserProjects($currentUser['id']);

Auth::start();
if (isset($_GET['project_id'])) $_SESSION['current_project_id'] = (int)$_GET['project_id'];
$projectId = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;
if (!$currentProject && !empty($userProjects)) { $currentProject = $userProjects[0]; $projectId = $currentProject['id']; }

$action = $_GET['action'] ?? 'list';
$feedbackId = (int)($_GET['id'] ?? 0);

// ---- Handle POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { flash('error', 'Security check failed.'); redirect($_SERVER['REQUEST_URI']); }

    $postAction = $_POST['_action'] ?? '';
    // Load feedback record early so all action handlers (including comment) can use it
    if ($feedbackId && !isset($fb)) {
        $fb = DB::fetch("SELECT * FROM ff_feedback WHERE id = ? AND project_id = ?", [$feedbackId, $projectId]);
    }

    // Submit new feedback (admin side)
    if ($postAction === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $desc  = sanitize($_POST['description'] ?? '');
        $catId = (int)($_POST['category_id'] ?? 0) ?: null;
        $status = $_POST['status'] ?? 'new';
        $priority = $_POST['priority'] ?? 'medium';
        $assignee = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        $name = sanitize($_POST['submitter_name'] ?? '');
        $email = $_POST['submitter_email'] ?? '';
        if (empty($title)) { flash('error', 'Title is required.'); redirect($_SERVER['REQUEST_URI']); }
        $id = DB::insert('ff_feedback', compact('title', 'status', 'priority', 'is_public') + [
            'project_id' => $projectId, 'category_id' => $catId, 'description' => $desc,
            'submitter_name' => $name, 'submitter_email' => $email ?: null,
            'assigned_to' => $assignee, 'submitter_id' => $currentUser['id'],
        ]);
        // Handle tags
        if (!empty($_POST['tags'])) {
            foreach ((array)$_POST['tags'] as $tagId) {
                DB::query("INSERT IGNORE INTO ff_feedback_tags (feedback_id, tag_id) VALUES (?, ?)", [$id, (int)$tagId]);
            }
        }
        // Handle attachments
        if (!empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['name'] as $i => $name) {
                $file = ['name' => $name, 'tmp_name' => $_FILES['attachments']['tmp_name'][$i], 'error' => $_FILES['attachments']['error'][$i], 'size' => $_FILES['attachments']['size'][$i], 'type' => $_FILES['attachments']['type'][$i]];
                $uploaded = uploadFile($file);
                if ($uploaded) DB::insert('ff_attachments', array_merge($uploaded, ['feedback_id' => $id, 'uploaded_by' => $currentUser['id']]));
            }
        }
        logActivity($projectId, $currentUser['id'], $id, 'Created feedback: ' . $title);
        triggerWebhooks($projectId, 'feedback.created', ['id' => $id, 'title' => $title]);
        // AI Analysis
        if (AI_ENABLED) {
            $fb = DB::fetch("SELECT * FROM ff_feedback WHERE id = ?", [$id]);
            $ai = aiAnalyzeFeedback($fb);
            if ($ai) DB::update('ff_feedback', ['ai_sentiment' => $ai['sentiment'] ?? null, 'ai_sentiment_score' => $ai['sentiment_score'] ?? null, 'ai_summary' => $ai['summary'] ?? null, 'ai_priority_score' => $ai['priority_score'] ?? null], 'id = ?', [$id]);
        }
        flash('success', 'Feedback created successfully!');
        redirect(APP_URL . '/admin/feedback.php?id=' . $id);
    }

    // Update status/assignment
    if ($postAction === 'update' && $feedbackId) {
        $updates = [];
        if (isset($_POST['status'])) $updates['status'] = $_POST['status'];
        if (isset($_POST['priority'])) $updates['priority'] = $_POST['priority'];
        if (isset($_POST['assigned_to'])) $updates['assigned_to'] = (int)$_POST['assigned_to'] ?: null;
        if (isset($_POST['category_id'])) $updates['category_id'] = (int)$_POST['category_id'] ?: null;
        if (isset($_POST['is_public'])) $updates['is_public'] = (int)$_POST['is_public'];
        if ($updates) DB::update('ff_feedback', $updates, 'id = ? AND project_id = ?', [$feedbackId, $projectId]);
        logActivity($projectId, $currentUser['id'], $feedbackId, 'Updated feedback');
        flash('success', 'Updated!');
        redirect(APP_URL . '/admin/feedback.php?id=' . $feedbackId);
    }

    // Add comment / internal note
    if ($postAction === 'comment' && $feedbackId) {
        $content    = trim($_POST['content'] ?? '');
        $isInternal = isset($_POST['is_internal']) ? 1 : 0;
        if (!empty($content)) {
            DB::insert('ff_comments', ['feedback_id' => $feedbackId, 'user_id' => $currentUser['id'], 'content' => $content, 'is_internal' => $isInternal, 'is_admin_reply' => 1]);
            DB::query("UPDATE ff_feedback SET comment_count = comment_count + 1 WHERE id = ?", [$feedbackId]);
            logActivity($projectId, $currentUser['id'], $feedbackId, $isInternal ? 'Added internal note' : 'Added reply');

            // Email the submitter when it's a public (non-internal) reply and they have an email
            if (!$isInternal && !empty($fb['submitter_email'])) {
                $recipientName  = $fb['submitter_name'] ?: 'there';
                $firstName      = explode(' ', trim($recipientName))[0];
                $projectName    = $currentProject['name'] ?? APP_NAME;
                $replyerName    = $currentUser['name'] ?? $projectName . ' Team';
                $feedbackTitle  = $fb['title'] ?? 'your feedback';
                $boardUrl       = APP_URL . '/public/board.php?slug=' . ($currentProject['slug'] ?? '');

                $emailHtml = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f9fafb;font-family:sans-serif">
<div style="max-width:540px;margin:32px auto;background:#fff;border-radius:16px;border:1px solid #e5e7eb;overflow:hidden">
  <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:24px 28px">
    <h1 style="margin:0;color:#fff;font-size:18px;font-weight:700">' . htmlspecialchars($projectName) . '</h1>
    <p style="margin:6px 0 0;color:#e0e7ff;font-size:13px">Reply to your feedback</p>
  </div>
  <div style="padding:28px">
    <p style="color:#374151;font-size:15px;margin:0 0 6px">Hi ' . htmlspecialchars($firstName) . ',</p>
    <p style="color:#6b7280;font-size:14px;margin:0 0 20px">The team replied to your feedback: <strong>' . htmlspecialchars($feedbackTitle) . '</strong></p>
    <div style="background:#f3f4f6;border-left:4px solid #6366f1;border-radius:8px;padding:16px 18px;margin-bottom:24px">
      <p style="margin:0;color:#111827;font-size:14px;line-height:1.7">' . nl2br(htmlspecialchars($content)) . '</p>
      <p style="margin:10px 0 0;font-size:12px;color:#9ca3af">— ' . htmlspecialchars($replyerName) . '</p>
    </div>
    <a href="' . $boardUrl . '" style="display:inline-block;background:#6366f1;color:#fff;font-weight:600;font-size:14px;padding:10px 20px;border-radius:10px;text-decoration:none">View on Feedback Board</a>
  </div>
  <div style="padding:16px 28px;border-top:1px solid #f3f4f6;text-align:center">
    <p style="margin:0;font-size:11px;color:#d1d5db">You received this because you submitted feedback to ' . htmlspecialchars($projectName) . '.</p>
  </div>
</div>
</body></html>';

                $err = '';
                ffSendMail(
                    $fb['submitter_email'],
                    $fb['submitter_name'] ?? '',
                    $replyerName . ' replied to your feedback',
                    $emailHtml,
                    $err
                );
                // Note: we don't block the redirect on email failure — reply is saved regardless
            }
        }
        redirect(APP_URL . '/admin/feedback.php?id=' . $feedbackId . '#comments');
    }

    // Delete feedback
    if ($postAction === 'delete' && $feedbackId) {
        DB::delete('ff_feedback', 'id = ? AND project_id = ?', [$feedbackId, $projectId]);
        flash('success', 'Feedback deleted.');
        redirect(APP_URL . '/admin/feedback.php');
    }

    // Merge duplicate
    if ($postAction === 'merge' && $feedbackId) {
        $mergeIntoId = (int)($_POST['merge_into'] ?? 0);
        if ($mergeIntoId) {
            DB::update('ff_feedback', ['status' => 'duplicate', 'duplicate_of' => $mergeIntoId], 'id = ?', [$feedbackId]);
            DB::query("UPDATE ff_feedback SET vote_count = vote_count + (SELECT vote_count FROM ff_feedback WHERE id = ?) WHERE id = ?", [$feedbackId, $mergeIntoId]);
            flash('success', 'Merged into #' . $mergeIntoId . '!');
        }
        redirect(APP_URL . '/admin/feedback.php?id=' . $feedbackId);
    }

    // AI Analyze
    if ($postAction === 'ai_analyze' && $feedbackId) {
        $fb = DB::fetch("SELECT * FROM ff_feedback WHERE id = ?", [$feedbackId]);
        $ai = aiAnalyzeFeedback($fb);
        if ($ai) {
            DB::update('ff_feedback', ['ai_sentiment' => $ai['sentiment'] ?? null, 'ai_sentiment_score' => $ai['sentiment_score'] ?? null, 'ai_summary' => $ai['summary'] ?? null, 'ai_priority_score' => $ai['priority_score'] ?? null], 'id = ?', [$feedbackId]);
            flash('success', 'AI analysis complete!');
        } else { flash('error', 'AI analysis failed. Check your OpenAI API key.'); }
        redirect(APP_URL . '/admin/feedback.php?id=' . $feedbackId);
    }
}

// ---- Views ----
$categories = $projectId ? DB::fetchAll("SELECT * FROM ff_categories WHERE project_id = ? ORDER BY sort_order", [$projectId]) : [];
$tags = $projectId ? DB::fetchAll("SELECT * FROM ff_tags WHERE project_id = ?", [$projectId]) : [];
$teamMembers = $projectId ? DB::fetchAll("SELECT u.id, u.name FROM ff_users u JOIN ff_project_members pm ON pm.user_id = u.id WHERE pm.project_id = ? UNION SELECT id, name FROM ff_users WHERE id = ?", [$projectId, $currentProject['owner_id'] ?? 0]) : [];

if ($action === 'view' || $feedbackId) {
    // Single feedback view
    $fb = $feedbackId ? DB::fetch("SELECT f.*, c.name as category_name, c.color as category_color, u.name as assignee_name FROM ff_feedback f LEFT JOIN ff_categories c ON c.id = f.category_id LEFT JOIN ff_users u ON u.id = f.assigned_to WHERE f.id = ? AND f.project_id = ?", [$feedbackId, $projectId]) : null;
    if (!$fb) { flash('error', 'Feedback not found.'); redirect(APP_URL . '/admin/feedback.php'); }
    $comments = DB::fetchAll("SELECT cm.*, u.name as user_name, u.role as user_role FROM ff_comments cm LEFT JOIN ff_users u ON u.id = cm.user_id WHERE cm.feedback_id = ? ORDER BY cm.created_at ASC", [$feedbackId]);
    $attachments = DB::fetchAll("SELECT * FROM ff_attachments WHERE feedback_id = ?", [$feedbackId]);
    $fbTags = DB::fetchAll("SELECT t.* FROM ff_tags t JOIN ff_feedback_tags ft ON ft.tag_id = t.id WHERE ft.feedback_id = ?", [$feedbackId]);
    DB::query("UPDATE ff_feedback SET view_count = view_count + 1 WHERE id = ?", [$feedbackId]);
    $pageTitle = h($fb['title']) . ' – ' . APP_NAME;
    include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center gap-4">
    <a href="<?= APP_URL ?>/admin/feedback.php" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
    <div class="flex-1">
      <h1 class="text-lg font-bold text-gray-900"><?= h($fb['title']) ?></h1>
      <p class="text-xs text-gray-400">Feedback #<?= $fb['id'] ?> &middot; <?= timeAgo($fb['created_at']) ?></p>
    </div>
    <div class="flex items-center gap-2">
      <?= statusBadge($fb['status']) ?>
      <?= priorityBadge($fb['priority']) ?>
    </div>
  </header>

  <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
      <!-- Feedback Card -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0 text-indigo-600 font-semibold">
            <?= strtoupper(substr($fb['submitter_name'] ?? 'A', 0, 1)) ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <span class="font-semibold text-gray-900"><?= h($fb['submitter_name'] ?? 'Anonymous') ?></span>
              <?php if ($fb['submitter_email']): ?><span class="text-xs text-gray-400">&lt;<?= h($fb['submitter_email']) ?>&gt;</span><?php endif; ?>
              <?php if ($fb['category_name']): ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background:<?= h($fb['category_color']) ?>20;color:<?= h($fb['category_color']) ?>"><?= h($fb['category_name']) ?></span>
              <?php endif; ?>
            </div>
            <p class="text-gray-700 leading-relaxed whitespace-pre-wrap"><?= h($fb['description'] ?? '') ?></p>
            <div class="flex items-center gap-4 mt-3 text-xs text-gray-400">
              <span><i class="fas fa-thumbs-up mr-1"></i><?= $fb['vote_count'] ?> votes</span>
              <span><i class="fas fa-eye mr-1"></i><?= $fb['view_count'] ?> views</span>
              <?php if ($fb['page_url']): ?><a href="<?= h($fb['page_url']) ?>" target="_blank" class="hover:text-indigo-600 truncate max-w-xs"><i class="fas fa-link mr-1"></i><?= h($fb['page_url']) ?></a><?php endif; ?>
            </div>
            <!-- Tags -->
            <?php if (!empty($fbTags)): ?>
              <div class="flex flex-wrap gap-1.5 mt-3">
                <?php foreach ($fbTags as $tag): ?>
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs" style="background:<?= h($tag['color']) ?>20;color:<?= h($tag['color']) ?>">#<?= h($tag['name']) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- AI Analysis -->
      <?php if ($fb['ai_summary'] || AI_ENABLED): ?>
      <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-2xl border border-indigo-100 p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-robot text-indigo-600"></i> AI Analysis</h3>
          <?php if (AI_ENABLED): ?>
          <form method="POST" class="inline">
            <input type="hidden" name="_csrf" value="<?= csrf() ?>">
            <input type="hidden" name="_action" value="ai_analyze">
            <button type="submit" class="text-xs text-indigo-600 hover:underline flex items-center gap-1"><i class="fas fa-sync-alt"></i> Re-analyze</button>
          </form>
          <?php endif; ?>
        </div>
        <?php if ($fb['ai_summary']): ?>
          <p class="text-sm text-gray-700 mb-3"><?= h($fb['ai_summary']) ?></p>
          <div class="flex items-center gap-3 text-xs">
            <?= sentimentBadge($fb['ai_sentiment']) ?>
            <?php if ($fb['ai_priority_score']): ?>
              <span class="bg-white border border-gray-200 px-2 py-0.5 rounded-full text-gray-600">Priority Score: <strong><?= $fb['ai_priority_score'] ?>/10</strong></span>
            <?php endif; ?>
          </div>
        <?php elseif (AI_ENABLED): ?>
          <p class="text-sm text-gray-500">No analysis yet.</p>
          <form method="POST" class="mt-3">
            <input type="hidden" name="_csrf" value="<?= csrf() ?>">
            <input type="hidden" name="_action" value="ai_analyze">
            <button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
              <i class="fas fa-magic"></i> Run AI Analysis
            </button>
          </form>
        <?php else: ?>
          <p class="text-sm text-gray-400">Enable AI by adding your OpenAI API key in config.php</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Attachments -->
      <?php if (!empty($attachments)): ?>
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-3">Attachments</h3>
        <div class="grid grid-cols-2 gap-3">
          <?php foreach ($attachments as $att): ?>
            <a href="<?= UPLOAD_URL ?>attachments/<?= h($att['filename']) ?>" target="_blank"
               class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-200 hover:bg-gray-100 transition text-sm">
              <i class="fas fa-file text-gray-400 text-lg"></i>
              <div class="flex-1 min-w-0">
                <p class="truncate font-medium text-gray-700"><?= h($att['original_name']) ?></p>
                <p class="text-xs text-gray-400"><?= number_format($att['file_size'] / 1024, 1) ?> KB</p>
              </div>
              <i class="fas fa-download text-gray-400"></i>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Comments & Notes -->
      <div class="bg-white rounded-2xl border border-gray-200" id="comments">
        <div class="px-6 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900">Discussion & Notes</h3>
        </div>
        <div class="divide-y divide-gray-50">
          <?php if (empty($comments)): ?>
            <p class="px-6 py-8 text-center text-gray-400 text-sm">No comments yet. Start the discussion!</p>
          <?php else: foreach ($comments as $cm): ?>
            <div class="px-6 py-4 <?= $cm['is_internal'] ? 'bg-amber-50' : '' ?>">
              <div class="flex items-start gap-3">
                <div class="w-8 h-8 <?= $cm['is_internal'] ? 'bg-amber-100' : 'bg-indigo-100' ?> rounded-full flex items-center justify-center flex-shrink-0 text-sm font-semibold <?= $cm['is_internal'] ? 'text-amber-700' : 'text-indigo-700' ?>">
                  <?= strtoupper(substr($cm['user_name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900"><?= h($cm['user_name'] ?? 'Anonymous') ?></span>
                    <?php if ($cm['is_internal']): ?><span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium"><i class="fas fa-lock mr-1"></i>Internal Note</span><?php endif; ?>
                    <?php if ($cm['is_admin_reply']): ?><span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-medium">Team</span><?php endif; ?>
                    <span class="text-xs text-gray-400 ml-auto"><?= timeAgo($cm['created_at']) ?></span>
                  </div>
                  <p class="text-sm text-gray-700 mt-1 leading-relaxed whitespace-pre-wrap"><?= h($cm['content']) ?></p>
                </div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
        <!-- Add Comment Form -->
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf() ?>">
            <input type="hidden" name="_action" value="comment">
            <textarea name="content" placeholder="Write a reply or internal note..." rows="3"
                      class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm resize-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none" required></textarea>
            <div class="flex items-center justify-between mt-3">
              <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" name="is_internal" class="rounded border-gray-300 text-amber-500 focus:ring-amber-400">
                <i class="fas fa-lock text-amber-500"></i> Internal note (not visible to submitter)
              </label>
              <button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                <i class="fas fa-paper-plane"></i> Send
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-4">
      <!-- Update Status -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">Update</h3>
        <form method="POST" class="space-y-3">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="_action" value="update">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select name="status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
              <?php foreach (['new','under_review','planned','in_progress','done','declined','duplicate'] as $s): ?>
                <option value="<?= $s ?>" <?= $fb['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Priority</label>
            <select name="priority" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
              <?php foreach (['critical','high','medium','low'] as $p): ?>
                <option value="<?= $p ?>" <?= $fb['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Category</label>
            <select name="category_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
              <option value="">— None —</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $fb['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
<!--            <label class="block text-xs font-medium text-gray-500 mb-1">Assigned To</label>
            <select name="assigned_to" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
              <option value="">— Unassigned —</option>
              <?php foreach ($teamMembers as $tm): ?>
                <option value="<?= $tm['id'] ?>" <?= $fb['assigned_to'] == $tm['id'] ? 'selected' : '' ?>><?= h($tm['name']) ?></option>
              <?php endforeach; ?>
            </select>-->
          </div>
<!--          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Visibility</label>
            <select name="is_public" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
              <option value="1" <?= $fb['is_public'] ? 'selected' : '' ?>>Public</option>
              <option value="0" <?= !$fb['is_public'] ? 'selected' : '' ?>>Private</option>
            </select>
          </div>-->
          <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl transition text-sm">Save Changes</button>
        </form>
      </div>

      <!-- Merge -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5" x-data="{ open: false }">
        <button @click="open = !open" class="w-full text-left text-sm font-semibold text-gray-900 flex items-center justify-between">
          <span><i class="fas fa-code-branch mr-2 text-gray-400"></i>Merge as Duplicate</span>
          <i class="fas fa-chevron-down text-gray-400 text-xs" :class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-3">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf() ?>">
            <input type="hidden" name="_action" value="merge">
            <input type="number" name="merge_into" placeholder="Feedback ID to merge into" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-2 outline-none focus:ring-2 focus:ring-indigo-500">
            <button type="submit" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 rounded-xl text-sm transition">Merge</button>
          </form>
        </div>
      </div>

      <!-- Delete -->
      <div class="bg-white rounded-2xl border border-red-100 p-5">
        <h3 class="text-sm font-semibold text-red-600 mb-3"><i class="fas fa-exclamation-triangle mr-1"></i>Danger Zone</h3>
        <form method="POST" onsubmit="return confirm('Delete this feedback permanently?')">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="_action" value="delete">
          <button type="submit" class="w-full bg-red-50 hover:bg-red-100 text-red-600 font-medium py-2 rounded-xl text-sm transition">Delete Feedback</button>
        </form>
      </div>
    </div>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; return; }

// ---- List View ----
$search = sanitize($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterCategory = (int)($_GET['category'] ?? 0);
$filterPriority = $_GET['priority'] ?? '';
$filterAssigned = $_GET['assigned'] ?? '';
$sortBy = in_array($_GET['sort'] ?? '', ['created_at','vote_count','comment_count']) ? $_GET['sort'] : 'created_at';
$sortDir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 25;

$where = ['f.project_id = ?'];
$params = [$projectId];
if ($search) { $where[] = '(f.title LIKE ? OR f.description LIKE ? OR f.submitter_email LIKE ?)'; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
if ($filterStatus) { $where[] = 'f.status = ?'; $params[] = $filterStatus; }
if ($filterCategory) { $where[] = 'f.category_id = ?'; $params[] = $filterCategory; }
if ($filterPriority) { $where[] = 'f.priority = ?'; $params[] = $filterPriority; }
$whereStr = implode(' AND ', $where);
$total = DB::count("SELECT COUNT(*) FROM ff_feedback f WHERE $whereStr", $params);
$feedbacks = DB::fetchAll("SELECT f.*, c.name as category_name, c.color as category_color, u.name as assignee_name FROM ff_feedback f LEFT JOIN ff_categories c ON c.id = f.category_id LEFT JOIN ff_users u ON u.id = f.assigned_to WHERE $whereStr ORDER BY f.$sortBy $sortDir LIMIT $perPage OFFSET " . (($page-1)*$perPage), $params);
$pageTitle = 'Feedback – ' . APP_NAME;
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <h1 class="text-xl font-bold text-gray-900">Feedback</h1>
    <div class="flex items-center gap-3">
      <a href="?action=new" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
        <i class="fas fa-plus"></i> Add Feedback
      </a>
    </div>
  </header>

  <?php foreach (getFlash() as $f): ?>
    <div data-flash class="mx-6 mt-4 px-4 py-3 rounded-xl text-sm font-medium transition-opacity <?= $f['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
      <?= h($f['msg']) ?>
    </div>
  <?php endforeach; ?>

  <!-- Filters -->
  <div class="px-6 py-4 border-b border-gray-100 bg-white">
    <form method="GET" class="flex items-center gap-3 flex-wrap">
      <input type="hidden" name="project_id" value="<?= $projectId ?>">
      <div class="relative flex-1 min-w-48">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search feedback..."
               class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
      </div>
      <select name="status" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        <option value="">All Statuses</option>
        <?php foreach (['new','under_review','planned','in_progress','done','declined','duplicate'] as $s): ?>
          <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="category" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="priority" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        <option value="">All Priorities</option>
        <?php foreach (['critical','high','medium','low'] as $p): ?>
          <option value="<?= $p ?>" <?= $filterPriority === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="sort" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Newest</option>
        <option value="vote_count" <?= $sortBy === 'vote_count' ? 'selected' : '' ?>>Most Voted</option>
        <option value="comment_count" <?= $sortBy === 'comment_count' ? 'selected' : '' ?>>Most Discussed</option>
      </select>
      <button type="submit" class="bg-gray-900 text-white text-sm font-medium px-4 py-2.5 rounded-xl hover:bg-gray-700 transition">Filter</button>
      <?php if ($search || $filterStatus || $filterCategory || $filterPriority): ?>
        <a href="<?= APP_URL ?>/admin/feedback.php" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Table -->
  <div class="p-6">
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <?php if (empty($feedbacks)): ?>
        <div class="py-16 text-center">
          <i class="fas fa-inbox text-4xl text-gray-300 block mb-3"></i>
          <p class="text-gray-500 font-medium">No feedback found</p>
          <p class="text-sm text-gray-400 mt-1">Try adjusting your filters or add new feedback</p>
        </div>
      <?php else: ?>
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/2">Feedback</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Priority</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Votes</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($feedbacks as $fb): ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="px-5 py-4">
                <a href="?id=<?= $fb['id'] ?>" class="group">
                  <div class="flex items-center gap-2 mb-0.5">
                    <?php if ($fb['category_name']): ?>
                      <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium" style="background:<?= h($fb['category_color']) ?>20;color:<?= h($fb['category_color']) ?>"><?= h($fb['category_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!$fb['is_public']): ?><span class="text-xs text-gray-400"><i class="fas fa-lock"></i></span><?php endif; ?>
                    <?php if ($fb['ai_sentiment']): ?><?= sentimentBadge($fb['ai_sentiment']) ?><?php endif; ?>
                  </div>
                  <p class="font-medium text-gray-900 group-hover:text-indigo-600 leading-tight"><?= h($fb['title']) ?></p>
                  <p class="text-xs text-gray-400 mt-0.5"><?= h($fb['submitter_name'] ?? 'Anonymous') ?> <?php if ($fb['submitter_email']): ?>&middot; <?= h($fb['submitter_email']) ?><?php endif; ?>
                    <?php if ($fb['assignee_name']): ?>&middot; <i class="fas fa-user"></i> <?= h($fb['assignee_name']) ?><?php endif; ?>
                  </p>
                </a>
              </td>
              <td class="px-5 py-4"><?= statusBadge($fb['status']) ?></td>
              <td class="px-5 py-4"><?= priorityBadge($fb['priority']) ?></td>
              <td class="px-5 py-4">
                <span class="flex items-center gap-1 text-gray-600"><i class="fas fa-thumbs-up text-gray-400"></i><?= $fb['vote_count'] ?></span>
              </td>
              <td class="px-5 py-4 text-gray-400 text-xs whitespace-nowrap"><?= timeAgo($fb['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <!-- Pagination -->
      <?php $totalPages = ceil($total / $perPage); if ($totalPages > 1): ?>
        <div class="flex items-center justify-between px-5 py-4 border-t border-gray-100">
          <p class="text-sm text-gray-500">Showing <?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage,$total) ?> of <?= $total ?></p>
          <div class="flex gap-1">
            <?php for ($i=1; $i<=$totalPages; $i++): ?>
              <a href="?p=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&category=<?= $filterCategory ?>&priority=<?= urlencode($filterPriority) ?>&sort=<?= $sortBy ?>"
                 class="w-8 h-8 flex items-center justify-center rounded-lg text-sm <?= $i === $page ? 'bg-indigo-600 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100' ?>"><?= $i ?></a>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php';
