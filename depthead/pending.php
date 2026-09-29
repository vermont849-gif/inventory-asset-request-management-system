<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['DepartmentHead']);

$user = current_user();
$deptStmt = db()->prepare('SELECT DepartmentID FROM Employee WHERE EmployeeID = ?');
$deptStmt->execute([$user['employee_id']]);
$deptId = $deptStmt->fetchColumn();

$listStmt = db()->prepare(
    "SELECT r.RequestID, e.FullName, e.JobTitle, r.SubmittedDate,
            GROUP_CONCAT(a.Name SEPARATOR ', ') AS Items
     FROM Request r
     JOIN Employee e ON e.EmployeeID = r.EmployeeID
     LEFT JOIN RequestDetail rd ON rd.RequestID = r.RequestID
     LEFT JOIN Asset a ON a.AssetID = rd.AssetID
     WHERE e.DepartmentID = ? AND r.Status = 'Pending'
     GROUP BY r.RequestID ORDER BY r.SubmittedDate ASC"
);
$listStmt->execute([$deptId]);
$pendingList = $listStmt->fetchAll();

$pageTitle = 'Pending Requests';
$breadcrumb = 'Pending Requests';
$activeNav = 'Pending Requests';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
  <?php if (empty($pendingList)): ?>
    <div class="empty-state"><i class="bi bi-check2-circle"></i>No pending requests right now. All caught up!</div>
  <?php else: ?>
  <table class="table-modern">
    <thead><tr><th>Employee</th><th>Item(s)</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($pendingList as $r): ?>
      <tr>
        <td><div class="name-cell"><div class="avatar" style="background:var(--nbe-navy);"><?= h(initials($r['FullName'])) ?></div>
            <div><div class="nm"><?= h($r['FullName']) ?></div><div class="sub"><?= h($r['JobTitle']) ?></div></div></div></td>
        <td><?= h($r['Items'] ?: '—') ?></td>
        <td class="subtle"><?= fdate($r['SubmittedDate']) ?></td>
        <td><a href="<?= BASE_URL ?>/depthead/request_detail.php?id=<?= (int)$r['RequestID'] ?>" class="btn-outline-nbe" style="text-decoration:none; padding:6px 14px; font-size:12px;"><i class="bi bi-eye"></i> Review</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
