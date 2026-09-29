<?php
/**
 * receive.php
 * ---------------------------------------------------------------
 * Implements FR-15: inventory officers can record new stock
 * received from suppliers, including supplier, item, quantity,
 * price, and date. Increases Asset.CurrentQty accordingly.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['InventoryOfficer']);

$user = current_user();
$suppliers = db()->query('SELECT SupplierID, Name FROM Supplier ORDER BY Name')->fetchAll();
$assets    = db()->query('SELECT AssetID, Name, UnitOfMeasure FROM Asset ORDER BY Name')->fetchAll();

$errors = [];
$form = ['supplier_id' => '', 'asset_id' => '', 'quantity' => '', 'unit_price' => '', 'date_received' => date('Y-m-d')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $form = array_merge($form, $_POST);
    $form['quantity'] = (int)($_POST['quantity'] ?? 0);

    if ($form['asset_id'] === '' || !ctype_digit((string)$form['asset_id'])) {
        $errors[] = 'Please select an item.';
    }
    if ($form['quantity'] < 1) {
        $errors[] = 'Quantity received must be at least 1.';
    }
    if ($form['date_received'] === '') {
        $errors[] = 'Please provide the date received.';
    }

    if (empty($errors)) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare('UPDATE Asset SET CurrentQty = CurrentQty + ?, SupplierID = COALESCE(?, SupplierID) WHERE AssetID = ?');
            $upd->execute([$form['quantity'], $form['supplier_id'] ?: null, $form['asset_id']]);

            if (!empty($form['unit_price'])) {
                $priceUpd = $pdo->prepare('UPDATE Asset SET UnitPrice = ? WHERE AssetID = ?');
                $priceUpd->execute([$form['unit_price'], $form['asset_id']]);
            }

            $auditStmt = $pdo->prepare(
                "INSERT INTO AuditLog (UserID, Action, TableName, RecordID, Timestamp) VALUES (?, ?, 'Asset', ?, NOW())"
            );
            $auditStmt->execute([$user['user_id'], "Received stock: +{$form['quantity']} units", $form['asset_id']]);

            $pdo->commit();
            flash_set('success', 'Stock entry saved. Quantity on hand has been updated.');
            header('Location: ' . BASE_URL . '/inventory/stock.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong while saving this stock entry.';
        }
    }
}

$pageTitle = 'Receive Stock';
$breadcrumb = 'Receive Stock';
$activeNav = 'Receive Stock';
require __DIR__ . '/../includes/header.php';
?>

<?php foreach ($errors as $err): ?>
  <div class="alert-modern alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($err) ?></div>
<?php endforeach; ?>

<div class="panel" style="max-width:640px;">
  <div class="panel-body form-modern">
    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-12 fieldset-block"><label>Supplier</label>
          <select class="form-select" name="supplier_id">
            <option value="">— Select supplier —</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= (int)$s['SupplierID'] ?>" <?= $form['supplier_id']==$s['SupplierID']?'selected':'' ?>><?= h($s['Name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 fieldset-block"><label>Item <span class="required-star">*</span></label>
          <select class="form-select" name="asset_id" required>
            <option value="">— Select item —</option>
            <?php foreach ($assets as $a): ?>
              <option value="<?= (int)$a['AssetID'] ?>" <?= (string)$form['asset_id']===(string)$a['AssetID']?'selected':'' ?>><?= h($a['Name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 fieldset-block"><label>Quantity Received <span class="required-star">*</span></label>
          <input class="form-control" type="number" min="1" name="quantity" required value="<?= h((string)$form['quantity']) ?>"></div>
        <div class="col-md-6 fieldset-block"><label>Unit Price (ETB)</label>
          <input class="form-control" type="number" step="0.01" min="0" name="unit_price" value="<?= h((string)$form['unit_price']) ?>"></div>
        <div class="col-12 fieldset-block"><label>Date Received <span class="required-star">*</span></label>
          <input class="form-control" type="date" name="date_received" required value="<?= h($form['date_received']) ?>"></div>
      </div>
      <div class="divider"></div>
      <button type="submit" class="btn-nbe" style="border:none;"><i class="bi bi-save"></i> Save Stock Entry</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
