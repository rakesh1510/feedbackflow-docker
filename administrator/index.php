<?php
require_once __DIR__ . '/_bootstrap.php';
if (admin_user()) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = admin_pdo()->prepare("SELECT * FROM ff_super_admins WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['super_admin_id'] = $admin['id'];
        admin_log((int)$admin['id'], 'login');
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Administrator Login</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-6">
<div class="w-full max-w-md bg-white rounded-3xl shadow-xl p-8">
<div class="mb-8 text-center"><div class="mx-auto w-16 h-16 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-2xl font-bold">F</div><h1 class="mt-4 text-2xl font-bold text-slate-900">FeedbackFlow Administrator</h1><p class="text-sm text-slate-500 mt-1">Super admin login</p></div>
<?php if ($error): ?><div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm"><?= admin_h($error) ?></div><?php endif; ?>
<form method="post" class="space-y-4">
<div><label class="block text-sm font-medium text-slate-700 mb-1">Email</label><input type="email" name="email" required class="w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="admin@rakesh"></div>
<div><label class="block text-sm font-medium text-slate-700 mb-1">Password</label><input type="password" name="password" required class="w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="admin"></div>
<button class="w-full rounded-xl bg-indigo-600 text-white py-3 font-semibold hover:bg-indigo-700">Sign in</button>
</form></div></body></html>
