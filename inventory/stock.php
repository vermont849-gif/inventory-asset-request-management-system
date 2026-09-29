<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['InventoryOfficer', 'Procurement']);

$assets = db()->query(
    "SELECT a.Name, a.CurrentQty, a.MinStockLevel, c.CategoryName
     FROM Asset a LEFT JOIN Category c ON c.CategoryID = a.CategoryID
     ORDER BY (a.CurrentQty < a.MinStockLevel) DESC, a.Name"
)->fetchAll();

$pageTitle = 'Stock Overview';
$breadcrumb = 'Stock Overview';
$activeNav = 'Stock Overview';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
  <table class="table-modern">
    <thead><tr><th>Asset</th><th>Category</th><th>Current Qty</th><th>Min Level</th><th>Stock Level</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($assets as $a):
        $low = $a['CurrentQty'] < $a['MinStockLevel'];
        $pct = $a['MinStockLevel'] > 0 ? min(100, (int)round($a['CurrentQty'] / ($a['MinStockLevel'] * 2) * 100)) : 100;
        $barColor = $low ? 'var(--danger)' : 'var(--ok)';
    ?>
      <tr>
        <td style="font-weight:600;"><?= h($a['Name']) ?></td>
        <td class="subtle"><?= h($a['CategoryName'] ?? '—') ?></td>
        <td><?= (int)$a['CurrentQty'] ?></td>
        <td><?= (int)$a['MinStockLevel'] ?></td>
        <td style="width:160px;"><div class="progress-modern"><div style="width:<?= $pct ?>%; background:<?= $barColor ?>;"></div></div></td>
        <td><?= $low ? '<span class="badge-pill b-danger"><span class="dot"></span>Low Stock</span>' : '<span class="badge-pill b-ok"><span class="dot"></span>OK</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
