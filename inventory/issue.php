<?php
/**
 * issue.php
 * ---------------------------------------------------------------
 * Fulfils an approved request: records who received the item(s),
 * the quantity, and the expected return date (FR-09), deducts the
 * quantity from Asset.CurrentQty, and marks the Request as
 * 'Fulfilled'. Runs inside a single DB transaction so stock and
 * issuance records never go out of sync.
 *
 * When opened without a specific request_id (e.g. from the sidebar
 * "Issue Asset" link), this page shows a picker of all approved
 * requests waiting to be issued instead of erroring.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['InventoryOfficer']);

$user = current_user();
$requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? 0);

// ---- No request selected yet: show a picker of approved requests ----
if ($requestId === 0) {
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

    $pageTitle = 'Issue Asset';
    $breadcrumb = 'Issue Asset';
    $activeNav = 'Issue Asset';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="subtle" style="margin-bottom:16px;">Select an approved request below to issue its item(s).</div>
    <div class="panel">
      <?php if (empty($queue)): ?>
        <div class="empty-state"><i class="bi bi-inbox"></i>No approved requests are waiting to be issued right now.</div>
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
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// ---- A specific request was selected: show the confirm-and-issue form ----
$stmt = db()->prepare(
    "SELECT r.RequestID, r.EmployeeID, r.RequestType, e.FullName, e.JobTitle
     FROM Request r JOIN Employee e ON e.EmployeeID = r.EmployeeID
     WHERE r.RequestID = ? AND r.Status = 'Approved'"
);
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    $pageTitle = 'Issue Asset';
    $breadcrumb = 'Issue Asset';
    $activeNav = 'Issue Asset';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="alert-modern alert-danger"><i class="bi bi-exclamation-triangle-fill"></i>
      That request could not be found, is not currently Approved, or has already been issued.
    </div>
    <a href="<?= BASE_URL ?>/inventory/issue.php" class="btn-outline-nbe" style="text-decoration:none;"><i class="bi bi-arrow-left"></i> Back to Approved Requests</a>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$detailsStmt = db()->prepare(
    "SELECT rd.DetailID, rd.AssetID, rd.Quantity, a.Name, a.CurrentQty, a.UnitOfMeasure
     FROM RequestDetail rd JOIN Asset a ON a.AssetID = rd.AssetID
     WHERE rd.RequestID = ?"
);
$detailsStmt->execute([$requestId]);
$lineItems = $detailsStmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $condition = $_POST['condition'] ?? 'New';
    $expectedReturn = $_POST['expected_return'] ?: null;

    // Guard against issuing more than is currently in stock.
    foreach ($lineItems as $li) {
        if ((int)$li['Quantity'] > (int)$li['CurrentQty']) {
            $errors[] = "Not enough stock for {$li['Name']} (requested {$li['Quantity']}, only {$li['CurrentQty']} in stock).";
        }
    }

    if (empty($errors)) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach ($lineItems as $li) {
                $ins = $pdo->prepare(
                    "INSERT INTO IssuedAsset (RequestID, EmployeeID, AssetID, QuantityIssued, IssueDate, ExpectedReturnDate, Condition_)
                     VALUES (:req, :emp, :asset, :qty, CURDATE(), :ret, :cond)"
                );
                $ins->execute([
                    ':req'   => $requestId,
                    ':emp'   => $request['EmployeeID'],
                    ':asset' => $li['AssetID'],
                    ':qty'   => $li['Quantity'],
                    ':ret'   => $expectedReturn,
                    ':cond'  => $condition,
                ]);

                // ---- Deduct stock (FR-09 / FR-10) ----
                $upd = $pdo->prepare('UPDATE Asset SET CurrentQty = CurrentQty - ? WHERE AssetID = ?');
                $upd->execute([$li['Quantity'], $li['AssetID']]);
            }

            $reqUpd = $pdo->prepare("UPDATE Request SET Status = 'Fulfilled' WHERE RequestID = ?");
            $reqUpd->execute([$requestId]);

            $auditStmt = $pdo->prepare(
                "INSERT INTO AuditLog (UserID, Action, TableName, RecordID, Timestamp) VALUES (?, ?, 'IssuedAsset', ?, NOW())"
            );
            $auditStmt->execute([$user['user_id'], "Issued assets for request R-{$requestId}", $requestId]);

            $pdo->commit();

            flash_set('success', "Assets issued successfully for R-" . str_pad((string)$requestId, 4, '0', STR_PAD_LEFT) . '.');
            header('Location: ' . BASE_URL . '/inventory/queue.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong while issuing the asset(s). Please try again.';
        }
    }
}

$pageTitle = 'Issue Asset';
$breadcrumb = 'Issue Asset';
$activeNav = 'Issue Asset';
require __DIR__ . '/../includes/header.php';
?>

<?php foreach ($errors as $err): ?>
  <div class="alert-modern alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($err) ?></div>
<?php endforeach; ?>

<div class="panel" style="max-width:680px;">
  <div class="panel-body form-modern">
    <div class="row g-3">
      <div class="col-12 fieldset-block"><label>Recipient</label>
        <input class="form-control" value="<?= h($request['FullName']) ?> &mdash; <?= h($request['JobTitle']) ?>" disabled style="background:#F7F9FC;"></div>
      <div class="col-12 fieldset-block"><label>Item(s) to Issue</label>
        <ul style="margin:0 0 0 18px;">
          <?php foreach ($lineItems as $li): ?>
            <li><?= h($li['Name']) ?> &times; <?= (int)$li['Quantity'] ?> <?= h($li['UnitOfMeasure']) ?>
              <span class="subtle">(<?= (int)$li['CurrentQty'] ?> currently in stock)</span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="request_id" value="<?= $requestId ?>">
      <div class="row g-3">
        <div class="col-md-6 fieldset-block"><label>Expected Return Date <span class="subtle">(if applicable)</span></label>
          <input class="form-control" type="date" name="expected_return"></div>
        <div class="col-md-6 fieldset-block"><label>Condition at Issuance</label>
          <select class="form-select" name="condition">
            <option>New</option><option>Good</option><option>Fair</option>
          </select>
        </div>
      </div>
      <div class="divider"></div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn-nbe" style="border:none;"><i class="bi bi-check2-circle"></i> Confirm Issuance</button>
        <a href="<?= BASE_URL ?>/inventory/issue.php" class="btn-outline-nbe" style="text-decoration:none; border:none; color:var(--nbe-muted);">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
