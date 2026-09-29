<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Procurement']);

$totalRequests = (int)db()->query(
    "SELECT COUNT(*) FROM Request WHERE YEAR(SubmittedDate) = YEAR(CURDATE())"
)->fetchColumn();

$topCategory = db()->query(
    "SELECT c.CategoryName, COUNT(*) AS cnt
     FROM RequestDetail rd JOIN Asset a ON a.AssetID = rd.AssetID
     LEFT JOIN Category c ON c.CategoryID = a.CategoryID
     GROUP BY c.CategoryName ORDER BY cnt DESC LIMIT 1"
)->fetch();

$monthly = db()->query(
    "SELECT MONTH(SubmittedDate) AS m, COUNT(*) AS cnt
     FROM Request WHERE YEAR(SubmittedDate) = YEAR(CURDATE())
     GROUP BY MONTH(SubmittedDate) ORDER BY m"
)->fetchAll();
$monthCounts = array_fill(1, 12, 0);
foreach ($monthly as $row) { $monthCounts[(int)$row['m']] = (int)$row['cnt']; }
$maxCount = max(1, max($monthCounts));

$pageTitle = 'Procurement Dashboard';
$breadcrumb = 'Dashboard';
$activeNav = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-3" style="margin-bottom:22px;">
  <div class="stat-card"><div><div class="lbl">Requests This Year</div><div class="val"><?= $totalRequests ?></div></div><div class="icon-wrap icon-blue"><i class="bi bi-receipt"></i></div></div>
  <div class="stat-card"><div><div class="lbl">Top Category</div><div class="val" style="font-size:18px;"><?= h($topCategory['CategoryName'] ?? '—') ?></div></div><div class="icon-wrap icon-gold"><i class="bi bi-cpu"></i></div></div>
  <div class="stat-card"><div><div class="lbl">Reporting Period</div><div class="val" style="font-size:18px;">FY <?= date('Y') ?></div></div><div class="icon-wrap icon-green"><i class="bi bi-calendar3"></i></div></div>
</div>

<div class="panel">
  <div class="panel-head"><h6>Requests per Month</h6><span class="badge-pill b-info">FY <?= date('Y') ?></span></div>
  <div class="panel-body">
    <div class="kpi-trend" style="height:140px; align-items:flex-end;">
      <?php foreach ($monthCounts as $m => $cnt):
          $h = $cnt > 0 ? max(6, (int)round($cnt / $maxCount * 130)) : 3;
      ?>
        <div style="height:<?= $h ?>px;" title="<?= $cnt ?> requests"></div>
      <?php endforeach; ?>
    </div>
    <div class="d-flex justify-content-between subtle mt-2" style="font-size:11px;">
      <?php foreach (['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $lbl): ?>
        <span><?= $lbl ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
