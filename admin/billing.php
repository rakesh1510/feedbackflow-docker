<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/billing.php';

$currentUser = Auth::requirePermission('manage_billing_plan');
if (!Auth::isAdmin($currentUser)) {
    flash('error', 'Access denied.'); redirect(APP_URL . '/admin/index.php');
}
$pageTitle = 'Billing & Payments – ' . APP_NAME;

// ── Resolve company ────────────────────────────────────────────────────────
$company = BillingService::getCompany($currentUser['id']);
if (!$company) {
    // Fallback: use user id as pseudo-company
    $companyId  = (int)($currentUser['company_id'] ?? $currentUser['id']);
    $planSlug   = 'starter';
} else {
    $companyId  = (int)$company['id'];
    $planSlug   = $company['plan'] ?? 'starter';
}

// ── POST handlers ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    // Change plan
    if ($action === 'change_plan') {
        $newSlug = $_POST['plan']  ?? '';
        $cycle   = $_POST['cycle'] ?? 'monthly';
        $newPlan = BillingService::getPlan($newSlug);
        if ($newPlan) {
            // Create invoice for the charge
            $vatRate  = (float)($company['vat_rate'] ?? 0.0);
            $price    = ($cycle === 'yearly') ? (float)$newPlan['price_yearly'] : (float)$newPlan['price_monthly'];
            if ($price > 0) {
                $addonsTotal = BillingService::addonsMonthlyTotal($companyId);
                if ($cycle === 'yearly') $addonsTotal *= 12;
                $subtotal = $price + $addonsTotal;
                BillingService::createInvoice($companyId, $newSlug, $subtotal, $vatRate, [
                    ['label' => $newPlan['name'] . ' Plan (' . $cycle . ')', 'amount' => $price],
                    ['label' => 'Add-ons', 'amount' => $addonsTotal],
                ], $cycle);
            }
            BillingService::changePlan($companyId, $newSlug, $cycle);
            flash('success', "Plan changed to {$newPlan['name']}. Invoice generated.");
        } else {
            flash('error', 'Invalid plan selected.');
        }
        redirect(APP_URL . '/admin/billing.php');
    }

    // Update add-on quantity
    if ($action === 'set_addon') {
        $addonId  = (int)($_POST['addon_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        BillingService::setAddon($companyId, $addonId, $quantity);
        flash('success', 'Add-on updated.');
        redirect(APP_URL . '/admin/billing.php');
    }

    // Update billing details (name, address, VAT)
    if ($action === 'billing_details' && $company) {
        DB::update('ff_companies', [
            'billing_name'    => trim($_POST['billing_name']    ?? ''),
            'billing_address' => trim($_POST['billing_address'] ?? ''),
            'billing_city'    => trim($_POST['billing_city']    ?? ''),
            'billing_zip'     => trim($_POST['billing_zip']     ?? ''),
            'billing_country' => trim($_POST['billing_country'] ?? ''),
            'billing_email'   => trim($_POST['billing_email']   ?? ''),
            'vat_number'      => trim($_POST['vat_number']      ?? ''),
            'vat_rate'        => (float)($_POST['vat_rate']     ?? 0),
        ], 'id = ?', [$companyId]);
        flash('success', 'Billing details saved.');
        redirect(APP_URL . '/admin/billing.php');
    }
}

// ── Data ───────────────────────────────────────────────────────────────────
$plans          = BillingService::getAllPlans();
$currentPlan    = BillingService::getPlan($planSlug);
$effectiveLimits= BillingService::getEffectiveLimits($companyId);
$usage          = BillingService::getUsage($companyId);
$availableAddons= BillingService::getAvailableAddons();
$companyAddons  = BillingService::getCompanyAddons($companyId);
$prompts        = BillingService::upgradePrompts($companyId, $usage, 80);
$addonMap       = [];
foreach ($companyAddons as $ca) { $addonMap[$ca['addon_id']] = $ca; }

try {
    $invoices = DB::fetchAll(
        "SELECT * FROM ff_invoices WHERE company_id = ? ORDER BY created_at DESC LIMIT 20",
        [$companyId]
    );
} catch (\Throwable $e) {
    $invoices = []; // ff_invoices not yet migrated
}

$billingCycle   = $company['billing_cycle'] ?? 'monthly';
$addonMonthly   = BillingService::addonsMonthlyTotal($companyId);

// Refresh company after possible POST
if ($company) {
    $company = BillingService::getCompanyById($companyId);
    $planSlug = $company['plan'] ?? 'starter';
}

// ── Usage bars config ──────────────────────────────────────────────────────
$usageBars = [
    ['key'=>'max_projects',           'label'=>'Projects',            'icon'=>'fa-folder',         'used'=>$usage['projects'],  'color'=>'indigo'],
    ['key'=>'max_users',              'label'=>'Team Members',        'icon'=>'fa-users',           'used'=>$usage['users'],     'color'=>'violet'],
    ['key'=>'max_feedback_per_month', 'label'=>'Feedback (this mo.)', 'icon'=>'fa-comments',        'used'=>$usage['feedback'],  'color'=>'blue'],
    ['key'=>'max_emails',             'label'=>'Emails (this mo.)',   'icon'=>'fa-envelope',        'used'=>$usage['emails'],    'color'=>'purple'],
    ['key'=>'max_whatsapp',           'label'=>'WhatsApp (this mo.)', 'icon'=>'fa-whatsapp',        'used'=>$usage['whatsapp'],  'color'=>'green'],
    ['key'=>'max_sms',                'label'=>'SMS (this mo.)',      'icon'=>'fa-comment-sms',     'used'=>$usage['sms'],       'color'=>'orange'],
];

$flash = getFlash();
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto bg-gray-50">

  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Billing &amp; Payments</h1>
        <p class="text-sm text-gray-500 mt-0.5">Manage your subscription, add-ons, and invoices</p>
      </div>
      <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-semibold capitalize"
            style="background:<?= h($currentPlan['highlight_color'] ?? '#6366f1') ?>18;color:<?= h($currentPlan['highlight_color'] ?? '#6366f1') ?>">
        <i class="fas fa-circle text-xs"></i> <?= h($currentPlan['name'] ?? 'Starter') ?>
      </span>
    </div>
  </header>

  <div class="p-6 space-y-6">
    <?php foreach ($flash as $f): ?>
    <div class="rounded-xl px-4 py-3 text-sm font-medium <?= $f['type']==='success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
      <i class="fas <?= $f['type']==='success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> mr-2"></i><?= h($f['msg']) ?>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($prompts)): ?>
    <!-- ── Upgrade Prompts ──────────────────────────────────────────────── -->
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
      <div class="flex items-start gap-3">
        <div class="w-9 h-9 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
          <i class="fas fa-triangle-exclamation text-amber-600"></i>
        </div>
        <div class="flex-1">
          <p class="font-semibold text-amber-900 mb-1">You're approaching your plan limits</p>
          <div class="flex flex-wrap gap-2 mb-3">
            <?php foreach ($prompts as $pr): ?>
            <span class="bg-amber-100 text-amber-800 text-xs font-medium px-2.5 py-1 rounded-full">
              <?= h($pr['label']) ?>: <?= $pr['pct'] ?>%
            </span>
            <?php endforeach; ?>
          </div>
          <?php $next = BillingService::getNextPlan($planSlug); ?>
          <?php if ($next): ?>
          <form method="post" class="inline-flex">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="change_plan">
            <input type="hidden" name="plan" value="<?= h($next['slug']) ?>">
            <input type="hidden" name="cycle" value="<?= h($billingCycle) ?>">
            <button class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
              Upgrade to <?= h($next['name']) ?> — €<?= number_format($next['price_monthly']) ?>/mo
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Usage Overview ──────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-base font-bold text-gray-900">Usage This Period</h2>
        <span class="text-xs text-gray-400">Effective limits include add-ons &amp; overrides</span>
      </div>
      <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($usageBars as $bar):
          $limit = $effectiveLimits[$bar['key']] ?? 0;
          $pct   = BillingService::usagePercent($limit, $bar['used']);
          $unlimited = ($limit === -1);
          $barColor  = $pct >= 90 ? '#ef4444' : ($pct >= 70 ? '#f59e0b' : '#6366f1');
        ?>
        <div class="bg-gray-50 rounded-xl p-4">
          <div class="flex items-center gap-2 mb-2">
            <i class="fas <?= h($bar['icon']) ?> text-indigo-400 text-xs"></i>
            <p class="text-xs font-medium text-gray-600"><?= h($bar['label']) ?></p>
          </div>
          <div class="flex items-end justify-between mb-1.5">
            <span class="text-xl font-bold text-gray-900"><?= number_format($bar['used']) ?></span>
            <span class="text-xs text-gray-400">
              / <?= $unlimited ? '<i class="fas fa-infinity"></i>' : number_format($limit) ?>
            </span>
          </div>
          <?php if (!$unlimited && $limit > 0): ?>
          <div class="w-full bg-gray-200 rounded-full h-1.5">
            <div class="h-1.5 rounded-full transition-all"
                 style="width:<?= min(100,$pct) ?>%;background:<?= $barColor ?>"></div>
          </div>
          <p class="text-xs text-gray-400 mt-1"><?= $pct ?>% used</p>
          <?php else: ?>
          <p class="text-xs text-indigo-400 mt-1"><i class="fas fa-infinity mr-1"></i>Unlimited</p>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Add-ons ─────────────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-5">
        <div>
          <h2 class="text-base font-bold text-gray-900">Add-ons</h2>
          <p class="text-sm text-gray-500 mt-0.5">Extend your plan limits without upgrading</p>
        </div>
        <?php if ($addonMonthly > 0): ?>
        <span class="text-sm font-semibold text-indigo-700 bg-indigo-50 px-3 py-1 rounded-full">
          +€<?= number_format($addonMonthly, 2) ?>/mo
        </span>
        <?php endif; ?>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($availableAddons as $addon):
          $purchased = $addonMap[$addon['id']] ?? null;
          $qty       = $purchased ? (int)$purchased['quantity'] : 0;
        ?>
        <div class="border border-gray-200 rounded-xl p-4 hover:border-indigo-300 transition">
          <div class="flex items-start gap-3 mb-3">
            <div class="w-9 h-9 bg-indigo-50 rounded-xl flex items-center justify-center flex-shrink-0">
              <i class="fas <?= h($addon['icon']) ?> text-indigo-600 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-gray-900 text-sm"><?= h($addon['name']) ?></p>
              <p class="text-xs text-gray-500 leading-relaxed"><?= h($addon['description']) ?></p>
            </div>
          </div>
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-bold text-gray-900">
              €<?= number_format($addon['price_per_qty'], 2) ?><span class="text-xs font-normal text-gray-400">/slot/mo</span>
            </span>
            <?php if ($addon['type'] === 'quantity'): ?>
            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">
              +<?= $addon['units_per_qty'] ?> <?= h($addon['unit_label']) ?> per slot
            </span>
            <?php else: ?>
            <span class="text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">Feature unlock</span>
            <?php endif; ?>
          </div>
          <form method="post" class="flex items-center gap-2">
            <?= csrfInput() ?>
            <input type="hidden" name="action"   value="set_addon">
            <input type="hidden" name="addon_id" value="<?= (int)$addon['id'] ?>">
            <?php if ($addon['type'] === 'quantity'): ?>
            <input type="number" name="quantity" value="<?= $qty ?>"
                   min="0" max="<?= (int)$addon['max_qty'] ?>"
                   class="w-16 border border-gray-200 rounded-lg px-2 py-1.5 text-sm text-center font-medium focus:ring-2 focus:ring-indigo-400 outline-none">
            <button class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-1.5 rounded-xl transition">
              <?= $qty > 0 ? 'Update' : 'Add' ?>
            </button>
            <?php if ($qty > 0): ?>
            <button type="submit" name="quantity" value="0"
                    class="w-9 h-9 border border-red-200 text-red-400 hover:bg-red-50 rounded-xl text-sm flex items-center justify-center transition"
                    onclick="return confirm('Remove this add-on?')">
              <i class="fas fa-trash"></i>
            </button>
            <?php endif; ?>
            <?php else: ?>
            <input type="hidden" name="quantity" value="<?= $qty > 0 ? '0' : '1' ?>">
            <button class="w-full py-1.5 rounded-xl text-sm font-semibold transition <?= $qty > 0 ? 'bg-red-50 text-red-600 hover:bg-red-100 border border-red-200' : 'bg-indigo-600 text-white hover:bg-indigo-700' ?>">
              <?= $qty > 0 ? '<i class="fas fa-xmark mr-1"></i>Remove' : '<i class="fas fa-plus mr-1"></i>Enable' ?>
            </button>
            <?php endif; ?>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Plans Grid ──────────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-5">
        <div>
          <h2 class="text-base font-bold text-gray-900">Change Plan</h2>
          <p class="text-sm text-gray-500 mt-0.5">Switch immediately — billed pro-rata</p>
        </div>
        <!-- Monthly/Yearly toggle -->
        <div class="flex items-center gap-2 text-sm">
          <span id="lbl-m" class="font-medium text-gray-700">Monthly</span>
          <button id="cycle-toggle" onclick="toggleCycle()"
                  class="relative w-11 h-6 rounded-full bg-gray-300 transition-colors duration-200 focus:outline-none"
                  aria-label="Toggle billing cycle">
            <span id="cycle-knob" class="absolute left-1 top-1 w-4 h-4 rounded-full bg-white shadow transition-transform duration-200"></span>
          </button>
          <span id="lbl-y" class="font-medium text-gray-400">Yearly <span class="text-green-500 text-xs font-bold">−20%</span></span>
        </div>
      </div>
      <input type="hidden" id="billing-cycle-input" value="monthly">

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach ($plans as $plan):
          $isCurrent  = ($plan['slug'] === $planSlug);
          $isUpgrade  = (($plan['sort_order'] ?? 0) > ($currentPlan['sort_order'] ?? 0));
          $borderCls  = $isCurrent ? 'ring-2' : 'border border-gray-200';
          $ringColor  = $isCurrent ? 'style="--tw-ring-color:' . $plan['highlight_color'] . '"' : '';
          $features   = json_decode($plan['features'] ?? '[]', true) ?: [];
        ?>
        <div class="<?= $borderCls ?> rounded-2xl p-5 relative flex flex-col <?= $plan['slug']==='pro' ? 'shadow-lg' : '' ?>" <?= $ringColor ?>>
          <?php if ($plan['slug'] === 'pro'): ?>
          <div class="absolute -top-3 left-1/2 -translate-x-1/2">
            <span class="text-white text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap"
                  style="background:<?= h($plan['highlight_color']) ?>">Most Popular</span>
          </div>
          <?php endif; ?>

          <div class="w-9 h-9 rounded-xl mb-3 flex items-center justify-center"
               style="background:<?= h($plan['highlight_color']) ?>18">
            <i class="fas fa-circle text-xs" style="color:<?= h($plan['highlight_color']) ?>"></i>
          </div>
          <h3 class="font-bold text-gray-900 mb-0.5"><?= h($plan['name']) ?></h3>
          <p class="text-xs text-gray-500 mb-3"><?= h($plan['description'] ?? '') ?></p>

          <div class="mb-4">
            <?php if ((float)$plan['price_monthly'] === 0.0 && $plan['slug']==='enterprise'): ?>
              <span class="text-2xl font-bold text-gray-900">Custom</span>
            <?php elseif ((float)$plan['price_monthly'] === 0.0): ?>
              <span class="text-2xl font-bold text-gray-900">Free</span>
            <?php else: ?>
              <span class="text-2xl font-bold text-gray-900" data-m="€<?= number_format($plan['price_monthly'],0) ?>" data-y="€<?= number_format(round($plan['price_yearly']/12),0) ?>">
                €<?= number_format($plan['price_monthly'],0) ?>
              </span>
              <span class="text-xs text-gray-400">/mo</span>
              <p class="text-xs text-gray-400 mt-0.5 yearly-save hidden">€<?= number_format($plan['price_yearly'],0) ?>/yr — save €<?= number_format($plan['price_monthly']*12 - $plan['price_yearly'],0) ?></p>
            <?php endif; ?>
          </div>

          <ul class="space-y-1.5 mb-5 flex-1">
            <?php foreach (array_slice($features,0,6) as $feat): ?>
            <li class="flex items-start gap-1.5 text-xs text-gray-600">
              <i class="fas fa-check mt-0.5 flex-shrink-0" style="color:<?= h($plan['highlight_color']) ?>"></i>
              <?= h($feat) ?>
            </li>
            <?php endforeach; ?>
          </ul>

          <?php if ($isCurrent): ?>
          <button disabled class="w-full bg-gray-100 text-gray-400 py-2 rounded-xl text-sm font-medium cursor-not-allowed">
            <i class="fas fa-circle-check mr-1"></i>Current Plan
          </button>
          <?php elseif ($plan['slug'] === 'enterprise'): ?>
          <a href="mailto:sales@feedbackflow.app"
             class="block w-full text-center bg-gray-900 hover:bg-gray-800 text-white py-2 rounded-xl text-sm font-semibold transition">
            Contact Sales
          </a>
          <?php else: ?>
          <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="change_plan">
            <input type="hidden" name="plan"   value="<?= h($plan['slug']) ?>">
            <input class="billing-cycle-field" type="hidden" name="cycle" value="monthly">
            <button class="w-full py-2 rounded-xl text-sm font-semibold transition text-white"
                    style="background:<?= h($plan['highlight_color']) ?>">
              <?= $isUpgrade ? 'Upgrade' : 'Downgrade' ?>
            </button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Invoice History ─────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-base font-bold text-gray-900">Invoice History</h2>
        <span class="text-xs text-gray-400">VAT-included invoices generated on each billing cycle</span>
      </div>
      <?php if (empty($invoices)): ?>
      <div class="text-center py-10">
        <i class="fas fa-file-invoice text-4xl text-gray-200 mb-3"></i>
        <p class="text-gray-400 text-sm font-medium">No invoices yet</p>
        <p class="text-gray-300 text-xs mt-1">Invoices appear here after each billing cycle.</p>
      </div>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100">
              <th class="text-left py-2 px-2 text-xs font-semibold text-gray-500">Invoice #</th>
              <th class="text-left py-2 px-2 text-xs font-semibold text-gray-500">Plan</th>
              <th class="text-right py-2 px-2 text-xs font-semibold text-gray-500">Subtotal</th>
              <th class="text-right py-2 px-2 text-xs font-semibold text-gray-500">VAT</th>
              <th class="text-right py-2 px-2 text-xs font-semibold text-gray-500">Total</th>
              <th class="text-left py-2 px-2 text-xs font-semibold text-gray-500">Status</th>
              <th class="text-left py-2 px-2 text-xs font-semibold text-gray-500">Date</th>
              <th class="py-2 px-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($invoices as $inv):
              $statusCls = match($inv['status']) {
                'paid'    => 'bg-green-100 text-green-700',
                'overdue' => 'bg-red-100 text-red-700',
                'void'    => 'bg-gray-100 text-gray-400',
                default   => 'bg-yellow-100 text-yellow-700',
              };
              $subtotal  = isset($inv['subtotal']) ? (float)$inv['subtotal'] : ((float)$inv['amount'] - (float)($inv['vat_amount'] ?? 0));
            ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="py-3 px-2 font-mono text-xs text-indigo-700 font-medium"><?= h($inv['invoice_number']) ?></td>
              <td class="py-3 px-2 text-xs capitalize text-gray-600"><?= h($inv['plan_slug'] ?? '—') ?></td>
              <td class="py-3 px-2 text-right text-xs text-gray-600"><?= $inv['currency'] ?? 'EUR' ?> <?= number_format($subtotal,2) ?></td>
              <td class="py-3 px-2 text-right text-xs text-gray-500">
                <?php if ((float)($inv['vat_rate'] ?? 0) > 0): ?>
                  <?= number_format($inv['vat_rate'],0) ?>% / <?= $inv['currency'] ?? 'EUR' ?> <?= number_format($inv['vat_amount'] ?? $inv['tax_amount'] ?? 0,2) ?>
                <?php else: ?>
                  <span class="text-gray-300">—</span>
                <?php endif; ?>
              </td>
              <td class="py-3 px-2 text-right font-semibold text-gray-900"><?= $inv['currency'] ?? 'EUR' ?> <?= number_format($inv['amount'],2) ?></td>
              <td class="py-3 px-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $statusCls ?>">
                  <?= ucfirst($inv['status']) ?>
                </span>
              </td>
              <td class="py-3 px-2 text-xs text-gray-400"><?= formatDate($inv['created_at']) ?></td>
              <td class="py-3 px-2">
                <a href="<?= APP_URL ?>/admin/invoice-pdf.php?id=<?= (int)$inv['id'] ?>"
                   class="text-indigo-600 hover:text-indigo-800 text-xs inline-flex items-center gap-1">
                  <i class="fas fa-download"></i>PDF
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Billing Details ─────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="text-base font-bold text-gray-900 mb-5">Billing Details &amp; VAT</h2>
      <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="billing_details">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Billing Name / Company</label>
          <input type="text" name="billing_name" value="<?= h($company['billing_name'] ?? $company['name'] ?? '') ?>"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Billing Email</label>
          <input type="email" name="billing_email" value="<?= h($company['billing_email'] ?? $company['email'] ?? '') ?>"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs font-semibold text-gray-600 mb-1">Billing Address</label>
          <input type="text" name="billing_address" value="<?= h($company['billing_address'] ?? '') ?>"
                 placeholder="Street, number…"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">City</label>
          <input type="text" name="billing_city" value="<?= h($company['billing_city'] ?? '') ?>"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">ZIP / Postal Code</label>
          <input type="text" name="billing_zip" value="<?= h($company['billing_zip'] ?? '') ?>"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Country</label>
          <input type="text" name="billing_country" value="<?= h($company['billing_country'] ?? $company['country'] ?? '') ?>"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">VAT Number <span class="font-normal text-gray-400">(optional)</span></label>
          <input type="text" name="vat_number" value="<?= h($company['vat_number'] ?? '') ?>"
                 placeholder="e.g. DE123456789"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">VAT Rate % <span class="font-normal text-gray-400">(applied to invoices)</span></label>
          <input type="number" name="vat_rate" value="<?= h($company['vat_rate'] ?? '0') ?>"
                 min="0" max="30" step="0.01"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div class="md:col-span-2 flex justify-end">
          <button class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2 rounded-xl transition">
            <i class="fas fa-floppy-disk mr-1.5"></i>Save Billing Details
          </button>
        </div>
      </form>
    </div>

    <!-- ── Payment Method Placeholder ─────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="text-base font-bold text-gray-900 mb-1">Payment Method</h2>
      <p class="text-sm text-gray-500 mb-4">Connect a payment gateway for automatic billing</p>
      <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl p-8 text-center">
        <i class="fas fa-credit-card text-3xl text-gray-300 mb-3"></i>
        <p class="text-gray-500 text-sm font-medium">Stripe integration</p>
        <p class="text-xs text-gray-400 mt-1">
          Add <code class="bg-gray-100 px-1 rounded">STRIPE_SECRET_KEY</code> to <code class="bg-gray-100 px-1 rounded">config.php</code>
          to enable automatic payment processing.
        </p>
      </div>
    </div>

  </div><!-- /p-6 -->
</main>
</div>

<script>
let isYearly = false;
function toggleCycle() {
  isYearly = !isYearly;
  const toggle = document.getElementById('cycle-toggle');
  const knob   = document.getElementById('cycle-knob');
  const lm     = document.getElementById('lbl-m');
  const ly     = document.getElementById('lbl-y');

  toggle.style.background = isYearly ? '#6366f1' : '#d1d5db';
  knob.style.transform    = isYearly ? 'translateX(20px)' : 'translateX(0)';
  lm.classList.toggle('text-gray-700',  !isYearly);
  lm.classList.toggle('text-gray-400',   isYearly);
  ly.classList.toggle('text-gray-900',   isYearly);
  ly.classList.toggle('text-gray-400',  !isYearly);

  // Update price displays
  document.querySelectorAll('[data-m]').forEach(el => {
    el.textContent = isYearly ? el.dataset.y : el.dataset.m;
  });
  document.querySelectorAll('.yearly-save').forEach(el => {
    el.classList.toggle('hidden', !isYearly);
  });
  // Update form hidden inputs
  document.querySelectorAll('.billing-cycle-field').forEach(el => {
    el.value = isYearly ? 'yearly' : 'monthly';
  });
}
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
