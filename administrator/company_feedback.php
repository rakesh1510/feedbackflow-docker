<?php
require_once __DIR__ . '/_bootstrap.php';
$adminUser = admin_require_login();
$companyId = (int)($_GET['id'] ?? 0);
$company = admin_company_by_id($companyId);
$dbInfo = admin_company_db($companyId);
$tenantError = null; $rows = [];
if ($company && $dbInfo && (($dbInfo['db_status'] ?? '') === 'active')) {
    try { $tenant = tenant_pdo_for_company($companyId); $rows = admin_safe_rows($tenant, "SELECT * FROM ff_feedback ORDER BY id DESC"); }
    catch (Throwable $e) { $tenantError = $e->getMessage(); }
}
ob_start();
?>
<?php if (!$company): ?>
<div class="bg-white rounded-2xl border border-slate-200 p-6">Company not found.</div>
<?php elseif ($tenantError): ?>
<div class="bg-white rounded-2xl border border-amber-200 bg-amber-50 text-amber-700 p-6"><?= admin_h($tenantError) ?></div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
<div class="px-4 py-3 border-b border-slate-200 font-semibold">Feedback for <?= admin_h($company['name'] ?? '') ?></div>
<table class="w-full text-sm">
<thead class="bg-slate-50"><tr><th class="text-left px-4 py-3">ID</th><th class="text-left px-4 py-3">Title</th><th class="text-left px-4 py-3">Status</th><th class="text-left px-4 py-3">Created</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr class="border-t border-slate-100"><td class="px-4 py-3">#<?= (int)($row['id'] ?? 0) ?></td><td class="px-4 py-3 font-medium"><?= admin_h($row['title'] ?? ($row['name'] ?? '')) ?></td><td class="px-4 py-3 text-slate-500"><?= admin_h($row['status'] ?? '') ?></td><td class="px-4 py-3 text-slate-500"><?= admin_h($row['created_at'] ?? '') ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No feedback found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$pageTitle = 'Company Feedback';
include __DIR__ . '/_layout.php';
