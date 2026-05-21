<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser  = Auth::require();
$userProjects = getUserProjects($currentUser['id']);
Auth::start();
if (isset($_GET['project_id'])) $_SESSION['current_project_id'] = (int)$_GET['project_id'];
$projectId      = $_SESSION['current_project_id'] ?? ($userProjects[0]['id'] ?? null);
$currentProject = $projectId ? getProject($projectId, $currentUser['id']) : null;
if (!$currentProject && !empty($userProjects)) { $currentProject = $userProjects[0]; $projectId = $currentProject['id']; }
if (!$currentProject) { redirect(APP_URL . '/admin/projects.php'); }

// ── Actions ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['_action'] ?? '';

    // Run full AI analysis
    if ($action === 'run_analysis') {
        set_time_limit(120);
        $result = runAICopilotAnalysis($projectId);
        flash('success', "Analysis complete: {$result['clustered']} feedback items processed into {$result['clusters']} clusters, {$result['insights']} insights generated.");
        redirect(APP_URL . '/admin/ai-copilot.php');
    }

    // Create roadmap item from cluster
    if ($action === 'to_roadmap') {
        $clusterId = (int)($_POST['cluster_id'] ?? 0);
        $cluster   = DB::fetch("SELECT * FROM ff_ai_clusters WHERE id = ? AND project_id = ?", [$clusterId, $projectId]);
        if ($cluster) {
            $roadmapId = DB::insert('ff_roadmap', [
                'project_id'  => $projectId,
                'title'       => $cluster['title'],
                'description' => 'Auto-created from AI cluster with ' . $cluster['feedback_count'] . ' feedback items. Intent: ' . $cluster['intent'] . '. Suggested action: ' . $cluster['suggested_action'],
                'status'      => 'planned',
                'is_public'   => 1,
            ]);
            flash('success', 'Roadmap item created: "' . $cluster['title'] . '"');
        }
        redirect(APP_URL . '/admin/ai-copilot.php');
    }

    // Mark cluster as a bug (update all feedback in cluster)
    if ($action === 'mark_bug') {
        $clusterId = (int)($_POST['cluster_id'] ?? 0);
        $cluster   = DB::fetch("SELECT * FROM ff_ai_clusters WHERE id = ? AND project_id = ?", [$clusterId, $projectId]);
        if ($cluster) {
            DB::query(
                "UPDATE ff_feedback SET priority = 'high', status = 'under_review'
                 WHERE id IN (SELECT feedback_id FROM ff_cluster_feedback WHERE cluster_id = ?)",
                [$clusterId]
            );
            flash('success', $cluster['feedback_count'] . ' feedback items marked as high-priority bug under review.');
        }
        redirect(APP_URL . '/admin/ai-copilot.php');
    }

    // Send AI auto-reply to all users in a cluster who have an email
    if ($action === 'auto_reply_cluster') {
        $clusterId = (int)($_POST['cluster_id'] ?? 0);
        $cluster   = DB::fetch("SELECT * FROM ff_ai_clusters WHERE id = ? AND project_id = ?", [$clusterId, $projectId]);
        if ($cluster) {
            $feedbackItems = DB::fetchAll(
                "SELECT f.* FROM ff_feedback f
                 JOIN ff_cluster_feedback cf ON f.id = cf.feedback_id
                 WHERE cf.cluster_id = ? AND f.submitter_email IS NOT NULL AND f.submitter_email != '' AND f.ai_reply_sent = 0",
                [$clusterId]
            );
            $sent = 0;
            foreach ($feedbackItems as $fb) {
                $reply = $fb['ai_reply'] ?: aiGenerateReply($fb, $currentProject['name']);
                if (!$reply) continue;
                $html = '<div style="font-family:sans-serif;max-width:520px;padding:24px">'
                      . '<h2 style="color:#6366f1;font-size:18px;margin-bottom:12px">Re: ' . htmlspecialchars($fb['title']) . '</h2>'
                      . '<p style="color:#374151;line-height:1.6">' . nl2br(htmlspecialchars($reply)) . '</p>'
                      . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0">'
                      . '<p style="color:#9ca3af;font-size:12px">— The ' . htmlspecialchars($currentProject['name']) . ' Team</p>'
                      . '</div>';
                $err = '';
                if (ffSendMail($fb['submitter_email'], $fb['submitter_name'] ?? '', 'Re: ' . $fb['title'], $html, $err)) {
                    DB::update('ff_feedback', ['ai_reply' => $reply, 'ai_reply_sent' => 1, 'ai_reply_sent_at' => date('Y-m-d H:i:s')], 'id = ?', [$fb['id']]);
                    // Post as admin comment
                    DB::insert('ff_comments', ['feedback_id' => $fb['id'], 'user_id' => $currentUser['id'], 'content' => $reply, 'is_internal' => 0, 'is_admin_reply' => 1]);
                    $sent++;
                }
            }
            flash('success', "AI replies sent to {$sent} users in this cluster.");
        }
        redirect(APP_URL . '/admin/ai-copilot.php');
    }

    // Generate + send reply for a single feedback item
    if ($action === 'reply_one') {
        $feedbackId = (int)($_POST['feedback_id'] ?? 0);
        $fb = DB::fetch("SELECT * FROM ff_feedback WHERE id = ? AND project_id = ?", [$feedbackId, $projectId]);
        if ($fb) {
            $reply = aiGenerateReply($fb, $currentProject['name']);
            DB::update('ff_feedback', ['ai_reply' => $reply], 'id = ?', [$feedbackId]);
            // Post as admin reply comment
            DB::insert('ff_comments', ['feedback_id' => $feedbackId, 'user_id' => $currentUser['id'], 'content' => $reply, 'is_internal' => 0, 'is_admin_reply' => 1]);
            // Email if we have the address
            if ($fb['submitter_email']) {
                $html = '<div style="font-family:sans-serif;max-width:520px;padding:24px">'
                      . '<h2 style="color:#6366f1;font-size:18px;margin-bottom:12px">Re: ' . htmlspecialchars($fb['title']) . '</h2>'
                      . '<p style="color:#374151;line-height:1.6">' . nl2br(htmlspecialchars($reply)) . '</p>'
                      . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0">'
                      . '<p style="color:#9ca3af;font-size:12px">— The ' . htmlspecialchars($currentProject['name']) . ' Team</p>'
                      . '</div>';
                $err = '';
                if (ffSendMail($fb['submitter_email'], $fb['submitter_name'] ?? '', 'Re: ' . $fb['title'], $html, $err)) {
                    DB::update('ff_feedback', ['ai_reply_sent' => 1, 'ai_reply_sent_at' => date('Y-m-d H:i:s')], 'id = ?', [$feedbackId]);
                    flash('success', 'AI reply sent to ' . $fb['submitter_email']);
                } else {
                    flash('success', 'AI reply saved as comment. Email not sent: ' . $err);
                }
            } else {
                flash('success', 'AI reply saved as internal comment (no email address on file).');
            }
        }
        redirect(APP_URL . '/admin/ai-copilot.php');
    }
}

