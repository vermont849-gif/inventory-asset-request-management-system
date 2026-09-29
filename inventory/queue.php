<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['InventoryOfficer']);

$queue = db()->query(
    "SELECT r.RequestID, e.FullName, r.ReviewedDate,
            GROUP_CONCAT(CONCAT(a.Name,' x',rd.Quantity) SEPARATOR ', ') AS Items
     FROM Request r
     JOIN Employee e ON e.EmployeeID = r.EmployeeID
     JOIN RequestDetail rd ON rd.RequestID = r.RequestID
     JOIN Asset a ON a.AssetID = rd.AssetID
     WHERE r.Status = 'Approved'
     GROUP BY r.RequestID
     ORDER BY r.ReviewedDate ASC"
)->fetchAll();

$pageTitle = 'Approved Requests Queue';
$breadcrumb = 'Approved Queue';
$activeNav = 'Approved Queue';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
  <?php if (empty($queue)): ?>
    <div class="empty-state"><i class="bi bi-inbox"></i>No approved requests are waiting to be issued.</div>
  <?php else: ?>
  <table class="table-modern">
    <thead><tr><th>Employee</th><th>Item(s)</th><th>Approved Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($queue as $r): ?>
      <tr>
        <td><?= h($r['FullName']) ?></td>
        <td><?= h($r['Items']) ?></td>
        <td class="subtle"><?= fdate($r['ReviewedDate']) ?></td>
        <td><a href="<?= BASE_URL ?>/inventory/issue.php?request_id=<?= (int)$r['RequestID'] ?>" class="btn-nbe" style="text-decoration:none; padding:7px 14px; font-size:12px; border:none;"><i class="bi bi-box-arrow-up-right"></i> Issue</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
