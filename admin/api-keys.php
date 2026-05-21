<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = Auth::requirePermission('project_settings');
$userProjects = getUserProjects($currentUser['id']);
$pageTitle = 'API Keys – ' . APP_NAME;

$newKey = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? 'My API Key');
        $scopes = $_POST['scopes'] ?? ['read:feedback'];
        if (!is_array($scopes)) $scopes = [$scopes];

        // Generate a secure API key
        $rawKey  = 'ffk_' . bin2hex(random_bytes(20));
        $prefix  = substr($rawKey, 0, 12);
        $keyHash = hash('sha256', $rawKey);

        DB::insert('ff_api_keys', [
            'user_id'    => $currentUser['id'],
            'name'       => $name,
            'key_hash'   => $keyHash,
            'key_prefix' => $prefix,
            'scopes'     => json_encode($scopes),
            'is_active'  => 1,
        ]);

        $newKey = $rawKey; // Show once
        logAudit($currentUser, 'api_key.create', 'api_key', null, null, ['name' => $name]);
        flash('success', 'API key created. Copy it now — it will not be shown again!');
    }

    if ($action === 'revoke' && isset($_POST['id'])) {
        DB::update('ff_api_keys', ['is_active' => 0], 'id = ? AND user_id = ?', [(int)$_POST['id'], $currentUser['id']]);
        flash('success', 'API key revoked.');
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        DB::delete('ff_api_keys', 'id = ? AND user_id = ?', [(int)$_POST['id'], $currentUser['id']]);
        flash('success', 'API key deleted.');
    }

    if (!$newKey) redirect(APP_URL . '/admin/api-keys.php');
}

$keys = DB::fetchAll("SELECT * FROM ff_api_keys WHERE user_id = ? ORDER BY created_at DESC", [$currentUser['id']]);
$flash = getFlash();

$allScopes = [
    'read:feedback'   => 'Read feedback',
    'write:feedback'  => 'Submit feedback',
    'read:projects'   => 'Read projects',
    'read:analytics'  => 'Read analytics',
    'write:campaigns' => 'Send campaigns',
    'admin'           => 'Full admin access',
];

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">

  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">API Keys</h1>
      <p class="text-sm text-gray-500 mt-0.5">Manage API keys for third-party integrations (Module 29)</p>
    </div>
    <button onclick="document.getElementById('createModal').classList.remove('hidden')"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
      <i class="fas fa-plus"></i> Create API Key
    </button>
  </header>

  <div class="p-6 space-y-5">
    <?php foreach ($flash as $f): ?>
      <div class="rounded-xl px-4 py-3 text-sm font-medium <?= $f['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
        <?= h($f['msg']) ?>
      </div>
    <?php endforeach; ?>

    <!-- New key reveal -->
    <?php if ($newKey): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
      <div class="flex items-start gap-3">
        <i class="fas fa-key text-amber-600 mt-0.5"></i>
        <div class="flex-1">
          <p class="font-semibold text-amber-800 mb-1">⚠ Copy your API key now — it won't be shown again!</p>
          <div class="flex items-center gap-2 bg-white border border-amber-200 rounded-xl px-4 py-2.5 font-mono text-sm text-gray-800">
            <span id="newKeyText"><?= h($newKey) ?></span>
            <button onclick="navigator.clipboard.writeText('<?= h($newKey) ?>');this.textContent='Copied!'" class="ml-auto text-xs text-amber-600 hover:text-amber-800 font-semibold">Copy</button>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Docs callout -->
    <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-5 flex items-start gap-4">
      <i class="fas fa-book text-indigo-500 mt-0.5"></i>
      <div>
        <p class="text-sm font-semibold text-gray-900">API Documentation</p>
        <p class="text-sm text-gray-600 mt-0.5">Use your API keys to authenticate requests. Include the key in the <code class="bg-white px-1 rounded border border-gray-200">Authorization: Bearer YOUR_KEY</code> header.</p>
        <p class="text-xs text-gray-400 mt-1">Base URL: <code class="bg-white px-1 rounded"><?= APP_URL ?>/api/</code></p>
      </div>
    </div>

    <!-- Keys list -->
    <?php if (empty($keys)): ?>
    <div class="ff-card p-12 text-center">
      <i class="fas fa-key text-4xl text-gray-200 mb-3"></i>
      <p class="text-gray-400 font-medium">No API keys yet</p>
      <p class="text-sm text-gray-300 mt-1">Create a key to start integrating with external services.</p>
    </div>
    <?php else: ?>
    <div class="ff-card divide-y divide-gray-100 overflow-hidden">
      <?php foreach ($keys as $key): ?>
      <?php $scopes = json_decode($key['scopes'] ?? '[]', true) ?? []; ?>
      <div class="flex items-center gap-4 px-5 py-4 <?= !$key['is_active'] ? 'opacity-50' : '' ?>">
        <div class="w-10 h-10 rounded-xl <?= $key['is_active'] ? 'bg-indigo-100' : 'bg-gray-100' ?> flex items-center justify-center flex-shrink-0">
          <i class="fas fa-key <?= $key['is_active'] ? 'text-indigo-600' : 'text-gray-400' ?>"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <p class="font-medium text-gray-900"><?= h($key['name']) ?></p>
            <span class="badge <?= $key['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>"><?= $key['is_active'] ? 'Active' : 'Revoked' ?></span>
          </div>
          <p class="text-xs font-mono text-gray-500 mt-0.5"><?= h($key['key_prefix']) ?>•••••••••••••••••••</p>
          <div class="flex gap-1 mt-1">
            <?php foreach ($scopes as $scope): ?>
            <span class="badge bg-gray-100 text-gray-600 text-xs"><?= h($scope) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="text-right text-xs text-gray-400 flex-shrink-0">
          <?php if ($key['last_used_at']): ?>
          <p>Last used: <?= timeAgo($key['last_used_at']) ?></p>
          <?php else: ?>
          <p>Never used</p>
          <?php endif; ?>
          <p>Created: <?= timeAgo($key['created_at']) ?></p>
        </div>
        <div class="flex gap-1">
          <?php if ($key['is_active']): ?>
          <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="revoke">
            <input type="hidden" name="id" value="<?= $key['id'] ?>">
            <button onclick="return confirm('Revoke this key?')" class="px-3 py-1.5 text-xs border border-amber-200 text-amber-700 rounded-lg hover:bg-amber-50 transition">Revoke</button>
          </form>
          <?php endif; ?>
          <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $key['id'] ?>">
            <button onclick="return confirm('Delete this key permanently?')" class="px-3 py-1.5 text-xs border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition">Delete</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>

<!-- Create Modal -->
<div id="createModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-lg font-bold text-gray-900">Create API Key</h2>
      <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>
    <form method="post" class="space-y-4">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="create">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Key Name *</label>
        <input type="text" name="name" required placeholder="e.g. My Zapier Integration"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Permissions (Scopes)</label>
        <div class="space-y-2">
          <?php foreach ($allScopes as $scope => $label): ?>
          <label class="flex items-center gap-2.5 cursor-pointer">
            <input type="checkbox" name="scopes[]" value="<?= $scope ?>" class="rounded" <?= $scope === 'read:feedback' ? 'checked' : '' ?>>
            <span class="text-sm text-gray-700"><?= $label ?></span>
            <code class="text-xs text-gray-400 font-mono ml-auto"><?= $scope ?></code>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                class="flex-1 border border-gray-200 text-gray-700 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition">Cancel</button>
        <button class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-sm font-semibold transition">Create Key</button>
      </div>
    </form>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
