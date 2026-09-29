<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Employee']);

$search = trim($_GET['q'] ?? '');
$sql = "SELECT a.AssetID, a.Name, a.CurrentQty, a.UnitOfMeasure, c.CategoryName
        FROM Asset a LEFT JOIN Category c ON c.CategoryID = a.CategoryID
        WHERE a.Status = 'Available'";
$params = [];
if ($search !== '') {
    $sql .= " AND a.Name LIKE :q";
    $params[':q'] = "%$search%";
}
$sql .= " ORDER BY a.Name";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll();

$categoryIcons = [
    'Equipment' => ['bi-laptop', 'icon-blue'],
    'Furniture' => ['bi-person-workspace', 'icon-gold'],
    'Stationery' => ['bi-file-earmark-text', 'icon-green'],
    'Vehicle' => ['bi-truck', 'icon-red'],
];

$pageTitle = 'Asset Catalog';
$breadcrumb = 'Asset Catalog';
$activeNav = 'Asset Catalog';
require __DIR__ . '/../includes/header.php';
?>

<form method="get" class="d-flex gap-2 mb-3">
  <input class="form-control" style="max-width:340px; border-radius:9px; border:1.4px solid var(--nbe-border); padding:9px 14px; font-size:13px;"
         name="q" value="<?= h($search) ?>" placeholder="Search the catalog...">
  <button class="btn-outline-nbe" type="submit"><i class="bi bi-search"></i> Search</button>
</form>

<?php if (empty($assets)): ?>
  <div class="empty-state"><i class="bi bi-box-seam"></i>No matching assets found.</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($assets as $a):
      [$icon, $color] = $categoryIcons[$a['CategoryName']] ?? ['bi-box', 'icon-blue'];
      $lowStock = $a['CurrentQty'] < 10;
  ?>
  <div class="col-md-4">
    <div class="panel" style="height:100%;">
      <div class="panel-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div class="icon-wrap <?= $color ?>" style="width:46px;height:46px;font-size:20px;"><i class="bi <?= $icon ?>"></i></div>
          <span class="badge-pill b-<?= $lowStock ? 'warn' : 'ok' ?>"><span class="dot"></span><?= (int)$a['CurrentQty'] ?> in stock</span>
        </div>
        <div style="font-weight:700; font-size:14px; margin-bottom:2px;"><?= h($a['Name']) ?></div>
        <div class="subtle"><?= h($a['CategoryName'] ?? 'Uncategorized') ?> &middot; unit: <?= h($a['UnitOfMeasure']) ?></div>
        <a href="<?= BASE_URL ?>/employee/new_request.php" class="text-link" style="display:inline-block; margin-top:10px;">Request this item <i class="bi bi-arrow-right"></i></a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
