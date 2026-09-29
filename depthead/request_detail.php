<?php
/**
 * request_detail.php  (Department Head)
 * ---------------------------------------------------------------
 * Implements "Approve Request" (Section 3.6 / Figure 3.10):
 *   1. DH opens a pending request
 *   2. DH clicks Approve / Reject / Return
 *   3. Controller verifies permission (department match)
 *   4. BEGIN TRANSACTION -> UPDATE Request.Status -> INSERT AuditLog -> COMMIT
 *   5. Show confirmation; (Inventory Officer sees it in their queue next)
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['DepartmentHead']);

$user = current_user();
$requestId = (int)($_GET['id'] ?? 0);

$deptStmt = db()->prepare('SELECT DepartmentID FROM Employee WHERE EmployeeID = ?');
$deptStmt->execute([$user['employee_id']]);
$deptId = $deptStmt->fetchColumn();

function load_request(int $requestId, $deptId)
{
    $stmt = db()->prepare(
        "SELECT r.*, e.FullName, e.JobTitle, e.DepartmentID FROM Request r
         JOIN Employee e ON e.EmployeeID = r.EmployeeID
         WHERE r.RequestID = ?"
    );
    $stmt->execute([$requestId]);
    return $stmt->fetch();
}

$request = load_request($requestId, $deptId);
if (!$request) {
    http_response_code(404);
    die('Request not found.');
}

// ---- Step 3: verify permission — this request must belong to the DH's own department ----
if ((int)$request['DepartmentID'] !== (int)$deptId) {
    http_response_code(403);
    die('Access denied: this request does not belong to your department.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $decision = $_POST['decision'] ?? '';
    $comment  = trim($_POST['comment'] ?? '');

    if ($request['Status'] !== 'Pending') {
        $errors[] = 'This request has already been reviewed.';
    } elseif (!in_array($decision, ['Approved', 'Rejected', 'Returned'], true)) {
        $errors[] = 'Please choose a decision.';
    } else {
        // ---- Step 4: BEGIN TRANSACTION / UPDATE / INSERT AuditLog / COMMIT ----
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare(
                "UPDATE Request SET Status = :status, ReviewComment = :comment,
                                     ReviewedBy = :reviewer, ReviewedDate = NOW()
                 WHERE RequestID = :id AND Status = 'Pending'"
            );
            $upd->execute([
                ':status'   => $decision,
                ':comment'  => $comment ?: null,
                ':reviewer' => $user['user_id'],
                ':id'       => $requestId,
            ]);

            if ($upd->rowCount() === 0) {
                throw new RuntimeException('This request was already reviewed by someone else.');
            }

            $auditStmt = $pdo->prepare(
                "INSERT INTO AuditLog (UserID, Action, TableName, RecordID, Timestamp)
                 VALUES (?, ?, 'Request', ?, NOW())"
            );
            $auditStmt->execute([$user['user_id'], "{$decision} request R-{$requestId}", $requestId]);

            $pdo->commit();

            flash_set('success', "Request R-" . str_pad((string)$requestId, 4, '0', STR_PAD_LEFT) . " marked as {$decision}. The employee has been notified.");
            header('Location: ' . BASE_URL . '/depthead/pending.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$detailsStmt = db()->prepare(
    "SELECT rd.Quantity, a.Name, a.UnitOfMeasure FROM RequestDetail rd
     JOIN Asset a ON a.AssetID = rd.AssetID WHERE rd.RequestID = ?"
);
$detailsStmt->execute([$requestId]);
$lineItems = $detailsStmt->fetchAll();

$request = load_request($requestId, $deptId); // reload in case of decision above (in case of error, unchanged)
$reqCode = 'R-' . str_pad((string)$requestId, 4, '0', STR_PAD_LEFT);
$pageTitle = "Request $reqCode";
$breadcrumb = "Pending Requests / $reqCode";
$activeNav = 'Pending Requests';
require __DIR__ . '/../includes/header.php';
?>

<?php foreach ($errors as $err): ?>
  <div class="alert-modern alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($err) ?></div>
<?php endforeach; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel">
      <div class="panel-head"><h6>Request from <?= h($request['FullName']) ?></h6><?= status_badge($request['Status']) ?></div>
      <div class="panel-body">
        <div class="row g-3" style="font-size:13px;">
          <div class="col-6"><div class="subtle">Employee</div><div style="font-weight:700;"><?= h($request['FullName']) ?> &mdash; <?= h($request['JobTitle']) ?></div></div>
          <div class="col-6"><div class="subtle">Requested</div><div style="font-weight:700;"><?= fdate($request['SubmittedDate']) ?></div></div>
          <div class="col-6"><div class="subtle">Type</div><div style="font-weight:700;"><?= h($request['RequestType']) ?></div></div>
          <div class="col-6"><div class="subtle">Required By</div><div style="font-weight:700;"><?= fdate($request['RequiredByDate']) ?></div></div>
          <div class="col-12"><div class="subtle">Item(s)</div>
            <ul style="margin:6px 0 0 18px;">
              <?php foreach ($lineItems as $li): ?>
                <li><?= h($li['Name']) ?> &times; <?= (int)$li['Quantity'] ?> <?= h($li['UnitOfMeasure']) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="col-12"><div class="subtle">Reason</div><div><?= nl2br(h($request['Reason'])) ?></div></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <?php if ($request['Status'] === 'Pending'): ?>
    <div class="panel form-modern">
      <div class="panel-head"><h6>Decision</h6></div>
      <div class="panel-body">
        <form method="post">
          <?= csrf_field() ?>
          <textarea class="form-control mb-3" name="comment" rows="3" placeholder="Add a comment (optional, required for reject/return)"></textarea>
          <div class="d-flex flex-column gap-2">
            <button type="submit" name="decision" value="Approved" class="btn-nbe justify-content-center" style="background:var(--ok); border:none;"><i class="bi bi-check2-circle"></i> Approve</button>
            <button type="submit" name="decision" value="Rejected" class="btn-nbe justify-content-center" style="background:var(--danger); border:none;" data-confirm="Reject this request?"><i class="bi bi-x-circle"></i> Reject</button>
            <button type="submit" name="decision" value="Returned" class="btn-outline-nbe">Return for More Info</button>
          </div>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="panel"><div class="panel-body">
      <div class="subtle">This request has already been reviewed.</div>
      <?php if ($request['ReviewComment']): ?><div style="margin-top:8px;"><b>Comment:</b> <?= nl2br(h($request['ReviewComment'])) ?></div><?php endif; ?>
    </div></div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
