<?php
// FeedbackFlow Installer
// DELETE this file after installation!

define('INSTALL_MODE', true);
require_once __DIR__ . '/config.php';

$step = $_GET['step'] ?? 1;
$errors = [];
$success = '';

if ($step == 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Run installation
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
        // Run SQL schema
        $sql = file_get_contents(__DIR__ . '/install.sql');
        // Split by semicolons and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) $pdo->exec($stmt);
        }
        
        // Create admin user
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($name) || empty($email) || empty($password)) {
            $errors[] = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("INSERT INTO ff_users (name, email, password, role, is_active, email_verified) VALUES (?, ?, ?, 'owner', 1, 1)")
                ->execute([$name, $email, $hash]);
            $userId = $pdo->lastInsertId();
            
            // Create a demo project
            $widgetKey = bin2hex(random_bytes(16));
            $pdo->prepare("INSERT INTO ff_projects (name, slug, description, owner_id, widget_key) VALUES (?, ?, ?, ?, ?)")
                ->execute(['My First Project', 'my-first-project', 'Your first FeedbackFlow project. Start collecting feedback!', $userId, $widgetKey]);
            $projectId = $pdo->lastInsertId();
            
            // Insert demo categories
            $categories = [
                ['name' => 'Bug Report', 'slug' => 'bug-report', 'color' => '#ef4444', 'icon' => 'bug'],
                ['name' => 'Feature Request', 'slug' => 'feature-request', 'color' => '#6366f1', 'icon' => 'lightbulb'],
                ['name' => 'Improvement', 'slug' => 'improvement', 'color' => '#10b981', 'icon' => 'arrow-up'],
                ['name' => 'Question', 'slug' => 'question', 'color' => '#f59e0b', 'icon' => 'question-circle'],
                ['name' => 'Other', 'slug' => 'other', 'color' => '#6b7280', 'icon' => 'tag'],
            ];
            foreach ($categories as $i => $cat) {
                $pdo->prepare("INSERT INTO ff_categories (project_id, name, slug, color, icon, sort_order) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$projectId, $cat['name'], $cat['slug'], $cat['color'], $cat['icon'], $i]);
            }
            
            // Insert demo feedback
            $demoFeedback = [
                ['Dark mode support', 'It would be great to have a dark mode option for the interface. Many users prefer it at night.', 'feature-request', 'planned', 'high', 12],
                ['Export to CSV', 'Please add the ability to export all feedback to CSV or Excel format.', 'feature-request', 'under_review', 'medium', 8],
                ['Dashboard loads slowly', 'The dashboard takes about 5 seconds to load on my connection. Can you optimize it?', 'bug-report', 'in_progress', 'high', 5],
                ['Mobile app needed', 'It would be amazing to have a native mobile app for iOS and Android.', 'feature-request', 'new', 'medium', 21],
                ['Email notifications not working', 'I stopped receiving email notifications 3 days ago. Using Gmail.', 'bug-report', 'done', 'critical', 3],
            ];
            foreach ($demoFeedback as $fb) {
                [$title, $desc, $catSlug, $status, $priority, $votes] = $fb;
                $catRow = $pdo->prepare("SELECT id FROM ff_categories WHERE project_id = ? AND slug = ?");
                $catRow->execute([$projectId, $catSlug]);
                $catId = $catRow->fetchColumn();
                $pdo->prepare("INSERT INTO ff_feedback (project_id, category_id, title, description, status, priority, submitter_name, submitter_email, vote_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$projectId, $catId, $title, $desc, $status, $priority, 'Demo User', 'demo@example.com', $votes]);
            }
            
            $success = 'Installation successful!';
            $step = 3;
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FeedbackFlow Installer</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-purple-50 min-h-screen flex items-center justify-center p-4">
<div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8">
  <div class="text-center mb-8">
    <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-600 rounded-2xl mb-4">
      <i class="fas fa-comments text-white text-2xl"></i>
    </div>
    <h1 class="text-2xl font-bold text-gray-900">FeedbackFlow</h1>
    <p class="text-gray-500 mt-1">Installation Wizard</p>
  </div>

  <!-- Steps indicator -->
  <div class="flex items-center justify-center gap-2 mb-8">
    <?php for ($i = 1; $i <= 3; $i++): ?>
      <div class="flex items-center <?= $i < 3 ? 'gap-2' : '' ?>">
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold
          <?= $step >= $i ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-400' ?>">
          <?= $step > $i ? '<i class="fas fa-check text-xs"></i>' : $i ?>
        </div>
        <?php if ($i < 3): ?>
          <div class="w-12 h-0.5 <?= $step > $i ? 'bg-indigo-600' : 'bg-gray-200' ?>"></div>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
      <?php foreach ($errors as $e): ?>
        <p class="text-red-700 text-sm"><i class="fas fa-exclamation-circle mr-1"></i> <?= h($e) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($step == 1): ?>
    <h2 class="text-lg font-semibold text-gray-900 mb-2">System Requirements</h2>
    <p class="text-gray-500 text-sm mb-6">Checking your server compatibility...</p>
    <div class="space-y-3">
      <?php
      $checks = [
          'PHP 8.0+' => version_compare(PHP_VERSION, '8.0', '>='),
          'PDO MySQL' => extension_loaded('pdo_mysql'),
          'cURL' => extension_loaded('curl'),
          'JSON' => extension_loaded('json'),
          'Uploads writable' => is_writable(__DIR__ . '/uploads') || mkdir(__DIR__ . '/uploads', 0755, true),
          'Database connection' => (function() {
              try { new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS); return true; }
              catch (Exception $e) { return false; }
          })(),
      ];
      foreach ($checks as $label => $ok): ?>
        <div class="flex items-center justify-between py-2 border-b border-gray-100">
          <span class="text-sm text-gray-700"><?= $label ?></span>
          <?php if ($ok): ?>
            <span class="text-green-600 text-sm"><i class="fas fa-check-circle"></i> OK</span>
          <?php else: ?>
            <span class="text-red-600 text-sm"><i class="fas fa-times-circle"></i> Failed</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php $allOk = !in_array(false, $checks); ?>
    <a href="?step=2" class="mt-6 w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl
       <?= $allOk ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none' ?> font-semibold transition">
      Continue <i class="fas fa-arrow-right"></i>
    </a>

  <?php elseif ($step == 2): ?>
    <h2 class="text-lg font-semibold text-gray-900 mb-2">Create Admin Account</h2>
    <p class="text-gray-500 text-sm mb-6">Set up your administrator credentials.</p>
    <form method="POST" action="?step=2" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
        <input type="text" name="name" required value="<?= h($_POST['name'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
        <input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input type="password" name="password" required minlength="8"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none">
        <p class="text-xs text-gray-400 mt-1">Minimum 8 characters</p>
      </div>
      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-xl transition flex items-center justify-center gap-2">
        Install FeedbackFlow <i class="fas fa-rocket"></i>
      </button>
    </form>

  <?php else: ?>
    <div class="text-center">
      <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-check-circle text-green-600 text-3xl"></i>
      </div>
      <h2 class="text-xl font-bold text-gray-900 mb-2">Installation Complete!</h2>
      <p class="text-gray-500 text-sm mb-6">
        FeedbackFlow is ready to use. <strong class="text-red-600">Delete install.php now</strong> for security.
      </p>
      <a href="<?= APP_URL ?>/index.php" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-xl transition">
        <i class="fas fa-arrow-right"></i> Go to Dashboard
      </a>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
