<?php
/**
 * Onboarding Step 2: Select Plan
 * Only accessible to new company owners mid-onboarding.
 * Invited users never reach this page.
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

if (!$company) {
    // No company — redirect to register
    redirect(APP_URL . '/index.php?action=register');
}

$companyId   = (int)$company['id'];
$companyName = $company['name'];

// If already on a plan and onboarding complete → go to dashboard
$onboardingComplete = (int)($company['onboarding_complete'] ?? 0);
if ($onboardingComplete && ($company['plan'] ?? '')) {
    redirect(APP_URL . '/admin/index.php');
}

// ── Handle plan selection ─────────────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { $errors[] = 'Security check failed. Please try again.'; }
    else {
        $planSlug = $_POST['plan']  ?? 'free';
        $cycle    = $_POST['cycle'] ?? 'monthly';
        if (!in_array($cycle, ['monthly','yearly'], true)) $cycle = 'monthly';

        $ok = OnboardingService::activatePlan($companyId, $planSlug, $cycle);
        if ($ok) {
            OnboardingService::log($currentUser['id'], $companyId, 'select_plan', [
                'plan' => $planSlug, 'cycle' => $cycle,
            ], 'company_signup');
            redirect(APP_URL . '/onboarding/setup.php');
        } else {
            $errors[] = 'Invalid plan selected. Please choose one of the plans below.';
        }
    }
}

// Load plans from DB
$plans = [];
try { $plans = BillingService::getAllPlans(); } catch (\Throwable $e) { }
$planMap = [];
foreach ($plans as $p) { $planMap[$p['slug']] = $p; }

$pageTitle = 'Choose Your Plan – ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
  * { font-family: 'Inter', sans-serif; }
  .gradient-text { background: linear-gradient(135deg,#6366f1,#8b5cf6,#a855f7); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
  .plan-card { transition: transform .2s ease, box-shadow .2s ease; cursor: pointer; }
  .plan-card:hover { transform: translateY(-4px); box-shadow: 0 20px 60px -10px rgba(99,102,241,.18); }
  .plan-card.popular { transform: scale(1.04); }
  .plan-card.popular:hover { transform: scale(1.04) translateY(-4px); }
  .plan-card.selected { outline: 3px solid #6366f1; }
  .toggle-bg { background:#e5e7eb; transition: background .2s; }
  .toggle-bg.active { background:#6366f1; }
  .toggle-knob { transition: transform .2s; }
  .badge-popular { background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; font-size:11px; font-weight:700; letter-spacing:.06em; padding:3px 12px; border-radius:99px; text-transform:uppercase; }
  .check { color:#6366f1; } .cross { color:#d1d5db; }
  .step-active { background:#6366f1; color:#fff; }
  .step-done  { background:#d1fae5; color:#065f46; }
  .step-todo  { background:#f3f4f6; color:#9ca3af; }
</style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

<!-- Nav -->
<nav class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-gray-100">
  <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
    <div class="flex items-center gap-2.5">
      <div class="w-8 h-8 rounded-xl flex items-center justify-center text-white text-sm font-bold"
           style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
        <i class="fas fa-comments"></i>
      </div>
      <span class="font-bold text-gray-900 text-lg tracking-tight"><?= h(APP_NAME) ?></span>
    </div>
    <div class="text-sm text-gray-500">
      Setting up <strong class="text-gray-900"><?= h($companyName) ?></strong>
    </div>
    <a href="<?= APP_URL ?>/index.php?action=logout" class="text-xs text-gray-400 hover:text-gray-600">Sign out</a>
  </div>
</nav>

<!-- Progress Steps -->
<div class="bg-white border-b border-gray-100 py-4">
  <div class="max-w-2xl mx-auto px-6 flex items-center gap-3">
    <div class="flex items-center gap-2">
      <div class="step-done w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"><i class="fas fa-check text-xs"></i></div>
      <span class="text-sm font-medium text-emerald-700">Account</span>
    </div>
    <div class="flex-1 h-px bg-indigo-200"></div>
    <div class="flex items-center gap-2">
      <div class="step-active w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold">2</div>
      <span class="text-sm font-bold text-indigo-700">Choose Plan</span>
    </div>
    <div class="flex-1 h-px bg-gray-200"></div>
    <div class="flex items-center gap-2">
      <div class="step-todo w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold">3</div>
      <span class="text-sm text-gray-400">First Project</span>
    </div>
  </div>
</div>

<!-- Error -->
<?php if ($errors): ?>
  <div class="max-w-xl mx-auto mt-6 px-6">
    <?php foreach ($errors as $e): ?>
      <div class="px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm mb-2">
        <i class="fas fa-exclamation-circle mr-2"></i><?= h($e) ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Hero -->
<section class="pt-12 pb-8 px-6 text-center">
  <p class="inline-flex items-center gap-2 bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-semibold px-4 py-1.5 rounded-full mb-5">
    <i class="fas fa-star text-yellow-500"></i> 14-day free trial on all paid plans · No credit card required
  </p>
  <h1 class="text-4xl font-black text-gray-900 mb-3">Pick the right plan for <span class="gradient-text"><?= h($companyName) ?></span></h1>
  <p class="text-lg text-gray-500 max-w-xl mx-auto">Start with a free trial. Cancel any time. Upgrade or downgrade as your needs change.</p>

  <!-- Billing toggle -->
  <div class="inline-flex items-center gap-3 bg-gray-100 px-4 py-2 rounded-full mt-6">
    <span id="label-monthly" class="text-sm font-semibold text-gray-900">Monthly</span>
    <button type="button" onclick="toggleBilling()" id="toggle-btn"
            class="toggle-bg w-11 h-6 rounded-full relative flex items-center px-0.5 focus:outline-none" aria-label="Toggle billing cycle">
      <span id="toggle-knob" class="toggle-knob w-5 h-5 bg-white rounded-full shadow"></span>
    </button>
    <span id="label-annual" class="text-sm font-medium text-gray-400">
      Annual <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full ml-1">Save 20%</span>
    </span>
  </div>
</section>

<!-- Billing cycle hidden input (shared across all forms) -->
<input type="hidden" id="global-cycle" value="monthly">

<!-- Plan Cards -->
<section class="px-6 pb-16">
  <div class="max-w-6xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 items-start">

    <!-- FREE -->
    <?php $freePlan = $planMap['free'] ?? null; ?>
    <div class="plan-card bg-white border-2 border-gray-200 rounded-2xl p-6 flex flex-col">
      <div class="mb-5">
        <span class="text-2xl mb-2 block">🌱</span>
        <h3 class="text-base font-bold text-gray-900 mb-1">Free</h3>
        <p class="text-xs text-gray-400 mb-4">Perfect to get started</p>
        <div class="flex items-end gap-1">
          <span class="text-3xl font-black text-gray-900">€0</span>
          <span class="text-gray-400 text-sm mb-1">/mo</span>
        </div>
      </div>
      <ul class="space-y-2.5 flex-1 mb-6 text-sm">
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> 1 project</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> 50 feedback items</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> 3 users</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> Basic dashboard</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> Public board</li>
        <li class="flex items-center gap-2 text-gray-300"><i class="fas fa-times cross w-4"></i> AI Insights</li>
        <li class="flex items-center gap-2 text-gray-300"><i class="fas fa-times cross w-4"></i> Campaigns</li>
      </ul>
      <form method="POST" class="plan-form">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="plan" value="free">
        <input type="hidden" name="cycle" value="monthly" class="cycle-input">
        <button type="submit" class="w-full text-center text-sm font-semibold py-2.5 rounded-xl border-2 border-gray-200 text-gray-600 hover:border-indigo-300 hover:text-indigo-600 transition">
          Start Free
        </button>
      </form>
    </div>

    <!-- STARTER -->
    <?php $sp = $planMap['starter'] ?? null; ?>
    <div class="plan-card bg-white border-2 border-gray-200 rounded-2xl p-6 flex flex-col">
      <div class="mb-5">
        <span class="text-2xl mb-2 block">🚀</span>
        <h3 class="text-base font-bold text-gray-900 mb-1">Starter</h3>
        <p class="text-xs text-gray-400 mb-4">For small teams</p>
        <div class="flex items-end gap-1">
          <span class="price-display text-3xl font-black text-gray-900" data-monthly="€19" data-annual="€15">€19</span>
          <span class="text-gray-400 text-sm mb-1">/mo</span>
        </div>
        <p class="save-text text-xs text-green-600 font-medium mt-1 hidden">Billed €180/yr — save €48</p>
      </div>
      <ul class="space-y-2.5 flex-1 mb-6 text-sm">
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> 2 projects</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> 500 feedback/mo</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> 10 users</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> Full dashboard</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> Email campaigns</li>
        <li class="flex items-center gap-2 text-gray-300"><i class="fas fa-times cross w-4"></i> AI Copilot</li>
        <li class="flex items-center gap-2 text-gray-300"><i class="fas fa-times cross w-4"></i> QR / WhatsApp</li>
      </ul>
      <form method="POST" class="plan-form">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="plan" value="starter">
        <input type="hidden" name="cycle" value="monthly" class="cycle-input">
        <button type="submit" class="w-full text-sm font-semibold py-2.5 rounded-xl border-2 border-indigo-200 text-indigo-600 hover:bg-indigo-50 transition">
          Start 14-Day Trial
        </button>
      </form>
    </div>

    <!-- GROWTH — POPULAR -->
    <div class="plan-card popular bg-white border-2 border-indigo-500 rounded-2xl p-6 flex flex-col shadow-xl shadow-indigo-100 relative">
      <div class="absolute -top-4 left-0 right-0 flex justify-center">
        <span class="badge-popular"><i class="fas fa-fire mr-1"></i>Most Popular</span>
      </div>
      <div class="mb-5 mt-2">
        <span class="text-2xl mb-2 block">💥</span>
        <h3 class="text-base font-bold text-gray-900 mb-1">Growth</h3>
        <p class="text-xs text-indigo-500 font-semibold mb-4">Best for growing teams</p>
        <div class="flex items-end gap-1">
          <span class="price-display text-3xl font-black text-gray-900" data-monthly="€49" data-annual="€39">€49</span>
          <span class="text-gray-400 text-sm mb-1">/mo</span>
        </div>
        <p class="save-text text-xs text-green-600 font-medium mt-1 hidden">Billed €468/yr — save €120</p>
      </div>
      <ul class="space-y-2.5 flex-1 mb-6 text-sm">
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> 5 projects</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> 2,000 feedback/mo</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> 25 users</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> Full AI Insights</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> <strong>AI Auto-Replies</strong></li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> Full Campaigns</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> QR + Embedded form</li>
      </ul>
      <form method="POST" class="plan-form">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="plan" value="growth">
        <input type="hidden" name="cycle" value="monthly" class="cycle-input">
        <button type="submit" class="w-full text-sm font-bold py-2.5 rounded-xl text-white transition"
                style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
          Start 14-Day Trial
        </button>
      </form>
    </div>

    <!-- PRO -->
    <div class="plan-card bg-white border-2 border-gray-200 rounded-2xl p-6 flex flex-col">
      <div class="mb-5">
        <span class="text-2xl mb-2 block">⚡</span>
        <h3 class="text-base font-bold text-gray-900 mb-1">Pro</h3>
        <p class="text-xs text-gray-400 mb-4">For power users</p>
        <div class="flex items-end gap-1">
          <span class="price-display text-3xl font-black text-gray-900" data-monthly="€99" data-annual="€79">€99</span>
          <span class="text-gray-400 text-sm mb-1">/mo</span>
        </div>
        <p class="save-text text-xs text-green-600 font-medium mt-1 hidden">Billed €948/yr — save €240</p>
      </div>
      <ul class="space-y-2.5 flex-1 mb-6 text-sm">
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> 15 projects</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> 10,000 feedback/mo</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> 100 users</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> AI Copilot</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> White-label</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> API Access</li>
        <li class="flex items-center gap-2 text-gray-600"><i class="fas fa-check check w-4"></i> Priority support</li>
      </ul>
      <form method="POST" class="plan-form">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="plan" value="pro">
        <input type="hidden" name="cycle" value="monthly" class="cycle-input">
        <button type="submit" class="w-full text-sm font-semibold py-2.5 rounded-xl border-2 border-purple-200 text-purple-600 hover:bg-purple-50 transition">
          Start 14-Day Trial
        </button>
      </form>
    </div>

    <!-- ENTERPRISE -->
    <div class="plan-card bg-gradient-to-b from-gray-900 to-gray-800 border-2 border-gray-700 rounded-2xl p-6 flex flex-col text-white">
      <div class="mb-5">
        <span class="text-2xl mb-2 block">🏢</span>
        <h3 class="text-base font-bold mb-1">Enterprise</h3>
        <p class="text-xs text-gray-400 mb-4">For large organisations</p>
        <div class="flex items-end gap-1">
          <span class="price-display text-3xl font-black" data-monthly="€299" data-annual="€239">€299</span>
          <span class="text-gray-400 text-sm mb-1">/mo</span>
        </div>
        <p class="save-text text-xs text-green-400 font-medium mt-1 hidden">Billed €2,868/yr — save €720</p>
      </div>
      <ul class="space-y-2.5 flex-1 mb-6 text-sm">
        <li class="flex items-center gap-2 text-gray-300"><i class="fas fa-check text-indigo-400 w-4"></i> Unlimited everything</li>
        <li class="flex items-center gap-2 text-gray-300"><i class="fas fa-check text-indigo-400 w-4"></i> SSO / SAML</li>
        <li class="flex items-center gap-2 text-gray-300"><i class="fas fa-check text-indigo-400 w-4"></i> Dedicated support</li>
        <li class="flex items-center gap-2 text-gray-300"><i class="fas fa-check text-indigo-400 w-4"></i> SLA guarantee</li>
        <li class="flex items-center gap-2 text-gray-300"><i class="fas fa-check text-indigo-400 w-4"></i> Custom contract</li>
        <li class="flex items-center gap-2 text-gray-300"><i class="fas fa-check text-indigo-400 w-4"></i> AI Copilot</li>
        <li class="flex items-center gap-2 text-gray-300"><i class="fas fa-check text-indigo-400 w-4"></i> White-label</li>
      </ul>
      <form method="POST" class="plan-form">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="plan" value="enterprise">
        <input type="hidden" name="cycle" value="monthly" class="cycle-input">
        <button type="submit" class="w-full text-sm font-semibold py-2.5 rounded-xl bg-white text-gray-900 hover:bg-gray-100 transition">
          Start 14-Day Trial
        </button>
      </form>
    </div>

  </div><!-- /grid -->

  <!-- Trust row -->
  <div class="max-w-6xl mx-auto mt-10 flex flex-wrap items-center justify-center gap-6 text-sm text-gray-400">
    <span><i class="fas fa-shield-alt text-green-500 mr-1"></i> No credit card required</span>
    <span><i class="fas fa-sync text-indigo-500 mr-1"></i> Cancel any time</span>
    <span><i class="fas fa-lock text-purple-500 mr-1"></i> GDPR compliant</span>
    <span><i class="fas fa-headset text-blue-500 mr-1"></i> Support included</span>
  </div>
</section>

<!-- Comparison Table -->
<section id="compare" class="px-6 py-16 bg-white border-t border-gray-100">
  <div class="max-w-5xl mx-auto">
    <h2 class="text-3xl font-black text-center text-gray-900 mb-10">Full Plan Comparison</h2>
    <div class="overflow-x-auto rounded-2xl border border-gray-200">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="text-left px-5 py-4 font-semibold text-gray-600 w-1/3">Feature</th>
            <th class="text-center px-3 py-4 font-semibold text-gray-600">Free</th>
            <th class="text-center px-3 py-4 font-semibold text-indigo-600 bg-indigo-50">Starter</th>
            <th class="text-center px-3 py-4 font-bold text-indigo-700 bg-indigo-50">Growth ⭐</th>
            <th class="text-center px-3 py-4 font-semibold text-purple-600">Pro</th>
            <th class="text-center px-3 py-4 font-semibold text-gray-700">Enterprise</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ([
            ['Projects',              '1',    '2',    '5',      '15',    'Unlimited'],
            ['Users',                 '3',    '10',   '25',     '100',   'Unlimited'],
            ['Feedback / month',      '50',   '500',  '2,000',  '10,000','Unlimited'],
            ['Email campaigns',       '❌',  '✅',   '✅',     '✅',    '✅'],
            ['WhatsApp & SMS',        '❌',  '❌',   '✅',     '✅',    '✅'],
            ['AI Insights',           '❌',  'Basic','Full',   'Full',  'Full'],
            ['AI Copilot',            '❌',  '❌',   '❌',     '✅',    '✅'],
            ['AI Auto-Replies',       '❌',  '❌',   '✅',     '✅',    '✅'],
            ['White-label',           '❌',  '❌',   '❌',     '✅',    '✅'],
            ['API Access',            '❌',  '❌',   '✅',     '✅',    '✅'],
            ['SSO / SAML',            '❌',  '❌',   '❌',     '❌',    '✅'],
            ['Priority support',      '❌',  '❌',   '❌',     '✅',    'Dedicated'],
          ] as $row): ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="px-5 py-3.5 font-medium text-gray-700"><?= $row[0] ?></td>
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <td class="px-3 py-3.5 text-center <?= $i >= 3 && $i <= 4 ? 'bg-indigo-50/40' : '' ?> text-gray-600">
                  <?= $row[$i] ?>
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- FAQ -->
<section id="faq" class="px-6 py-16 bg-gray-50 border-t border-gray-100">
  <div class="max-w-2xl mx-auto">
    <h2 class="text-3xl font-black text-center text-gray-900 mb-10">Frequently Asked Questions</h2>
    <div class="space-y-3" id="faqList">
      <?php foreach ([
        ['Do I need a credit card to start?',
         'No. Start your 14-day free trial without any payment details. You\'ll only need a card when you choose to upgrade after your trial.'],
        ['Can I change plans later?',
         'Yes! You can upgrade, downgrade, or cancel at any time from the Billing settings inside your workspace.'],
        ['What happens when my trial ends?',
         'Your account automatically moves to the Free plan. Your data is safe — you can upgrade any time to restore full access.'],
        ['Can I invite my team now?',
         'Yes. After selecting a plan and creating your first project, you can invite team members from the Team page. Invited users don\'t need to select a plan.'],
        ['Is there a long-term contract?',
         'No contracts. Monthly plans can be cancelled any time. Annual plans are billed once yearly with a 20% discount.'],
        ['What payment methods do you accept?',
         'We accept all major credit/debit cards (Visa, Mastercard, Amex) and SEPA bank transfers for Enterprise.'],
      ] as $i => $qa): ?>
        <div class="faq-item bg-white rounded-xl border border-gray-200 overflow-hidden">
          <button onclick="toggleFaq(<?= $i ?>)"
                  class="w-full flex items-center justify-between px-5 py-4 text-left font-semibold text-gray-900 text-sm hover:bg-gray-50 transition">
            <span><?= $qa[0] ?></span>
            <i id="faq-icon-<?= $i ?>" class="fas fa-plus text-gray-400 transition-transform"></i>
          </button>
          <div id="faq-body-<?= $i ?>" class="hidden px-5 pb-4 text-sm text-gray-500 leading-relaxed">
            <?= $qa[1] ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<script>
let isAnnual = false;

function toggleBilling() {
  isAnnual = !isAnnual;
  const btn   = document.getElementById('toggle-btn');
  const knob  = document.getElementById('toggle-knob');
  const mLabel = document.getElementById('label-monthly');
  const aLabel = document.getElementById('label-annual');

  btn.classList.toggle('active', isAnnual);
  knob.style.transform = isAnnual ? 'translateX(20px)' : '';
  mLabel.className = isAnnual ? 'text-sm font-medium text-gray-400' : 'text-sm font-semibold text-gray-900';
  aLabel.className = isAnnual ? 'text-sm font-semibold text-gray-900' : 'text-sm font-medium text-gray-400';

  // Update price displays
  document.querySelectorAll('.price-display').forEach(el => {
    el.textContent = isAnnual ? el.dataset.annual : el.dataset.monthly;
  });
  // Show/hide savings text
  document.querySelectorAll('.save-text').forEach(el => {
    el.classList.toggle('hidden', !isAnnual);
  });
  // Update hidden cycle inputs
  document.querySelectorAll('.cycle-input').forEach(el => {
    el.value = isAnnual ? 'yearly' : 'monthly';
  });
  document.getElementById('global-cycle').value = isAnnual ? 'yearly' : 'monthly';
}

function toggleFaq(i) {
  const body = document.getElementById('faq-body-' + i);
  const icon = document.getElementById('faq-icon-' + i);
  const open = !body.classList.contains('hidden');
  body.classList.toggle('hidden', open);
  icon.style.transform = open ? '' : 'rotate(45deg)';
}

// Prevent double-submit on plan forms
document.querySelectorAll('.plan-form').forEach(form => {
  form.addEventListener('submit', function() {
    this.querySelector('button[type=submit]').disabled = true;
    this.querySelector('button[type=submit]').innerHTML =
      '<i class="fas fa-spinner fa-spin mr-2"></i> Starting…';
  });
});
</script>
</body>
</html>
