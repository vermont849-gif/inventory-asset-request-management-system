<?php
/**
 * auth.php
 * ---------------------------------------------------------------
 * Session bootstrap, login/role guards, and current-user helpers.
 * Include this at the very top of every protected page:
 *
 *     require_once __DIR__ . '/../includes/auth.php';
 *     require_role(['Employee']);   // or whichever roles may view the page
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- "Remember me" auto-login (runs before the idle-timeout check
//      below, so a valid remember-me cookie can transparently start
//      a fresh session even if the old one expired or was never
//      started in this browser session) ----
if (!isset($_SESSION['user_id']) && !empty($_COOKIE['remember_me'])) {
    attempt_remember_me_login();
}

// ---- Idle session timeout ----
if (isset($_SESSION['user_id'])) {
    $idleLimit = SESSION_TIMEOUT_MINUTES * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $idleLimit) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/auth/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Checks the "remember_me" cookie (format: "selector:validator")
 * against the RememberToken table using the selector/validator
 * pattern, and if valid, transparently re-establishes the session
 * exactly as a fresh login would. The token is rotated (old one
 * deleted, new one issued) on every successful use, so a copied
 * cookie stops working the next time the legitimate user's browser
 * uses it.
 */
function attempt_remember_me_login(): void
{
    ensure_remember_token_table();
    $raw = $_COOKIE['remember_me'] ?? '';
    $parts = explode(':', $raw, 2);
    if (count($parts) !== 2) {
        clear_remember_cookie();
        return;
    }
    [$selector, $validator] = $parts;

    $stmt = db()->prepare(
        'SELECT rt.TokenID, rt.UserID, rt.ValidatorHash, rt.ExpiresAt
         FROM RememberToken rt WHERE rt.Selector = ?'
    );
    $stmt->execute([$selector]);
    $token = $stmt->fetch();

    if (!$token || strtotime($token['ExpiresAt']) < time() || !password_verify($validator, $token['ValidatorHash'])) {
        clear_remember_cookie();
        if ($token) {
            db()->prepare('DELETE FROM RememberToken WHERE TokenID = ?')->execute([$token['TokenID']]);
        }
        return;
    }

    $userStmt = db()->prepare(
        "SELECT u.UserID, u.Username, u.Status AS AccountStatus, u.EmployeeID, r.RoleName,
                e.FullName, e.JobTitle, e.Status AS EmployeeStatus, d.DepartmentName
         FROM UserAccount u
         JOIN Role r ON r.RoleID = u.RoleID
         JOIN Employee e ON e.EmployeeID = u.EmployeeID
         LEFT JOIN Department d ON d.DepartmentID = e.DepartmentID
         WHERE u.UserID = ? LIMIT 1"
    );
    $userStmt->execute([$token['UserID']]);
    $user = $userStmt->fetch();

    if (!$user || $user['AccountStatus'] !== 'Active' || $user['EmployeeStatus'] !== 'Active') {
        clear_remember_cookie();
        db()->prepare('DELETE FROM RememberToken WHERE TokenID = ?')->execute([$token['TokenID']]);
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id']       = (int)$user['UserID'];
    $_SESSION['username']      = $user['Username'];
    $_SESSION['role_name']     = $user['RoleName'];
    $_SESSION['employee_id']   = (int)$user['EmployeeID'];
    $_SESSION['full_name']     = $user['FullName'];
    $_SESSION['job_title']     = $user['JobTitle'];
    $_SESSION['department']    = $user['DepartmentName'];
    $_SESSION['last_activity'] = time();

    // Rotate the token: delete the old one, issue a fresh selector/validator.
    db()->prepare('DELETE FROM RememberToken WHERE TokenID = ?')->execute([$token['TokenID']]);
    set_remember_cookie((int)$user['UserID']);

    log_action((int)$user['UserID'], 'Logged in via "remember me"', 'UserAccount', (int)$user['UserID']);
}

/**
 * Returns true if a user is currently logged in.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Redirects to the login page if nobody is logged in.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Redirects to the login page (or shows 403) unless the current
 * user's role is in the allowed list.
 *
 * @param string[] $roles e.g. ['Admin'], ['DepartmentHead','Admin']
 */
function require_role(array $roles): void
{
    require_login();
    if (!in_array($_SESSION['role_name'], $roles, true)) {
        http_response_code(403);
        echo '<div style="font-family:sans-serif;padding:40px;">
                <h2>403 &mdash; Access Denied</h2>
                <p>Your role (' . htmlspecialchars($_SESSION['role_name']) . ') does not have permission to view this page.</p>
                <a href="' . BASE_URL . '/index.php">Return to dashboard</a>
              </div>';
        exit;
    }
}

/**
 * Convenience accessor for the logged-in user's basic info.
 */
function current_user(): array
{
    return [
        'user_id'     => $_SESSION['user_id']     ?? null,
        'username'    => $_SESSION['username']    ?? null,
        'employee_id' => $_SESSION['employee_id'] ?? null,
        'full_name'   => $_SESSION['full_name']   ?? null,
        'role_name'   => $_SESSION['role_name']   ?? null,
        'job_title'   => $_SESSION['job_title']   ?? null,
        'department'  => $_SESSION['department']  ?? null,
    ];
}

/**
 * Maps a RoleName to its "home" dashboard URL, used right after login
 * and by the base layout's logo link.
 */
function role_home_url(string $roleName): string
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
