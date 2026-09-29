<?php
/**
 * login.php
 * ---------------------------------------------------------------
 * Implements the "User Login" flow described in Section 3.6 /
 * Figure 3.8 of the System Development Documentation:
 *   1. View submits username + password (POST)
 *   2. Controller validates input format (empty / length)
 *   3. Controller queries UserAccount by username
 *   4. Controller verifies password hash and account status
 *   5. On success: create session with role, redirect to dashboard
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in? Go straight to the dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: ' . role_home_url_safe($_SESSION['role_name']));
    exit;
}

/** Local copy so this page works even before auth.php is loaded. */
function role_home_url_safe(string $roleName): string
{
    $map = [
        'Employee'         => '/employee/dashboard.php',
        'DepartmentHead'   => '/depthead/dashboard.php',
        'InventoryOfficer' => '/inventory/dashboard.php',
        'Procurement'      => '/procurement/dashboard.php',
        'HR'               => '/hr/assignments.php',
        'Admin'            => '/admin/dashboard.php',
    ];
    return BASE_URL . ($map[$roleName] ?? '/auth/login.php');
}

$errors = [];
$usernameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $usernameValue = $username;

    // ---- Step 2: validate input (empty, format, length) ----
    if ($username === '' || $password === '') {
        $errors[] = 'Please enter both your username and password.';
    } elseif (strlen($username) < 3 || strlen($password) < 6) {
        $errors[] = 'Invalid username or password format.';
    }

    if (empty($errors)) {
        // ---- Step 3: SELECT * FROM UserAccount WHERE Username = ? ----
        $stmt = db()->prepare(
            'SELECT u.UserID, u.Username, u.PasswordHash, u.Status AS AccountStatus,
                    u.EmployeeID, r.RoleName,
                    e.FullName, e.JobTitle, e.Status AS EmployeeStatus,
                    d.DepartmentName
             FROM UserAccount u
             JOIN Role r     ON r.RoleID = u.RoleID
             JOIN Employee e ON e.EmployeeID = u.EmployeeID
             LEFT JOIN Department d ON d.DepartmentID = e.DepartmentID
             WHERE u.Username = :username
             LIMIT 1'
        );
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        // ---- Step 4: verify password hash & account status ----
        if (!$user || !password_verify($password, $user['PasswordHash'])) {
            $errors[] = 'Invalid username or password.';
            log_action(null, "Failed login attempt for username '{$username}'", 'UserAccount', null);
        } elseif ($user['AccountStatus'] !== 'Active' || $user['EmployeeStatus'] !== 'Active') {
            $errors[] = 'This account has been deactivated. Please contact your system administrator.';
        } else {
            // ---- Step 5: success — create session, redirect to role-based dashboard ----
            session_regenerate_id(true);
            $_SESSION['user_id']       = (int)$user['UserID'];
            $_SESSION['username']      = $user['Username'];
            $_SESSION['role_name']     = $user['RoleName'];
            $_SESSION['employee_id']   = (int)$user['EmployeeID'];
            $_SESSION['full_name']     = $user['FullName'];
            $_SESSION['job_title']     = $user['JobTitle'];
            $_SESSION['department']    = $user['DepartmentName'];
            $_SESSION['last_activity'] = time();

            log_action((int)$user['UserID'], 'Logged in', 'UserAccount', (int)$user['UserID']);

            if (!empty($_POST['remember_me'])) {
                set_remember_cookie((int)$user['UserID']);
            }

            header('Location: ' . role_home_url_safe($user['RoleName']));
            exit;
        }
    }
}

$timeout = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign In &middot; <?= h(APP_SHORT_NAME) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="text-center">
      <div class="login-logo">NBE</div>
      <h5 style="font-weight:800; color:var(--nbe-navy); margin-bottom:2px;"><?= h(app_name()) ?></h5>
      <div class="subtle" style="margin-bottom:26px;">Inventory &amp; Asset Request Management System</div>
    </div>

    <?php if ($timeout): ?>
      <div class="login-error"><i class="bi bi-clock-history"></i> Your session expired due to inactivity. Please sign in again.</div>
    <?php endif; ?>
    <?php foreach ($errors as $err): ?>
      <div class="login-error"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($err) ?></div>
    <?php endforeach; ?>

    <form method="post" class="form-modern">
      <?= csrf_field() ?>
      <div class="fieldset-block">
        <label>Username</label>
        <input class="form-control" name="username" required value="<?= h($usernameValue) ?>" placeholder="e.g. a.tesfaye" autofocus>
      </div>
      <div class="fieldset-block">
        <label>Password</label>
        <input class="form-control" type="password" name="password" required placeholder="••••••••••">
      </div>
      <div class="d-flex justify-content-between align-items-center mb-3" style="font-size:12px;">
        <label class="d-flex align-items-center gap-2 subtle"><input type="checkbox" name="remember_me" value="1"> Remember me</label>
        <a href="<?= BASE_URL ?>/auth/forgot_password.php" style="color:var(--nbe-navy); font-weight:600; text-decoration:none;">Forgot password?</a>
      </div>
      <button type="submit" class="btn-nbe w-100 justify-content-center" style="padding:11px; border:none;">Sign In <i class="bi bi-arrow-right"></i></button>
    </form>

    <div class="text-center subtle" style="margin-top:22px; font-size:11px;">
      Protected system &middot; Authorized personnel only &middot; <?= h(APP_VERSION) ?>
    </div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
