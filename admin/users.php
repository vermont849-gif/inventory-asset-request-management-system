<?php
/**
 * users.php  (Admin)
 * ---------------------------------------------------------------
 * Implements FR-12: administrators can add, update, deactivate,
 * or delete user accounts.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['Admin']);

$user = current_user();
$errors = [];

// ---- Handle "Add User" ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    csrf_verify();
    $fullName = trim($_POST['full_name'] ?? '');
    $jobTitle = trim($_POST['job_title'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $deptId   = $_POST['department_id'] ?: null;
    $username = trim($_POST['username'] ?? '');
    $roleId   = $_POST['role_id'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($fullName === '' || $jobTitle === '' || $username === '' || $roleId === '') {
        $errors[] = 'Please fill in all required fields.';
    }
    if (!is_valid_email($email)) {
        $errors[] = 'Please provide a valid email address.';
    }
    $policyErrors = validate_password_policy($password);
    if ($policyErrors) $errors = array_merge($errors, $policyErrors);

    if (empty($errors)) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $empIns = $pdo->prepare(
                'INSERT INTO Employee (FullName, JobTitle, Email, DepartmentID, Status) VALUES (?,?,?,?,\'Active\')'
            );
            $empIns->execute([$fullName, $jobTitle, $email, $deptId]);
            $newEmpId = (int)$pdo->lastInsertId();

            $userIns = $pdo->prepare(
                'INSERT INTO UserAccount (Username, PasswordHash, RoleID, EmployeeID, Status) VALUES (?,?,?,?,\'Active\')'
            );
            $userIns->execute([$username, password_hash($password, PASSWORD_DEFAULT), $roleId, $newEmpId]);
            $newUserId = (int)$pdo->lastInsertId();

            $pdo->commit();
            log_action((int)$user['user_id'], "Created user account '{$username}'", 'UserAccount', $newUserId);
            flash_set('success', "User '{$username}' created successfully.");
            header('Location: ' . BASE_URL . '/admin/users.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = str_contains($e->getMessage(), 'Duplicate') ? 'That username or email is already in use.' : 'Could not create the user account.';
        }
    }
}

// ---- Handle Activate / Deactivate toggle ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    csrf_verify();
    $targetId = (int)$_POST['user_id'];
    $newStatus = $_POST['new_status'] === 'Active' ? 'Active' : 'Inactive';
    $upd = db()->prepare('UPDATE UserAccount SET Status = ? WHERE UserID = ?');
    $upd->execute([$newStatus, $targetId]);
    log_action((int)$user['user_id'], "Set user #{$targetId} status to {$newStatus}", 'UserAccount', $targetId);
    flash_set('success', 'User status updated.');
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

// ---- Handle Reset Password (answers the "Forgot password" flow:
//      the admin generates a new temporary password here and shares
//      it with the employee directly) ----
$resetTempPassword = null;
$resetForUsername = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    csrf_verify();
    $targetId = (int)$_POST['user_id'];
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $tempPassword = '';
    for ($i = 0; $i < 10; $i++) {
        $tempPassword .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $upd = db()->prepare('UPDATE UserAccount SET PasswordHash = ? WHERE UserID = ?');
    $upd->execute([password_hash($tempPassword, PASSWORD_DEFAULT), $targetId]);

    $nameStmt = db()->prepare('SELECT Username FROM UserAccount WHERE UserID = ?');
    $nameStmt->execute([$targetId]);
    $resetForUsername = $nameStmt->fetchColumn();

    log_action((int)$user['user_id'], "Reset password for user #{$targetId}", 'UserAccount', $targetId);
    $resetTempPassword = $tempPassword;
}

$departments = db()->query('SELECT DepartmentID, DepartmentName FROM Department ORDER BY DepartmentName')->fetchAll();
$roles = db()->query('SELECT RoleID, RoleName FROM Role ORDER BY RoleName')->fetchAll();

$search = trim($_GET['q'] ?? '');
$sql = "SELECT u.UserID, u.Username, u.Status, r.RoleName, e.FullName, e.JobTitle
        FROM UserAccount u JOIN Role r ON r.RoleID = u.RoleID JOIN Employee e ON e.EmployeeID = u.EmployeeID";
$params = [];
if ($search !== '') {
    $sql .= " WHERE e.FullName LIKE :q1 OR u.Username LIKE :q2";
    $params[':q1'] = "%$search%";
    $params[':q2'] = "%$search%";
}
$sql .= " ORDER BY e.FullName";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'User Management';
$breadcrumb = 'User Management';
$activeNav = 'User Management';
require __DIR__ . '/../includes/header.php';
?>

<?php foreach ($errors as $err): ?>
  <div class="alert-modern alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($err) ?></div>
<?php endforeach; ?>

<?php if ($resetTempPassword): ?>
  <div class="alert-modern alert-success" style="align-items:flex-start;">
    <i class="bi bi-key-fill" style="margin-top:2px;"></i>
    <div>
      New temporary password for <b>@<?= h($resetForUsername) ?></b>: <code style="background:#fff; padding:2px 8px; border-radius:6px; font-size:13px; user-select:all;"><?= h($resetTempPassword) ?></code>
      <div style="font-weight:500; margin-top:4px;">Share this with the employee directly — it will not be shown again. They should change it after signing in.</div>
    </div>
  </div>
<?php endif; ?>

<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
  <form method="get"><input class="form-control" style="max-width:280px; border-radius:9px; border:1.4px solid var(--nbe-border); padding:9px 14px; font-size:13px;" name="q" value="<?= h($search) ?>" placeholder="Search users..."></form>
  <button class="btn-nbe" style="border:none;" data-bs-toggle="modal" onclick="document.getElementById('addUserPanel').classList.toggle('d-none')"><i class="bi bi-plus-lg"></i> Add User</button>
</div>

<div id="addUserPanel" class="panel d-none form-modern" style="margin-bottom:20px;">
  <div class="panel-head"><h6>New User Account</h6></div>
  <div class="panel-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="add_user" value="1">
      <div class="row g-3">
        <div class="col-md-6 fieldset-block"><label>Full Name <span class="required-star">*</span></label><input class="form-control" name="full_name" required></div>
        <div class="col-md-6 fieldset-block"><label>Job Title <span class="required-star">*</span></label><input class="form-control" name="job_title" required></div>
        <div class="col-md-6 fieldset-block"><label>Email <span class="required-star">*</span></label><input class="form-control" type="email" name="email" required></div>
        <div class="col-md-6 fieldset-block"><label>Department</label>
          <select class="form-select" name="department_id">
            <option value="">— None —</option>
            <?php foreach ($departments as $d): ?><option value="<?= (int)$d['DepartmentID'] ?>"><?= h($d['DepartmentName']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 fieldset-block"><label>Username <span class="required-star">*</span></label><input class="form-control" name="username" required></div>
        <div class="col-md-6 fieldset-block"><label>Role <span class="required-star">*</span></label>
          <select class="form-select" name="role_id" required>
            <option value="">— Select role —</option>
            <?php foreach ($roles as $r): ?><option value="<?= (int)$r['RoleID'] ?>"><?= h($r['RoleName']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 fieldset-block"><label>Temporary Password <span class="required-star">*</span></label><input class="form-control" type="text" name="password" required placeholder="Min. 8 chars, letters + numbers"></div>
      </div>
      <button class="btn-nbe" style="border:none;" type="submit"><i class="bi bi-check2"></i> Create User</button>
    </form>
  </div>
</div>

<div class="panel">
  <table class="table-modern">
    <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><div class="name-cell"><div class="avatar" style="background:var(--nbe-navy);"><?= h(initials($u['FullName'])) ?></div>
            <div><div class="nm"><?= h($u['FullName']) ?></div><div class="sub">@<?= h($u['Username']) ?> &middot; <?= h($u['JobTitle']) ?></div></div></div></td>
        <td><?= h($u['RoleName']) ?></td>
        <td><?= status_badge($u['Status']) ?></td>
        <td>
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="toggle_status" value="1">
            <input type="hidden" name="user_id" value="<?= (int)$u['UserID'] ?>">
            <input type="hidden" name="new_status" value="<?= $u['Status'] === 'Active' ? 'Inactive' : 'Active' ?>">
            <?php if ($u['Status'] === 'Active'): ?>
              <button type="submit" class="btn-sm-danger" data-confirm="Deactivate this user account?">Deactivate</button>
            <?php else: ?>
              <button type="submit" class="btn-sm-ok">Activate</button>
            <?php endif; ?>
          </form>
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="reset_password" value="1">
            <input type="hidden" name="user_id" value="<?= (int)$u['UserID'] ?>">
            <button type="submit" class="btn-sm-gray" data-confirm="Generate a new temporary password for @<?= h($u['Username']) ?>?"><i class="bi bi-key"></i> Reset Password</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
