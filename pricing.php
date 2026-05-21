<?php
/**
 * Public Pricing Page — DB-driven
 * Reads plans and add-ons directly from ff_billing_plans and ff_addons.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/billing.php';

try {
    $plans        = BillingService::getAllPlans();
    $addonCatalog = BillingService::getAvailableAddons();
} catch (\Throwable $e) {
    $plans        = [];
    $addonCatalog = [];
}

// Helper: escape for HTML output
function h2(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Comparison matrix rows
$featureMatrix = [
    ['label'=>'Projects',              'key'=>'max_projects',            'type'=>'num'],
    ['label'=>'Team Members',          'key'=>'max_users',               'type'=>'num'],
    ['label'=>'Feedback / month',      'key'=>'max_feedback_per_month',  'type'=>'num'],
    ['label'=>'Email campaigns / mo',  'key'=>'max_campaigns_per_month', 'type'=>'num'],
    ['label'=>'Emails / month',        'key'=>'max_emails',              'type'=>'num'],
    ['label'=>'WhatsApp / month',      'key'=>'max_whatsapp',            'type'=>'num'],
    ['label'=>'SMS / month',           'key'=>'max_sms',                 'type'=>'num'],
    ['label'=>'AI Insights & Copilot', 'key'=>'allow_ai',                'type'=>'bool'],
    ['label'=>'Automations',           'key'=>'allow_automations',       'type'=>'bool'],
    ['label'=>'Audit Logs',            'key'=>'allow_audit_logs',        'type'=>'bool'],
    ['label'=>'API Access',            'key'=>'allow_api',               'type'=>'bool'],
    ['label'=>'Bulk Export',           'key'=>'allow_export',            'type'=>'bool'],
    ['label'=>'White-label',           'key'=>'allow_white_label',       'type'=>'bool'],
    ['label'=>'SSO / SAML',            'key'=>'allow_sso',               'type'=>'bool'],
];

$planIcons = [
    'starter'    => 'fa-seedling',
    'growth'     => 'fa-chart-line',
    'pro'        => 'fa-rocket',
    'enterprise' => 'fa-building',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pricing – <?= APP_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
  *{font-family:'Inter',sans-serif;}
  .gradient-text{background:linear-gradient(135deg,#6366f1,#8b5cf6,#a855f7);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
  .plan-card{transition:transform .2s,box-shadow .2s;}
  .plan-card:hover{transform:translateY(-4px);box-shadow:0 20px 60px -10px rgba(99,102,241,.18);}
  .popular-card{transform:scale(1.04);}
  .popular-card:hover{transform:scale(1.04) translateY(-4px);}
  .toggle-bg{background:#e5e7eb;transition:background .2s;}
  .toggle-bg.active{background:#6366f1;}
  .toggle-knob{transition:transform .2s;}
  .check{color:#6366f1;}
  .cross{color:#d1d5db;}
  .feature-row:hover{background:#fafafa;}
  .cta-bg{background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#a855f7 100%);}
  .nav-glass{backdrop-filter:blur(12px);background:rgba(255,255,255,.85);}
  .hero-blob{position:absolute;border-radius:50%;filter:blur(80px);opacity:.15;pointer-events:none;}
  .faq-answer{max-height:0;overflow:hidden;transition:max-height .35s ease;}
  .faq-answer.open{max-height:300px;}
  .faq-item.open .faq-icon{transform:rotate(45deg);}
  .faq-icon{transition:transform .3s;}
  .addon-card:hover{border-color:#818cf8;}
</style>
</head>
<body class="bg-white text-gray-900 antialiased">

<!-- ── Navbar ────────────────────────────────────────────────────────────── -->
<nav class="nav-glass sticky top-0 z-50 border-b border-gray-100">
  <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
    <div class="flex items-center gap-2.5">
      <div class="w-8 h-8 rounded-xl flex items-center justify-center text-white text-sm font-bold"
           style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
        <i class="fas fa-comments"></i>
      </div>
      <span class="font-bold text-gray-900 text-lg tracking-tight"><?= APP_NAME ?></span>
    </div>
    <div class="hidden md:flex items-center gap-7 text-sm font-medium text-gray-500">
      <a href="#pricing"  class="hover:text-indigo-600 transition">Pricing</a>
      <a href="#addons"   class="hover:text-indigo-600 transition">Add-ons</a>
      <a href="#compare"  class="hover:text-indigo-600 transition">Compare</a>
      <a href="#faq"      class="hover:text-indigo-600 transition">FAQ</a>
    </div>
    <div class="flex items-center gap-3">
      <a href="<?= APP_URL ?>/admin/"      class="text-sm font-medium text-gray-600 hover:text-indigo-600 transition">Sign in</a>
      <a href="<?= APP_URL ?>/install.php" class="text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl transition">
        Start Free Trial
      </a>
    </div>
  </div>
</nav>

<!-- ── Hero ──────────────────────────────────────────────────────────────── -->
<section class="relative overflow-hidden pt-20 pb-16 px-6">
  <div class="hero-blob w-96 h-96 bg-indigo-500 top-0 left-1/4"></div>
  <div class="hero-blob w-72 h-72 bg-purple-500 top-12 right-1/4"></div>
  <div class="max-w-3xl mx-auto text-center relative">
    <div class="inline-flex items-center gap-2 bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-semibold px-4 py-1.5 rounded-full mb-6">
      <i class="fas fa-star text-yellow-500"></i> Trusted by 500+ product teams worldwide
    </div>
    <h1 class="text-5xl font-black text-gray-900 leading-tight mb-5">
      Simple pricing.<br><span class="gradient-text">Powerful results.</span>
    </h1>
    <p class="text-xl text-gray-500 mb-10 max-w-xl mx-auto leading-relaxed">
      One flat monthly fee per workspace. No per-seat charges. No hidden fees. Cancel any time.
    </p>
    <!-- Billing toggle -->
    <div class="inline-flex items-center gap-3 bg-gray-100 px-4 py-2 rounded-full">
      <span id="label-monthly" class="text-sm font-semibold text-gray-900">Monthly</span>
      <button onclick="toggleBilling()" id="toggle-btn"
              class="toggle-bg w-11 h-6 rounded-full relative flex items-center px-0.5 focus:outline-none">
        <span id="toggle-knob" class="toggle-knob w-5 h-5 bg-white rounded-full shadow"></span>
      </button>
      <span id="label-annual" class="text-sm font-medium text-gray-400">
        Annual <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full ml-1">Save 20%</span>
      </span>
    </div>
  </div>
</section>

<!-- ── Pricing Cards ──────────────────────────────────────────────────────── -->
<section id="pricing" class="px-6 pb-20">
  <div class="max-w-6xl mx-auto">
    <?php if (empty($plans)): ?>
    <div class="text-center py-16 text-gray-400">
      <i class="fas fa-database text-4xl mb-4"></i>
      <p class="text-lg font-medium">No plans found</p>
      <p class="text-sm mt-2">Run <code class="bg-gray-100 px-2 py-0.5 rounded text-gray-700">db-billing-migration.sql</code> to seed pricing data.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 items-start">
      <?php foreach ($plans as $plan):
        $monthly  = (float)$plan['price_monthly'];
        $yearly   = (float)$plan['price_yearly'];
        $perMoY   = ($yearly > 0) ? (int)round($yearly / 12) : 0;
        $save     = ($monthly > 0) ? (int)round($monthly * 12 - $yearly) : 0;
        $isPopular= ($plan['slug'] === 'pro');
        $isEnt    = ($plan['slug'] === 'enterprise');
        $color    = $plan['highlight_color'] ?? '#6366f1';
        $features = json_decode($plan['features'] ?? '[]', true) ?: [];
        $icon     = $planIcons[$plan['slug']] ?? 'fa-star';
        $cardCls  = $isPopular ? 'popular-card border-2' : 'plan-card border';
        $borderSt = $isPopular ? "border-color:{$color}" : 'border-color:#e5e7eb';
      ?>
      <div class="<?= $cardCls ?> bg-white rounded-2xl p-7 flex flex-col relative" style="<?= $borderSt ?>">
        <?php if ($isPopular): ?>
        <div class="absolute -top-3.5 left-1/2 -translate-x-1/2">
          <span class="text-white text-xs font-bold px-4 py-1.5 rounded-full shadow-sm"
                style="background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>cc)">Most Popular</span>
        </div>
        <?php endif; ?>

        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4"
             style="background:<?= $color ?>18">
          <i class="fas <?= $icon ?> text-sm" style="color:<?= $color ?>"></i>
        </div>

        <h3 class="text-lg font-bold text-gray-900 mb-1"><?= h2($plan['name']) ?></h3>
        <p class="text-xs text-gray-500 mb-5 leading-relaxed"><?= h2($plan['description'] ?? '') ?></p>

        <!-- Price -->
        <div class="mb-5">
          <?php if ($isEnt): ?>
            <span class="text-3xl font-black text-gray-900">€<?= number_format($monthly, 0) ?>+</span>
            <span class="text-gray-400 text-sm">/mo</span>
            <p class="text-xs text-gray-400 mt-1">Billed monthly or yearly · custom contracts available</p>
          <?php else: ?>
            <div>
              <span class="price-monthly-<?= h2($plan['slug']) ?> text-4xl font-black text-gray-900">€<?= number_format($monthly, 0) ?></span>
              <span class="price-monthly-label-<?= h2($plan['slug']) ?> text-gray-400 text-sm">/mo</span>
              <span class="price-yearly-<?= h2($plan['slug']) ?> hidden text-4xl font-black text-gray-900">€<?= $perMoY ?></span>
              <span class="price-yearly-label-<?= h2($plan['slug']) ?> hidden text-gray-400 text-sm">/mo</span>
            </div>
            <?php if ($yearly > 0 && $save > 0): ?>
            <p class="price-yearly-note-<?= h2($plan['slug']) ?> hidden text-xs text-green-600 font-semibold mt-1">
              €<?= number_format($yearly, 0) ?>/yr · you save €<?= number_format($save, 0) ?>
            </p>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- Quick stats -->
        <?php
          $statItems = [
            ['v'=>$plan['max_projects'],           'l'=>'Projects'],
            ['v'=>$plan['max_users'],              'l'=>'Users'],
            ['v'=>$plan['max_feedback_per_month'], 'l'=>'Feedback/mo'],
          ];
        ?>
        <div class="flex gap-4 mb-5">
          <?php foreach($statItems as $st):
            $v = ((int)$st['v'] === -1) ? '∞' : (((int)$st['v'] >= 1000) ? number_format((int)$st['v']/1000,0).'k' : (string)(int)$st['v']);
          ?>
          <div class="text-center">
            <div class="text-base font-bold" style="color:<?= $color ?>"><?= $v ?></div>
            <div class="text-xs text-gray-400 leading-tight"><?= $st['l'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Features -->
        <ul class="space-y-2.5 flex-1 mb-6 text-sm">
          <?php foreach (array_slice($features, 0, 8) as $feat): ?>
          <li class="flex items-start gap-2 text-gray-600">
            <i class="fas fa-check mt-0.5 flex-shrink-0 text-xs" style="color:<?= $color ?>"></i>
            <?= htmlspecialchars($feat) ?>
          </li>
          <?php endforeach; ?>
        </ul>

        <!-- CTA -->
        <?php if ($isEnt): ?>
        <a href="mailto:sales@feedbackflow.app?subject=Enterprise%20plan%20enquiry"
           class="block w-full text-center py-3 rounded-xl text-sm font-bold text-white transition"
           style="background:<?= $color ?>">
          Contact Sales <i class="fas fa-arrow-right ml-1"></i>
        </a>
        <?php else: ?>
        <a href="<?= APP_URL ?>/install.php"
           class="block w-full text-center py-3 rounded-xl text-sm font-bold transition <?= $isPopular ? 'text-white shadow-md' : '' ?>"
           style="<?= $isPopular ? "background:{$color}" : "border:2px solid {$color};color:{$color}" ?>">
          Start Free 14-day Trial
        </a>
        <p class="text-xs text-center text-gray-400 mt-2">No credit card · Cancel anytime</p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ── Add-ons ────────────────────────────────────────────────────────────── -->
<?php if (!empty($addonCatalog)): ?>
<section id="addons" class="bg-gray-50 py-20 px-6">
  <div class="max-w-5xl mx-auto">
    <div class="text-center mb-12">
      <h2 class="text-3xl font-bold text-gray-900 mb-3">Extend with Add-ons</h2>
      <p class="text-gray-500 max-w-xl mx-auto">
        Need more capacity without switching plans? Buy exactly what you need and pay only for what you use.
      </p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach ($addonCatalog as $addon): ?>
      <div class="addon-card bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4 transition">
        <div class="w-11 h-11 bg-indigo-50 rounded-xl flex items-center justify-center flex-shrink-0">
          <i class="fas <?= h2($addon['icon']) ?> text-indigo-600"></i>
        </div>
        <div>
          <p class="font-semibold text-gray-900 mb-1"><?= h2($addon['name']) ?></p>
          <p class="text-xs text-gray-500 mb-2.5 leading-relaxed"><?= h2($addon['description']) ?></p>
          <div class="flex items-center flex-wrap gap-2">
            <span class="text-sm font-bold text-indigo-700">€<?= number_format((float)$addon['price_per_qty'], 2) ?>/slot/mo</span>
            <?php if ($addon['type'] === 'quantity'): ?>
            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">
              +<?= number_format((int)$addon['units_per_qty']) ?> <?= h2($addon['unit_label']) ?>
            </span>
            <?php else: ?>
            <span class="text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">Feature unlock</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="text-center text-sm text-gray-400 mt-8">
      Add-ons are activated instantly from your billing dashboard · Remove any time · No lock-in
    </p>
  </div>
</section>
<?php endif; ?>

<!-- ── Comparison Table ───────────────────────────────────────────────────── -->
<section id="compare" class="py-20 px-6">
  <div class="max-w-6xl mx-auto">
    <div class="text-center mb-12">
      <h2 class="text-3xl font-bold text-gray-900 mb-3">Compare All Plans</h2>
      <p class="text-gray-500">Everything you need to choose with confidence</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 overflow-x-auto shadow-sm">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100">
            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 w-52">Feature</th>
            <?php foreach ($plans as $plan): ?>
            <th class="px-4 py-4 text-center">
              <span class="block text-sm font-bold text-gray-900"><?= h2($plan['name']) ?></span>
              <?php $m = (float)$plan['price_monthly']; ?>
              <span class="block text-xs font-normal text-gray-400 mt-0.5">
                <?= ($plan['slug']==='enterprise') ? 'Custom' : (($m===0.0) ? 'Free' : '€'.number_format($m,0).'/mo') ?>
              </span>
            </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($featureMatrix as $row): ?>
          <tr class="feature-row border-b border-gray-50">
            <td class="px-6 py-3.5 font-medium text-gray-700 text-xs"><?= h2($row['label']) ?></td>
            <?php foreach ($plans as $plan):
              $val = $plan[$row['key']] ?? null;
            ?>
            <td class="px-4 py-3.5 text-center text-xs">
              <?php if ($row['type'] === 'bool'):
                $on = ((int)$val === 1 || (int)$val === -1);
              ?>
                <?php if ($on): ?>
                <i class="fas fa-check check text-base"></i>
                <?php else: ?>
                <i class="fas fa-xmark cross text-base"></i>
                <?php endif; ?>
              <?php else:
                if ((int)$val === -1): ?>
                <i class="fas fa-infinity text-indigo-400"></i>
                <?php else: ?>
                <span class="font-medium text-gray-700"><?= number_format((int)($val ?? 0)) ?></span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
          <tr>
            <td class="px-6 py-5"></td>
            <?php foreach ($plans as $plan):
              $color = $plan['highlight_color'] ?? '#6366f1';
            ?>
            <td class="px-4 py-5 text-center">
              <?php if ($plan['slug']==='enterprise'): ?>
              <a href="mailto:sales@feedbackflow.app"
                 class="inline-block text-xs font-bold px-4 py-2 rounded-xl text-white"
                 style="background:<?= $color ?>">Contact</a>
              <?php else: ?>
              <a href="<?= APP_URL ?>/install.php"
                 class="inline-block text-xs font-bold px-4 py-2 rounded-xl text-white"
                 style="background:<?= $color ?>">Get Started</a>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- ── FAQ ───────────────────────────────────────────────────────────────── -->
<section id="faq" class="bg-gray-50 py-20 px-6">
  <div class="max-w-3xl mx-auto">
    <div class="text-center mb-12">
      <h2 class="text-3xl font-bold text-gray-900 mb-3">Frequently Asked Questions</h2>
    </div>
    <?php
    $faqs = [
      ['q'=>'Can I change plans at any time?',
       'a'=>'Yes — upgrade or downgrade instantly from your billing dashboard. Upgrades apply immediately; downgrades take effect at the end of your billing period.'],
      ['q'=>'Do you charge per seat / per user?',
       'a'=>'No. All plans are flat-rate per workspace. Your plan\'s user limit is the number of team members you can invite — no extra per-seat charge on top.'],
      ['q'=>'What happens when I hit a limit?',
       'a'=>'You\'ll receive an in-app warning at 80% of any limit. When you reach the limit the affected feature pauses — we never automatically charge overage fees.'],
      ['q'=>'Are there setup fees or overage charges?',
       'a'=>'None. No setup fees, no hidden overage billing. If you exceed a limit, buy an add-on or upgrade to restore capacity.'],
      ['q'=>'Do you support VAT and EU invoicing?',
       'a'=>'Yes. Enter your VAT number and billing address in billing settings. Invoices are auto-generated with the correct tax breakdown per billing cycle.'],
      ['q'=>'What currency is used?',
       'a'=>'All prices are in Euros (€). If you need invoices in a different currency, contact us at billing@feedbackflow.app.'],
      ['q'=>'Is FeedbackFlow self-hosted?',
       'a'=>'Yes — it\'s a self-hosted LAMP application (PHP 8.2 + MySQL). Deploy on any VPS or shared host. Your data stays on your own server.'],
      ['q'=>'Is there a free trial?',
       'a'=>'Every paid plan comes with a 14-day free trial. No credit card required to start. Cancel any time during the trial at no charge.'],
    ];
    ?>
    <div class="space-y-3">
      <?php foreach ($faqs as $i => $faq): ?>
      <div id="faq-<?= $i ?>" class="faq-item bg-white border border-gray-200 rounded-2xl overflow-hidden">
        <button onclick="toggleFaq(<?= $i ?>)"
                class="w-full flex items-center justify-between px-6 py-4 text-left font-semibold text-sm text-gray-900 hover:bg-gray-50 transition">
          <span><?= h2($faq['q']) ?></span>
          <i class="faq-icon fas fa-plus text-gray-400 flex-shrink-0 ml-4"></i>
        </button>
        <div id="faq-answer-<?= $i ?>" class="faq-answer">
          <p class="px-6 pb-5 text-sm text-gray-600 leading-relaxed"><?= h2($faq['a']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── CTA ───────────────────────────────────────────────────────────────── -->
<section class="cta-bg py-20 px-6 text-white text-center">
  <div class="max-w-2xl mx-auto">
    <h2 class="text-3xl font-black mb-4">Ready to get started?</h2>
    <p class="text-indigo-200 mb-8 text-lg">Join 500+ product teams using <?= APP_NAME ?> to build better products, faster.</p>
    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
      <a href="<?= APP_URL ?>/install.php"
         class="inline-flex items-center gap-2 bg-white text-indigo-700 font-bold px-7 py-3.5 rounded-xl hover:bg-indigo-50 transition text-sm">
        <i class="fas fa-rocket"></i> Start Free Trial — No Card Needed
      </a>
      <a href="mailto:hello@feedbackflow.app"
         class="inline-flex items-center gap-2 text-white border-2 border-white/30 font-semibold px-7 py-3.5 rounded-xl hover:bg-white/10 transition text-sm">
        <i class="fas fa-comments"></i> Talk to Sales
      </a>
    </div>
    <p class="text-indigo-300 text-xs mt-6">14-day free trial · No contracts · Cancel anytime · Self-hosted on your server</p>
  </div>
</section>

<!-- ── Footer ─────────────────────────────────────────────────────────────── -->
<footer class="bg-gray-900 py-12 px-6">
  <div class="max-w-5xl mx-auto">
    <div class="flex flex-col md:flex-row items-start justify-between gap-8 mb-10">
      <div>
        <div class="flex items-center gap-2.5 mb-3">
          <div class="w-8 h-8 rounded-xl flex items-center justify-center text-white text-sm font-bold"
               style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
            <i class="fas fa-comments"></i>
          </div>
          <span class="font-bold text-white text-lg tracking-tight"><?= APP_NAME ?></span>
        </div>
        <p class="text-sm text-gray-400 max-w-xs leading-relaxed">
          Self-hosted product feedback management. Collect, analyse, and act on user feedback — on your own server.
        </p>
      </div>
      <div class="grid grid-cols-2 gap-x-16 gap-y-2 text-sm text-gray-400">
        <a href="#pricing"  class="hover:text-white transition">Pricing</a>
        <a href="#addons"   class="hover:text-white transition">Add-ons</a>
        <a href="#compare"  class="hover:text-white transition">Compare Plans</a>
        <a href="#faq"      class="hover:text-white transition">FAQ</a>
        <a href="docs/user-guide.md" class="hover:text-white transition">Documentation</a>
        <a href="mailto:hello@feedbackflow.app" class="hover:text-white transition">Contact</a>
      </div>
    </div>
    <div class="border-t border-gray-800 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500">
      <p>© <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
      <div class="flex items-center gap-4">
        <span class="flex items-center gap-1.5"><i class="fas fa-shield-halved text-green-500"></i> GDPR Compliant</span>
        <span class="flex items-center gap-1.5"><i class="fas fa-server text-indigo-400"></i> Self-Hosted</span>
        <span class="flex items-center gap-1.5"><i class="fas fa-euro-sign text-yellow-400"></i> EUR Pricing</span>
      </div>
    </div>
  </div>
</footer>

<script>
let isAnnual = false;
const planSlugs = <?= json_encode(array_column($plans, 'slug')) ?>;

function toggleBilling() {
  isAnnual = !isAnnual;
  const btn  = document.getElementById('toggle-btn');
  const knob = document.getElementById('toggle-knob');
  const lbM  = document.getElementById('label-monthly');
  const lbA  = document.getElementById('label-annual');

  btn.classList.toggle('active', isAnnual);
  knob.style.transform = isAnnual ? 'translateX(20px)' : 'translateX(0)';
  lbM.classList.toggle('text-gray-900', !isAnnual);
  lbM.classList.toggle('text-gray-400',  isAnnual);
  lbA.classList.toggle('text-gray-900',  isAnnual);
  lbA.classList.toggle('text-gray-400', !isAnnual);

  planSlugs.forEach(slug => {
    const mEl  = document.querySelector('.price-monthly-'      + slug);
    const mLbl = document.querySelector('.price-monthly-label-'+ slug);
    const yEl  = document.querySelector('.price-yearly-'       + slug);
    const yLbl = document.querySelector('.price-yearly-label-' + slug);
    const yNote= document.querySelector('.price-yearly-note-'  + slug);
    if (mEl)  mEl.classList.toggle ('hidden', isAnnual);
    if (mLbl) mLbl.classList.toggle('hidden', isAnnual);
    if (yEl)  yEl.classList.toggle ('hidden', !isAnnual);
    if (yLbl) yLbl.classList.toggle('hidden', !isAnnual);
    if (yNote)yNote.classList.toggle('hidden', !isAnnual);
  });
}

function toggleFaq(i) {
  const item   = document.getElementById('faq-'        + i);
  const answer = document.getElementById('faq-answer-' + i);
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item').forEach(el => el.classList.remove('open'));
  document.querySelectorAll('.faq-answer').forEach(el => el.classList.remove('open'));
  if (!isOpen) { item.classList.add('open'); answer.classList.add('open'); }
}

document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) { e.preventDefault(); target.scrollIntoView({behavior:'smooth', block:'start'}); }
  });
});
</script>
</body>
</html>
