<?php
require_once __DIR__ . '/_bootstrap.php';
$adminUser = admin_require_login();
$master = admin_pdo();
$companies = admin_safe_rows($master, "SELECT c.id, c.name, d.db_status FROM ff_companies c LEFT JOIN ff_company_databases d ON d.company_id = c.id ORDER BY c.id DESC");
$rows = [];
foreach ($companies as $company) {
    if (($company['db_status'] ?? '') !== 'active') continue;
    try {
        $tenant = tenant_pdo_for_company((int)$company['id']);
        $items = admin_safe_rows($tenant, "SELECT * FROM ff_feedback ORDER BY id DESC LIMIT 50");
        foreach ($items as $it) { $it['_company_name'] = $company['name']; $rows[] = $it; }
    } catch (Throwable $e) {}
}
ob_start();
?>
<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
<table class="w-full text-sm">
<thead class="bg-slate-50"><tr><th class="text-left px-4 py-3">Company</th><th class="text-left px-4 py-3">ID</th><th class="text-left px-4 py-3">Title</th><th class="text-left px-4 py-3">Status</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr class="border-t border-slate-100"><td class="px-4 py-3"><?= admin_h($row['_company_name'] ?? '') ?></td><td class="px-4 py-3 font-medium">#<?= (int)($row['id'] ?? 0) ?></td><td class="px-4 py-3"><?= admin_h($row['title'] ?? ($row['name'] ?? '')) ?></td><td class="px-4 py-3 text-slate-500"><?= admin_h($row['status'] ?? '') ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No feedback found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php
$content = ob_get_clean();
$pageTitle = 'All Feedback';
include __DIR__ . '/_layout.php';
