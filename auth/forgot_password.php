<?php
/**
 * forgot_password.php
 * ---------------------------------------------------------------
 * The Project Proposal explicitly places an email/notification
 * delivery system out of scope, so this page cannot send a reset
 * link. Instead it offers the honest, still-useful alternative:
 * the employee's request is logged to the AuditLog (visible to the
 * Administrator) so the System Administrator can follow up and
 * issue a new temporary password via Admin > User Management,
 * where a "Reset Password" action is available for exactly this.
 *
 * For security, the response message is identical whether or not
 * the username exists, so this page cannot be used to discover
 * valid usernames.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    if ($username !== '') {
        $stmt = db()->prepare('SELECT UserID FROM UserAccount WHERE Username = ?');
        $stmt->execute([$username]);
        $userId = $stmt->fetchColumn();
        // Log the request either way (log_action tolerates a null
        // UserID) so the message shown to the visitor never reveals
        // whether the username exists.
        log_action($userId ?: null, "Password reset requested for username '{$username}'", 'UserAccount', $userId ?: null);
    }
    $submitted = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot Password &middot; <?= h(APP_SHORT_NAME) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="text-center">
      <div class="login-logo"><i class="bi bi-key-fill"></i></div>
      <h5 style="font-weight:800; color:var(--nbe-navy); margin-bottom:2px;">Reset Your Password</h5>
      <div class="subtle" style="margin-bottom:26px;">
        This system does not send reset emails. Submit your username below and
        your System Administrator will be notified to issue you a new
        temporary password.
      </div>
    </div>

    <?php if ($submitted): ?>
      <div class="alert-modern alert-success" style="display:flex;">
        <i class="bi bi-check-circle-fill"></i>
        If that username exists, your request has been recorded. Please
        contact your System Administrator directly for the fastest response.
      </div>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn-nbe w-100 justify-content-center" style="padding:11px; text-decoration:none;">Back to Sign In</a>
    <?php else: ?>
      <form method="post" class="form-modern">
        <?= csrf_field() ?>
        <div class="fieldset-block">
          <label>Username</label>
          <input class="form-control" name="username" required placeholder="e.g. a.tesfaye" autofocus>
        </div>
        <button type="submit" class="btn-nbe w-100 justify-content-center" style="padding:11px; border:none;">Submit Request <i class="bi bi-arrow-right"></i></button>
      </form>
      <div class="text-center" style="margin-top:18px;">
        <a href="<?= BASE_URL ?>/auth/login.php" class="text-link">Back to Sign In</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
