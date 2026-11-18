<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
header('Content-Type: application/json; charset=utf-8');
$modelsPath = __DIR__ . '/../models';
set_include_path($modelsPath . PATH_SEPARATOR . get_include_path());
require_once $modelsPath . '/notifications.php';
require_once $modelsPath . '/notification_triggers.php';

function loginResponse($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function clearAdminSession()
{
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_username'],
        $_SESSION['admin_email'],
        $_SESSION['admin_role'],
        $_SESSION['admin_name']
    );
}

function ensureRiderPasswordColumn()
{
    global $conn;
    $result = mysqli_query($conn, "SHOW COLUMNS FROM riders LIKE 'password'");
    if (!$result || mysqli_num_rows($result) === 0) {
        mysqli_query($conn, 'ALTER TABLE riders ADD COLUMN password VARCHAR(255) NULL AFTER email');
    }
    if ($result) mysqli_free_result($result);
}

function ensurePasswordResetTable()
{
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS account_password_resets (
        reset_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        account_type ENUM('rider', 'admin') NOT NULL,
        account_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (reset_id),
        UNIQUE KEY token_hash (token_hash),
        KEY account_created (account_type, account_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!mysqli_query($conn, $sql)) {
        throw new RuntimeException('Could not prepare password reset storage: ' . mysqli_error($conn));
    }
}

function passwordResetUrl($token)
{
    $configuredBaseUrl = defined('TRICYCLE_APP_URL') ? TRICYCLE_APP_URL : getenv('TRICYCLE_APP_URL');
    if ($configuredBaseUrl) {
        $parts = parse_url($configuredBaseUrl);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('TRICYCLE_APP_URL must be an http(s) URL to the application root.');
        }
        $baseUrl = rtrim($configuredBaseUrl, '/');
    } else {
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        if (!preg_match('/\A(?:[a-z0-9.-]+|\[[a-f0-9:]+\])\z/i', $host)) {
            throw new RuntimeException('The server name cannot be used to build a password reset link.');
        }
        $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
        if ($port && !(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
            $host .= ':' . $port;
        }
        $scriptDirectory = dirname($_SERVER['SCRIPT_NAME'] ?? '/controllers/rider_login.php');
        $applicationPath = dirname(str_replace('\\', '/', $scriptDirectory));
        $baseUrl = $scheme . '://' . $host . rtrim($applicationPath, '/');
    }

    return $baseUrl . '/rider/reset_password.html?token=' . rawurlencode($token);
}

function sendPasswordResetEmail($email, $token)
{
    $resetUrl = passwordResetUrl($token);
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
    $message = "Use this link to reset your password. It expires in 60 minutes and can only be used once:\n\n" . $resetUrl . "\n\nIf you did not request a password reset, you can ignore this email.";
    $html = '<p>We received a request to reset your password.</p>'
        . '<p><a href="' . $safeUrl . '">Reset your password</a></p>'
        . '<p>This link expires in 60 minutes and can only be used once.</p>'
        . '<p>If you did not request a password reset, you can ignore this email.</p>';
    return sendEmail($email, 'Password Reset', $message, $html);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    loginResponse(['success' => false, 'message' => 'Only POST requests are allowed.'], 405);
}

ensureRiderPasswordColumn();
$data = json_decode(file_get_contents('php://input'), true);
$data = is_array($data) ? $data : $_POST;
$action = $data['action'] ?? 'login';

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    loginResponse(['success' => true]);
}

if ($action === 'request-password-reset') {
    $emailInput = $data['email'] ?? '';
    if (!is_string($emailInput)) {
        loginResponse(['success' => false, 'message' => 'Enter a valid email address.'], 422);
    }
    $email = trim($emailInput);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        loginResponse(['success' => false, 'message' => 'Enter a valid email address.'], 422);
    }

    try {
        ensurePasswordResetTable();
        if (!mysqli_query($conn, 'DELETE FROM account_password_resets WHERE expires_at <= NOW()')) {
            throw new RuntimeException('Could not remove expired password reset tokens: ' . mysqli_error($conn));
        }
        $lookup = mysqli_prepare($conn, "SELECT 'rider' AS account_type, rider_id AS account_id, email FROM riders WHERE email = ? AND status = 'Active' LIMIT 1");
        mysqli_stmt_bind_param($lookup, 's', $email);
        mysqli_stmt_execute($lookup);
        $account = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup));
        mysqli_stmt_close($lookup);

        if (!$account) {
            $lookup = mysqli_prepare($conn, "SELECT 'admin' AS account_type, admin_id AS account_id, email FROM admins WHERE email = ? AND role = 'Admin' AND status = 'Active' LIMIT 1");
            mysqli_stmt_bind_param($lookup, 's', $email);
            mysqli_stmt_execute($lookup);
            $account = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup));
            mysqli_stmt_close($lookup);
        }

        if ($account) {
            $type = $account['account_type'];
            $accountId = (int) $account['account_id'];
            $throttle = mysqli_prepare($conn, 'SELECT reset_id FROM account_password_resets WHERE account_type = ? AND account_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE) LIMIT 1');
            mysqli_stmt_bind_param($throttle, 'si', $type, $accountId);
            mysqli_stmt_execute($throttle);
            $recentRequest = mysqli_fetch_assoc(mysqli_stmt_get_result($throttle));
            mysqli_stmt_close($throttle);

            if (!$recentRequest) {
                $delete = mysqli_prepare($conn, 'DELETE FROM account_password_resets WHERE account_type = ? AND account_id = ?');
                mysqli_stmt_bind_param($delete, 'si', $type, $accountId);
                mysqli_stmt_execute($delete);
                mysqli_stmt_close($delete);

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $insert = mysqli_prepare($conn, 'INSERT INTO account_password_resets (account_type, account_id, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))');
                mysqli_stmt_bind_param($insert, 'sis', $type, $accountId, $tokenHash);
                mysqli_stmt_execute($insert);
                mysqli_stmt_close($insert);

                if (!sendPasswordResetEmail($email, $token)) {
                    $delete = mysqli_prepare($conn, 'DELETE FROM account_password_resets WHERE token_hash = ?');
                    mysqli_stmt_bind_param($delete, 's', $tokenHash);
                    mysqli_stmt_execute($delete);
                    mysqli_stmt_close($delete);
                }
            }
        }
    } catch (Throwable $error) {
        error_log('Password reset request failed: ' . $error->getMessage());
        loginResponse(['success' => false, 'message' => 'Unable to process the password reset request right now.'], 500);
    }

    loginResponse(['success' => true, 'message' => 'If an active account uses that email, a password reset link will be sent.']);
}

