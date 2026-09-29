<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . role_home_url($_SESSION['role_name']));
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
}
exit;
