<?php
require_once __DIR__ . '/_bootstrap.php';
$adminUser = admin_require_login();
$companyId = (int)($_GET['id'] ?? 0);
$company = admin_company_by_id($companyId);
$dbInfo = admin_company_db($companyId);
$tenantUsers = []; $tenantProjects = []; $tenantFeedback = []; $tenantError = null;
if ($company && $dbInfo && !empty($dbInfo['db_name']) && (($dbInfo['db_status'] ?? '') === 'active')) {
    try {
        $tenant = tenant_pdo_for_company($companyId);
        $tenantUsers = admin_safe_rows($tenant, "SELECT id,name,email,role,is_active,created_at FROM ff_users ORDER BY id DESC LIMIT 10");
        $tenantProjects = admin_safe_rows($tenant, "SELECT * FROM ff_projects ORDER BY id DESC LIMIT 10");
        $tenantFeedback = admin_safe_rows($tenant, "SELECT * FROM ff_feedback ORDER BY id DESC LIMIT 10");
    } catch (Throwable $e) {
        $tenantError = $e->getMessage();
    }
}
ob_start();
?>
<?php if (!$company): ?>
<div class="bg-white rounded-2xl border border-slate-200 p-6">Company not found.</div>
<?php else: ?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
<div class="xl:col-span-1 bg-white rounded-2xl border border-slate-200 p-6">
<h2 class="text-lg font-semibold text-slate-900 mb-4">Company</h2>
<dl class="space-y-3 text-sm">
<div><dt class="text-slate-500">Name</dt><dd class="font-medium"><?= admin_h($company['name'] ?? '') ?></dd></div>
<div><dt class="text-slate-500">Slug</dt><dd class="font-medium"><?= admin_h($company['slug'] ?? '') ?></dd></div>
<div><dt class="text-slate-500">Plan</dt><dd class="font-medium"><?= admin_h($company['plan'] ?? '') ?></dd></div>
<div><dt class="text-slate-500">Active</dt><dd class="font-medium"><?= !empty($company['is_active']) ? 'Yes' : 'No' ?></dd></div>
<div><dt class="text-slate-500">Tenant DB</dt><dd class="font-medium"><?= admin_h($dbInfo['db_name'] ?? '-') ?></dd></div>
</dl>
<div class="mt-6 space-y-2">
<a class="block rounded-xl bg-indigo-600 text-white text-center py-3 font-semibold" href="company_users.php?id=<?= (int)$companyId ?>">Manage Users</a>
<a class="block rounded-xl bg-slate-800 text-white text-center py-3 font-semibold" href="company_projects.php?id=<?= (int)$companyId ?>">View Projects</a>
<a class="block rounded-xl bg-slate-700 text-white text-center py-3 font-semibold" href="company_feedback.php?id=<?= (int)$companyId ?>">View Feedback</a>
</div>
</div>
<div class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
<h2 class="text-lg font-semibold text-slate-900 mb-4">Tenant Data Preview</h2>
<?php if ($tenantError): ?>
<div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-700 px-4 py-3 text-sm"><?= admin_h($tenantError) ?></div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
<div class="rounded-xl bg-slate-50 p-4"><div class="text-xs text-slate-500">Users</div><div class="text-2xl font-bold"><?= count($tenantUsers) ?></div></div>
<div class="rounded-xl bg-slate-50 p-4"><div class="text-xs text-slate-500">Projects</div><div class="text-2xl font-bold"><?= count($tenantProjects) ?></div></div>
<div class="rounded-xl bg-slate-50 p-4"><div class="text-xs text-slate-500">Feedback</div><div class="text-2xl font-bold"><?= count($tenantFeedback) ?></div></div>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div><h3 class="font-semibold mb-2">Users</h3><div class="space-y-2 text-sm">
<?php foreach ($tenantUsers as $row): ?><div class="rounded-xl border border-slate-200 px-3 py-2"><div class="font-medium"><?= admin_h($row['name'] ?? '') ?></div><div class="text-xs text-slate-500"><?= admin_h($row['email'] ?? '') ?> · <?= admin_h($row['role'] ?? '') ?></div></div><?php endforeach; ?>
<?php if (!$tenantUsers): ?><div class="text-slate-500 text-sm">No users found.</div><?php endif; ?>
</div></div>
<div><h3 class="font-semibold mb-2">Projects</h3><div class="space-y-2 text-sm">
<?php foreach ($tenantProjects as $row): ?><div class="rounded-xl border border-slate-200 px-3 py-2"><div class="font-medium"><?= admin_h($row['name'] ?? ('Project #' . ($row['id'] ?? ''))) ?></div></div><?php endforeach; ?>
<?php if (!$tenantProjects): ?><div class="text-slate-500 text-sm">No projects found.</div><?php endif; ?>
</div></div>
<div><h3 class="font-semibold mb-2">Feedback</h3><div class="space-y-2 text-sm">
<?php foreach ($tenantFeedback as $row): ?><div class="rounded-xl border border-slate-200 px-3 py-2"><div class="font-medium">#<?= (int)($row['id'] ?? 0) ?></div><div class="text-xs text-slate-500"><?= admin_h($row['status'] ?? '') ?></div></div><?php endforeach; ?>
<?php if (!$tenantFeedback): ?><div class="text-slate-500 text-sm">No feedback found.</div><?php endif; ?>
</div></div>
</div>
<?php endif; ?>
</div></div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$pageTitle = 'Company Detail';
include __DIR__ . '/_layout.php';
