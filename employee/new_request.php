<?php
/**
 * new_request.php
 * ---------------------------------------------------------------
 * Implements "Submit Asset Request" (Section 3.6 / Figure 3.9):
 *   1. View submits form data (POST)
 *   2. Controller validates required fields (item, quantity, reason)
 *   3. Controller checks for a duplicate pending request (FR-11)
 *   4. If none: INSERT INTO Request + RequestDetail, show success
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['Employee']);

$user = current_user();
$empId = $user['employee_id'];

$assets = db()->query("SELECT AssetID, Name, UnitOfMeasure, CurrentQty FROM Asset WHERE Status='Available' ORDER BY Name")->fetchAll();

$errors = [];
$form = ['request_type' => 'New', 'asset_id' => '', 'quantity' => 1, 'required_by' => '', 'reason' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $form['request_type'] = $_POST['request_type'] ?? 'New';
    $form['asset_id']     = $_POST['asset_id'] ?? '';
    $form['quantity']     = (int)($_POST['quantity'] ?? 0);
    $form['required_by']  = $_POST['required_by'] ?? '';
    $form['reason']       = trim($_POST['reason'] ?? '');

    // ---- Step 2: validate required fields ----
    if (!in_array($form['request_type'], ['New', 'Maintenance', 'Disposal'], true)) {
        $errors[] = 'Please choose a valid request type.';
    }
    if ($form['asset_id'] === '' || !ctype_digit((string)$form['asset_id'])) {
        $errors[] = 'Please select an item from the asset catalog.';
    }
    if ($form['quantity'] < 1) {
        $errors[] = 'Quantity must be at least 1.';
    }
    if ($form['reason'] === '') {
        $errors[] = 'Please provide a reason for this request.';
    }

    if (empty($errors)) {
        // ---- Step 3: check for duplicate pending request for same item ----
        $dupStmt = db()->prepare(
            "SELECT COUNT(*) FROM Request r
             JOIN RequestDetail rd ON rd.RequestID = r.RequestID
             WHERE r.EmployeeID = :emp AND r.Status = 'Pending' AND rd.AssetID = :asset"
        );
        $dupStmt->execute([':emp' => $empId, ':asset' => $form['asset_id']]);
        $duplicateCount = (int)$dupStmt->fetchColumn();

        if ($duplicateCount > 0) {
            $errors[] = 'You already have a pending request for this item. Please wait for it to be reviewed before submitting another.';
        } else {
            // ---- Step 4: save new request ----
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO Request (EmployeeID, RequestType, Status, SubmittedDate, RequiredByDate, Reason)
                     VALUES (:emp, :type, 'Pending', NOW(), :reqby, :reason)"
                );
                $ins->execute([
                    ':emp'    => $empId,
                    ':type'   => $form['request_type'],
                    ':reqby'  => $form['required_by'] ?: null,
                    ':reason' => $form['reason'],
                ]);
                $requestId = (int)$pdo->lastInsertId();

                $detail = $pdo->prepare(
                    "INSERT INTO RequestDetail (RequestID, AssetID, Quantity) VALUES (?, ?, ?)"
                );
                $detail->execute([$requestId, $form['asset_id'], $form['quantity']]);

                $pdo->commit();

                log_action((int)$user['user_id'], "Submitted request R-{$requestId}", 'Request', $requestId);

                flash_set('success', "Request submitted successfully. Your reference number is R-" . str_pad((string)$requestId, 4, '0', STR_PAD_LEFT) . '.');
                header('Location: ' . BASE_URL . '/employee/my_requests.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Something went wrong while saving your request. Please try again.';
            }
        }
    }
}

$pageTitle = 'New Asset Request';
$breadcrumb = 'New Request';
$activeNav = 'New Request';
require __DIR__ . '/../includes/header.php';
?>

<div class="subtle" style="margin-bottom:18px;">Fill in the details below. Fields marked with <span class="required-star">*</span> are required.</div>

<?php foreach ($errors as $err): ?>
  <div class="alert-modern alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($err) ?></div>
<?php endforeach; ?>

<div class="panel" style="max-width:760px;">
  <div class="panel-body form-modern">
    <form method="post" class="needs-validation">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6 fieldset-block">
          <label>Request Type <span class="required-star">*</span></label>
          <select class="form-select" name="request_type" required>
            <?php foreach (['New' => 'New Item', 'Maintenance' => 'Maintenance', 'Disposal' => 'Disposal'] as $val => $label): ?>
              <option value="<?= $val ?>" <?= $form['request_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 fieldset-block">
          <label>Required-by Date</label>
          <input class="form-control" type="date" name="required_by" value="<?= h($form['required_by']) ?>">
        </div>
        <div class="col-12 fieldset-block">
          <label>Asset <span class="required-star">*</span></label>
          <select class="form-select" name="asset_id" required>
            <option value="">Search and select an item from the catalog...</option>
            <?php foreach ($assets as $a): ?>
              <option value="<?= (int)$a['AssetID'] ?>" <?= (string)$form['asset_id'] === (string)$a['AssetID'] ? 'selected' : '' ?>>
                <?= h($a['Name']) ?> (<?= (int)$a['CurrentQty'] ?> <?= h($a['UnitOfMeasure']) ?> in stock)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 fieldset-block">
          <label>Quantity <span class="required-star">*</span></label>
          <input class="form-control" type="number" min="1" name="quantity" required value="<?= (int)$form['quantity'] ?>">
        </div>
        <div class="col-md-8 fieldset-block">
          <label>Department (auto-filled)</label>
          <input class="form-control" value="<?= h($user['department']) ?>" disabled style="background:#F7F9FC;">
        </div>
        <div class="col-12 fieldset-block">
          <label>Reason for Request <span class="required-star">*</span></label>
          <textarea class="form-control" rows="4" name="reason" required placeholder="Explain briefly why this item / service is needed..."><?= h($form['reason']) ?></textarea>
        </div>
      </div>
      <div class="divider"></div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn-nbe" style="border:none;"><i class="bi bi-send-check"></i> Submit Request</button>
        <a href="<?= BASE_URL ?>/employee/dashboard.php" class="btn-outline-nbe" style="text-decoration:none; border:none; color:var(--nbe-muted);">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
