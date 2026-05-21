<?php
/**
 * Onboarding Step 3: Create First Project + Choose Channels
 * Only accessible to new company owners who have already selected a plan.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/billing.php';
require_once dirname(__DIR__) . '/includes/onboarding.php';

$currentUser = Auth::require();

// Resolve company
$company = null;
try { $company = BillingService::getCompany($currentUser['id']); } catch (\Throwable $e) { }

if (!$company) { redirect(APP_URL . '/index.php?action=register'); }

$companyId   = (int)$company['id'];
$companyName = $company['name'];
$planSlug    = $company['plan'] ?? '';

// Must have a plan before reaching this step
if (!$planSlug) { redirect(APP_URL . '/onboarding/select-plan.php'); }

// Already fully onboarded → dashboard
if ((int)($company['onboarding_complete'] ?? 0)) { redirect(APP_URL . '/admin/index.php'); }

// ── Handle form submission ────────────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { $errors[] = 'Security check failed. Please try again.'; }
    else {
        $projectName = trim(sanitize($_POST['project_name'] ?? ''));
        $projectDesc = trim(sanitize($_POST['project_desc'] ?? ''));
        $channels    = $_POST['channels'] ?? [];

        if (empty($projectName)) {
            $errors[] = 'Please enter a project name.';
        } else {
            $projectId = OnboardingService::createFirstProject(
                $currentUser['id'],
                $projectName,
                $projectDesc,
                (array)$channels
            );

            if ($projectId) {
                OnboardingService::log($currentUser['id'], $companyId, 'create_first_project', [
                    'project_id'   => $projectId,
                    'project_name' => $projectName,
                    'channels'     => $channels,
                ], 'company_signup');

                OnboardingService::complete($companyId, $currentUser['id']);

                redirect(APP_URL . '/admin/index.php?welcome=1&project=' . $projectId);
            } else {
                $errors[] = 'Failed to create project. Please try again.';
            }
        }
    }
}

$planLabel = ucfirst($planSlug);
$pageTitle = 'Set Up Your First Project – ' . APP_NAME;

$allChannels = [
    'widget'    => ['icon' => 'fa-window-maximize', 'label' => 'Website Widget', 'desc' => 'Embed a feedback button on your site'],
    'email'     => ['icon' => 'fa-envelope',         'label' => 'Email',          'desc' => 'Collect feedback via email campaigns'],
    'qr_code'   => ['icon' => 'fa-qrcode',           'label' => 'QR Code',        'desc' => 'Print QR codes for physical locations'],
    'whatsapp'  => ['icon' => 'fa-whatsapp',          'label' => 'WhatsApp',       'desc' => 'Collect feedback via WhatsApp messages'],
    'sms'       => ['icon' => 'fa-comment-sms',       'label' => 'SMS',            'desc' => 'Collect feedback via text messages'],
    'in_app'    => ['icon' => 'fa-mobile-alt',        'label' => 'In-App',         'desc' => 'Feedback forms inside your mobile app'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { font-family: 'Inter', sans-serif; }
  .gradient-text { background:linear-gradient(135deg,#6366f1,#8b5cf6,#a855f7); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
  .channel-card { cursor:pointer; transition: all .15s ease; }
  .channel-card:hover { transform:translateY(-2px); }
  .channel-card input:checked ~ .card-inner { border-color:#6366f1; background:#f5f3ff; }
  .channel-card input:checked ~ .card-inner .check-icon { display:flex; }
  .check-icon { display:none; }
  .step-active { background:#6366f1; color:#fff; }
  .step-done   { background:#d1fae5; color:#065f46; }
  .step-todo   { background:#f3f4f6; color:#9ca3af; }
</style>
</head>
<body class="bg-gray-50 antialiased">

<!-- Nav -->
<nav class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-gray-100">
  <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
    <div class="flex items-center gap-2.5">
      <div class="w-8 h-8 rounded-xl flex items-center justify-center text-white text-sm font-bold"
           style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
        <i class="fas fa-comments"></i>
      </div>
      <span class="font-bold text-gray-900 text-lg"><?= h(APP_NAME) ?></span>
    </div>
    <div class="text-sm text-gray-500">
      Setting up <strong class="text-gray-900"><?= h($companyName) ?></strong> · <span class="text-indigo-600 font-medium"><?= $planLabel ?> plan</span>
    </div>
    <a href="<?= APP_URL ?>/index.php?action=logout" class="text-xs text-gray-400 hover:text-gray-600">Sign out</a>
  </div>
</nav>

<!-- Progress -->
<div class="bg-white border-b border-gray-100 py-4">
  <div class="max-w-2xl mx-auto px-6 flex items-center gap-3">
    <div class="flex items-center gap-2">
      <div class="step-done w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"><i class="fas fa-check text-xs"></i></div>
      <span class="text-sm font-medium text-emerald-700">Account</span>
    </div>
    <div class="flex-1 h-px bg-emerald-200"></div>
    <div class="flex items-center gap-2">
      <div class="step-done w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"><i class="fas fa-check text-xs"></i></div>
      <span class="text-sm font-medium text-emerald-700">Plan</span>
    </div>
    <div class="flex-1 h-px bg-indigo-200"></div>
    <div class="flex items-center gap-2">
      <div class="step-active w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold">3</div>
      <span class="text-sm font-bold text-indigo-700">First Project</span>
    </div>
  </div>
</div>

<div class="max-w-2xl mx-auto px-6 py-12">

  <!-- Header -->
  <div class="text-center mb-10">
    <div class="text-5xl mb-4">🎯</div>
    <h1 class="text-3xl font-black text-gray-900 mb-2">
      Create your <span class="gradient-text">first project</span>
    </h1>
    <p class="text-gray-500 text-base">A project is where your feedback, roadmap, and campaigns live. You can always add more projects later.</p>
  </div>

  <!-- Errors -->
  <?php if ($errors): ?>
    <?php foreach ($errors as $e): ?>
      <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
        <i class="fas fa-exclamation-circle mr-2"></i><?= h($e) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="POST" id="setupForm" class="space-y-7">
    <input type="hidden" name="_csrf" value="<?= csrf() ?>">

    <!-- Project Name -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="font-bold text-gray-900 mb-5 flex items-center gap-2">
        <span class="w-6 h-6 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-xs font-bold">1</span>
        Name your project
      </h2>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Project Name <span class="text-red-500">*</span></label>
          <input type="text" name="project_name" required maxlength="150"
                 value="<?= h($_POST['project_name'] ?? '') ?>"
                 placeholder="e.g. My Product, Customer Portal, Mobile App…"
                 oninput="updateSlugPreview(this.value)"
                 class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
          <p class="text-xs text-gray-400 mt-1.5">
            Public URL: <span class="font-mono text-indigo-600" id="slugPreview"><?= APP_URL ?>/feedback/<em>your-project</em></span>
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Short Description <span class="text-gray-400 font-normal">(optional)</span></label>
          <textarea name="project_desc" rows="2" maxlength="500"
                    placeholder="What is this project about?"
                    class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition resize-none"><?= h($_POST['project_desc'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Channels -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="font-bold text-gray-900 mb-2 flex items-center gap-2">
        <span class="w-6 h-6 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-xs font-bold">2</span>
        How will you collect feedback? <span class="text-sm font-normal text-gray-400 ml-1">(optional, select any)</span>
      </h2>
      <p class="text-sm text-gray-500 mb-5">Choose the channels you plan to use. You can add or remove channels any time.</p>

      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <?php foreach ($allChannels as $slug => $ch): ?>
          <label class="channel-card block">
            <input type="checkbox" name="channels[]" value="<?= $slug ?>"
                   class="sr-only"
                   <?= in_array($slug, (array)($_POST['channels'] ?? ['widget','email'])) ? 'checked' : '' ?>>
            <div class="card-inner relative border-2 border-gray-200 rounded-xl p-4 bg-white hover:border-indigo-300 transition">
              <!-- Check badge -->
              <div class="check-icon absolute top-2 right-2 w-5 h-5 bg-indigo-600 rounded-full items-center justify-center">
                <i class="fas fa-check text-white text-xs"></i>
              </div>
              <i class="fas <?= $ch['icon'] ?> text-xl text-indigo-500 mb-2 block"></i>
              <p class="text-sm font-semibold text-gray-900"><?= $ch['label'] ?></p>
              <p class="text-xs text-gray-400 mt-0.5 leading-tight"><?= $ch['desc'] ?></p>
            </div>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Submit -->
    <button type="submit" id="submitBtn"
            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 rounded-2xl transition flex items-center justify-center gap-2 text-base">
      <i class="fas fa-rocket"></i> Create Project &amp; Go to Dashboard
    </button>

    <p class="text-center text-sm text-gray-400">
      <i class="fas fa-shield-alt text-green-500 mr-1"></i>
      Your data is safe. You can change everything from settings later.
    </p>
  </form>
</div>

<script>
function slugify(str) {
  return str.toLowerCase().trim()
    .replace(/[^a-z0-9\s\-]/g, '')
    .replace(/[\s\-]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'your-project';
}

function updateSlugPreview(val) {
  const s = slugify(val);
  document.getElementById('slugPreview').textContent = '<?= APP_URL ?>/feedback/' + s;
}

// Channel card toggle visual
document.querySelectorAll('.channel-card input').forEach(cb => {
  const updateCard = () => {
    const inner = cb.closest('.channel-card').querySelector('.card-inner');
    const icon  = inner.querySelector('.check-icon');
    if (cb.checked) {
      inner.classList.add('border-indigo-500', 'bg-indigo-50');
      icon.style.display = 'flex';
    } else {
      inner.classList.remove('border-indigo-500', 'bg-indigo-50');
      icon.style.display = 'none';
    }
  };
  updateCard(); // init on load
  cb.addEventListener('change', updateCard);
});

// Prevent double-submit
document.getElementById('setupForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating…';
});
</script>
</body>
</html>
