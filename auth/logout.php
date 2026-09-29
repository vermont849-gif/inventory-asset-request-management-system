<?php
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    log_action((int)$_SESSION['user_id'], 'Logged out', 'UserAccount', (int)$_SESSION['user_id']);
}

// Revoke any "remember me" token tied to this browser, both the
// cookie and the server-side database row, so logging out is final.
if (!empty($_COOKIE['remember_me'])) {
    ensure_remember_token_table();
    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) === 2) {
        db()->prepare('DELETE FROM RememberToken WHERE Selector = ?')->execute([$parts[0]]);
    }
    clear_remember_cookie();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: ' . BASE_URL . '/auth/login.php');
exit;
