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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { flash('error','CSRF failed.'); redirect($_SERVER['REQUEST_URI']); }
    $pAction = $_POST['_action'] ?? '';
    if ($pAction === 'save_slack') {
        DB::query("INSERT INTO ff_settings (project_id, `key`, value) VALUES (?, 'slack_webhook', ?) ON DUPLICATE KEY UPDATE value = ?", [$projectId, $_POST['slack_webhook'] ?? '', $_POST['slack_webhook'] ?? '']);
        flash('success', 'Slack configured!');
    } elseif ($pAction === 'save_jira') {
        foreach (['jira_url','jira_project','jira_email','jira_token'] as $k) {
            DB::query("INSERT INTO ff_settings (project_id, `key`, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = ?", [$projectId, $k, $_POST[$k]??'', $_POST[$k]??'']);
        }
        flash('success', 'Jira configured!');
    } elseif ($pAction === 'save_email') {
        foreach (['email_new_feedback','email_on_comment','notify_emails'] as $k) {
            DB::query("INSERT INTO ff_settings (project_id, `key`, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = ?", [$projectId, $k, $_POST[$k]??'0', $_POST[$k]??'0']);
        }
        flash('success', 'Email settings saved!');
    } elseif ($pAction === 'add_webhook') {
        DB::insert('ff_webhooks', ['project_id' => $projectId, 'name' => sanitize($_POST['name']??''), 'url' => trim($_POST['url']??''), 'secret' => sanitize($_POST['secret']??''), 'events' => implode(',', (array)($_POST['events']??[]))]);
        flash('success', 'Webhook added!');
    } elseif ($pAction === 'delete_webhook') {
        DB::delete('ff_webhooks', 'id = ? AND project_id = ?', [(int)$_POST['webhook_id'], $projectId]);
        flash('success', 'Webhook deleted.');
    } elseif ($pAction === 'test_webhook') {
        $wh = DB::fetch("SELECT * FROM ff_webhooks WHERE id = ? AND project_id = ?", [(int)$_POST['webhook_id'], $projectId]);
        if ($wh) { triggerWebhooks($projectId, 'test', ['message' => 'This is a test webhook from FeedbackFlow']); flash('success', 'Test webhook sent!'); }
    }
    redirect(APP_URL . '/admin/integrations.php');
}

