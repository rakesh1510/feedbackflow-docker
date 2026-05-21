<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/billing.php';
require_once dirname(__DIR__) . '/includes/user-management.php';

$currentUser = Auth::require();

// ── Resolve company ─────────────────────────────────────────────────────────
$company = null;
try {
    $company = BillingService::getCompany($currentUser['id']);
} catch (\Throwable $e) { }

if (!$company) {
    // Fallback: treat user as their own company context
    $companyId   = (int)($currentUser['company_id'] ?? $currentUser['id']);
    $companyName = APP_NAME;
    $planSlug    = 'starter';
} else {
    $companyId   = (int)$company['id'];
    $companyName = $company['name'];
    $planSlug    = $company['plan'] ?? 'starter';
}

$canInvite = UserManagement::canInvite($currentUser);

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { flash('error', 'Security check failed.'); redirect(APP_URL . '/admin/team.php'); }

    $action = $_POST['_action'] ?? '';
    $error  = '';

    // Only admins/owners may perform write actions
    if (!$canInvite && in_array($action, ['invite','resend_invite','change_role','deactivate','reactivate','remove'])) {
        flash('error', 'You do not have permission to manage users.');
        redirect(APP_URL . '/admin/team.php');
    }

    if ($action === 'invite') {
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role']  ?? 'member';
        $ok = UserManagement::invite($companyId, $currentUser['id'], $email, $role, $companyName, $error);
        flash($ok ? 'success' : 'error', $ok ? "Invitation sent to {$email}." : $error);
    }

    elseif ($action === 'resend_invite') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $ok  = UserManagement::resendInvite($uid, $companyName, $error);
        flash($ok ? 'success' : 'error', $ok ? 'Invite resent.' : $error);
    }

    elseif ($action === 'change_role') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? 'member';
        $ok   = UserManagement::changeRole($companyId, $uid, $role, $currentUser, $error);
        flash($ok ? 'success' : 'error', $ok ? 'Role updated.' : $error);
    }

    elseif ($action === 'deactivate') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $ok  = UserManagement::deactivate($companyId, $uid, $currentUser, $error);
        flash($ok ? 'success' : 'error', $ok ? 'User deactivated.' : $error);
    }

    elseif ($action === 'reactivate') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $ok  = UserManagement::reactivate($companyId, $uid, $currentUser, $error);
        flash($ok ? 'success' : 'error', $ok ? 'User reactivated.' : $error);
    }

    elseif ($action === 'remove') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $ok  = UserManagement::removeFromCompany($companyId, $uid, $currentUser, $error);
        flash($ok ? 'success' : 'error', $ok ? 'User removed from company.' : $error);
    }

    redirect(APP_URL . '/admin/team.php');
}

// ── Data ─────────────────────────────────────────────────────────────────────
$members        = UserManagement::getCompanyUsers($companyId);
$limitCheck     = UserManagement::checkLimit($companyId);
$effectiveLimits= [];
try { $effectiveLimits = BillingService::getEffectiveLimits($companyId); } catch (\Throwable $e) { }

$usedUsers  = $limitCheck['used'];
$maxUsers   = $limitCheck['limit']; // -1 = unlimited
$atLimit    = !$limitCheck['ok'];
$nearLimit  = ($maxUsers > 0 && $maxUsers !== -1 && ($usedUsers / $maxUsers) >= 0.8);

$pageTitle = 'Team – ' . APP_NAME;
include dirname(__DIR__) . '/includes/header.php';

