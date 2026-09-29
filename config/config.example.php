<?php
// Copy this file to config.php and set values for your local environment.
// Do not commit config.php or real credentials.
/**
 * Local configuration for IARMS.
 * Copy this file to config.php and set local database credentials.
 * Demo seed data are not included in this public copy.
 */

// ---- Database connection settings ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'iarms_db');
define('DB_USER', 'your_local_db_user');
define('DB_PASS', 'your_local_db_password');

// ---- Application settings ----
define('APP_NAME', 'National Bank of Ethiopia — IARMS');
define('APP_SHORT_NAME', 'IARMS');
define('APP_VERSION', 'v1.1');
define('BASE_URL', '/iarms');
define('SESSION_TIMEOUT_MINUTES', 30);
define('REMEMBER_ME_DAYS', 30);

// ---- Password policy (also configurable at runtime by Admin > Settings) ----
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_EXPIRY_DAYS', 90);

// ---- Error reporting (turn off display_errors in production) ----
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Africa/Addis_Ababa');

/**
 * Get a shared PDO connection (singleton).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Database connection failed. Please check config/config.php. (' . $e->getMessage() . ')');
        }
    }
    return $pdo;
}
