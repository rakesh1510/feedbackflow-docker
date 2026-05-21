<?php
require_once __DIR__ . '/_bootstrap.php';
$adminUser = admin_require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    if (!password_verify($current, $adminUser['password'])) {
        admin_flash('Current password is incorrect.', 'error');
    } elseif (strlen($new) < 4) {
        admin_flash('New password must be at least 4 characters.', 'error');
    } else {
        $stmt = admin_pdo()->prepare("UPDATE ff_super_admins SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($new, PASSWORD_BCRYPT), $adminUser['id']]);
        admin_flash('Password updated.');
        admin_log((int)$adminUser['id'], 'change_password');
    }
    header('Location: settings.php');
    exit;
}
ob_start();
?>
<div class="bg-white rounded-2xl border border-slate-200 p-6 max-w-xl">
<h2 class="text-lg font-semibold text-slate-900 mb-4">Change password</h2>
<form method="post" class="space-y-4">
<div><label class="block text-sm mb-1">Current password</label><input type="password" name="current_password" class="w-full rounded-xl border border-slate-300 px-4 py-3" required></div>
<div><label class="block text-sm mb-1">New password</label><input type="password" name="new_password" class="w-full rounded-xl border border-slate-300 px-4 py-3" required></div>
<button class="rounded-xl bg-indigo-600 text-white px-5 py-3 font-semibold">Save</button>
</form></div>
<?php
$content = ob_get_clean();
$pageTitle = 'Settings';
include __DIR__ . '/_layout.php';
