<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/billing.php';
require_once __DIR__ . '/includes/onboarding.php';

Auth::start();

$action = $_GET['action'] ?? $_GET['page'] ?? '';
$errors = [];

// Handle logout
if ($action === 'logout') {
    Auth::logout();
    redirect(APP_URL . '/index.php');
}

// If already logged in and not forcing a specific action, check onboarding state
if (Auth::check() && !in_array($action, ['login', 'register', 'forgot', 'logout'], true)) {
    $u = Auth::user();
    try {
        $company = BillingService::getCompany($u['id']);
        if ($company && !(int)($company['onboarding_complete'] ?? 1)) {
            // Mid-onboarding: route to the right step
            $plan = $company['plan'] ?? '';
            if (!$plan) {
                redirect(APP_URL . '/onboarding/select-plan.php');
            } else {
                redirect(APP_URL . '/onboarding/setup.php');
            }
        }
    } catch (\Throwable $e) { }
    redirect(APP_URL . '/admin/index.php');
}

// ── LOGIN ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    if (!verifyCsrf()) {
        $errors[] = 'Security check failed. Please try again.';
    } elseif (!rateLimit('login', 5, 300)) {
        $errors[] = 'Too many login attempts. Wait 5 minutes.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (Auth::login($email, $password)) {
            $u = Auth::user();
            // Flow 2 (invited user) — invited user is already linked to a company
            // and onboarding_complete is set to 1 via accept-invite.php
            try {
                $company = BillingService::getCompany($u['id']);
                if ($company && !(int)($company['onboarding_complete'] ?? 1)) {
                    $plan = $company['plan'] ?? '';
                    if (!$plan) {
                        redirect(APP_URL . '/onboarding/select-plan.php');
                    } else {
                        redirect(APP_URL . '/onboarding/setup.php');
                    }
                }
            } catch (\Throwable $e) { }
            redirect(APP_URL . '/admin/index.php');
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}

// ── REGISTER (Flow 1: New Company Signup) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register') {
    if (!verifyCsrf()) {
        $errors[] = 'Security check failed.';
    } else {
        $name        = sanitize($_POST['name']         ?? '');
        $email       = trim($_POST['email']            ?? '');
        $password    = $_POST['password']              ?? '';
        $companyName = sanitize($_POST['company_name'] ?? '');

        if (empty($name) || empty($email) || empty($password) || empty($companyName)) {
            $errors[] = 'All fields are required.';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif (strlen($companyName) < 2) {
            $errors[] = 'Company name must be at least 2 characters.';
        } else {
            // Duplicate company check
            $dup = OnboardingService::checkDuplicate($companyName, $email);
            if ($dup) {
                $errors[] = 'A company named "' . h($dup['name']) . '" already exists. '
                          . 'If this is your organisation, ask your admin to invite you instead.';
            } else {
                // Email uniqueness must be checked across tenant databases, not in feedbackflow_master.
                $emailExists = false;
                try {
                    $master = DBManager::master();
                    $stmt = $master->query("SELECT company_id FROM ff_company_databases WHERE db_status = 'active'");
                    $companyRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    foreach ($companyRows as $companyRow) {
                        try {
                            $tenant = DBManager::forCompany((int)$companyRow['company_id']);
                            $check = $tenant->prepare("SELECT id FROM ff_users WHERE email = ? LIMIT 1");
                            $check->execute([strtolower($email)]);
                            if ($check->fetch(PDO::FETCH_ASSOC)) {
                                $emailExists = true;
                                break;
                            }
                        } catch (Throwable $e) {
                        }
                    }
                } catch (Throwable $e) {
                }

                if ($emailExists) {
                    $errors[] = 'That email is already registered. <a href="?action=login" class="underline">Sign in instead</a>.';
                } else {
                    try {
                        $provisioned = OnboardingService::createCompany($companyName, strtolower($email), [
                            'name'     => $name,
                            'email'    => strtolower($email),
                            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                        ]);

                        $companyId    = (int)($provisioned['company_id'] ?? 0);
                        $tenantUserId = (int)($provisioned['tenant_user_id'] ?? 0);

                        if ($companyId <= 0 || $tenantUserId <= 0) {
                            throw new RuntimeException('Company provisioning failed.');
                        }

                        // Log the tenant owner in immediately.
                        session_regenerate_id(true);
                        $_SESSION['user_id']    = $tenantUserId;
                        $_SESSION['user_role']  = 'owner';
                        $_SESSION['company_id'] = $companyId;

                        OnboardingService::log($tenantUserId, $companyId, 'register', [
                            'name'    => $name,
                            'company' => $companyName,
                            'email'   => strtolower($email),
                        ], 'company_signup');

                        OnboardingService::start($tenantUserId, $companyId);

                        redirect(APP_URL . '/onboarding/select-plan.php');
                    } catch (Throwable $e) {
                        $errors[] = 'Could not create account. ' . (defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Please try again.');
                    }
                }
            }
        }
    }
}

// ── FORGOT PASSWORD ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'forgot') {
    if (!verifyCsrf()) {
        $errors[] = 'Security check failed.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!isValidEmail($email)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            // Always show success (avoid user enumeration)
            setFlash('success', 'If that email is registered, a reset link has been sent.');
            redirect(APP_URL . '/index.php?action=login');
        }
    }
}