function statusBadgeUser(string $status): string {
    return match($status) {
        'invited'  => '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span>Invited</span>',
        'disabled' => '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600 border border-red-200"><span class="w-1.5 h-1.5 rounded-full bg-red-400 inline-block"></span>Disabled</span>',
        default    => '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200"><span class="w-1.5 h-1.5 rounded-full bg-green-400 inline-block"></span>Active</span>',
    };
}
?>
<div class="flex h-full">
<?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="ml-64 flex-1 overflow-y-auto">

  <!-- Header -->
  <header class="sticky top-0 z-40 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Team Members</h1>
      <p class="text-sm text-gray-500 mt-0.5"><?= h($companyName) ?> · <?= ucfirst($planSlug) ?> plan</p>
    </div>
    <?php if ($canInvite && !$atLimit): ?>
      <button onclick="document.getElementById('inviteModal').classList.remove('hidden')"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
        <i class="fas fa-user-plus"></i> Invite Member
      </button>
    <?php elseif ($canInvite && $atLimit): ?>
      <a href="<?= APP_URL ?>/admin/billing.php"
         class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
        <i class="fas fa-arrow-up"></i> Upgrade to Invite More
      </a>
    <?php endif; ?>
  </header>

  <?php foreach (getFlash() as $f): ?>
    <div data-flash class="mx-6 mt-4 px-4 py-3 rounded-xl text-sm font-medium
      <?= $f['type']==='success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
      <i class="fas fa-<?= $f['type']==='success'?'check-circle':'exclamation-circle' ?> mr-2"></i><?= h($f['msg']) ?>
    </div>
  <?php endforeach; ?>

  <div class="p-6 space-y-6">

    <!-- Usage bar -->
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
      <div class="flex items-center justify-between mb-3">
        <div>
          <p class="text-sm font-semibold text-gray-700">Team Members</p>
          <p class="text-xs text-gray-400 mt-0.5">Active users in your company</p>
        </div>
        <div class="text-right">
          <?php if ($maxUsers === -1): ?>
            <p class="text-lg font-bold text-gray-900"><?= $usedUsers ?> <span class="text-sm font-normal text-gray-400">/ unlimited</span></p>
          <?php else: ?>
            <p class="text-lg font-bold text-gray-900"><?= $usedUsers ?> <span class="text-sm font-normal text-gray-400">/ <?= $maxUsers ?></span></p>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($maxUsers > 0 && $maxUsers !== -1): ?>
        <?php $pct = min(100, (int)(($usedUsers/$maxUsers)*100)); ?>
        <div class="w-full bg-gray-100 rounded-full h-2.5">
          <div class="h-2.5 rounded-full transition-all <?= $atLimit ? 'bg-red-500' : ($nearLimit ? 'bg-amber-400' : 'bg-indigo-500') ?>"
               style="width:<?= $pct ?>%"></div>
        </div>
        <?php if ($atLimit): ?>
          <p class="text-xs text-red-600 mt-2 font-medium">
            <i class="fas fa-ban mr-1"></i>User limit reached.
            <a href="<?= APP_URL ?>/admin/billing.php" class="underline">Upgrade plan or add Extra Users add-on.</a>
          </p>
        <?php elseif ($nearLimit): ?>
          <p class="text-xs text-amber-600 mt-2 font-medium">
            <i class="fas fa-exclamation-triangle mr-1"></i>Approaching limit (<?= $pct ?>% used).
            <a href="<?= APP_URL ?>/admin/billing.php" class="underline">Upgrade now.</a>
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Members table -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-900">All Members <span class="ml-1 text-sm font-normal text-gray-400">(<?= count($members) ?>)</span></h2>
        <div class="flex gap-2">
          <input id="memberSearch" type="search" placeholder="Search…"
                 class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 w-48"
                 oninput="filterMembers(this.value)">
        </div>
      </div>

      <?php if (empty($members)): ?>
        <div class="py-16 text-center text-gray-400">
          <i class="fas fa-users text-4xl block mb-3 text-gray-200"></i>
          <p class="font-medium text-gray-500">No team members yet</p>
          <?php if ($canInvite): ?>
            <p class="text-sm mt-1">Invite your first team member to get started.</p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div id="memberList">
        <?php foreach ($members as $m):
            $mStatus = $m['status'] ?? ($m['is_active'] ? 'active' : 'disabled');
            $isMe    = ((int)$m['id'] === (int)$currentUser['id']);
            $isOwner = ($m['role'] === 'owner');
            $canEdit = $canInvite && !$isMe && !$isOwner;
        ?>
          <div class="member-row flex items-center gap-4 px-6 py-4 border-b border-gray-50 hover:bg-gray-50/50 transition"
               data-name="<?= strtolower(h($m['name'])) ?>" data-email="<?= strtolower(h($m['email'])) ?>">
            <!-- Avatar -->
            <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center font-bold text-sm
              <?= $isOwner ? 'bg-indigo-600 text-white' : ($mStatus==='invited' ? 'bg-amber-100 text-amber-700' : ($mStatus==='disabled' ? 'bg-gray-200 text-gray-400' : 'bg-indigo-100 text-indigo-700')) ?>">
              <?= strtoupper(substr($m['name'] ?? $m['email'], 0, 1)) ?>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="font-medium text-gray-900 <?= $mStatus==='disabled'?'opacity-50':'' ?>">
                  <?= h($m['name']) ?>
                  <?php if ($isMe): ?><span class="text-xs text-gray-400">(you)</span><?php endif; ?>
                </p>
                <?= statusBadgeUser($mStatus) ?>
              </div>
              <p class="text-sm text-gray-500"><?= h($m['email']) ?></p>
              <p class="text-xs text-gray-400 mt-0.5">
                <?php if ($mStatus === 'invited'): ?>
                  <i class="fas fa-envelope mr-1"></i>Invite pending
                  <?php if ($m['invited_by_name']): ?> · Invited by <?= h($m['invited_by_name']) ?><?php endif; ?>
                <?php elseif ($m['last_login']): ?>
                  Last login <?= timeAgo($m['last_login']) ?>
                <?php else: ?>
                  Never logged in
                <?php endif; ?>
              </p>
            </div>

            <!-- Role badge / selector -->
            <div class="flex items-center gap-3">
              <?php if ($isOwner || !$canEdit): ?>
                <span class="text-sm font-medium text-gray-600 bg-gray-100 px-3 py-1 rounded-lg">
                  <?= ucfirst($m['role']) ?>
                </span>
              <?php else: ?>
                <form method="POST">
                  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                  <input type="hidden" name="_action" value="change_role">
                  <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                  <select name="role" onchange="this.form.submit()"
                          class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                    <?php foreach (['admin'=>'Admin','manager'=>'Manager','member'=>'Staff','viewer'=>'Viewer'] as $rv => $rl): ?>
                      <option value="<?= $rv ?>" <?= $m['role']===$rv?'selected':'' ?>><?= $rl ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              <?php endif; ?>

              <!-- Actions dropdown -->
              <?php if ($canEdit): ?>
                <div class="relative" x-data="{open:false}">
                  <button @click="open=!open" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-ellipsis-v"></i>
                  </button>
                  <div x-show="open" @click.away="open=false" x-cloak
                       class="absolute right-0 mt-1 w-44 bg-white border border-gray-200 rounded-xl shadow-lg z-30 py-1">
                    <?php if ($mStatus === 'invited'): ?>
                      <form method="POST">
                        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                        <input type="hidden" name="_action" value="resend_invite">
                        <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                        <button class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                          <i class="fas fa-paper-plane w-4 text-indigo-500"></i>Resend Invite
                        </button>
                      </form>
                    <?php endif; ?>

                    <?php if ($mStatus === 'active'): ?>
                      <form method="POST" onsubmit="return confirm('Deactivate this user?')">
                        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                        <input type="hidden" name="_action" value="deactivate">
                        <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                        <button class="w-full text-left px-4 py-2 text-sm text-amber-700 hover:bg-amber-50 flex items-center gap-2">
                          <i class="fas fa-user-slash w-4"></i>Deactivate
                        </button>
                      </form>
                    <?php elseif ($mStatus === 'disabled'): ?>
                      <form method="POST">
                        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                        <input type="hidden" name="_action" value="reactivate">
                        <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                        <button class="w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50 flex items-center gap-2">
                          <i class="fas fa-user-check w-4"></i>Reactivate
                        </button>
                      </form>
                    <?php endif; ?>

                    <form method="POST" onsubmit="return confirm('Remove this user from the company?')">
                      <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                      <input type="hidden" name="_action" value="remove">
                      <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                      <button class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                        <i class="fas fa-user-times w-4"></i>Remove
                      </button>
                    </form>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Role permissions reference -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Role Permissions</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 text-gray-500 text-xs uppercase tracking-wide">
              <th class="text-left py-2 font-medium">Permission</th>
              <th class="text-center py-2 px-3 font-medium">Owner</th>
              <th class="text-center py-2 px-3 font-medium">Admin</th>
              <th class="text-center py-2 px-3 font-medium">Manager</th>
              <th class="text-center py-2 px-3 font-medium">Staff</th>
              <th class="text-center py-2 px-3 font-medium">Viewer</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ([
              ['Invite & manage users',    '✅','✅','❌','❌','❌'],
              ['Manage billing & plan',    '✅','❌','❌','❌','❌'],
              ['View all feedback',        '✅','✅','✅','✅','✅'],
              ['Reply & resolve feedback', '✅','✅','✅','✅','❌'],
              ['Manage roadmap',           '✅','✅','✅','❌','❌'],
              ['Run campaigns',            '✅','✅','✅','❌','❌'],
              ['Project settings',         '✅','✅','❌','❌','❌'],
              ['Delete project',           '✅','❌','❌','❌','❌'],
            ] as $pRow): $label = $pRow[0]; $vals = array_slice($pRow, 1); ?>
              <tr class="hover:bg-gray-50">
                <td class="py-2.5 text-gray-700"><?= $label ?></td>
                <?php foreach ($vals as $v): ?><td class="text-center py-2.5 px-3"><?= $v ?></td><?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /p-6 -->
