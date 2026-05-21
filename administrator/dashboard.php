<?php
require_once __DIR__ . '/_bootstrap.php';
$adminUser = admin_require_login();
$master = admin_pdo();
$stats = [
    'Companies' => admin_safe_count($master, "SELECT COUNT(*) FROM ff_companies"),
    'Tenant DBs' => admin_safe_count($master, "SELECT COUNT(*) FROM ff_company_databases"),
    'Plans' => admin_safe_count($master, "SELECT COUNT(*) FROM ff_billing_plans"),
    'Subscriptions' => admin_safe_count($master, "SELECT COUNT(*) FROM ff_subscriptions"),
    'Payments' => admin_safe_count($master, "SELECT COUNT(*) FROM ff_payments"),
    'Invoices' => admin_safe_count($master, "SELECT COUNT(*) FROM ff_invoices"),
];
$companies = admin_safe_rows($master, "SELECT * FROM ff_companies ORDER BY id DESC LIMIT 8");
$logs = admin_safe_rows($master, "SELECT * FROM ff_provisioning_log ORDER BY id DESC LIMIT 10");
ob_start();
?>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
<?php foreach ($stats as $label => $value): ?>
<div class="bg-white rounded-2xl border border-slate-200 p-6"><div class="text-sm text-slate-500"><?= admin_h($label) ?></div><div class="mt-2 text-3xl font-bold text-slate-900"><?= number_format($value) ?></div></div>
<?php endforeach; ?>
</div>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-8">
<div class="bg-white rounded-2xl border border-slate-200 p-6"><h2 class="text-lg font-semibold text-slate-900 mb-4">Recent Companies</h2><div class="space-y-3">
<?php foreach ($companies as $row): ?>
<a class="block rounded-xl border border-slate-200 px-4 py-3 hover:bg-slate-50" href="company_view.php?id=<?= (int)$row['id'] ?>"><div class="font-medium text-slate-900"><?= admin_h($row['name'] ?? '') ?></div><div class="text-xs text-slate-500"><?= admin_h($row['slug'] ?? '') ?> · <?= admin_h($row['plan'] ?? '') ?></div></a>
<?php endforeach; ?>
<?php if (!$companies): ?><p class="text-sm text-slate-500">No companies found.</p><?php endif; ?>
</div></div>
<div class="bg-white rounded-2xl border border-slate-200 p-6"><h2 class="text-lg font-semibold text-slate-900 mb-4">Recent Provisioning Log</h2><div class="space-y-3">
<?php foreach ($logs as $row): ?>
<div class="rounded-xl border border-slate-200 px-4 py-3"><div class="text-sm font-medium text-slate-900"><?= admin_h($row['action'] ?? '') ?> · <?= admin_h($row['status'] ?? '') ?></div><div class="text-xs text-slate-500 mt-1"><?= admin_h($row['detail'] ?? '') ?></div></div>
<?php endforeach; ?>
<?php if (!$logs): ?><p class="text-sm text-slate-500">No provisioning logs found.</p><?php endif; ?>
</div></div></div>
<?php
$content = ob_get_clean();
$pageTitle = 'Administrator Dashboard';
include __DIR__ . '/_layout.php';
