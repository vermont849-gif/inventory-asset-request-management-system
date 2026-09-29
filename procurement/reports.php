<?php
/**
 * reports.php  (Procurement)
 * ---------------------------------------------------------------
 * Implements FR-21: usage trends and request history to help with
 * annual fiscal year budget planning. FR-18/FR-19: filterable by
 * date/category and downloadable as CSV.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['Procurement']);

$year = (int)($_GET['year'] ?? date('Y'));

$rows = db()->prepare(
    "SELECT c.CategoryName,
            COUNT(DISTINCT r.RequestID) AS RequestCount,
            SUM(rd.Quantity) AS TotalQty,
            SUM(rd.Quantity * a.UnitPrice) AS EstCost
     FROM Request r
     JOIN RequestDetail rd ON rd.RequestID = r.RequestID
     JOIN Asset a ON a.AssetID = rd.AssetID
     LEFT JOIN Category c ON c.CategoryID = a.CategoryID
     WHERE YEAR(r.SubmittedDate) = ?
     GROUP BY c.CategoryName
     ORDER BY EstCost DESC"
);
$rows->execute([$year]);
$reportRows = $rows->fetchAll();
$grandTotal = array_sum(array_column($reportRows, 'EstCost'));

// ---- CSV export ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="budget_report_' . $year . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Category', 'Requests', 'Total Qty', 'Estimated Cost (ETB)']);
    foreach ($reportRows as $r) {
        fputcsv($out, [$r['CategoryName'] ?? 'Uncategorized', $r['RequestCount'], $r['TotalQty'], number_format((float)$r['EstCost'], 2, '.', '')]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Budget Planning Reports';
$breadcrumb = 'Budget Reports';
$activeNav = 'Budget Reports';
require __DIR__ . '/../includes/header.php';
?>

<form method="get" class="d-flex justify-content-between mb-3 flex-wrap gap-2">
  <div class="d-flex gap-2 align-items-center">
    <label class="subtle" style="font-size:12.5px;">Fiscal Year</label>
    <select name="year" class="form-select" style="width:120px; border-radius:9px; border:1.4px solid var(--nbe-border); padding:7px 10px; font-size:13px;" onchange="this.form.submit()">
      <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 4; $y--): ?>
        <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <a href="?year=<?= $year ?>&export=csv" class="btn-outline-nbe" style="text-decoration:none;"><i class="bi bi-download"></i> Export CSV</a>
</form>

<div class="panel">
  <?php if (empty($reportRows)): ?>
    <div class="empty-state"><i class="bi bi-bar-chart"></i>No request data found for FY <?= $year ?>.</div>
  <?php else: ?>
  <table class="table-modern">
    <thead><tr><th>Category</th><th>Requests</th><th>Total Qty</th><th>Est. Cost</th><th>Budget Share</th></tr></thead>
    <tbody>
    <?php foreach ($reportRows as $r):
        $pct = $grandTotal > 0 ? (int)round($r['EstCost'] / $grandTotal * 100) : 0;
    ?>
      <tr>
        <td style="font-weight:600;"><?= h($r['CategoryName'] ?? 'Uncategorized') ?></td>
        <td><?= (int)$r['RequestCount'] ?></td>
        <td><?= (int)$r['TotalQty'] ?></td>
        <td><?= fmoney($r['EstCost']) ?></td>
        <td style="width:160px;"><div class="progress-modern"><div style="width:<?= $pct ?>%; background:var(--nbe-navy);"></div></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
