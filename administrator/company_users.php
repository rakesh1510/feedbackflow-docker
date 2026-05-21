<?php
require_once __DIR__ . '/_bootstrap.php';
$adminUser = admin_require_login();
$companyId = (int)($_GET['id'] ?? 0);
$company = admin_company_by_id($companyId);
$dbInfo = admin_company_db($companyId);
$tenantError = null;
$tenant = null;

if ($company && $dbInfo && (($dbInfo['db_status'] ?? '') === 'active')) {
    try { $tenant = tenant_pdo_for_company($companyId); } catch (Throwable $e) { $tenantError = $e->getMessage(); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tenant) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_user') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = trim($_POST['role'] ?? 'member');
            $hasCompanyId = (bool)admin_safe_row($tenant, "SHOW COLUMNS FROM ff_users LIKE 'company_id'");
            if ($hasCompanyId) {
                $stmt = $tenant->prepare("INSERT INTO ff_users (company_id, name, email, password, role, is_active, email_verified, created_at) VALUES (?, ?, ?, ?, ?, 1, 1, NOW())");
                $stmt->execute([$companyId, $name, $email, password_hash($password, PASSWORD_BCRYPT), $role]);
            } else {
                $stmt = $tenant->prepare("INSERT INTO ff_users (name, email, password, role, is_active, email_verified, created_at) VALUES (?, ?, ?, ?, 1, 1, NOW())");
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), $role]);
            }
            admin_flash('User added.');
            admin_log((int)$adminUser['id'], 'add_company_user', $companyId, null, ['email' => $email]);
        } elseif ($action === 'delete_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $stmt = $tenant->prepare("DELETE FROM ff_users WHERE id = ?");
            $stmt->execute([$userId]);
            admin_flash('User removed.');
            admin_log((int)$adminUser['id'], 'delete_company_user', $companyId, $userId);
        }
    } catch (Throwable $e) {
        admin_flash($e->getMessage(), 'error');
    }
    header('Location: company_users.php?id=' . $companyId);
    exit;
}

$rows = $tenant ? admin_safe_rows($tenant, "SELECT id,name,email,role,is_active,created_at FROM ff_users ORDER BY id DESC") : [];
ob_start();
?>
<?php if (!$company): ?>
<div class="bg-white rounded-2xl border border-slate-200 p-6">Company not found.</div>
<?php elseif ($tenantError): ?>
<div class="bg-white rounded-2xl border border-amber-200 bg-amber-50 text-amber-700 p-6"><?= admin_h($tenantError) ?></div>
<?php else: ?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
<div class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
<div class="px-4 py-3 border-b border-slate-200 font-semibold">Users for <?= admin_h($company['name'] ?? '') ?></div>
<table class="w-full text-sm">
<thead class="bg-slate-50"><tr><th class="text-left px-4 py-3">Name</th><th class="text-left px-4 py-3">Email</th><th class="text-left px-4 py-3">Role</th><th class="text-left px-4 py-3">Active</th><th class="text-left px-4 py-3">Action</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr class="border-t border-slate-100">
<td class="px-4 py-3 font-medium"><?= admin_h($row['name'] ?? '') ?></td>
<td class="px-4 py-3 text-slate-500"><?= admin_h($row['email'] ?? '') ?></td>
<td class="px-4 py-3"><?= admin_h($row['role'] ?? '') ?></td>
<td class="px-4 py-3"><?= !empty($row['is_active']) ? 'Yes' : 'No' ?></td>
<td class="px-4 py-3">
<form method="post" onsubmit="return confirm('Remove this user?');">
<input type="hidden" name="action" value="delete_user">
<input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
<button class="text-rose-600 hover:underline">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No users found.</td></tr><?php endif; ?>
</tbody></table></div>

<div class="bg-white rounded-2xl border border-slate-200 p-6">
<h2 class="text-lg font-semibold text-slate-900 mb-4">Add user</h2>
<form method="post" class="space-y-4">
<input type="hidden" name="action" value="add_user">
<div><label class="block text-sm mb-1">Name</label><input name="name" required class="w-full rounded-xl border border-slate-300 px-4 py-3"></div>
<div><label class="block text-sm mb-1">Email</label><input type="email" name="email" required class="w-full rounded-xl border border-slate-300 px-4 py-3"></div>
<div><label class="block text-sm mb-1">Password</label><input type="password" name="password" required class="w-full rounded-xl border border-slate-300 px-4 py-3"></div>
<div><label class="block text-sm mb-1">Role</label><select name="role" class="w-full rounded-xl border border-slate-300 px-4 py-3"><option>owner</option><option>admin</option><option>manager</option><option selected>member</option><option>viewer</option></select></div>
<button class="w-full rounded-xl bg-indigo-600 text-white py-3 font-semibold">Add User</button>
</form>
</div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$pageTitle = 'Company Users';
include __DIR__ . '/_layout.php';