</main>
</div>

<!-- Invite Modal -->
<div id="inviteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-lg font-bold text-gray-900">Invite Team Member</h3>
      <button onclick="document.getElementById('inviteModal').classList.add('hidden')"
              class="text-gray-400 hover:text-gray-600 p-1"><i class="fas fa-times"></i></button>
    </div>

    <?php if ($atLimit): ?>
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <strong>User limit reached (<?= $usedUsers ?>/<?= $maxUsers ?>).</strong><br>
        <a href="<?= APP_URL ?>/admin/billing.php" class="underline font-semibold">Upgrade your plan or add the Extra Users add-on</a> to invite more members.
      </div>
    <?php else: ?>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="_action" value="invite">

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
          <input type="email" name="email" required autofocus placeholder="teammate@company.com"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
          <select name="role" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="admin">Admin — Can invite & manage users</option>
            <option value="manager">Manager — Manages feedback & roadmap</option>
            <option value="member" selected>Staff — Views & responds to feedback</option>
            <option value="viewer">Viewer — Read-only access</option>
          </select>
        </div>

        <?php if ($maxUsers > 0 && $maxUsers !== -1): ?>
          <div class="flex items-center gap-2 text-xs text-gray-500 bg-gray-50 rounded-xl px-3 py-2.5">
            <i class="fas fa-users text-indigo-400"></i>
            <?= $usedUsers ?> / <?= $maxUsers ?> seats used
            <?php if ($nearLimit): ?><span class="text-amber-600 font-medium ml-1">(<?= min(100,(int)(($usedUsers/$maxUsers)*100)) ?>% full)</span><?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="bg-blue-50 border border-blue-100 rounded-xl p-3 text-xs text-blue-700">
          <i class="fas fa-info-circle mr-1"></i>
          The invitee will receive an email with a secure link to set their password and join your workspace.
          The link expires in 72 hours.
        </div>

        <div class="flex gap-3 pt-1">
          <button type="submit"
                  class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl transition">
            <i class="fas fa-paper-plane mr-1"></i> Send Invitation
          </button>
          <button type="button" onclick="document.getElementById('inviteModal').classList.add('hidden')"
                  class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50 transition">
            Cancel
          </button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
function filterMembers(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.member-row').forEach(row => {
        const match = row.dataset.name.includes(q) || row.dataset.email.includes(q);
        row.style.display = match ? '' : 'none';
    });
}
// Auto-dismiss flash messages
document.querySelectorAll('[data-flash]').forEach(el => setTimeout(() => el.remove(), 5000));
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
