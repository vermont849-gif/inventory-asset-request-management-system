<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Employee']);

$user = current_user();
$empId = $user['employee_id'];
$requestId = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare(
    "SELECT r.*, e.FullName FROM Request r JOIN Employee e ON e.EmployeeID = r.EmployeeID
     WHERE r.RequestID = ? AND r.EmployeeID = ?"
);
$stmt->execute([$requestId, $empId]);
$request = $stmt->fetch();

if (!$request) {
    http_response_code(404);
    die('Request not found, or you do not have permission to view it.');
}

$detailsStmt = db()->prepare(
    "SELECT rd.Quantity, a.Name, a.UnitOfMeasure FROM RequestDetail rd
     JOIN Asset a ON a.AssetID = rd.AssetID WHERE rd.RequestID = ?"
);
$detailsStmt->execute([$requestId]);
$lineItems = $detailsStmt->fetchAll();

$reqCode = 'R-' . str_pad((string)$requestId, 4, '0', STR_PAD_LEFT);
$pageTitle = "Request $reqCode";
$breadcrumb = "My Requests / $reqCode";
$activeNav = 'My Requests';
require __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="panel" style="margin-bottom:18px;">
      <div class="panel-head"><h6>Request Details</h6><?= status_badge($request['Status']) ?></div>
      <div class="panel-body">
        <div class="row g-3" style="font-size:13px;">
          <div class="col-6"><div class="subtle">Type</div><div style="font-weight:700;"><?= h($request['RequestType']) ?></div></div>
          <div class="col-6"><div class="subtle">Submitted</div><div style="font-weight:700;"><?= fdate($request['SubmittedDate']) ?></div></div>
          <div class="col-12"><div class="subtle">Item(s)</div>
            <ul style="margin:6px 0 0 18px;">
              <?php foreach ($lineItems as $li): ?>
                <li><?= h($li['Name']) ?> &times; <?= (int)$li['Quantity'] ?> <?= h($li['UnitOfMeasure']) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="col-12"><div class="subtle">Reason</div><div><?= nl2br(h($request['Reason'])) ?></div></div>
          <?php if ($request['ReviewComment']): ?>
          <div class="col-12"><div class="subtle">Reviewer Comment</div><div><?= nl2br(h($request['ReviewComment'])) ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="panel">
      <div class="panel-head"><h6>Status History</h6></div>
      <div class="panel-body">
        <div class="d-flex flex-column gap-3" style="font-size:12.8px;">
          <div class="d-flex gap-3"><i class="bi bi-check-circle-fill" style="color:var(--ok);"></i>
            <div><b>Request Submitted</b><div class="subtle"><?= fdate($request['SubmittedDate'], 'M d, Y \a\t g:i A') ?></div></div></div>
          <?php if ($request['Status'] === 'Pending'): ?>
          <div class="d-flex gap-3"><i class="bi bi-hourglass-split" style="color:var(--warn);"></i>
            <div><b>Under Review by Department Head</b><div class="subtle">Awaiting decision</div></div></div>
          <?php else: ?>
          <div class="d-flex gap-3"><i class="bi bi-<?= $request['Status']==='Approved' ? 'check-circle-fill' : 'x-circle-fill' ?>" style="color:<?= $request['Status']==='Approved' ? 'var(--ok)' : 'var(--danger)' ?>;"></i>
            <div><b><?= h($request['Status']) ?> by Department Head</b><div class="subtle"><?= fdate($request['ReviewedDate'], 'M d, Y \a\t g:i A') ?></div></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <a href="<?= BASE_URL ?>/employee/my_requests.php" class="btn-outline-nbe" style="text-decoration:none; display:inline-flex;"><i class="bi bi-arrow-left"></i> Back to My Requests</a>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