$getSetting = fn($key) => DB::fetch("SELECT value FROM ff_settings WHERE project_id = ? AND `key` = ?", [$projectId, $key])['value'] ?? '';
$webhooks = DB::fetchAll("SELECT * FROM ff_webhooks WHERE project_id = ? ORDER BY created_at DESC", [$projectId]);
$pageTitle = 'Integrations – ' . APP_NAME;
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4">
    <h1 class="text-xl font-bold text-gray-900">Integrations</h1>
    <p class="text-sm text-gray-500 mt-0.5">Connect FeedbackFlow with your tools</p>
  </header>

  <?php foreach (getFlash() as $f): ?>
    <div data-flash class="mx-6 mt-4 px-4 py-3 rounded-xl text-sm <?= $f['type']==='success'?'bg-green-50 text-green-700 border border-green-200':'bg-red-50 text-red-700 border border-red-200' ?>"><?= h($f['msg']) ?></div>
  <?php endforeach; ?>

  <div class="p-6 space-y-6" x-data="{ tab: 'slack' }">
    <!-- Tab Nav -->
    <div class="flex border-b border-gray-200 gap-1">
      <?php $tabs = [['slack','Slack','hashtag'],['email','Email','envelope'],['jira','Jira','bug'],['zapier','Zapier','bolt'],['webhooks','Webhooks','code']]; ?>
      <?php foreach ($tabs as [$id,$label,$icon]): ?>
        <button @click="tab='<?= $id ?>'" :class="tab==='<?= $id ?>'?'border-indigo-600 text-indigo-600 font-semibold':'border-transparent text-gray-500 hover:text-gray-700'"
                class="flex items-center gap-2 px-4 py-2.5 text-sm border-b-2 transition-colors">
          <i class="fas fa-<?= $icon ?>"></i> <?= $label ?>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- Slack -->
    <div x-show="tab==='slack'" x-cloak>
      <div class="bg-white rounded-2xl border border-gray-200 p-6 max-w-2xl">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 bg-[#4A154B] rounded-xl flex items-center justify-center">
            <i class="fab fa-slack text-white text-xl"></i>
          </div>
          <div><h2 class="font-bold text-gray-900">Slack</h2><p class="text-sm text-gray-500">Get notified in Slack when new feedback arrives</p></div>
        </div>
        <form method="POST" class="space-y-4">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="_action" value="save_slack">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Slack Webhook URL</label>
            <input type="url" name="slack_webhook" value="<?= h($getSetting('slack_webhook')) ?>" placeholder="https://hooks.slack.com/services/..." class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            <p class="text-xs text-gray-400 mt-1">Create at Slack API → Incoming Webhooks</p>
          </div>
          <button type="submit" class="bg-[#4A154B] hover:opacity-90 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition">Save Slack Integration</button>
        </form>
      </div>
    </div>

    <!-- Email -->
    <div x-show="tab==='email'" x-cloak>
      <div class="bg-white rounded-2xl border border-gray-200 p-6 max-w-2xl">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center"><i class="fas fa-envelope text-white"></i></div>
          <div><h2 class="font-bold text-gray-900">Email Notifications</h2><p class="text-sm text-gray-500">Configure when and who receives email alerts</p></div>
        </div>
        <form method="POST" class="space-y-4">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="_action" value="save_email">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notify Emails (comma-separated)</label>
            <input type="text" name="notify_emails" value="<?= h($getSetting('notify_emails')) ?>" placeholder="admin@company.com, team@company.com" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div class="space-y-3">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" name="email_new_feedback" value="1" <?= $getSetting('email_new_feedback')?'checked':'' ?> class="rounded text-indigo-600">
              <div><p class="text-sm font-medium text-gray-700">New feedback submitted</p><p class="text-xs text-gray-400">Get an email whenever new feedback is submitted</p></div>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" name="email_on_comment" value="1" <?= $getSetting('email_on_comment')?'checked':'' ?> class="rounded text-indigo-600">
              <div><p class="text-sm font-medium text-gray-700">New public comment</p><p class="text-xs text-gray-400">Get an email when someone comments on feedback</p></div>
            </label>
          </div>
          <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-700">
            <i class="fas fa-info-circle mr-1"></i> Configure SMTP settings in <code>config.php</code> for emails to work.
          </div>
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition">Save Email Settings</button>
        </form>
      </div>
    </div>

    <!-- Jira -->
    <div x-show="tab==='jira'" x-cloak>
      <div class="bg-white rounded-2xl border border-gray-200 p-6 max-w-2xl">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 bg-[#0052CC] rounded-xl flex items-center justify-center"><i class="fab fa-jira text-white text-xl"></i></div>
          <div><h2 class="font-bold text-gray-900">Jira</h2><p class="text-sm text-gray-500">Create Jira issues from feedback items</p></div>
        </div>
        <form method="POST" class="space-y-4">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="_action" value="save_jira">
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Jira URL</label>
            <input type="url" name="jira_url" value="<?= h($getSetting('jira_url')) ?>" placeholder="https://yourcompany.atlassian.net" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Project Key</label>
            <input type="text" name="jira_project" value="<?= h($getSetting('jira_project')) ?>" placeholder="PROJ" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="jira_email" value="<?= h($getSetting('jira_email')) ?>" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">API Token</label>
            <input type="password" name="jira_token" value="<?= h($getSetting('jira_token')) ?>" placeholder="Your Jira API token" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
          <button type="submit" class="bg-[#0052CC] hover:opacity-90 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition">Save Jira Integration</button>
        </form>
      </div>
    </div>

    <!-- Zapier -->
    <div x-show="tab==='zapier'" x-cloak>
      <div class="bg-white rounded-2xl border border-gray-200 p-6 max-w-2xl">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 bg-[#FF4A00] rounded-xl flex items-center justify-center"><i class="fas fa-bolt text-white"></i></div>
          <div><h2 class="font-bold text-gray-900">Zapier</h2><p class="text-sm text-gray-500">Connect with 6,000+ apps via Zapier</p></div>
        </div>
        <div class="prose prose-sm text-gray-600 space-y-3">
          <p>Use our Webhook integration to connect FeedbackFlow with Zapier:</p>
          <ol class="space-y-2 pl-4">
            <li>In Zapier, create a new Zap with <strong>Webhooks by Zapier</strong> as the trigger</li>
            <li>Copy the Zapier webhook URL</li>
            <li>Go to the <strong>Webhooks</strong> tab and add the Zapier URL</li>
            <li>Select which events should trigger your Zap</li>
          </ol>
          <div class="bg-gray-50 rounded-xl p-4">
            <p class="font-semibold text-gray-900 mb-2">Available Events:</p>
            <ul class="text-sm space-y-1">
              <?php foreach (['feedback.created','feedback.updated','feedback.voted','comment.created'] as $ev): ?>
                <li><code class="bg-gray-200 px-1.5 py-0.5 rounded text-xs"><?= $ev ?></code></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
        <a onclick="document.querySelector('[\\@click=\"tab=\\'webhooks\\'\"]').click()" href="javascript:void(0)" class="mt-4 inline-flex items-center gap-2 bg-[#FF4A00] hover:opacity-90 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition">
          <i class="fas fa-plus"></i> Add Webhook for Zapier
        </a>
      </div>
    </div>

    <!-- Webhooks -->
    <div x-show="tab==='webhooks'" x-cloak>
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden max-w-3xl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h2 class="font-bold text-gray-900">Custom Webhooks</h2>
          <button onclick="document.getElementById('addWebhookModal').classList.remove('hidden')" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-xl hover:bg-indigo-700 transition">
            <i class="fas fa-plus"></i> Add Webhook
          </button>
        </div>
        <?php if (empty($webhooks)): ?>
          <div class="py-12 text-center text-gray-400"><i class="fas fa-plug text-3xl block mb-2"></i>No webhooks configured</div>
        <?php else: foreach ($webhooks as $wh): ?>
          <div class="flex items-center gap-4 px-6 py-4 border-b border-gray-50 hover:bg-gray-50">
            <div class="flex-1 min-w-0">
              <p class="font-medium text-gray-900"><?= h($wh['name']) ?></p>
              <p class="text-sm text-gray-400 truncate"><?= h($wh['url']) ?></p>
              <p class="text-xs text-gray-300 mt-0.5">Events: <?= h($wh['events']) ?> <?php if ($wh['last_triggered']): ?>&middot; Last: <?= timeAgo($wh['last_triggered']) ?><?php endif; ?></p>
            </div>
            <div class="flex items-center gap-2">
              <form method="POST"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="_action" value="test_webhook"><input type="hidden" name="webhook_id" value="<?= $wh['id'] ?>">
                <button type="submit" class="text-xs text-indigo-600 border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-50">Test</button></form>
              <form method="POST" onsubmit="return confirm('Delete?')"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="_action" value="delete_webhook"><input type="hidden" name="webhook_id" value="<?= $wh['id'] ?>">
                <button type="submit" class="text-red-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button></form>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</main>
</div>

<!-- Add Webhook Modal -->
<div id="addWebhookModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-lg font-bold">Add Webhook</h3>
      <button onclick="document.getElementById('addWebhookModal').classList.add('hidden')" class="text-gray-400"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="_csrf" value="<?= csrf() ?>">
      <input type="hidden" name="_action" value="add_webhook">
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
        <input type="text" name="name" required placeholder="My Webhook" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
        <input type="url" name="url" required placeholder="https://..." class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Secret (optional)</label>
        <input type="text" name="secret" placeholder="For signature verification" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-2">Events</label>
        <div class="grid grid-cols-2 gap-2">
          <?php foreach (['feedback.created','feedback.updated','feedback.voted','comment.created','status.changed'] as $ev): ?>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" name="events[]" value="<?= $ev ?>" class="rounded text-indigo-600"> <?= $ev ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl">Add Webhook</button>
        <button type="button" onclick="document.getElementById('addWebhookModal').classList.add('hidden')" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-600">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
