<?php
/**
 * settings.php  (Admin)
 * ---------------------------------------------------------------
 * NFR-Configuration: organization name, logo, password policy,
 * and other adjustable values, stored in a simple key/value
 * SystemSetting table (created on first use if missing) so ordinary
 * changes do not require touching source code.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['Admin']);

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    set_setting('org_name', trim($_POST['org_name'] ?? APP_NAME));
    set_setting('password_min_length', (string)max(6, (int)($_POST['password_min_length'] ?? PASSWORD_MIN_LENGTH)));
    set_setting('password_expiry_days', (string)max(0, (int)($_POST['password_expiry_days'] ?? PASSWORD_EXPIRY_DAYS)));

    log_action((int)$user['user_id'], 'Updated system settings', 'SystemSetting', null);
    flash_set('success', 'System settings saved.');
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$orgName = get_setting('org_name', APP_NAME);
$minLen = get_setting('password_min_length', (string)PASSWORD_MIN_LENGTH);
$expiryDays = get_setting('password_expiry_days', (string)PASSWORD_EXPIRY_DAYS);

$pageTitle = 'System Settings';
$breadcrumb = 'System Settings';
$activeNav = 'System Settings';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel" style="max-width:680px;">
  <div class="panel-body form-modern">
    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-12 fieldset-block"><label>Organization Name</label><input class="form-control" name="org_name" value="<?= h($orgName) ?>"></div>
        <div class="col-12 fieldset-block"><label>Logo</label>
          <div class="d-flex align-items-center gap-3">
            <div class="login-logo" style="margin:0; width:44px;height:44px;font-size:13px;">NBE</div>
            <span class="subtle">Logo upload requires a writable /assets/img folder — configure in a future release.</span>
          </div>
        </div>
        <div class="col-md-6 fieldset-block"><label>Minimum Password Length</label><input class="form-control" type="number" min="6" name="password_min_length" value="<?= h($minLen) ?>"></div>
        <div class="col-md-6 fieldset-block"><label>Password Expiry (days)</label><input class="form-control" type="number" min="0" name="password_expiry_days" value="<?= h($expiryDays) ?>"></div>
        <div class="col-12 fieldset-block"><label>System Version</label><input class="form-control" value="<?= h(APP_VERSION) ?>" disabled style="background:#F7F9FC;"></div>
      </div>
      <div class="divider"></div>
      <button type="submit" class="btn-nbe" style="border:none;"><i class="bi bi-save"></i> Save Settings</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
