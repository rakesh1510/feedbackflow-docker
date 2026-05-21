<?php
/**
 * Super-Admin: Plan & Billing Management
 * Manage plan pricing, limits, add-ons, and per-company overrides.
 * Access: super-admins only.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/billing.php';

$currentUser = Auth::require();
if (!($currentUser['is_super_admin'] ?? 0)) {
    flash('error', 'Super-admin access required.'); redirect(APP_URL . '/admin/index.php');
}
$pageTitle = 'Plan & Billing Management – ' . APP_NAME;

// ── POST handlers ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    // Update a plan
    if ($action === 'update_plan') {
        $planId = (int)$_POST['plan_id'];
        DB::update('ff_billing_plans', [
            'name'                   => trim($_POST['name']                   ?? ''),
            'description'            => trim($_POST['description']            ?? ''),
            'price_monthly'          => (float)($_POST['price_monthly']       ?? 0),
            'price_yearly'           => (float)($_POST['price_yearly']        ?? 0),
            'max_projects'           => (int)($_POST['max_projects']          ?? 0) ?: -1,
            'max_users'              => (int)($_POST['max_users']             ?? 0) ?: -1,
            'max_feedback_per_month' => (int)($_POST['max_feedback']          ?? 0) ?: -1,
            'max_emails'             => (int)($_POST['max_emails']            ?? 0) ?: -1,
            'max_whatsapp'           => (int)($_POST['max_whatsapp']          ?? 0) ?: -1,
            'max_sms'                => (int)($_POST['max_sms']               ?? 0) ?: -1,
            'max_campaigns_per_month'=> (int)($_POST['max_campaigns']         ?? 0) ?: -1,
            'allow_ai'               => isset($_POST['allow_ai'])               ? 1 : 0,
            'allow_white_label'      => isset($_POST['allow_white_label'])      ? 1 : 0,
            'allow_api'              => isset($_POST['allow_api'])              ? 1 : 0,
            'allow_export'           => isset($_POST['allow_export'])           ? 1 : 0,
            'allow_automations'      => isset($_POST['allow_automations'])      ? 1 : 0,
            'allow_audit_logs'       => isset($_POST['allow_audit_logs'])       ? 1 : 0,
            'allow_sso'              => isset($_POST['allow_sso'])              ? 1 : 0,
            'highlight_color'        => trim($_POST['highlight_color']         ?? '#6366f1'),
            'is_active'              => isset($_POST['is_active'])              ? 1 : 0,
        ], 'id = ?', [$planId]);
        flash('success', 'Plan updated.');
        redirect(APP_URL . '/admin/super-admin-billing.php');
    }

    // Set admin override for a company
    if ($action === 'set_override') {
        $companyId = (int)$_POST['company_id'];
        $resource  = trim($_POST['resource'] ?? '');
        $value     = (int)$_POST['value'];
        $note      = trim($_POST['note'] ?? '');
        if ($companyId && $resource) {
            $existing = DB::fetch(
                "SELECT id FROM ff_admin_overrides WHERE company_id = ? AND resource = ?",
                [$companyId, $resource]
            );
            if ($existing) {
                DB::update('ff_admin_overrides',
                    ['override_value' => $value, 'note' => $note, 'set_by' => $currentUser['id']],
                    'company_id = ? AND resource = ?', [$companyId, $resource]
                );
            } else {
                DB::insert('ff_admin_overrides', [
                    'company_id'     => $companyId,
                    'resource'       => $resource,
                    'override_value' => $value,
                    'note'           => $note,
                    'set_by'         => $currentUser['id'],
                ]);
            }
            flash('success', "Override set: {$resource} = {$value} for company #{$companyId}.");
        }
        redirect(APP_URL . '/admin/super-admin-billing.php#overrides');
    }

    // Remove override
    if ($action === 'remove_override') {
        $overrideId = (int)$_POST['override_id'];
        DB::delete('ff_admin_overrides', 'id = ?', [$overrideId]);
        flash('success', 'Override removed.');
        redirect(APP_URL . '/admin/super-admin-billing.php#overrides');
    }
}

// ── Data ───────────────────────────────────────────────────────────────────
$plans     = BillingService::getAllPlans();
$companies = DB::fetchAll("SELECT id, name, plan, billing_cycle FROM ff_companies ORDER BY name LIMIT 200");
$overrides = DB::fetchAll(
    "SELECT ao.*, c.name as company_name, u.name as admin_name
     FROM ff_admin_overrides ao
     LEFT JOIN ff_companies c ON c.id = ao.company_id
     LEFT JOIN ff_users u ON u.id = ao.set_by
     ORDER BY ao.created_at DESC"
);
$editPlan  = null;
if (!empty($_GET['edit'])) {
    $editPlan = DB::fetch("SELECT * FROM ff_billing_plans WHERE id = ?", [(int)$_GET['edit']]);
}

$flash = getFlash();
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto bg-gray-50">

  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Plan &amp; Billing Management</h1>
        <p class="text-sm text-gray-500 mt-0.5">Edit plan pricing, limits, and per-company overrides</p>
      </div>
      <span class="bg-red-100 text-red-700 text-xs font-bold px-3 py-1 rounded-full">
        <i class="fas fa-crown mr-1"></i>Super-Admin Only
      </span>
    </div>
  </header>

  <div class="p-6 space-y-8">
    <?php foreach ($flash as $f): ?>
    <div class="rounded-xl px-4 py-3 text-sm font-medium <?= $f['type']==='success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
      <i class="fas <?= $f['type']==='success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> mr-2"></i><?= h($f['msg']) ?>
    </div>
    <?php endforeach; ?>

    <!-- ── Plan Editor ──────────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="text-base font-bold text-gray-900 mb-5">Plans</h2>
      <div class="overflow-x-auto mb-6">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100">
              <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500">Plan</th>
              <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500">Monthly</th>
              <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500">Yearly</th>
              <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500">Projects</th>
              <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500">Users</th>
              <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500">Feedback</th>
              <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500">Emails</th>
              <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500">WA</th>
              <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500">SMS</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($plans as $plan):
              function fmtVal($v): string { return (int)$v === -1 ? '∞' : number_format((int)$v); }
            ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="px-3 py-3">
                <div class="flex items-center gap-2">
                  <div class="w-3 h-3 rounded-full" style="background:<?= h($plan['highlight_color'] ?? '#6366f1') ?>"></div>
                  <span class="font-semibold text-gray-900"><?= h($plan['name']) ?></span>
                  <?php if (!$plan['is_active']): ?><span class="text-xs text-red-500">(inactive)</span><?php endif; ?>
                </div>
                <p class="text-xs text-gray-400 mt-0.5"><?= h($plan['slug']) ?></p>
              </td>
              <td class="px-3 py-3 text-right font-mono text-gray-700">€<?= number_format($plan['price_monthly'],2) ?></td>
              <td class="px-3 py-3 text-right font-mono text-gray-700">€<?= number_format($plan['price_yearly'],2) ?></td>
              <td class="px-3 py-3 text-right text-gray-600"><?= fmtVal($plan['max_projects']) ?></td>
              <td class="px-3 py-3 text-right text-gray-600"><?= fmtVal($plan['max_users']) ?></td>
              <td class="px-3 py-3 text-right text-gray-600"><?= fmtVal($plan['max_feedback_per_month']) ?></td>
              <td class="px-3 py-3 text-right text-gray-600"><?= fmtVal($plan['max_emails']) ?></td>
              <td class="px-3 py-3 text-right text-gray-600"><?= fmtVal($plan['max_whatsapp']) ?></td>
              <td class="px-3 py-3 text-right text-gray-600"><?= fmtVal($plan['max_sms']) ?></td>
              <td class="px-3 py-3">
                <a href="?edit=<?= (int)$plan['id'] ?>"
                   class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold">
                  <i class="fas fa-pencil mr-1"></i>Edit
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($editPlan): ?>
      <!-- ── Edit Form ─────────────────────────────────────────────────── -->
      <div class="border-t border-gray-100 pt-6">
        <h3 class="font-bold text-gray-900 mb-4">
          Edit Plan: <span class="text-indigo-600"><?= h($editPlan['name']) ?></span>
        </h3>
        <form method="post" class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <?= csrfInput() ?>
          <input type="hidden" name="action"  value="update_plan">
          <input type="hidden" name="plan_id" value="<?= (int)$editPlan['id'] ?>">

          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Name</label>
            <input type="text" name="name" value="<?= h($editPlan['name']) ?>"
                   class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Description</label>
            <input type="text" name="description" value="<?= h($editPlan['description'] ?? '') ?>"
                   class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
          </div>
          <?php
          $numFields = [
            ['name'=>'price_monthly', 'label'=>'Monthly Price (€)', 'val'=>$editPlan['price_monthly'], 'step'=>'0.01', 'min'=>'0'],
            ['name'=>'price_yearly',  'label'=>'Yearly Price (€)',  'val'=>$editPlan['price_yearly'],  'step'=>'0.01', 'min'=>'0'],
            ['name'=>'max_projects',  'label'=>'Max Projects (-1=∞)','val'=>$editPlan['max_projects'],  'step'=>'1',    'min'=>'-1'],
            ['name'=>'max_users',     'label'=>'Max Users (-1=∞)',   'val'=>$editPlan['max_users'],     'step'=>'1',    'min'=>'-1'],
            ['name'=>'max_feedback',  'label'=>'Feedback/mo (-1=∞)', 'val'=>$editPlan['max_feedback_per_month'],'step'=>'1','min'=>'-1'],
            ['name'=>'max_emails',    'label'=>'Emails/mo (-1=∞)',   'val'=>$editPlan['max_emails'],    'step'=>'1',    'min'=>'-1'],
            ['name'=>'max_whatsapp',  'label'=>'WhatsApp/mo (-1=∞)', 'val'=>$editPlan['max_whatsapp'],  'step'=>'1',    'min'=>'-1'],
            ['name'=>'max_sms',       'label'=>'SMS/mo (-1=∞)',      'val'=>$editPlan['max_sms'],       'step'=>'1',    'min'=>'-1'],
            ['name'=>'max_campaigns', 'label'=>'Campaigns/mo (-1=∞)','val'=>$editPlan['max_campaigns_per_month'],'step'=>'1','min'=>'-1'],
          ];
          foreach ($numFields as $f): ?>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1"><?= $f['label'] ?></label>
            <input type="number" name="<?= $f['name'] ?>" value="<?= h($f['val']) ?>"
                   step="<?= $f['step'] ?>" min="<?= $f['min'] ?>"
                   class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
          </div>
          <?php endforeach; ?>

          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Highlight Color</label>
            <div class="flex gap-2">
              <input type="color" name="highlight_color" value="<?= h($editPlan['highlight_color'] ?? '#6366f1') ?>"
                     class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer">
              <input type="text" id="color_text" value="<?= h($editPlan['highlight_color'] ?? '#6366f1') ?>"
                     class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none font-mono">
            </div>
          </div>

          <!-- Feature toggles -->
          <div class="md:col-span-4">
            <label class="block text-xs font-semibold text-gray-600 mb-2">Feature Flags</label>
            <div class="flex flex-wrap gap-4">
              <?php
              $boolFields = [
                'allow_ai'=>'AI Copilot','allow_white_label'=>'White-label',
                'allow_api'=>'API Access','allow_export'=>'Bulk Export',
                'allow_automations'=>'Automations','allow_audit_logs'=>'Audit Logs',
                'allow_sso'=>'SSO','is_active'=>'Plan Active',
              ];
              foreach ($boolFields as $fname => $flabel): ?>
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" name="<?= $fname ?>" value="1"
                       class="rounded text-indigo-600"
                       <?= ((int)($editPlan[$fname] ?? 0)) ? 'checked' : '' ?>>
                <?= $flabel ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="md:col-span-4 flex gap-3">
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2 rounded-xl transition">
              <i class="fas fa-floppy-disk mr-1.5"></i>Save Plan
            </button>
            <a href="?" class="border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium px-4 py-2 rounded-xl transition">
              Cancel
            </a>
          </div>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Per-Company Overrides ────────────────────────────────────────── -->
    <div id="overrides" class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2 class="text-base font-bold text-gray-900 mb-5">Per-Company Limit Overrides</h2>
      <p class="text-sm text-gray-500 mb-5">
        Override replaces the computed effective limit (plan + add-ons) for a specific company.
        Set to <code class="bg-gray-100 px-1 rounded">-1</code> for unlimited.
      </p>

      <!-- Add new override -->
      <form method="post" class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6 p-4 bg-gray-50 rounded-xl">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="set_override">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Company</label>
          <select name="company_id" required
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">Select…</option>
            <?php foreach ($companies as $co): ?>
            <option value="<?= (int)$co['id'] ?>"><?= h($co['name']) ?> (<?= h($co['plan']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Resource</label>
          <select name="resource" required
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">Select…</option>
            <?php foreach ([
              'max_projects','max_users','max_feedback_per_month','max_emails',
              'max_whatsapp','max_sms','max_campaigns_per_month',
              'allow_ai','allow_white_label','allow_api','allow_export',
              'allow_automations','allow_audit_logs','allow_sso',
            ] as $res): ?>
            <option value="<?= $res ?>"><?= $res ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Value (-1=∞)</label>
          <input type="number" name="value" value="0" min="-1"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Note</label>
          <input type="text" name="note" placeholder="Reason…"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div class="flex items-end">
          <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 rounded-xl transition">
            Set Override
          </button>
        </div>
      </form>

      <!-- Existing overrides table -->
      <?php if (empty($overrides)): ?>
      <p class="text-gray-400 text-sm text-center py-6">No overrides set yet.</p>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100">
              <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500">Company</th>
              <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500">Resource</th>
              <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500">Value</th>
              <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500">Note</th>
              <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500">Set by</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($overrides as $ov): ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="px-3 py-2.5 font-medium text-gray-900"><?= h($ov['company_name'] ?? '#'.$ov['company_id']) ?></td>
              <td class="px-3 py-2.5 font-mono text-xs text-indigo-700"><?= h($ov['resource']) ?></td>
              <td class="px-3 py-2.5 text-right font-bold <?= (int)$ov['override_value']===-1 ? 'text-green-600' : 'text-gray-900' ?>">
                <?= (int)$ov['override_value']===-1 ? '∞' : number_format((int)$ov['override_value']) ?>
              </td>
              <td class="px-3 py-2.5 text-xs text-gray-500"><?= h($ov['note'] ?? '—') ?></td>
              <td class="px-3 py-2.5 text-xs text-gray-400"><?= h($ov['admin_name'] ?? '—') ?></td>
              <td class="px-3 py-2.5">
                <form method="post" class="inline">
                  <?= csrfInput() ?>
                  <input type="hidden" name="action"      value="remove_override">
                  <input type="hidden" name="override_id" value="<?= (int)$ov['id'] ?>">
                  <button class="text-red-400 hover:text-red-600 text-xs"
                          onclick="return confirm('Remove this override?')">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /p-6 -->
</main>
</div>
<script>
// Sync color picker and text input
const colorPicker = document.querySelector('input[type="color"][name="highlight_color"]');
const colorText   = document.getElementById('color_text');
if (colorPicker && colorText) {
  colorPicker.addEventListener('input', () => { colorText.value = colorPicker.value; });
  colorText.addEventListener('input', () => {
    if (/^#[0-9a-f]{6}$/i.test(colorText.value)) colorPicker.value = colorText.value;
  });
}
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