// ── Data for display ─────────────────────────────────────────────────────────
$clusters      = DB::fetchAll("SELECT * FROM ff_ai_clusters WHERE project_id = ? ORDER BY FIELD(severity,'critical','high','medium','low'), feedback_count DESC", [$projectId]);
$totalFeedback = (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ?", [$projectId]);
$analyzedCount = (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_intent IS NOT NULL", [$projectId]);
$unreadInsights = (int)DB::count("SELECT COUNT(*) FROM ff_ai_insights WHERE project_id = ? AND is_read = 0", [$projectId]);

$intentIcons = ['bug'=>'🐛','feature'=>'💡','ux'=>'🎨','performance'=>'⚡','pricing'=>'💰','praise'=>'🎉','other'=>'📝'];
$sevClasses  = [
    'critical' => 'border-red-300 bg-red-50',
    'high'     => 'border-orange-300 bg-orange-50',
    'medium'   => 'border-yellow-200 bg-yellow-50',
    'low'      => 'border-gray-200 bg-white',
];
$sevBadge = [
    'critical' => 'bg-red-100 text-red-700 border-red-300',
    'high'     => 'bg-orange-100 text-orange-700 border-orange-200',
    'medium'   => 'bg-yellow-100 text-yellow-700 border-yellow-200',
    'low'      => 'bg-gray-100 text-gray-600 border-gray-200',
];

$pageTitle = 'AI Copilot – ' . APP_NAME;
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto bg-gray-50">

  <!-- Header -->
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">🤖 AI Copilot</h1>
      <p class="text-sm text-gray-500 mt-0.5">Clusters feedback · Suggests actions · Sends replies</p>
    </div>
    <div class="flex items-center gap-3">
      <?php if ($unreadInsights > 0): ?>
        <a href="<?= APP_URL ?>/admin/ai-insights.php" class="text-xs font-semibold bg-indigo-100 text-indigo-700 border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-200 transition">
          <?= $unreadInsights ?> new insight<?= $unreadInsights > 1 ? 's' : '' ?> →
        </a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/admin/ai-insights.php" class="text-sm text-gray-600 hover:text-gray-800 px-3 py-2 rounded-xl hover:bg-gray-100 transition">
        <i class="fas fa-chart-line mr-1"></i> View Insights
      </a>
      <!-- Run Analysis -->
      <form method="POST" id="analyzeForm">
        <input type="hidden" name="_csrf"   value="<?= csrf() ?>">
        <input type="hidden" name="_action" value="run_analysis">
        <button type="submit" id="analyzeBtn"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-xl text-sm transition"
                onclick="this.textContent='Analyzing…';this.disabled=true;document.getElementById('analyzeForm').submit()">
          <i class="fas fa-sync-alt"></i> Run Analysis
        </button>
      </form>
    </div>
  </header>

  <?php foreach (getFlash() as $f): ?>
    <div data-flash class="mx-6 mt-4 px-4 py-3 rounded-xl text-sm <?= $f['type']==='success'?'bg-green-50 text-green-700 border border-green-200':'bg-red-50 text-red-700 border border-red-200' ?>"><?= h($f['msg']) ?></div>
  <?php endforeach; ?>

  <div class="p-6 space-y-5">

    <!-- Stats Bar -->
    <div class="grid grid-cols-4 gap-4">
      <?php
      $stats = [
        ['Total Feedback', $totalFeedback, 'fa-comments', 'indigo'],
        ['Analyzed', $analyzedCount, 'fa-brain', 'purple'],
        ['Clusters Found', count($clusters), 'fa-layer-group', 'blue'],
        ['AI Mode', AI_ENABLED ? 'OpenAI' : 'Smart Rules', 'fa-robot', AI_ENABLED ? 'green' : 'amber'],
      ];
      foreach ($stats as [$label, $val, $icon, $color]):
      ?>
      <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:<?= ['indigo'=>'#eef2ff','purple'=>'#f5f3ff','blue'=>'#eff6ff','green'=>'#f0fdf4','amber'=>'#fffbeb'][$color] ?>">
          <i class="fas <?= $icon ?>" style="color:<?= ['indigo'=>'#6366f1','purple'=>'#8b5cf6','blue'=>'#3b82f6','green'=>'#22c55e','amber'=>'#f59e0b'][$color] ?>"></i>
        </div>
        <div>
          <p class="text-xs text-gray-500"><?= $label ?></p>
          <p class="font-bold text-gray-900"><?= $val ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (!AI_ENABLED): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800 flex items-center gap-3">
      <i class="fas fa-info-circle text-amber-500"></i>
      <span>Running in <strong>Smart Rules mode</strong> (no OpenAI). Add your API key to <code class="bg-amber-100 px-1 rounded">config.php</code> to enable GPT-powered clustering and personalized replies.</span>
    </div>
    <?php endif; ?>

    <?php if (empty($clusters)): ?>
    <!-- Empty state -->
    <div class="bg-white rounded-2xl border border-gray-200 p-14 text-center">
      <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
        <i class="fas fa-robot text-2xl text-white"></i>
      </div>
      <h2 class="text-lg font-bold text-gray-900 mb-2">Ready to analyze your feedback</h2>
      <p class="text-gray-500 mb-6 max-w-sm mx-auto">Click "Run Analysis" to automatically group <?= $totalFeedback ?> feedback items into actionable clusters.</p>
      <form method="POST">
        <input type="hidden" name="_csrf"   value="<?= csrf() ?>">
        <input type="hidden" name="_action" value="run_analysis">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-3 rounded-xl transition">
          <i class="fas fa-play mr-2"></i> Run AI Analysis
        </button>
      </form>
    </div>
    <?php else: ?>

    <!-- Cluster Cards -->
    <div class="space-y-5">
      <?php foreach ($clusters as $cl):
        $feedbackInCluster = DB::fetchAll(
            "SELECT f.* FROM ff_feedback f JOIN ff_cluster_feedback cf ON f.id = cf.feedback_id WHERE cf.cluster_id = ? LIMIT 5",
            [$cl['id']]
        );
        $withEmail     = array_filter($feedbackInCluster, fn($f) => !empty($f['submitter_email']));
        $alreadyReplied = array_filter($feedbackInCluster, fn($f) => $f['ai_reply_sent']);
        $trendIcon  = ['rising' => '↑', 'falling' => '↓', 'stable' => '→'][$cl['trend']] ?? '→';
        $trendColor = ['rising' => 'text-red-600', 'falling' => 'text-green-600', 'stable' => 'text-gray-400'][$cl['trend']] ?? 'text-gray-400';
        $sentIcon   = ['positive' => '😊', 'neutral' => '😐', 'negative' => '😞'][$cl['avg_sentiment']] ?? '😐';
      ?>
      <div id="cluster-<?= $cl['id'] ?>" class="bg-white rounded-2xl border-2 <?= $sevClasses[$cl['severity']] ?? 'border-gray-200 bg-white' ?> overflow-hidden">

        <!-- Cluster Header -->
        <div class="px-6 py-4 flex items-start gap-4">
          <span class="text-2xl mt-0.5 flex-shrink-0"><?= $intentIcons[$cl['intent']] ?? '📝' ?></span>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="font-bold text-gray-900"><?= h($cl['title']) ?></h3>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border <?= $sevBadge[$cl['severity']] ?? '' ?>">
                <?= ucfirst($cl['severity']) ?>
              </span>
              <span class="text-sm font-bold <?= $trendColor ?>"><?= $trendIcon ?> <?= $cl['trend_pct'] ?>% <?= $cl['trend'] ?></span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">
              <?= $cl['feedback_count'] ?> reports · <?= $sentIcon ?> <?= ucfirst($cl['avg_sentiment'] ?? 'neutral') ?> · <?= ucfirst($cl['intent']) ?>
            </p>
            <?php if ($cl['suggested_action']): ?>
              <p class="text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-1.5 mt-2 inline-block">
                <i class="fas fa-lightbulb mr-1"></i> <strong>Suggestion:</strong> <?= h($cl['suggested_action']) ?>
              </p>
            <?php endif; ?>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center gap-2 flex-shrink-0 flex-wrap justify-end">

            <!-- To Roadmap -->
            <form method="POST">
              <input type="hidden" name="_csrf"      value="<?= csrf() ?>">
              <input type="hidden" name="_action"    value="to_roadmap">
              <input type="hidden" name="cluster_id" value="<?= $cl['id'] ?>">
              <button type="submit" title="Add to Roadmap"
                      class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl border border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition">
                <i class="fas fa-map-signs"></i> Add to Roadmap
              </button>
            </form>

            <!-- Mark as Bug -->
            <?php if ($cl['intent'] === 'bug' || $cl['severity'] === 'critical'): ?>
            <form method="POST">
              <input type="hidden" name="_csrf"      value="<?= csrf() ?>">
              <input type="hidden" name="_action"    value="mark_bug">
              <input type="hidden" name="cluster_id" value="<?= $cl['id'] ?>">
              <button type="submit" title="Mark all as high-priority bug"
                      class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl border border-red-200 text-red-700 bg-red-50 hover:bg-red-100 transition">
                <i class="fas fa-bug"></i> Mark as Bug
              </button>
            </form>
            <?php endif; ?>

            <!-- Auto-Reply All -->
            <?php if (!empty($withEmail)): ?>
            <form method="POST" onsubmit="return confirm('Send AI-generated replies to <?= count($withEmail) ?> users in this cluster?')">
              <input type="hidden" name="_csrf"      value="<?= csrf() ?>">
              <input type="hidden" name="_action"    value="auto_reply_cluster">
              <input type="hidden" name="cluster_id" value="<?= $cl['id'] ?>">
              <button type="submit"
                      class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl border border-green-200 text-green-700 bg-green-50 hover:bg-green-100 transition">
                <i class="fas fa-paper-plane"></i> Reply All (<?= count($withEmail) ?>)
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>

        <!-- Feedback Items in Cluster -->
        <?php if (!empty($feedbackInCluster)): ?>
        <div class="border-t border-gray-100 divide-y divide-gray-50">
          <?php foreach ($feedbackInCluster as $fb):
            $sentBadge = ['positive' => 'bg-green-100 text-green-700', 'neutral' => 'bg-gray-100 text-gray-600', 'negative' => 'bg-red-100 text-red-700'][$fb['ai_sentiment'] ?? 'neutral'];
          ?>
          <div class="px-6 py-3 flex items-start gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="text-sm font-medium text-gray-800 truncate"><?= h($fb['title']) ?></p>
                <?php if ($fb['ai_sentiment']): ?>
                  <span class="text-xs px-1.5 py-0.5 rounded-full <?= $sentBadge ?>"><?= ucfirst($fb['ai_sentiment']) ?></span>
                <?php endif; ?>
                <?php if ($fb['ai_reply_sent']): ?>
                  <span class="text-xs px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700"><i class="fas fa-check mr-1"></i>Replied</span>
                <?php endif; ?>
              </div>
              <p class="text-xs text-gray-400 mt-0.5">
                <?= $fb['submitter_name'] ? h($fb['submitter_name']) : 'Anonymous' ?>
                <?= $fb['submitter_email'] ? '· ' . h($fb['submitter_email']) : '' ?>
                · <?= timeAgo($fb['created_at']) ?>
              </p>
              <?php if ($fb['ai_reply'] && !$fb['ai_reply_sent']): ?>
                <p class="text-xs text-indigo-600 mt-1 italic">Draft reply: "<?= h(mb_substr($fb['ai_reply'], 0, 100)) ?>…"</p>
              <?php endif; ?>
            </div>
            <!-- Per-item reply button -->
            <form method="POST" class="flex-shrink-0">
              <input type="hidden" name="_csrf"       value="<?= csrf() ?>">
              <input type="hidden" name="_action"     value="reply_one">
              <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
              <button type="submit" title="Generate & send AI reply"
                      class="text-xs text-indigo-600 hover:text-white hover:bg-indigo-600 border border-indigo-200 px-2 py-1 rounded-lg transition font-medium">
                <?= $fb['ai_reply_sent'] ? '<i class="fas fa-redo"></i> Re-reply' : '<i class="fas fa-magic"></i> Reply' ?>
              </button>
            </form>
          </div>
          <?php endforeach; ?>
          <?php if ($cl['feedback_count'] > 5): ?>
            <div class="px-6 py-2 text-xs text-gray-400">
              + <?= $cl['feedback_count'] - 5 ?> more items in this cluster
              <a href="<?= APP_URL ?>/admin/feedback.php?intent=<?= $cl['intent'] ?>" class="text-indigo-500 hover:underline ml-1">View all →</a>
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; // clusters ?>

  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
