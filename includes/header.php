<?php
/**
 * header.php
 * ---------------------------------------------------------------
 * Opens the HTML document and the app shell. Expects the including
 * page to have already required auth.php and to have set:
 *   $pageTitle   (string)  e.g. "Dashboard"
 *   $breadcrumb  (string)  e.g. "Dashboard" or "My Requests / R-1042"
 * before calling: require __DIR__ . '/../includes/header.php';
 * ---------------------------------------------------------------
 */
$pageTitle  = $pageTitle  ?? APP_SHORT_NAME;
$breadcrumb = $breadcrumb ?? $pageTitle;
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> &middot; <?= h(APP_SHORT_NAME) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div>
        <div class="crumbs"><?= h(APP_SHORT_NAME) ?> <i class="bi bi-chevron-right" style="font-size:9px;"></i> <span class="cur"><?= h($breadcrumb) ?></span></div>
        <h1><?= h($pageTitle) ?></h1>
      </div>
      <div class="actions">
        <div class="notif-wrap">
          <button type="button" class="icon-btn" id="notifBellBtn" title="Notifications">
            <i class="bi bi-bell"></i><?php $notifs = get_notifications($user); if (!empty($notifs)): ?><span class="dot"></span><?php endif; ?>
          </button>
          <div class="notif-panel" id="notifPanel">
            <div class="notif-panel-head">Notifications</div>
            <?php if (empty($notifs)): ?>
              <div class="notif-empty">You're all caught up.</div>
            <?php else: ?>
              <?php foreach ($notifs as $n): ?>
                <div class="notif-item">
                  <i class="bi <?= $n['icon'] ?>"></i>
                  <div><div class="notif-text"><?= $n['text'] /* already escaped by h() where needed */ ?></div><div class="notif-time"><?= h($n['time']) ?></div></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="icon-btn" title="Log out"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
    <div class="content">
      <?php foreach (flash_get() as $f): ?>
        <div class="alert-modern alert-<?= h($f['type']) ?>">
          <i class="bi bi-<?= $f['type']==='success' ? 'check-circle-fill' : ($f['type']==='danger' ? 'x-circle-fill' : 'info-circle-fill') ?>"></i>
          <?= h($f['message']) ?>
        </div>
      <?php endforeach; ?>
