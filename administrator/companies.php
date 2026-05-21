<?php
require_once __DIR__ . '/_bootstrap.php';
$adminUser = admin_require_login();
$master = admin_pdo();
$rows = admin_safe_rows($master, "SELECT c.*, d.db_name, d.db_status, d.provisioned_at FROM ff_companies c LEFT JOIN ff_company_databases d ON d.company_id = c.id ORDER BY c.id DESC");
ob_start();
?>
<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
<table class="w-full text-sm">
<thead class="bg-slate-50"><tr><th class="text-left px-4 py-3">Company</th><th class="text-left px-4 py-3">Slug</th><th class="text-left px-4 py-3">Plan</th><th class="text-left px-4 py-3">Active</th><th class="text-left px-4 py-3">Tenant DB</th><th class="text-left px-4 py-3">Action</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr class="border-t border-slate-100">
<td class="px-4 py-3 font-medium"><?= admin_h($row['name'] ?? '') ?></td>
<td class="px-4 py-3 text-slate-500"><?= admin_h($row['slug'] ?? '') ?></td>
<td class="px-4 py-3 text-slate-500"><?= admin_h($row['plan'] ?? '') ?></td>
<td class="px-4 py-3"><?= !empty($row['is_active']) ? 'Yes' : 'No' ?></td>
<td class="px-4 py-3 text-slate-500"><?= admin_h($row['db_name'] ?? '-') ?></td>
<td class="px-4 py-3"><a class="text-indigo-600 hover:underline" href="company_view.php?id=<?= (int)$row['id'] ?>">Open</a></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No companies found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php
$content = ob_get_clean();
$pageTitle = 'Companies';
include __DIR__ . '/_layout.php';
