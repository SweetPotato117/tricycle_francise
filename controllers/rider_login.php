<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../models/dbconn.php';

function loginResponse($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
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
$_SESSION['rider_id'] = (int) $rider['rider_id'];
$_SESSION['rider_name'] = $rider['full_name'];
$_SESSION['rider_email'] = $rider['email'];
$_SESSION['login_source'] = 'rider';
loginResponse(['success' => true, 'name' => $_SESSION['rider_name'], 'role' => 'Rider']);
