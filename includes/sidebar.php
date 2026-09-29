<?php
/**
 * sidebar.php
 * ---------------------------------------------------------------
 * Renders the left navigation. The set of links shown depends on
 * the logged-in user's role (role-based access control, FR-13).
 * $activeNav should be set by the including page to highlight the
 * current item, e.g.  $activeNav = 'Dashboard';
 * ---------------------------------------------------------------
 */
$user = current_user();
$activeNav = $activeNav ?? '';

$navByRole = [
    'Employee' => [
        ['section' => 'MAIN'],
        ['label' => 'Dashboard',      'icon' => 'bi-grid-1x2-fill',      'href' => '/employee/dashboard.php'],
        ['label' => 'New Request',    'icon' => 'bi-plus-circle',        'href' => '/employee/new_request.php'],
        ['label' => 'My Requests',    'icon' => 'bi-list-check',         'href' => '/employee/my_requests.php'],
        ['label' => 'Asset Catalog',  'icon' => 'bi-box-seam',           'href' => '/employee/catalog.php'],
        ['section' => 'ACCOUNT'],
        ['label' => 'Profile',        'icon' => 'bi-person-circle',      'href' => '/employee/profile.php'],
    ],
    'DepartmentHead' => [
        ['section' => 'MAIN'],
        ['label' => 'Dashboard',         'icon' => 'bi-grid-1x2-fill',   'href' => '/depthead/dashboard.php'],
        ['label' => 'Pending Requests',  'icon' => 'bi-hourglass-split', 'href' => '/depthead/pending.php'],
        ['label' => 'Dept. History',     'icon' => 'bi-clock-history',   'href' => '/depthead/history.php'],
    ],
    'InventoryOfficer' => [
        ['section' => 'MAIN'],
        ['label' => 'Dashboard',        'icon' => 'bi-grid-1x2-fill',       'href' => '/inventory/dashboard.php'],
        ['label' => 'Approved Queue',   'icon' => 'bi-inbox',               'href' => '/inventory/queue.php'],
        ['label' => 'Issue Asset',      'icon' => 'bi-box-arrow-up-right',  'href' => '/inventory/issue.php'],
        ['label' => 'Stock Overview',   'icon' => 'bi-bar-chart-steps',     'href' => '/inventory/stock.php'],
        ['label' => 'Receive Stock',    'icon' => 'bi-truck',               'href' => '/inventory/receive.php'],
        ['label' => 'Return Asset',     'icon' => 'bi-arrow-return-left',   'href' => '/inventory/return_asset.php'],
    ],
    'Procurement' => [
        ['section' => 'MAIN'],
        ['label' => 'Dashboard',       'icon' => 'bi-grid-1x2-fill',    'href' => '/procurement/dashboard.php'],
        ['label' => 'Budget Reports',  'icon' => 'bi-graph-up-arrow',   'href' => '/procurement/reports.php'],
    ],
    'HR' => [
        ['section' => 'MAIN'],
        ['label' => 'Asset Assignments', 'icon' => 'bi-person-badge', 'href' => '/hr/assignments.php'],
    ],
    'Admin' => [
        ['section' => 'MAIN'],
        ['label' => 'Dashboard',            'icon' => 'bi-grid-1x2-fill', 'href' => '/admin/dashboard.php'],
        ['label' => 'User Management',      'icon' => 'bi-people',       'href' => '/admin/users.php'],
        ['label' => 'Roles & Permissions',  'icon' => 'bi-shield-lock',  'href' => '/admin/roles.php'],
        ['label' => 'System Settings',      'icon' => 'bi-gear',         'href' => '/admin/settings.php'],
        ['label' => 'Audit Log',            'icon' => 'bi-journal-text', 'href' => '/admin/audit.php'],
    ],
];

$roleLabels = [
    'Employee'         => 'Employee Portal',
    'DepartmentHead'   => 'Department Head Portal',
    'InventoryOfficer' => 'Inventory Officer Portal',
    'Procurement'      => 'Procurement Portal',
    'HR'               => 'HR (Talent & Culture)',
    'Admin'            => 'Administrator Portal',
];

$items = $navByRole[$user['role_name']] ?? [];
$roleLabel = $roleLabels[$user['role_name']] ?? '';
?>
<div class="sidebar">
  <a href="<?= role_home_url($user['role_name']) ?>" style="text-decoration:none; color:inherit;">
    <div class="brand">
      <div class="logo">NBE</div>
      <div>
        <div class="name"><?= h(app_name()) ?></div>
        <div class="sub"><?= h($roleLabel) ?></div>
      </div>
    </div>
  </a>
  <nav>
    <?php foreach ($items as $item): ?>
      <?php if (isset($item['section'])): ?>
        <div class="nav-section"><?= h($item['section']) ?></div>
      <?php else: ?>
        <a href="<?= BASE_URL . $item['href'] ?>" class="nav-link <?= $activeNav === $item['label'] ? 'active' : '' ?>">
          <i class="bi <?= h($item['icon']) ?>"></i><span><?= h($item['label']) ?></span>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="foot">
    <div class="avatar-sm"><?= h(initials($user['full_name'] ?? '?')) ?></div>
    <div>
      <div class="uname"><?= h($user['full_name']) ?></div>
      <div class="urole"><?= h($roleLabel) ?></div>
    </div>
  </div>
</div>