if ($action === 'reset-password') {
    $tokenInput = $data['token'] ?? '';
    $password = $data['password'] ?? '';
    if (!is_string($tokenInput) || !preg_match('/\A[a-f0-9]{64}\z/i', $tokenInput)) {
        loginResponse(['success' => false, 'message' => 'This password reset link is invalid or expired. Request a new one.'], 400);
    }
    $token = trim($tokenInput);
    if (!is_string($password) || strlen($password) < 8) {
        loginResponse(['success' => false, 'message' => 'Your password must be at least 8 characters.'], 422);
    }

    $transactionStarted = false;
    $accountDetails = null;
    try {
        ensurePasswordResetTable();
        mysqli_begin_transaction($conn);
        $transactionStarted = true;

        $tokenHash = hash('sha256', $token);
        $lookup = mysqli_prepare($conn, 'SELECT account_type, account_id FROM account_password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1 FOR UPDATE');
        mysqli_stmt_bind_param($lookup, 's', $tokenHash);
        mysqli_stmt_execute($lookup);
        $reset = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup));
        mysqli_stmt_close($lookup);

        if (!$reset) {
            mysqli_rollback($conn);
            loginResponse(['success' => false, 'message' => 'This password reset link is invalid or expired. Request a new one.'], 400);
        }

        $accountId = (int) $reset['account_id'];
        if ($reset['account_type'] === 'rider') {
            $accountLookup = mysqli_prepare($conn, 'SELECT full_name AS account_name, email FROM riders WHERE rider_id = ? AND status = ?');
        } else {
            $accountLookup = mysqli_prepare($conn, "SELECT CONCAT_WS(' ', first_name, last_name) AS account_name, email FROM admins WHERE admin_id = ? AND role = 'Admin' AND status = 'Active'");
        }
        if (!$accountLookup) {
            throw new RuntimeException('Could not load account details for password reset: ' . mysqli_error($conn));
        }
        if ($reset['account_type'] === 'rider') {
            $activeStatus = 'Active';
            mysqli_stmt_bind_param($accountLookup, 'is', $accountId, $activeStatus);
        } else {
            mysqli_stmt_bind_param($accountLookup, 'i', $accountId);
        }
        if (!mysqli_stmt_execute($accountLookup)) {
            mysqli_stmt_close($accountLookup);
            throw new RuntimeException('Could not load account details for password reset: ' . mysqli_error($conn));
        }
        $accountDetails = mysqli_fetch_assoc(mysqli_stmt_get_result($accountLookup));
        mysqli_stmt_close($accountLookup);
        if (!$accountDetails) {
            mysqli_rollback($conn);
            loginResponse(['success' => false, 'message' => 'This password reset link is invalid or expired. Request a new one.'], 400);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($reset['account_type'] === 'rider') {
            $update = mysqli_prepare($conn, "UPDATE riders SET password = ? WHERE rider_id = ? AND status = 'Active'");
        } else {
            $update = mysqli_prepare($conn, "UPDATE admins SET password = ? WHERE admin_id = ? AND role = 'Admin' AND status = 'Active'");
        }
        mysqli_stmt_bind_param($update, 'si', $passwordHash, $accountId);
        mysqli_stmt_execute($update);
        $updated = mysqli_stmt_affected_rows($update) === 1;
        mysqli_stmt_close($update);

        if (!$updated) {
            mysqli_rollback($conn);
            loginResponse(['success' => false, 'message' => 'This password reset link is invalid or expired. Request a new one.'], 400);
        }

        $delete = mysqli_prepare($conn, 'DELETE FROM account_password_resets WHERE account_type = ? AND account_id = ?');
        mysqli_stmt_bind_param($delete, 'si', $reset['account_type'], $accountId);
        mysqli_stmt_execute($delete);
        mysqli_stmt_close($delete);
        mysqli_commit($conn);
        $transactionStarted = false;
        $_SESSION = [];
        session_destroy();
    } catch (Throwable $error) {
        if ($transactionStarted) mysqli_rollback($conn);
        error_log('Password reset failed: ' . $error->getMessage());
        loginResponse(['success' => false, 'message' => 'Unable to update your password right now. Request a new link and try again.'], 500);
    }

    $response = ['success' => true, 'message' => 'Your password has been updated. You can now sign in.'];
    $accountType = $reset['account_type'] === 'rider' ? 'Rider' : 'Admin';
    $accountName = trim((string) ($accountDetails['account_name'] ?? '')) ?: "$accountType #$accountId";
    $accountEmail = trim((string) ($accountDetails['email'] ?? ''));
    try {
        if (!notifyAccountPasswordChanged($accountId, $accountType, $accountName, $accountEmail)) {
            $response['notificationWarning'] = 'Your password changed, but one or more notification emails or Super Admin alerts could not be delivered.';
        }
    } catch (Throwable $error) {
        error_log("Password reset notifications failed for $accountType ID $accountId: " . $error->getMessage());
        $response['notificationWarning'] = 'Your password changed, but one or more notification emails or Super Admin alerts could not be delivered.';
    }
    loginResponse($response);
}

