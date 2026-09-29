<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['DepartmentHead']);

$user = current_user();

// A department head reviews requests from employees in their own department.
$deptStmt = db()->prepare('SELECT DepartmentID FROM Employee WHERE EmployeeID = ?');
$deptStmt->execute([$user['employee_id']]);
$deptId = $deptStmt->fetchColumn();

$pendingStmt = db()->prepare(
    "SELECT COUNT(*) FROM Request r JOIN Employee e ON e.EmployeeID = r.EmployeeID
     WHERE e.DepartmentID = ? AND r.Status = 'Pending'"
);
$pendingStmt->execute([$deptId]);
$pendingCount = (int)$pendingStmt->fetchColumn();

$approvedStmt = db()->prepare(
    "SELECT COUNT(*) FROM Request r JOIN Employee e ON e.EmployeeID = r.EmployeeID
     WHERE e.DepartmentID = ? AND r.Status IN ('Approved','Fulfilled')
     AND MONTH(r.ReviewedDate)=MONTH(CURDATE()) AND YEAR(r.ReviewedDate)=YEAR(CURDATE())"
);
$approvedStmt->execute([$deptId]);
$approvedCount = (int)$approvedStmt->fetchColumn();

$teamStmt = db()->prepare("SELECT COUNT(*) FROM Employee WHERE DepartmentID = ? AND Status='Active'");
$teamStmt->execute([$deptId]);
$teamCount = (int)$teamStmt->fetchColumn();

$listStmt = db()->prepare(
    "SELECT r.RequestID, e.FullName, e.JobTitle, r.SubmittedDate,
            GROUP_CONCAT(a.Name SEPARATOR ', ') AS Items
     FROM Request r
     JOIN Employee e ON e.EmployeeID = r.EmployeeID
     LEFT JOIN RequestDetail rd ON rd.RequestID = r.RequestID
     LEFT JOIN Asset a ON a.AssetID = rd.AssetID
     WHERE e.DepartmentID = ? AND r.Status = 'Pending'
     GROUP BY r.RequestID ORDER BY r.SubmittedDate ASC LIMIT 5"
);
$listStmt->execute([$deptId]);
$pendingList = $listStmt->fetchAll();

$pageTitle = 'Department Head Dashboard';
$breadcrumb = 'Dashboard';
$activeNav = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="subtle mb-3"><?= h($user['department']) ?></div>

<div class="grid grid-3" style="margin-bottom:22px;">
  <div class="stat-card">
    <div><div class="lbl">Pending Requests</div><div class="val"><?= $pendingCount ?></div><div class="delta up">Needs your review</div></div>
    <div class="icon-wrap icon-gold"><i class="bi bi-hourglass-split"></i></div>
  </div>
  <div class="stat-card">
    <div><div class="lbl">Approved this Month</div><div class="val"><?= $approvedCount ?></div></div>
    <div class="icon-wrap icon-green"><i class="bi bi-check-circle"></i></div>
  </div>
  <div class="stat-card">
    <div><div class="lbl">Team Members</div><div class="val"><?= $teamCount ?></div></div>
    <div class="icon-wrap icon-blue"><i class="bi bi-people"></i></div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <h6>Requests Awaiting Your Decision</h6>
    <a href="<?= BASE_URL ?>/depthead/pending.php" class="text-link">View all <i class="bi bi-arrow-right"></i></a>
  </div>
  <?php if (empty($pendingList)): ?>
    <div class="empty-state"><i class="bi bi-check2-circle"></i>No pending requests right now. All caught up!</div>
  <?php else: ?>
  <table class="table-modern">
    <thead><tr><th>Employee</th><th>Item(s)</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pendingList as $r): ?>
      <tr>
        <td><div class="name-cell"><div class="avatar" style="background:var(--nbe-navy);"><?= h(initials($r['FullName'])) ?></div>
            <div><div class="nm"><?= h($r['FullName']) ?></div><div class="sub"><?= h($r['JobTitle']) ?></div></div></div></td>
        <td><?= h($r['Items'] ?: '—') ?></td>
        <td class="subtle"><?= fdate($r['SubmittedDate']) ?></td>
        <td><a href="<?= BASE_URL ?>/depthead/request_detail.php?id=<?= (int)$r['RequestID'] ?>" class="btn-outline-nbe" style="text-decoration:none; padding:6px 14px; font-size:12px;">Review</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
