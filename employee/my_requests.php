<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Employee']);

$user = current_user();
$empId = $user['employee_id'];

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT r.RequestID, r.RequestType, r.Status, r.SubmittedDate,
               GROUP_CONCAT(a.Name SEPARATOR ', ') AS Items
        FROM Request r
        LEFT JOIN RequestDetail rd ON rd.RequestID = r.RequestID
        LEFT JOIN Asset a ON a.AssetID = rd.AssetID
        WHERE r.EmployeeID = :emp";
$params = [':emp' => $empId];
if ($statusFilter !== '') {
    $sql .= " AND r.Status = :status";
    $params[':status'] = $statusFilter;
}
$sql .= " GROUP BY r.RequestID ORDER BY r.SubmittedDate DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$pageTitle = 'My Requests';
$breadcrumb = 'My Requests';
$activeNav = 'My Requests';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center" style="margin-bottom:18px;">
  <div class="d-flex gap-2">
    <a href="?status=" class="chip" style="text-decoration:none; <?= $statusFilter==='' ? 'background:var(--nbe-navy); color:#fff;' : '' ?>"><i class="bi bi-funnel"></i> All</a>
    <a href="?status=Pending" class="chip" style="text-decoration:none; <?= $statusFilter==='Pending' ? 'background:var(--nbe-navy); color:#fff;' : '' ?>">Pending</a>
    <a href="?status=Approved" class="chip" style="text-decoration:none; <?= $statusFilter==='Approved' ? 'background:var(--nbe-navy); color:#fff;' : '' ?>">Approved</a>
    <a href="?status=Rejected" class="chip" style="text-decoration:none; <?= $statusFilter==='Rejected' ? 'background:var(--nbe-navy); color:#fff;' : '' ?>">Rejected</a>
  </div>
  <a href="<?= BASE_URL ?>/employee/new_request.php" class="btn-nbe" style="text-decoration:none;"><i class="bi bi-plus-lg"></i> New Request</a>
</div>

<div class="panel">
  <?php if (empty($requests)): ?>
    <div class="empty-state"><i class="bi bi-inbox"></i>No requests found for this filter.</div>
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
