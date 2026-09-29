<?php
/**
 * audit.php  (Admin)
 * ---------------------------------------------------------------
 * Implements FR-14: all actions in the system are saved in an
 * audit log that cannot be modified by any user. This page is
 * strictly read-only — no update/delete controls are exposed here
 * or anywhere else in the application.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['Admin']);

$dateFilter = $_GET['date'] ?? '';
$sql = "SELECT al.Timestamp, ua.Username, al.Action, al.TableName, al.RecordID
        FROM AuditLog al LEFT JOIN UserAccount ua ON ua.UserID = al.UserID";
$params = [];
if ($dateFilter !== '') {
    $sql .= " WHERE DATE(al.Timestamp) = :d";
    $params[':d'] = $dateFilter;
}
$sql .= " ORDER BY al.Timestamp DESC LIMIT 200";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$pageTitle = 'Audit Log Viewer';
$breadcrumb = 'Audit Log';
$activeNav = 'Audit Log';
require __DIR__ . '/../includes/header.php';
?>

<form method="get" class="d-flex gap-2 mb-3">
  <input class="form-control" type="date" name="date" value="<?= h($dateFilter) ?>" style="max-width:200px; border-radius:9px; border:1.4px solid var(--nbe-border); padding:9px 14px; font-size:13px;">
  <button class="btn-outline-nbe" type="submit"><i class="bi bi-funnel"></i> Filter</button>
  <?php if ($dateFilter): ?><a href="?" class="btn-outline-nbe" style="text-decoration:none;">Clear</a><?php endif; ?>
</form>

<div class="panel">
  <?php if (empty($logs)): ?>
    <div class="empty-state"><i class="bi bi-journal-text"></i>No audit entries found for this filter.</div>
  <?php else: ?>
  <table class="table-modern">
    <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Table</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td class="subtle"><?= fdate($l['Timestamp'], 'M d, Y g:i A') ?></td>
        <td style="font-weight:600;"><?= h($l['Username'] ?? 'system') ?></td>
        <td><?= h($l['Action']) ?></td>
        <td><?php if ($l['TableName']): ?><span class="chip" style="padding:3px 10px;"><?= h($l['TableName']) ?><?= $l['RecordID'] ? ' #' . (int)$l['RecordID'] : '' ?></span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
