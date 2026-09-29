<?php
/**
 * functions.php
 * ---------------------------------------------------------------
 * General-purpose helper functions shared across the application.
 * ---------------------------------------------------------------
 */

/**
 * Records an entry in the AuditLog table. Called after every
 * significant action (login, request submitted, approval, issuance,
 * stock update, user account change, etc.) per FR-14 / NFR Security.
 * The audit log is append-only: the application never issues an
 * UPDATE or DELETE against this table.
 */
function log_action(?int $userId, string $action, ?string $tableName = null, ?int $recordId = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO AuditLog (UserID, Action, TableName, RecordID, Timestamp)
         VALUES (:uid, :action, :tbl, :rid, NOW())'
    );
    $stmt->execute([
        ':uid'    => $userId,
        ':action' => $action,
        ':tbl'    => $tableName,
        ':rid'    => $recordId,
    ]);
}

/**
 * Stores a one-time "flash" message in the session to be displayed
 * on the next page load (e.g. after a redirect following a form post).
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieves and clears any pending flash messages.
 * @return array<int,array{type:string,message:string}>
 */
function flash_get(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Escapes a value for safe HTML output.
 */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Formats a date/datetime string for display, e.g. "Aug 02, 2026".
 */
function fdate($value, string $format = 'M d, Y'): string
{
    if (empty($value)) {
        return '—';
    }
    $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
    return $ts ? date($format, $ts) : '—';
}

/**
 * Formats a number as Ethiopian Birr currency, e.g. "ETB 55,000.00".
 */
function fmoney($value): string
{
    return 'ETB ' . number_format((float)$value, 2);
}

/**
 * Returns a Bootstrap-style status badge (matches the color coding
 * used throughout the UI mockups: green = approved/ok, orange =
 * pending/warning, red = rejected/danger, gray = neutral).
 */
function status_badge(string $status): string
{
    $map = [
        'Pending'   => 'warn',
        'Approved'  => 'ok',
        'Rejected'  => 'danger',
        'Returned'  => 'info',
        'Fulfilled' => 'ok',
        'Active'    => 'ok',
        'Inactive'  => 'danger',
        'Available'   => 'ok',
        'Unavailable' => 'danger',
    ];
    $cls = $map[$status] ?? 'gray';
    return '<span class="badge-pill b-' . $cls . '"><span class="dot"></span>' . h($status) . '</span>';
}

/**
 * Generates a simple CSRF token stored in the session and embeds a
 * hidden input for use inside <form> tags.
 */
function csrf_field(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

/**
 * Validates the CSRF token submitted with a POST request. Call this
 * at the top of every form handler before touching $_POST data.
 */
function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}

/**
 * Returns the initials for an avatar bubble, e.g. "Abel Tesfaye" -> "AT".
 */
function initials(string $fullName): string
{
    $parts = preg_split('/\s+/', trim($fullName));
    $letters = array_map(fn($p) => strtoupper(substr($p, 0, 1)), array_slice($parts, 0, 2));
    return implode('', $letters) ?: '?';
}

/**
 * Ensures the RememberToken table exists. Normally created by
 * database/schema.sql, but this lazy fallback means the "Remember
 * me" feature degrades gracefully (self-heals) instead of throwing
 * a fatal error if a deployment's database was migrated before this
 * table was added to the schema.
 */
function ensure_remember_token_table(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    db()->exec(
        "CREATE TABLE IF NOT EXISTS RememberToken (
            TokenID        INT AUTO_INCREMENT PRIMARY KEY,
            UserID         INT NOT NULL,
            Selector       VARCHAR(24) NOT NULL UNIQUE,
            ValidatorHash  VARCHAR(255) NOT NULL,
            ExpiresAt      DATETIME NOT NULL,
            CreatedAt      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );
    $ready = true;
}

/**
 * Issues a new remember-me token for the given user and sets the
 * corresponding cookie. Call this at login time when the "Remember
 * me" checkbox was ticked, or when rotating an existing token.
 */
function set_remember_cookie(int $userId): void
{
    ensure_remember_token_table();
    $selector  = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));
    $expires   = date('Y-m-d H:i:s', time() + REMEMBER_ME_DAYS * 86400);

    $stmt = db()->prepare(
        'INSERT INTO RememberToken (UserID, Selector, ValidatorHash, ExpiresAt) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $selector, password_hash($validator, PASSWORD_DEFAULT), $expires]);

    setcookie('remember_me', $selector . ':' . $validator, [
        'expires'  => time() + REMEMBER_ME_DAYS * 86400,
        'path'     => BASE_URL !== '' ? BASE_URL . '/' : '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Clears the remember-me cookie from the browser (does not touch
 * the database row — callers that also want the token revoked
 * server-side should DELETE it explicitly).
 */
function clear_remember_cookie(): void
{
    setcookie('remember_me', '', [
        'expires'  => time() - 3600,
        'path'     => BASE_URL !== '' ? BASE_URL . '/' : '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Returns a short list of role-relevant notifications for the topbar
 * bell dropdown. Each item has: icon (Bootstrap Icons class), text,
 * and time (a human-readable relative-ish label).
 */
function get_notifications(array $user): array
{
    $role = $user['role_name'];
    $out = [];

    if ($role === 'Employee') {
        $stmt = db()->prepare(
            "SELECT r.RequestID, r.Status, r.ReviewedDate FROM Request r
             WHERE r.EmployeeID = ? AND r.Status IN ('Approved','Rejected','Returned','Fulfilled')
             ORDER BY r.ReviewedDate DESC LIMIT 5"
        );
        $stmt->execute([$user['employee_id']]);
        foreach ($stmt->fetchAll() as $r) {
            $ok = in_array($r['Status'], ['Approved', 'Fulfilled'], true);
            $out[] = [
                'icon' => $ok ? 'bi-check-circle-fill text-ok' : 'bi-x-circle-fill text-danger',
                'text' => 'Request R-' . str_pad((string)$r['RequestID'], 4, '0', STR_PAD_LEFT) . ' ' . strtolower($r['Status']),
                'time' => fdate($r['ReviewedDate']),
            ];
        }
    } elseif ($role === 'DepartmentHead') {
        $deptStmt = db()->prepare('SELECT DepartmentID FROM Employee WHERE EmployeeID = ?');
        $deptStmt->execute([$user['employee_id']]);
        $deptId = $deptStmt->fetchColumn();
        $stmt = db()->prepare(
            "SELECT r.RequestID, e.FullName, r.SubmittedDate FROM Request r
             JOIN Employee e ON e.EmployeeID = r.EmployeeID
             WHERE e.DepartmentID = ? AND r.Status = 'Pending'
             ORDER BY r.SubmittedDate DESC LIMIT 5"
        );
        $stmt->execute([$deptId]);
        foreach ($stmt->fetchAll() as $r) {
            $out[] = [
                'icon' => 'bi-hourglass-split text-warn',
                'text' => h($r['FullName']) . ' submitted request R-' . str_pad((string)$r['RequestID'], 4, '0', STR_PAD_LEFT),
                'time' => fdate($r['SubmittedDate']),
            ];
        }
    } elseif ($role === 'InventoryOfficer') {
        $low = db()->query("SELECT Name, CurrentQty, MinStockLevel FROM Asset WHERE CurrentQty < MinStockLevel ORDER BY (MinStockLevel-CurrentQty) DESC LIMIT 3")->fetchAll();
        foreach ($low as $a) {
            $out[] = ['icon' => 'bi-exclamation-triangle-fill text-danger', 'text' => "Low stock: {$a['Name']} ({$a['CurrentQty']} left)", 'time' => 'Now'];
        }
        $ready = db()->query(
            "SELECT r.RequestID, e.FullName FROM Request r JOIN Employee e ON e.EmployeeID = r.EmployeeID
             WHERE r.Status='Approved' ORDER BY r.ReviewedDate DESC LIMIT 3"
        )->fetchAll();
        foreach ($ready as $r) {
            $out[] = ['icon' => 'bi-inbox text-info', 'text' => h($r['FullName']) . ' — ready to issue', 'time' => 'Pending'];
        }
    } elseif ($role === 'Procurement') {
        $stmt = db()->query(
            "SELECT r.RequestID, r.RequestType, r.SubmittedDate FROM Request r ORDER BY r.SubmittedDate DESC LIMIT 5"
        )->fetchAll();
        foreach ($stmt as $r) {
            $out[] = ['icon' => 'bi-receipt text-info', 'text' => 'New ' . strtolower($r['RequestType']) . ' request R-' . str_pad((string)$r['RequestID'], 4, '0', STR_PAD_LEFT), 'time' => fdate($r['SubmittedDate'])];
        }
    } elseif ($role === 'HR') {
        $stmt = db()->query(
            "SELECT ia.IssueID, e.FullName, a.Name AS AssetName, ia.IssueDate
             FROM IssuedAsset ia JOIN Employee e ON e.EmployeeID = ia.EmployeeID JOIN Asset a ON a.AssetID = ia.AssetID
             ORDER BY ia.IssueDate DESC LIMIT 5"
        )->fetchAll();
        foreach ($stmt as $r) {
            $out[] = ['icon' => 'bi-person-badge text-info', 'text' => h($r['FullName']) . ' assigned ' . h($r['AssetName']), 'time' => fdate($r['IssueDate'])];
        }
    } elseif ($role === 'Admin') {
        $stmt = db()->query(
            "SELECT al.Action, al.Timestamp, ua.Username FROM AuditLog al
             LEFT JOIN UserAccount ua ON ua.UserID = al.UserID ORDER BY al.Timestamp DESC LIMIT 5"
        )->fetchAll();
        foreach ($stmt as $r) {
            $out[] = ['icon' => 'bi-activity text-info', 'text' => h($r['Action']), 'time' => fdate($r['Timestamp'], 'M d, g:i A')];
        }
    }

    return $out;
}

/**
 * Simple key/value system settings, stored in a SystemSetting table
 * that is created on first use if missing (kept out of schema.sql so
 * the core ERD stays exactly as documented — this is a small config
 * aid, not part of the modeled business data).
 */
function get_setting(string $key, string $default = ''): string
{
    static $tableReady = false;
    if (!$tableReady) {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS SystemSetting (
                SettingKey   VARCHAR(50) PRIMARY KEY,
                SettingValue VARCHAR(255)
            ) ENGINE=InnoDB"
        );
        $tableReady = true;
    }
    $stmt = db()->prepare('SELECT SettingValue FROM SystemSetting WHERE SettingKey = ?');
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false && $val !== '' ? $val : $default;
}

function set_setting(string $key, string $value): void
{
    get_setting($key); // ensures the table exists
    $stmt = db()->prepare(
        'INSERT INTO SystemSetting (SettingKey, SettingValue) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE SettingValue = VALUES(SettingValue)'
    );
    $stmt->execute([$key, $value]);
}

/**
 * The organization / brand name shown throughout the UI (sidebar,
 * login screen, page titles). Reflects the value set on the Admin
 * Settings page, falling back to the APP_NAME constant if it has
 * never been changed.
 */
function app_name(): string
{
    return get_setting('org_name', APP_NAME);
}
function is_valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_password_policy(string $password): array
{
    $errors = [];
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must include both letters and numbers.';
    }
    return $errors;
}
