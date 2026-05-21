<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentUser = $currentUser ?? Auth::user();
$can = fn($perm) => Auth::can($perm, $currentUser);
Auth::start();
$currentProject = null;
if (!empty($_SESSION['current_project_id'])) {
    $currentProject = DB::fetch("SELECT * FROM ff_projects WHERE id = ?", [$_SESSION['current_project_id']]);
}
$unreadNotifications = ($currentUser && $can('view_notifications')) ? getUnreadNotificationCount($currentUser['id']) : 0;
?>
<aside style="width:256px;min-width:256px" class="fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-100 flex flex-col shadow-sm">

  <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-white text-sm font-bold" style="background: linear-gradient(135deg,#6366f1,#8b5cf6)">
      <i class="fas fa-comments"></i>
    </div>
    <span class="font-bold text-gray-900 text-base tracking-tight"><?= APP_NAME ?></span>
  </div>

  <?php if (!empty($userProjects) && (($currentUser['role'] ?? '') !== 'viewer')): ?>
  <div class="px-3 py-3 border-b border-gray-50">
    <div x-data="{ open: false }" class="relative">
      <button @click="open = !open" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors text-left">
        <div class="w-6 h-6 rounded-lg flex items-center justify-center flex-shrink-0 text-white text-xs font-bold" style="background: linear-gradient(135deg,#6366f1,#8b5cf6)">
          <?= strtoupper(substr($currentProject['name'] ?? 'P', 0, 1)) ?>
        </div>
        <span class="flex-1 text-xs font-semibold text-gray-700 truncate"><?= $currentProject ? h($currentProject['name']) : 'Select project' ?></span>
        <i class="fas fa-chevron-down text-gray-400" style="font-size:10px"></i>
      </button>
      <div x-show="open" @click.outside="open = false" x-cloak class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-50 py-1 max-h-48 overflow-y-auto">
        <?php foreach ($userProjects as $proj): ?>
          <a href="?project_id=<?= $proj['id'] ?>" class="flex items-center gap-2.5 px-3 py-2 text-xs hover:bg-gray-50 transition-colors <?= ($currentProject['id'] ?? 0) == $proj['id'] ? 'text-indigo-600 font-semibold' : 'text-gray-700' ?>">
            <div class="w-5 h-5 rounded-md flex items-center justify-center text-white font-bold flex-shrink-0" style="font-size:9px;background:<?= ($currentProject['id'] ?? 0) == $proj['id'] ? '#6366f1' : '#94a3b8' ?>"><?= strtoupper(substr($proj['name'], 0, 1)) ?></div>
            <span class="truncate flex-1"><?= h($proj['name']) ?></span>
          </a>
        <?php endforeach; ?>
        <?php if ($can('view_projects')): ?>
        <div class="border-t border-gray-100 mt-1 pt-1">
          <a href="<?= APP_URL ?>/admin/projects.php?action=new" class="flex items-center gap-2 px-3 py-2 text-xs text-indigo-600 hover:bg-indigo-50 font-medium"><i class="fas fa-plus" style="font-size:10px"></i> New Project</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-0.5">

    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-1 mt-1">Main</p>
    <?php if ($can('view_dashboard')): ?>
    <a href="<?= APP_URL ?>/admin/index.php" class="nav-link <?= $currentPage==='index'?'active':'' ?>"><span class="icon"><i class="fas fa-chart-pie"></i></span>Dashboard</a>
    <?php endif; ?>

    <?php if ($can('view_all_feedback')): ?>
    <a href="<?= APP_URL ?>/admin/feedback.php" class="nav-link <?= $currentPage==='feedback'?'active':'' ?>">
      <span class="icon"><i class="fas fa-comments"></i></span><span class="flex-1">Feedback</span>
      <?php if ($currentProject) { $n=DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id=? AND status='new'",[$currentProject['id']]); if($n>0) echo '<span class="ml-auto bg-indigo-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">'.min($n,99).'</span>'; } ?>
    </a>
    <?php endif; ?>

    <?php // if ($can('view_tasks')): ?>
    <!--<a href="<?= APP_URL ?>/admin/tasks.php" class="nav-link <?= $currentPage==='tasks'?'active':'' ?>"><span class="icon"><i class="fas fa-tasks"></i></span>Tasks</a>-->
    <?php // endif; ?>
<?php /*
    <?php if ($can('view_notifications')): ?>
    <a href="<?= APP_URL ?>/admin/notifications.php" class="nav-link <?= $currentPage==='notifications'?'active':'' ?>">
      <span class="icon"><i class="fas fa-bell"></i></span><span class="flex-1">Notifications</span>
      <?php if ($unreadNotifications>0): ?><span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold"><?= min($unreadNotifications,99) ?></span><?php endif; ?>
    </a>
    <?php endif; ?>
*/ ?>
    <?php if ((($currentUser['role'] ?? '') !== 'viewer') && ($can('view_projects') || $can('run_campaigns') || $can('project_settings'))): ?>
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-1 mt-3">Collect</p>
    <?php endif; ?>
    <?php if ($can('view_projects') && (($currentUser['role'] ?? '') !== 'viewer')): ?>
    <a href="<?= APP_URL ?>/admin/projects.php" class="nav-link <?= $currentPage==='projects'?'active':'' ?>"><span class="icon"><i class="fas fa-folder"></i></span>Projects</a>
    <?php endif; ?>
  
    <?php if ($can('run_campaigns')): ?>
    <a href="<?= APP_URL ?>/admin/channels.php" class="nav-link <?= $currentPage==='channels'?'active':'' ?>"><span class="icon"><i class="fas fa-broadcast-tower"></i></span>Channels</a>
    <?php endif; ?>
    <?php if ($can('project_settings')): ?>
    <a href="<?= APP_URL ?>/admin/widget.php" class="nav-link <?= $currentPage==='widget'?'active':'' ?>"><span class="icon"><i class="fas fa-code"></i></span>Widget</a>
    <?php endif; ?>
    <?php if ($currentProject && $can('view_projects') && (($currentUser['role'] ?? '') !== 'viewer')): ?>
    <a href="<?= APP_URL ?>/public/board.php?slug=<?= h($currentProject['slug']) ?>" target="_blank" class="nav-link">
      <span class="icon"><i class="fas fa-globe"></i></span><span class="flex-1">Public Board</span><i class="fas fa-external-link-alt text-gray-300" style="font-size:9px"></i>
    </a>
    <?php endif; ?>

    <?php if ($can('run_campaigns')): ?>
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-1 mt-3">Outreach</p>
    <a href="<?= APP_URL ?>/admin/email-campaigns.php" class="nav-link <?= $currentPage==='email-campaigns'?'active':'' ?>"><span class="icon"><i class="fas fa-paper-plane"></i></span>Campaigns</a>
    <!--<a href="<?= APP_URL ?>/admin/review-booster.php" class="nav-link <?= $currentPage==='review-booster'?'active':'' ?>"><span class="icon"><i class="fas fa-star"></i></span>Review Booster</a>-->
    <!--<a href="<?= APP_URL ?>/admin/suppression.php" class="nav-link <?= $currentPage==='suppression'?'active':'' ?>"><span class="icon"><i class="fas fa-ban"></i></span>Suppression List</a>-->
    <!--<a href="<?= APP_URL ?>/admin/automations.php" class="nav-link <?= $currentPage==='automations'?'active':'' ?>"><span class="icon"><i class="fas fa-bolt"></i></span>Automations</a>-->
    <?php endif; ?>

    <?php if ($can('manage_roadmap')): ?>
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-1 mt-3">Publish</p>
    <!--<a href="<?= APP_URL ?>/admin/roadmap.php" class="nav-link <?= $currentPage==='roadmap'?'active':'' ?>"><span class="icon"><i class="fas fa-road"></i></span>Roadmap</a>-->
    <!--<a href="<?= APP_URL ?>/admin/changelog.php" class="nav-link <?= $currentPage==='changelog'?'active':'' ?>"><span class="icon"><i class="fas fa-newspaper"></i></span>Changelog</a>-->
    <!--<a href="<?= APP_URL ?>/admin/status.php" class="nav-link <?= $currentPage==='status'?'active':'' ?>"><span class="icon"><i class="fas fa-heartbeat"></i></span>Status Pages</a>-->
    <?php endif; ?>

    <?php if ($can('view_intelligence')): ?>
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-1 mt-3">Intelligence</p>
    <a href="<?= APP_URL ?>/admin/analytics.php" class="nav-link <?= $currentPage==='analytics'?'active':'' ?>"><span class="icon"><i class="fas fa-chart-bar"></i></span>Analytics</a>
    <!--<a href="<?= APP_URL ?>/admin/reports.php" class="nav-link <?= $currentPage==='reports'?'active':'' ?>"><span class="icon"><i class="fas fa-chart-line"></i></span>Reports</a>-->
    <!--<a href="<?= APP_URL ?>/admin/ai-insights.php" class="nav-link <?= $currentPage==='ai-insights'?'active':'' ?>"><span class="icon"><i class="fas fa-brain"></i></span>AI Insights</a>-->
    <?php endif; ?>

    <?php if ($can('invite_manage_users') || $can('project_settings') || $can('manage_billing_plan')): ?>
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-1 mt-3">Account</p>
    <?php endif; ?>
    <?php if ($can('invite_manage_users')): ?>
    <!--<a href="<?= APP_URL ?>/admin/team.php" class="nav-link <?= $currentPage==='team'?'active':'' ?>"><span class="icon"><i class="fas fa-users"></i></span>Team</a>-->
    <?php endif; ?>
    <?php if ($can('project_settings')): ?>
    <!--<a href="<?= APP_URL ?>/admin/integrations.php" class="nav-link <?= $currentPage==='integrations'?'active':'' ?>"><span class="icon"><i class="fas fa-plug"></i></span>Integrations</a>-->
    <!--<a href="<?= APP_URL ?>/admin/api-keys.php" class="nav-link <?= $currentPage==='api-keys'?'active':'' ?>"><span class="icon"><i class="fas fa-key"></i></span>API Keys</a>-->
    <!--<a href="<?= APP_URL ?>/admin/export.php" class="nav-link <?= $currentPage==='export'?'active':'' ?>"><span class="icon"><i class="fas fa-download"></i></span>Export / Import</a>-->
    <a href="<?= APP_URL ?>/admin/settings.php" class="nav-link <?= $currentPage==='settings'?'active':'' ?>"><span class="icon"><i class="fas fa-cog"></i></span>Settings</a>
    <?php endif; ?>
    <?php if ($can('manage_billing_plan')): ?>
    <a href="<?= APP_URL ?>/admin/billing.php" class="nav-link <?= $currentPage==='billing'?'active':'' ?>"><span class="icon"><i class="fas fa-credit-card"></i></span>Billing</a>
    <?php endif; ?>

    <?php if ($currentUser && Auth::isAdmin($currentUser)): ?>
    <!--<p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-1 mt-3">Compliance</p>-->
    <!--<a href="<?= APP_URL ?>/admin/audit-logs.php" class="nav-link <?= $currentPage==='audit-logs'?'active':'' ?>"><span class="icon"><i class="fas fa-list-alt"></i></span>Audit Logs</a>-->
    <?php endif; ?>

    <?php if ($currentUser && !empty($currentUser['is_super_admin'])): ?>
    <p class="text-xs font-semibold text-red-400 uppercase tracking-wider px-3 mb-1 mt-3">Super Admin</p>
    <a href="<?= APP_URL ?>/admin/super-admin.php" class="nav-link <?= $currentPage==='super-admin'?'active':'' ?>" style="color:#ef4444">
      <span class="icon" style="color:#ef4444"><i class="fas fa-shield-alt"></i></span>Super Admin
    </a>
    <?php endif; ?>

  </nav>

  <div class="border-t border-gray-100 p-3 space-y-2">
    <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-gray-50">
      <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0" style="background: linear-gradient(135deg,#6366f1,#8b5cf6)">
        <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-800 truncate leading-tight"><?= h($currentUser['name'] ?? '') ?></p>
        <p class="text-xs text-gray-400 truncate leading-tight"><?= h($currentUser['email'] ?? '') ?></p>
      </div>
    </div>

    <a href="<?= APP_URL ?>/index.php?action=logout" class="flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-xl transition-colors">
      <i class="fas fa-sign-out-alt w-4 text-center" style="font-size:13px"></i>
      Sign Out
    </a>
  </div>

</aside>
