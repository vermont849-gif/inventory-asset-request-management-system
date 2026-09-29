<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['Employee', 'DepartmentHead', 'InventoryOfficer', 'Procurement', 'HR', 'Admin']);

$user = current_user();
$errors = [];

// ---- Password change handler (shared by every role) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrf_verify();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = db()->prepare('SELECT PasswordHash FROM UserAccount WHERE UserID = ?');
    $stmt->execute([$user['user_id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $errors[] = 'Your current password is incorrect.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    } else {
        $policyErrors = validate_password_policy($new);
        if ($policyErrors) {
            $errors = array_merge($errors, $policyErrors);
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $upd = db()->prepare('UPDATE UserAccount SET PasswordHash = ? WHERE UserID = ?');
            $upd->execute([$newHash, $user['user_id']]);
            log_action((int)$user['user_id'], 'Changed own password', 'UserAccount', (int)$user['user_id']);
            flash_set('success', 'Your password has been updated.');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Recent notifications = recent audit log entries relevant to this user's requests
$notifStmt = db()->prepare(
    "SELECT r.RequestID, r.Status, r.ReviewedDate FROM Request r
     WHERE r.EmployeeID = ? AND r.Status IN ('Approved','Rejected','Returned','Fulfilled')
     ORDER BY r.ReviewedDate DESC LIMIT 5"
);
$notifStmt->execute([$user['employee_id']]);
$notifications = $notifStmt->fetchAll();

$homeNav = ['Employee' => 'Profile', 'DepartmentHead' => '', 'InventoryOfficer' => '', 'Procurement' => '', 'HR' => '', 'Admin' => ''];
$pageTitle = 'Profile & Notifications';
$breadcrumb = 'Profile';
$activeNav = $homeNav[$user['role_name']] ?? '';
require __DIR__ . '/../includes/header.php';
?>

<?php foreach ($errors as $err): ?>
  <div class="alert-modern alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($err) ?></div>
<?php endforeach; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="panel">
      <div class="panel-body text-center">
        <div class="avatar mx-auto mb-3" style="width:72px;height:72px;font-size:22px; background:var(--nbe-navy);"><?= h(initials($user['full_name'])) ?></div>
        <div style="font-weight:800; font-size:16px;"><?= h($user['full_name']) ?></div>
        <div class="subtle mb-3"><?= h($user['job_title']) ?></div>
        <div class="text-start" style="font-size:12.8px;">
          <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--nbe-border);"><span class="subtle">Department</span><b><?= h($user['department']) ?></b></div>
          <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--nbe-border);"><span class="subtle">Username</span><b><?= h($user['username']) ?></b></div>
          <div class="d-flex justify-content-between py-2"><span class="subtle">Role</span><b><?= h($user['role_name']) ?></b></div>
        </div>
      </div>
    </div>

    <div class="panel form-modern" style="margin-top:18px;">
      <div class="panel-head"><h6>Change Password</h6></div>
      <div class="panel-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="change_password" value="1">
          <div class="fieldset-block"><label>Current Password</label><input class="form-control" type="password" name="current_password" required></div>
          <div class="fieldset-block"><label>New Password</label><input class="form-control" type="password" name="new_password" required></div>
          <div class="fieldset-block"><label>Confirm New Password</label><input class="form-control" type="password" name="confirm_password" required></div>
          <button class="btn-nbe w-100 justify-content-center" style="border:none;">Update Password</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="panel">
      <div class="panel-head"><h6>Notifications</h6><span class="badge-pill b-info"><?= count($notifications) ?> Recent</span></div>
      <div class="panel-body d-flex flex-column gap-3" style="font-size:12.8px;">
        <?php if (empty($notifications)): ?>
          <div class="subtle">No recent notifications.</div>
        <?php endif; ?>
        <?php foreach ($notifications as $n):
            $icon = $n['Status'] === 'Approved' || $n['Status'] === 'Fulfilled' ? ['bi-check-circle-fill', 'var(--ok)'] : ['bi-x-circle-fill', 'var(--danger)'];
        ?>
          <div class="d-flex gap-3"><i class="bi <?= $icon[0] ?>" style="color:<?= $icon[1] ?>;"></i>
            <div><b>Request R-<?= str_pad((string)$n['RequestID'],4,'0',STR_PAD_LEFT) ?> <?= strtolower(h($n['Status'])) ?></b>
              <div class="subtle"><?= fdate($n['ReviewedDate']) ?></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