if ($action === 'register') {
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        loginResponse(['success' => false, 'message' => 'Name, valid email, and an 8-character password are required.'], 422);
    }
    $check = mysqli_prepare($conn, 'SELECT rider_id FROM riders WHERE email = ? LIMIT 1');
    mysqli_stmt_bind_param($check, 's', $email);
    mysqli_stmt_execute($check);
    $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
    mysqli_stmt_close($check);
    if ($exists) loginResponse(['success' => false, 'message' => 'A rider account already uses this email address.'], 422);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $status = 'Active';
    $stmt = mysqli_prepare($conn, 'INSERT INTO riders (full_name, email, password, status) VALUES (?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $hash, $status);
    mysqli_stmt_execute($stmt);
    $riderId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    session_regenerate_id(true);
    clearAdminSession();
    $_SESSION['rider_id'] = $riderId;
    $_SESSION['rider_name'] = $name;
    $_SESSION['rider_email'] = $email;
    loginResponse(['success' => true, 'name' => $name], 201);
}

$identifier = trim($data['username'] ?? $data['email'] ?? '');
$password = $data['password'] ?? '';
if ($identifier === '' || $password === '') {
    loginResponse(['success' => false, 'message' => 'Please enter your email or username and password.'], 422);
}

$stmt = mysqli_prepare($conn, 'SELECT rider_id, full_name, email, password, status FROM riders WHERE email = ? OR email = ? LIMIT 1');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $identifier, $identifier);
    mysqli_stmt_execute($stmt);
    $rider = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
} else {
    $rider = null;
}

$validRider = $rider && $rider['status'] === 'Active' && !empty($rider['password']) && password_verify($password, $rider['password']);

if (!$validRider) {
    $adminStmt = mysqli_prepare($conn, 'SELECT admin_id, first_name, last_name, username, email, password, role, status FROM admins WHERE (username = ? OR email = ?) LIMIT 1');
    if ($adminStmt) {
        mysqli_stmt_bind_param($adminStmt, 'ss', $identifier, $identifier);
        mysqli_stmt_execute($adminStmt);
        $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($adminStmt));
        mysqli_stmt_close($adminStmt);
    } else {
        $admin = null;
    }

    $validAdmin = $admin
        && ($admin['role'] ?? '') === 'Admin'
        && ($admin['status'] ?? '') === 'Active'
        && !empty($admin['password'])
        && (password_verify($password, $admin['password']) || $admin['password'] === $password);

    if (!$validAdmin) {
        loginResponse(['success' => false, 'message' => 'Wrong password or account not found.'], 401);
    }

    $updateStmt = mysqli_prepare($conn, 'UPDATE admins SET last_login = NOW() WHERE admin_id = ?');
    if ($updateStmt) {
        mysqli_stmt_bind_param($updateStmt, 'i', $admin['admin_id']);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['admin_id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['admin_name'] = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')) ?: $admin['username'];
    $_SESSION['rider_id'] = (int) $admin['admin_id'];
    $_SESSION['rider_name'] = $_SESSION['admin_name'];
    $_SESSION['rider_email'] = $admin['email'];
    $_SESSION['login_source'] = 'admin';
    loginResponse(['success' => true, 'name' => $_SESSION['rider_name'], 'role' => 'Admin']);
}

session_regenerate_id(true);
clearAdminSession();
$_SESSION['rider_id'] = (int) $rider['rider_id'];
$_SESSION['rider_name'] = $rider['full_name'];
$_SESSION['rider_email'] = $rider['email'];
$_SESSION['login_source'] = 'rider';
loginResponse(['success' => true, 'name' => $_SESSION['rider_name'], 'role' => 'Rider']);
