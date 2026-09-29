<?php
/**
 * return_asset.php
 * ---------------------------------------------------------------
 * Implements FR-10: when an asset is returned, the system
 * automatically updates the stock level and closes the issuance
 * record (sets ReturnDate).
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['InventoryOfficer']);

$user = current_user();

$outstanding = db()->query(
    "SELECT ia.IssueID, ia.QuantityIssued, ia.IssueDate, ia.ExpectedReturnDate,
            a.Name AS AssetName, a.UnitOfMeasure, e.FullName
     FROM IssuedAsset ia
     JOIN Asset a ON a.AssetID = ia.AssetID
     JOIN Employee e ON e.EmployeeID = ia.EmployeeID
     WHERE ia.ReturnDate IS NULL
     ORDER BY ia.ExpectedReturnDate IS NULL, ia.ExpectedReturnDate ASC"
)->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $issueId = (int)($_POST['issue_id'] ?? 0);
    $condition = $_POST['condition'] ?? 'Good';
    $returnDate = $_POST['return_date'] ?: date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    $find = db()->prepare('SELECT AssetID, QuantityIssued FROM IssuedAsset WHERE IssueID = ? AND ReturnDate IS NULL');
    $find->execute([$issueId]);
    $issue = $find->fetch();

    if (!$issue) {
        $errors[] = 'That issuance record could not be found or has already been closed.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare(
                "UPDATE IssuedAsset SET ReturnDate = :rd, Condition_ = :cond WHERE IssueID = :id"
            );
            $upd->execute([':rd' => $returnDate, ':cond' => $condition, ':id' => $issueId]);

            // ---- Automatically update stock level (FR-10) ----
            $stockUpd = $pdo->prepare('UPDATE Asset SET CurrentQty = CurrentQty + ? WHERE AssetID = ?');
            $stockUpd->execute([$issue['QuantityIssued'], $issue['AssetID']]);

            $auditStmt = $pdo->prepare(
                "INSERT INTO AuditLog (UserID, Action, TableName, RecordID, Timestamp) VALUES (?, ?, 'IssuedAsset', ?, NOW())"
            );
            $auditStmt->execute([$user['user_id'], "Recorded asset return (Issue #{$issueId})" . ($notes ? " — {$notes}" : ''), $issueId]);

            $pdo->commit();
            flash_set('success', 'Return recorded and stock level updated.');
            header('Location: ' . BASE_URL . '/inventory/return_asset.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong while recording the return.';
        }
    }
}

$pageTitle = 'Return Asset';
$breadcrumb = 'Return Asset';
$activeNav = 'Return Asset';
require __DIR__ . '/../includes/header.php';
?>

<?php foreach ($errors as $err): ?>
  <div class="alert-modern alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($err) ?></div>
<?php endforeach; ?>

<div class="panel">
  <div class="panel-head"><h6>Outstanding Issued Assets</h6></div>
  <?php if (empty($outstanding)): ?>
    <div class="empty-state"><i class="bi bi-check2-circle"></i>No outstanding issued assets awaiting return.</div>
  <?php else: ?>
  <table class="table-modern">
    <thead><tr><th>Employee</th><th>Asset</th><th>Issued</th><th>Expected Return</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($outstanding as $o): ?>
      <tr>
        <td><?= h($o['FullName']) ?></td>
        <td><?= h($o['AssetName']) ?> &times; <?= (int)$o['QuantityIssued'] ?> <?= h($o['UnitOfMeasure']) ?></td>
        <td class="subtle"><?= fdate($o['IssueDate']) ?></td>
        <td class="subtle"><?= fdate($o['ExpectedReturnDate']) ?></td>
        <td>
          <button type="button" class="btn-outline-nbe" style="padding:6px 14px; font-size:12px;"
                  data-bs-toggle="collapse" data-bs-target="#return-<?= (int)$o['IssueID'] ?>"
                  onclick="document.getElementById('return-<?= (int)$o['IssueID'] ?>').classList.toggle('d-none')">
            <i class="bi bi-arrow-return-left"></i> Return
          </button>
        </td>
      </tr>
      <tr id="return-<?= (int)$o['IssueID'] ?>" class="d-none">
        <td colspan="5" style="background:#FAFBFD;">
          <form method="post" class="form-modern d-flex gap-3 align-items-end flex-wrap" style="padding:14px 6px;">
            <?= csrf_field() ?>
            <input type="hidden" name="issue_id" value="<?= (int)$o['IssueID'] ?>">
            <div style="min-width:160px;"><label>Condition on Return</label>
              <select class="form-select" name="condition"><option>Good</option><option>Fair</option><option>Damaged</option></select></div>
            <div style="min-width:160px;"><label>Return Date</label>
              <input class="form-control" type="date" name="return_date" value="<?= date('Y-m-d') ?>"></div>
            <div style="min-width:220px; flex:1;"><label>Notes</label>
              <input class="form-control" name="notes" placeholder="Optional notes on condition..."></div>
            <button type="submit" class="btn-nbe" style="border:none;">Confirm Return</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
