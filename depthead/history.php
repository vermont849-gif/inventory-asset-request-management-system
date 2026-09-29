<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['DepartmentHead']);

$user = current_user();
$deptStmt = db()->prepare('SELECT DepartmentID FROM Employee WHERE EmployeeID = ?');
$deptStmt->execute([$user['employee_id']]);
$deptId = $deptStmt->fetchColumn();

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT r.RequestID, e.FullName, r.RequestType, r.Status, r.SubmittedDate,
               GROUP_CONCAT(a.Name SEPARATOR ', ') AS Items
        FROM Request r
        JOIN Employee e ON e.EmployeeID = r.EmployeeID
        LEFT JOIN RequestDetail rd ON rd.RequestID = r.RequestID
        LEFT JOIN Asset a ON a.AssetID = rd.AssetID
        WHERE e.DepartmentID = :dept AND r.Status <> 'Pending'";
$params = [':dept' => $deptId];
if ($statusFilter !== '') {
    $sql .= " AND r.Status = :status";
    $params[':status'] = $statusFilter;
}
$sql .= " GROUP BY r.RequestID ORDER BY r.ReviewedDate DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

$pageTitle = 'Department History';
$breadcrumb = 'Dept. History';
$activeNav = 'Dept. History';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex gap-2 mb-3">
  <a href="?status=" class="chip" style="text-decoration:none; <?= $statusFilter==='' ? 'background:var(--nbe-navy); color:#fff;' : '' ?>">All</a>
  <a href="?status=Approved" class="chip" style="text-decoration:none; <?= $statusFilter==='Approved' ? 'background:var(--nbe-navy); color:#fff;' : '' ?>">Approved</a>
  <a href="?status=Rejected" class="chip" style="text-decoration:none; <?= $statusFilter==='Rejected' ? 'background:var(--nbe-navy); color:#fff;' : '' ?>">Rejected</a>
  <a href="?status=Returned" class="chip" style="text-decoration:none; <?= $statusFilter==='Returned' ? 'background:var(--nbe-navy); color:#fff;' : '' ?>">Returned</a>
</div>

<div class="panel">
  <?php if (empty($history)): ?>
    <div class="empty-state"><i class="bi bi-clock-history"></i>No reviewed requests found for this filter.</div>
  <?php else: ?>
  <table class="table-modern">
    <thead><tr><th>Req ID</th><th>Employee</th><th>Item(s)</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($history as $r): ?>
      <tr>
        <td style="font-weight:700; color:var(--nbe-navy);">R-<?= str_pad((string)$r['RequestID'],4,'0',STR_PAD_LEFT) ?></td>
        <td><?= h($r['FullName']) ?></td>
        <td><?= h($r['Items'] ?: '—') ?></td>
        <td class="subtle"><?= fdate($r['SubmittedDate']) ?></td>
        <td><?= status_badge($r['Status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
