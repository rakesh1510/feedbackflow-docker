<?php
/**
 * Accept Invite — Unauthenticated page for invited users to set their password.
 * URL: /accept-invite.php?token=<48-char-hex>
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/db-manager.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/user-management.php';
require_once __DIR__ . '/includes/onboarding.php';


$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
if ($token) {
    $resolvedCompanyId = DBManager::findCompanyIdByInviteToken($token);
    if ($resolvedCompanyId) {
        DB::useTenantForCompany($resolvedCompanyId);
    }
}
// If already logged in, redirect to dashboard
if (Auth::check()) {
    redirect(APP_URL . '/admin/index.php');
}

$token = trim($_GET['token'] ?? '');
$error = '';
$done  = false;

// Validate token early so we can show a useful error before the form
$invitedUser = null;
if ($token) {
    try {
        $invitedUser = DB::fetch(
            "SELECT u.*, c.name AS company_name
             FROM ff_users u
             LEFT JOIN ff_companies c ON c.id = u.company_id
             WHERE u.invite_token = ?",
            [$token]
        );
    } catch (\Throwable $e) {
        $invitedUser = DB::fetch(
            "SELECT * FROM ff_users WHERE invite_token = ?",
            [$token]
        );
    }

    if ($invitedUser) {
        try {
            if ($invitedUser['invite_expires'] && strtotime($invitedUser['invite_expires']) < time()) {
                $invitedUser = null;
                $error = 'This invite link has expired. Ask your admin to resend the invitation.';
            }
        } catch (\Throwable $e) { }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    $name     = trim($_POST['name']             ?? '');
    $password = $_POST['password']              ?? '';
    $confirm  = $_POST['password_confirm']      ?? '';

    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $accepted = UserManagement::acceptInvite($token, $name, $password, $error);
        if ($accepted) {
            // Log the user in
            Auth::start();
            session_regenerate_id(true);
            $_SESSION['user_id']   = $accepted['id'];
            $_SESSION['user_role'] = $accepted['role'];
            $_SESSION['company_id'] = $accepted['company_id'] ?? ($resolvedCompanyId ?? null);
            DB::query("UPDATE ff_users SET last_login = NOW() WHERE id = ?", [$accepted['id']]);
            // Flow 2 audit log — invited user, no plan/company creation
            OnboardingService::log($accepted['id'], $accepted['company_id'] ?? null,
                'invite_accepted', ['role' => $accepted['role']], 'invited_user');
            redirect(APP_URL . '/admin/index.php?welcome=1');
        }
    }
}

$pageTitle = 'Accept Invitation – ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    input:focus { outline: none; }
    .card { animation: fadeUp .4s ease both; }
    @keyframes fadeUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
    .strength-bar div { transition: width .3s ease; }
  </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

  <div class="card w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-6">
      <div class="inline-flex items-center gap-2 text-white text-2xl font-bold">
        <i class="fas fa-comments-alt"></i> <?= h(APP_NAME) ?>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">

      <!-- Token invalid / expired -->
      <?php if (!$token || (!$invitedUser && !$done)): ?>
        <div class="p-8 text-center">
          <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-link-slash text-2xl text-red-500"></i>
          </div>
          <h2 class="text-xl font-bold text-gray-900 mb-2">Invalid Invite Link</h2>
          <p class="text-gray-500 text-sm mb-6">
            <?= $error ?: 'This invite link is invalid or has already been used.' ?>
          </p>
          <a href="<?= APP_URL ?>" class="text-indigo-600 text-sm font-medium hover:underline">
            ← Back to <?= h(APP_NAME) ?>
          </a>
        </div>

      <?php else: ?>
        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-8 py-6 text-white text-center">
          <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-user-plus text-2xl"></i>
          </div>
          <h2 class="text-xl font-bold">
            <?php if ($invitedUser['company_name'] ?? ''): ?>
              Join <?= h($invitedUser['company_name']) ?>
            <?php else: ?>
              Accept Your Invitation
            <?php endif; ?>
          </h2>
          <p class="text-indigo-200 text-sm mt-1">
            Set your password to activate your <?= h(APP_NAME) ?> account
          </p>
        </div>

        <!-- Form -->
        <div class="p-8">
          <?php if ($error): ?>
            <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
              <i class="fas fa-exclamation-circle mr-2"></i><?= h($error) ?>
            </div>
          <?php endif; ?>

          <div class="mb-5 px-4 py-3 bg-indigo-50 rounded-xl text-sm text-indigo-700">
            <i class="fas fa-envelope mr-2"></i>
            Joining as <strong><?= h($invitedUser['email']) ?></strong>
            as <strong><?= ucfirst($invitedUser['role'] ?? 'member') ?></strong>
          </div>

          <form method="POST" class="space-y-5" id="inviteForm">
            <input type="hidden" name="token" value="<?= h($token) ?>">

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name</label>
              <div class="relative">
                <i class="fas fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="name" required
                       value="<?= h($_POST['name'] ?? ucfirst(explode('@', $invitedUser['email'])[0])) ?>"
                       placeholder="Your full name"
                       class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
              <div class="relative">
                <i class="fas fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="password" name="password" id="password" required minlength="8"
                       placeholder="At least 8 characters"
                       oninput="updateStrength(this.value)"
                       class="w-full border border-gray-200 rounded-xl pl-10 pr-10 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <button type="button" onclick="togglePwd('password',this)"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
              <!-- Strength bar -->
              <div class="strength-bar mt-2 flex gap-1 h-1">
                <div id="s1" class="flex-1 rounded-full bg-gray-200"></div>
                <div id="s2" class="flex-1 rounded-full bg-gray-200"></div>
                <div id="s3" class="flex-1 rounded-full bg-gray-200"></div>
                <div id="s4" class="flex-1 rounded-full bg-gray-200"></div>
              </div>
              <p id="strengthLabel" class="text-xs text-gray-400 mt-1"></p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
              <div class="relative">
                <i class="fas fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="password" name="password_confirm" id="password_confirm" required minlength="8"
                       placeholder="Repeat password"
                       class="w-full border border-gray-200 rounded-xl pl-10 pr-10 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <button type="button" onclick="togglePwd('password_confirm',this)"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>

            <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl transition flex items-center justify-center gap-2">
              <i class="fas fa-check-circle"></i> Activate Account &amp; Sign In
            </button>
          </form>

          <p class="text-center text-xs text-gray-400 mt-5">
            By accepting, you agree to our Terms of Service and Privacy Policy.
          </p>
        </div>
      <?php endif; ?>
    </div>

    <p class="text-center text-white/60 text-xs mt-4">
      Already have an account? <a href="<?= APP_URL ?>" class="text-white underline">Sign in</a>
    </p>
  </div>

  <script>
  function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
  }

  function updateStrength(val) {
    let score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const colors  = ['bg-red-400','bg-amber-400','bg-yellow-400','bg-green-500'];
    const labels  = ['Weak','Fair','Good','Strong'];
    const bars    = [document.getElementById('s1'),document.getElementById('s2'),
                     document.getElementById('s3'),document.getElementById('s4')];

    bars.forEach((b, i) => {
      b.className = 'flex-1 rounded-full transition-all ' + (i < score ? colors[score-1] : 'bg-gray-200');
    });
    document.getElementById('strengthLabel').textContent = val.length ? labels[score-1] ?? '' : '';
  }

  // Prevent double-submit
  document.getElementById('inviteForm')?.addEventListener('submit', function() {
    this.querySelector('button[type=submit]').disabled = true;
  });
  </script>
</body>
</html>