$pageTitle   = APP_NAME . ' – Sign in';
$currentPage = $action ?: 'login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  * { font-family:'Inter',sans-serif; }
  .gradient-text { background:linear-gradient(135deg,#6366f1,#8b5cf6); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
  .strength-bar { height:3px; border-radius:2px; transition:width .3s, background .3s; }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50 flex items-center justify-center p-4">
<div class="w-full max-w-md">

  <!-- Logo -->
  <div class="text-center mb-8">
    <a href="<?= APP_URL ?>" class="inline-flex items-center gap-3">
      <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center">
        <i class="fas fa-comments text-white"></i>
      </div>
      <span class="text-2xl font-bold text-gray-900"><?= h(APP_NAME) ?></span>
    </a>
  </div>

  <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-8">

    <?php if ($currentPage === 'register'): ?>
      <!-- ── REGISTER ──────────────────────────────────────────────────── -->
      <h1 class="text-2xl font-bold text-gray-900 mb-1">Create your account</h1>
      <p class="text-gray-500 text-sm mb-6">
        Start collecting product feedback today.
        <br><span class="text-xs text-indigo-600 font-medium">14-day free trial · No credit card needed</span>
      </p>

      <?php foreach ($errors as $e): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm">
          <i class="fas fa-exclamation-circle mr-1"></i><?= $e ?>
        </div>
      <?php endforeach; ?>

      <form method="POST" action="?action=register" class="space-y-4" id="registerForm">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">

        <!-- Full Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
          <input type="text" name="name" required value="<?= h($_POST['name'] ?? '') ?>"
                 placeholder="Jane Smith"
                 class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
        </div>

        <!-- Work Email -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Work Email</label>
          <input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"
                 placeholder="you@company.com"
                 class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
        </div>

        <!-- Company Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Company / Organisation</label>
          <div class="relative">
            <i class="fas fa-building absolute left-3.5 top-3.5 text-gray-400 text-sm"></i>
            <input type="text" name="company_name" required value="<?= h($_POST['company_name'] ?? '') ?>"
                   placeholder="Acme Corp" maxlength="150"
                   class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
          </div>
          <p class="text-xs text-gray-400 mt-1"><i class="fas fa-info-circle mr-0.5"></i> Your workspace will be created under this name.</p>
        </div>

        <!-- Password -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <div class="relative">
            <input type="password" name="password" id="password" required minlength="8"
                   placeholder="Min. 8 characters" oninput="checkStrength(this.value)"
                   class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition pr-10">
            <button type="button" onclick="togglePw('password',this)"
                    class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600 text-sm">
              <i class="fas fa-eye" id="pw-eye"></i>
            </button>
          </div>
          <!-- Strength bar -->
          <div class="mt-2 h-1 bg-gray-100 rounded-full overflow-hidden">
            <div id="strengthBar" class="strength-bar" style="width:0;background:#ef4444"></div>
          </div>
          <p id="strengthLabel" class="text-xs text-gray-400 mt-1"></p>
        </div>

        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-xl transition flex items-center justify-center gap-2">
          Create Account &amp; Continue <i class="fas fa-arrow-right"></i>
        </button>
      </form>

      <p class="text-center text-xs text-gray-400 mt-4">
        By creating an account you agree to our
        <a href="#" class="underline">Terms</a> and <a href="#" class="underline">Privacy Policy</a>.
      </p>
      <p class="text-center text-sm text-gray-500 mt-4">
        Already have an account? <a href="?action=login" class="text-indigo-600 font-medium hover:underline">Sign in</a>
      </p>
      <p class="text-center text-sm text-gray-500 mt-2">
        Joining a team? <a href="<?= APP_URL ?>/accept-invite.php" class="text-indigo-600 font-medium hover:underline">Use your invitation link</a>
      </p>

    <?php elseif ($currentPage === 'forgot'): ?>
      <!-- ── FORGOT PASSWORD ────────────────────────────────────────────── -->
      <h1 class="text-2xl font-bold text-gray-900 mb-1">Reset your password</h1>
      <p class="text-gray-500 text-sm mb-6">Enter your email and we'll send you a reset link.</p>

      <?php foreach ($errors as $e): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm">
          <i class="fas fa-exclamation-circle mr-1"></i><?= h($e) ?>
        </div>
      <?php endforeach; ?>

      <form method="POST" action="?action=forgot" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
          <input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"
                 placeholder="you@company.com"
                 class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition" autofocus>
        </div>
        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-xl transition">
          Send Reset Link
        </button>
      </form>
      <p class="text-center text-sm text-gray-500 mt-6">
        <a href="?action=login" class="text-indigo-600 font-medium hover:underline"><i class="fas fa-arrow-left mr-1"></i> Back to Sign In</a>
      </p>

    <?php else: ?>
      <!-- ── LOGIN ─────────────────────────────────────────────────────── -->
      <h1 class="text-2xl font-bold text-gray-900 mb-1">Welcome back</h1>
      <p class="text-gray-500 text-sm mb-6">Sign in to your <?= h(APP_NAME) ?> workspace</p>

      <?php foreach ($errors as $e): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm">
          <i class="fas fa-exclamation-circle mr-1"></i><?= $e ?>
        </div>
      <?php endforeach; ?>

      <?php $flashes = getFlash(); foreach ($flashes as $f): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm">
          <i class="fas fa-check-circle mr-1"></i><?= h($f['msg']) ?>
        </div>
      <?php endforeach; ?>

      <form method="POST" action="?action=login" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"
                 placeholder="you@company.com" autofocus
                 class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
        </div>

        <div>
          <div class="flex justify-between mb-1">
            <label class="block text-sm font-medium text-gray-700">Password</label>
            <a href="?action=forgot" class="text-xs text-indigo-600 hover:underline">Forgot password?</a>
          </div>
          <div class="relative">
            <input type="password" name="password" id="loginPw" required
                   placeholder="Your password"
                   class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition pr-10">
            <button type="button" onclick="togglePw('loginPw',this)"
                    class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600 text-sm">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input type="checkbox" name="remember" id="remember"
                 class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
          <label for="remember" class="text-sm text-gray-600">Remember me for 30 days</label>
        </div>

        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-xl transition flex items-center justify-center gap-2">
          Sign In <i class="fas fa-arrow-right"></i>
        </button>
      </form>

      <p class="text-center text-sm text-gray-500 mt-6">
        Don't have an account? <a href="?action=register" class="text-indigo-600 font-medium hover:underline">Sign up free</a>
      </p>
      <p class="text-center text-sm text-gray-500 mt-2">
        Have an invitation? <a href="<?= APP_URL ?>/accept-invite.php" class="text-indigo-600 font-medium hover:underline">Accept invite</a>
      </p>
    <?php endif; ?>

  </div><!-- /card -->

  <p class="text-center text-xs text-gray-400 mt-6">
    &copy; <?= date('Y') ?> <?= h(APP_NAME) ?> &middot; GDPR Compliant &middot; SSL Secured
  </p>
</div><!-- /wrapper -->

<script>
// Password show/hide
function togglePw(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}

// Password strength
function checkStrength(val) {
  const bar   = document.getElementById('strengthBar');
  const label = document.getElementById('strengthLabel');
  if (!bar) return;
  let score = 0;
  if (val.length >= 8)  score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const pct   = Math.min(score / 5, 1) * 100;
  const color = pct < 40 ? '#ef4444' : pct < 70 ? '#f59e0b' : '#10b981';
  const text  = pct < 40 ? 'Weak'    : pct < 70 ? 'Fair'    : pct < 90 ? 'Strong' : 'Very strong';
  bar.style.width      = pct + '%';
  bar.style.background = color;
  label.textContent    = val.length > 0 ? text : '';
  label.style.color    = color;
}
</script>
</body>
</html>
