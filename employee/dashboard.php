<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Employee']);

$user = current_user();
$empId = $user['employee_id'];

// ---- Stat counts ----
$pending = db()->prepare("SELECT COUNT(*) FROM Request WHERE EmployeeID=? AND Status='Pending'");
$pending->execute([$empId]);
$pendingCount = (int)$pending->fetchColumn();

$approvedThisMonth = db()->prepare(
    "SELECT COUNT(*) FROM Request WHERE EmployeeID=? AND Status IN ('Approved','Fulfilled')
     AND MONTH(SubmittedDate)=MONTH(CURDATE()) AND YEAR(SubmittedDate)=YEAR(CURDATE())"
);
$approvedThisMonth->execute([$empId]);
$approvedCount = (int)$approvedThisMonth->fetchColumn();

$assignedAssets = db()->prepare(
    "SELECT COUNT(*) FROM IssuedAsset WHERE EmployeeID=? AND ReturnDate IS NULL"
);
$assignedAssets->execute([$empId]);
$assignedCount = (int)$assignedAssets->fetchColumn();

// ---- Recent requests ----
$recent = db()->prepare(
    "SELECT r.RequestID, r.RequestType, r.Status, r.SubmittedDate,
            GROUP_CONCAT(a.Name SEPARATOR ', ') AS Items
     FROM Request r
     LEFT JOIN RequestDetail rd ON rd.RequestID = r.RequestID
     LEFT JOIN Asset a ON a.AssetID = rd.AssetID
     WHERE r.EmployeeID = ?
     GROUP BY r.RequestID
     ORDER BY r.SubmittedDate DESC
     LIMIT 6"
);
$recent->execute([$empId]);
$requests = $recent->fetchAll();

$pageTitle = 'Dashboard';
$breadcrumb = 'Dashboard';
$activeNav = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-title-row">
  <div class="subtle">Welcome back, <b style="color:var(--nbe-text)"><?= h($user['full_name']) ?></b> &middot; <?= h($user['department']) ?></div>
  <a href="<?= BASE_URL ?>/employee/new_request.php" class="btn-nbe"><i class="bi bi-plus-lg"></i> New Request</a>
</div>

<div class="grid grid-4" style="margin-bottom:22px;">
  <div class="stat-card">
    <div><div class="lbl">Pending Requests</div><div class="val"><?= $pendingCount ?></div></div>
    <div class="icon-wrap icon-gold"><i class="bi bi-hourglass-split"></i></div>
  </div>
  <div class="stat-card">
    <div><div class="lbl">Approved This Month</div><div class="val"><?= $approvedCount ?></div></div>
    <div class="icon-wrap icon-green"><i class="bi bi-check-circle"></i></div>
  </div>
  <div class="stat-card">
    <div><div class="lbl">Assigned Assets</div><div class="val"><?= $assignedCount ?></div></div>
    <div class="icon-wrap icon-blue"><i class="bi bi-laptop"></i></div>
  </div>
  <div class="stat-card">
    <div><div class="lbl">Notifications</div><div class="val">&mdash;</div></div>
    <div class="icon-wrap icon-red"><i class="bi bi-bell"></i></div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <h6>Recent Requests</h6>
    <a href="<?= BASE_URL ?>/employee/my_requests.php" class="text-link">View all <i class="bi bi-arrow-right"></i></a>
  </div>
  <?php if (empty($requests)): ?>
    <div class="empty-state"><i class="bi bi-inbox"></i>You haven't submitted any requests yet.</div>
  <?php else: ?>
  <table class="table-modern">
    <thead><tr><th>Request ID</th><th>Type</th><th>Item(s)</th><th>Date</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($requests as $r): ?>
      <tr>
        <td style="font-weight:700; color:var(--nbe-navy);">R-<?= str_pad((string)$r['RequestID'], 4, '0', STR_PAD_LEFT) ?></td>
        <td><?= h($r['RequestType']) ?></td>
        <td><?= h($r['Items'] ?: '—') ?></td>
        <td class="subtle"><?= fdate($r['SubmittedDate']) ?></td>
        <td><?= status_badge($r['Status']) ?></td>
        <td><a href="<?= BASE_URL ?>/employee/request_detail.php?id=<?= (int)$r['RequestID'] ?>" class="text-link">View <i class="bi bi-arrow-right"></i></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
