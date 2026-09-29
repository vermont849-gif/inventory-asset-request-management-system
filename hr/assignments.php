<?php
/**
 * assignments.php  (HR / Talent & Culture)
 * ---------------------------------------------------------------
 * Implements FR-22: Talent and Culture (HR) can view asset
 * assignment records when onboarding new employees or when an
 * employee exits.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['HR']);

$search = trim($_GET['q'] ?? '');
$sql = "SELECT ia.IssueID, e.FullName, e.JobTitle, a.Name AS AssetName, ia.IssueDate, ia.ReturnDate, ia.Condition_
        FROM IssuedAsset ia
        JOIN Employee e ON e.EmployeeID = ia.EmployeeID
        JOIN Asset a ON a.AssetID = ia.AssetID";
$params = [];
if ($search !== '') {
    $sql .= " WHERE e.FullName LIKE :q";
    $params[':q'] = "%$search%";
}
$sql .= " ORDER BY ia.IssueDate DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

$pageTitle = 'Employee Asset Assignments';
$breadcrumb = 'Asset Assignments';
$activeNav = 'Asset Assignments';
require __DIR__ . '/../includes/header.php';
?>

<form method="get" class="d-flex gap-2 mb-3">
  <input class="form-control" style="max-width:320px; border-radius:9px; border:1.4px solid var(--nbe-border); padding:9px 14px; font-size:13px;"
         name="q" value="<?= h($search) ?>" placeholder="Search employee...">
  <button class="btn-outline-nbe" type="submit"><i class="bi bi-search"></i> Search</button>
  <?php if ($search): ?><a href="<?= BASE_URL ?>/hr/assignments.php" class="btn-outline-nbe" style="text-decoration:none;">Clear</a><?php endif; ?>
</form>

<div class="panel">
  <?php if (empty($assignments)): ?>
    <div class="empty-state"><i class="bi bi-person-badge"></i>No asset assignments found.</div>
  <?php else: ?>
  <table class="table-modern">
    <thead><tr><th>Employee</th><th>Item</th><th>Assigned Date</th><th>Status</th><th>Condition</th></tr></thead>
    <tbody>
    <?php foreach ($assignments as $r): ?>
      <tr>
        <td><div class="name-cell"><div class="avatar" style="background:var(--nbe-navy);"><?= h(initials($r['FullName'])) ?></div>
            <div><div class="nm"><?= h($r['FullName']) ?></div><div class="sub"><?= h($r['JobTitle']) ?></div></div></div></td>
        <td><?= h($r['AssetName']) ?></td>
        <td class="subtle"><?= fdate($r['IssueDate']) ?></td>
        <td><?= $r['ReturnDate'] ? '<span class="badge-pill b-gray"><span class="dot"></span>Returned '.fdate($r['ReturnDate']).'</span>' : '<span class="badge-pill b-ok"><span class="dot"></span>Currently Assigned</span>' ?></td>
        <td><?= h($r['Condition_'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
