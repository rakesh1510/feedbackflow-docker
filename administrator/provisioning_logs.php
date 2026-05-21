<?php
require_once __DIR__ . '/_bootstrap.php';
$adminUser = admin_require_login();
$rows = admin_safe_rows(admin_pdo(), "SELECT * FROM ff_provisioning_log ORDER BY id DESC LIMIT 100");
ob_start();
?>
<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
<div class="p-4 overflow-auto"><pre class="text-xs text-slate-700 whitespace-pre-wrap"><?= admin_h(json_encode($rows, JSON_PRETTY_PRINT)) ?></pre></div>
</div>
<?php
$content = ob_get_clean();
$pageTitle = 'Provisioning Logs';
include __DIR__ . '/_layout.php';
