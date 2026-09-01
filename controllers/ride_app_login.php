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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    loginResponse(['success' => false, 'message' => 'Only POST requests are allowed.'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$data = is_array($data) ? $data : $_POST;
$action = $data['action'] ?? 'login';

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    loginResponse(['success' => true]);
}

$identifier = trim($data['username'] ?? $data['email'] ?? '');
$password = $data['password'] ?? '';

if ($identifier === '' || $password === '') {
    loginResponse(['success' => false, 'message' => 'Please enter your email or username and password.'], 422);
}

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
