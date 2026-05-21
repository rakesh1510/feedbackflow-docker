<?php $flash = admin_flash(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= admin_h($pageTitle ?? 'Administrator') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
  <div class="min-h-screen flex">
    <aside class="w-72 bg-slate-900 text-slate-100 p-6">
      <div class="mb-8">
        <div class="text-xs uppercase tracking-widest text-slate-400">FeedbackFlow</div>
        <div class="text-2xl font-bold">Administrator</div>
        <div class="text-sm text-slate-400 mt-1">Master DB control panel</div>
      </div>
      <nav class="space-y-2 text-sm">
        <a class="block px-4 py-3 rounded-xl hover:bg-slate-800" href="dashboard.php">Dashboard</a>
        <a class="block px-4 py-3 rounded-xl hover:bg-slate-800" href="companies.php">Companies</a>
        <a class="block px-4 py-3 rounded-xl hover:bg-slate-800" href="users.php">All Company Users</a>
        <a class="block px-4 py-3 rounded-xl hover:bg-slate-800" href="projects.php">All Projects</a>
        <a class="block px-4 py-3 rounded-xl hover:bg-slate-800" href="feedback.php">All Feedback</a>
        <a class="block px-4 py-3 rounded-xl hover:bg-slate-800" href="provisioning_logs.php">Provisioning Logs</a>
        <a class="block px-4 py-3 rounded-xl hover:bg-slate-800" href="settings.php">Settings</a>
        <a class="block px-4 py-3 rounded-xl text-rose-300 hover:bg-slate-800" href="logout.php">Logout</a>
      </nav>
    </aside>
    <main class="flex-1 p-8">
      <header class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-3xl font-bold text-slate-900"><?= admin_h($pageTitle ?? 'Administrator') ?></h1>
          <p class="text-sm text-slate-500 mt-1">Master DB: <?= defined('MASTER_DB_NAME') ? admin_h(MASTER_DB_NAME) : 'not set' ?></p>
        </div>
        <div class="text-right">
          <div class="text-sm font-medium text-slate-900"><?= admin_h($adminUser['name'] ?? '') ?></div>
          <div class="text-xs text-slate-500"><?= admin_h($adminUser['email'] ?? '') ?></div>
        </div>
      </header>
      <?php if ($flash): ?>
        <div class="mb-6 rounded-xl border px-4 py-3 <?= ($flash['type'] ?? 'success') === 'error' ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700' ?>">
          <?= admin_h($flash['message'] ?? '') ?>
        </div>
      <?php endif; ?>
      <?= $content ?>
    </main>
  </div>
</body>
</html>
