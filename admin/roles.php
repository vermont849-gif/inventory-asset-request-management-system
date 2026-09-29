<?php
/**
 * roles.php  (Admin)
 * ---------------------------------------------------------------
 * Role and Permission Management: an editable RBAC matrix backed
 * by the Role / Permission / RolePermission tables.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/auth.php';
require_role(['Admin']);

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $checked = $_POST['perm'] ?? []; // array of "roleId_permId" => "1"

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM RolePermission');
        $ins = $pdo->prepare('INSERT INTO RolePermission (RoleID, PermissionID) VALUES (?, ?)');
        foreach ($checked as $key => $val) {
            [$roleId, $permId] = explode('_', $key);
            $ins->execute([(int)$roleId, (int)$permId]);
        }
        $pdo->commit();
        log_action((int)$user['user_id'], 'Updated RBAC permission matrix', 'RolePermission', null);
        flash_set('success', 'Role permissions updated successfully.');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash_set('danger', 'Could not save the permission matrix.');
    }
    header('Location: ' . BASE_URL . '/admin/roles.php');
    exit;
}

$roles = db()->query('SELECT RoleID, RoleName FROM Role ORDER BY RoleID')->fetchAll();
$permissions = db()->query('SELECT PermissionID, PermissionName, Module FROM Permission ORDER BY PermissionID')->fetchAll();
$granted = db()->query('SELECT RoleID, PermissionID FROM RolePermission')->fetchAll();
$grantedSet = [];
foreach ($granted as $g) { $grantedSet[$g['RoleID'] . '_' . $g['PermissionID']] = true; }

$pageTitle = 'Role & Permission Management';
$breadcrumb = 'Roles & Permissions';
$activeNav = 'Roles & Permissions';
require __DIR__ . '/../includes/header.php';
?>

<form method="post">
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel-head"><h6>RBAC Matrix</h6><span class="subtle">Toggle to grant / revoke a permission per role</span></div>
    <table class="table-modern">
      <thead><tr><th>Permission</th>
        <?php foreach ($roles as $r): ?><th style="text-align:center;"><?= h($r['RoleName']) ?></th><?php endforeach; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($permissions as $p): ?>
        <tr>
          <td style="font-weight:600;"><?= h($p['PermissionName']) ?><div class="subtle" style="font-size:10.5px;"><?= h($p['Module']) ?></div></td>
          <?php foreach ($roles as $r):
              $key = $r['RoleID'] . '_' . $p['PermissionID'];
              $isChecked = isset($grantedSet[$key]);
          ?>
            <td style="text-align:center;">
              <input type="checkbox" name="perm[<?= $key ?>]" value="1" <?= $isChecked ? 'checked' : '' ?>
                     style="width:16px;height:16px; accent-color:var(--nbe-navy);">
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="panel-body"><button type="submit" class="btn-nbe" style="border:none;"><i class="bi bi-save"></i> Save Changes</button></div>
  </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
