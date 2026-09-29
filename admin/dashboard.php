<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Admin']);

$totalUsers = (int)db()->query("SELECT COUNT(*) FROM UserAccount WHERE Status='Active'")->fetchColumn();
$actionsToday = (int)db()->query("SELECT COUNT(*) FROM AuditLog WHERE DATE(Timestamp) = CURDATE()")->fetchColumn();

$byRole = db()->query(
    "SELECT r.RoleName, COUNT(*) AS cnt FROM UserAccount u JOIN Role r ON r.RoleID = u.RoleID
     WHERE u.Status='Active' GROUP BY r.RoleName ORDER BY cnt DESC"
)->fetchAll();
$maxRoleCount = max(1, max(array_column($byRole, 'cnt')));

$recentActivity = db()->query(
    "SELECT al.Action, al.Timestamp, ua.Username FROM AuditLog al
     LEFT JOIN UserAccount ua ON ua.UserID = al.UserID
     ORDER BY al.Timestamp DESC LIMIT 6"
)->fetchAll();

$pageTitle = 'Administrator Dashboard';
$breadcrumb = 'Dashboard';
$activeNav = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-4" style="margin-bottom:22px;">
  <div class="stat-card"><div><div class="lbl">Total Active Users</div><div class="val"><?= $totalUsers ?></div></div><div class="icon-wrap icon-blue"><i class="bi bi-people-fill"></i></div></div>
  <div class="stat-card"><div><div class="lbl">Actions Today</div><div class="val"><?= $actionsToday ?></div></div><div class="icon-wrap icon-gold"><i class="bi bi-activity"></i></div></div>
  <div class="stat-card"><div><div class="lbl">Roles Configured</div><div class="val"><?= count($byRole) ?></div></div><div class="icon-wrap icon-green"><i class="bi bi-shield-lock"></i></div></div>
  <div class="stat-card"><div><div class="lbl">System Version</div><div class="val" style="font-size:18px;"><?= h(APP_VERSION) ?></div></div><div class="icon-wrap icon-blue"><i class="bi bi-hdd-network"></i></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="panel"><div class="panel-head"><h6>Users by Role</h6></div>
    <div class="panel-body">
      <?php foreach ($byRole as $r): $pct = (int)round($r['cnt'] / $maxRoleCount * 100); ?>
        <div class="d-flex justify-content-between align-items-center mb-2" style="font-size:12.6px;">
          <span style="width:140px;"><?= h($r['RoleName']) ?></span>
          <div style="flex:1;"><div class="progress-modern"><div style="width:<?= $pct ?>%; background:var(--nbe-navy);"></div></div></div>
          <b style="width:30px; text-align:right;"><?= (int)$r['cnt'] ?></b>
        </div>
      <?php endforeach; ?>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="panel"><div class="panel-head"><h6>Recent Activity</h6><a href="<?= BASE_URL ?>/admin/audit.php" class="text-link">View log <i class="bi bi-arrow-right"></i></a></div>
    <div class="panel-body d-flex flex-column gap-3" style="font-size:12.6px;">
      <?php foreach ($recentActivity as $a): ?>
        <div class="d-flex gap-3"><i class="bi bi-dot" style="color:var(--nbe-navy); font-size:20px; line-height:0;"></i>
          <div><b><?= h($a['Action']) ?></b><div class="subtle"><?= h($a['Username'] ?? 'system') ?> &middot; <?= fdate($a['Timestamp'], 'M d, g:i A') ?></div></div></div>
      <?php endforeach; ?>
    </div></div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
