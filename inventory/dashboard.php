<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['InventoryOfficer']);

$totalItems = (int)db()->query("SELECT COUNT(*) FROM Asset")->fetchColumn();
$lowStockCount = (int)db()->query("SELECT COUNT(*) FROM Asset WHERE CurrentQty < MinStockLevel")->fetchColumn();
$pendingQueue = (int)db()->query("SELECT COUNT(*) FROM Request WHERE Status='Approved'")->fetchColumn();
$issuedThisMonth = (int)db()->query(
    "SELECT COUNT(*) FROM IssuedAsset WHERE MONTH(IssueDate)=MONTH(CURDATE()) AND YEAR(IssueDate)=YEAR(CURDATE())"
)->fetchColumn();

$lowStock = db()->query(
    "SELECT Name, CurrentQty, MinStockLevel FROM Asset WHERE CurrentQty < MinStockLevel ORDER BY (MinStockLevel - CurrentQty) DESC LIMIT 5"
)->fetchAll();

$readyToIssue = db()->query(
    "SELECT r.RequestID, e.FullName, GROUP_CONCAT(CONCAT(a.Name,' x',rd.Quantity) SEPARATOR ', ') AS Items
     FROM Request r JOIN Employee e ON e.EmployeeID = r.EmployeeID
     JOIN RequestDetail rd ON rd.RequestID = r.RequestID
     JOIN Asset a ON a.AssetID = rd.AssetID
     WHERE r.Status = 'Approved'
     GROUP BY r.RequestID ORDER BY r.ReviewedDate ASC LIMIT 5"
)->fetchAll();

$pageTitle = 'Inventory Dashboard';
$breadcrumb = 'Dashboard';
$activeNav = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-4" style="margin-bottom:22px;">
  <div class="stat-card"><div><div class="lbl">Total Stock Items</div><div class="val"><?= $totalItems ?></div></div><div class="icon-wrap icon-blue"><i class="bi bi-boxes"></i></div></div>
  <div class="stat-card"><div><div class="lbl">Low-Stock Alerts</div><div class="val"><?= $lowStockCount ?></div><div class="delta down">Needs reorder</div></div><div class="icon-wrap icon-red"><i class="bi bi-exclamation-triangle"></i></div></div>
  <div class="stat-card"><div><div class="lbl">Pending Queue</div><div class="val"><?= $pendingQueue ?></div><div class="delta up">Ready to issue</div></div><div class="icon-wrap icon-gold"><i class="bi bi-inbox"></i></div></div>
  <div class="stat-card"><div><div class="lbl">Issued this Month</div><div class="val"><?= $issuedThisMonth ?></div></div><div class="icon-wrap icon-green"><i class="bi bi-box-arrow-up-right"></i></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="panel">
      <div class="panel-head"><h6>Low Stock Alerts</h6><span class="badge-pill b-danger"><span class="dot"></span>Action needed</span></div>
      <?php if (empty($lowStock)): ?>
        <div class="empty-state"><i class="bi bi-check2-circle"></i>All stock levels are healthy.</div>
      <?php else: ?>
      <table class="table-modern"><thead><tr><th>Asset</th><th>Current</th><th>Minimum</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lowStock as $a): ?>
        <tr><td><?= h($a['Name']) ?></td><td><?= (int)$a['CurrentQty'] ?></td><td><?= (int)$a['MinStockLevel'] ?></td><td><span class="badge-pill b-danger"><span class="dot"></span>Low</span></td></tr>
      <?php endforeach; ?>
      </tbody></table>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <div class="panel-head"><h6>Approved &amp; Ready to Issue</h6></div>
      <?php if (empty($readyToIssue)): ?>
        <div class="empty-state"><i class="bi bi-inbox"></i>Nothing waiting to be issued.</div>
      <?php else: ?>
      <div class="panel-body d-flex flex-column gap-3" style="font-size:12.8px;">
        <?php foreach ($readyToIssue as $r): ?>
          <div class="d-flex justify-content-between">
            <div><b><?= h($r['FullName']) ?></b><div class="subtle"><?= h($r['Items']) ?></div></div>
            <a href="<?= BASE_URL ?>/inventory/issue.php?request_id=<?= (int)$r['RequestID'] ?>" class="text-link">Issue</a>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
