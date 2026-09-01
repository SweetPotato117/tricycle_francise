<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$modelsPath = __DIR__ . '/../models';
set_include_path($modelsPath . PATH_SEPARATOR . get_include_path());
require_once $modelsPath . '/functions.php';

function respond($payload, $status = 200)
{
	http_response_code($status);
	echo json_encode($payload);
	exit;
}

function requestData()
{
	$raw = file_get_contents('php://input');
	if ($raw !== '') {
		$data = json_decode($raw, true);
		if (json_last_error() === JSON_ERROR_NONE && is_array($data)) return $data;
	}
	return $_POST;
}

function resolveAccountId($data)
{
	$id = $data['admin_id'] ?? $data['id'] ?? null;
	$id = filter_var($id, FILTER_VALIDATE_INT);
	return $id === false ? null : (int) $id;
}

function requireSuperAdmin()
{
	if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'Super Admin') {
		respond(['success' => false, 'message' => 'Super Admin access required.'], 403);
	}
}

function ensureAdminAddressColumn()
{
	global $conn;
	$result = mysqli_query($conn, "SHOW COLUMNS FROM admins LIKE 'address'");
	if (!$result || mysqli_num_rows($result) === 0) mysqli_query($conn, 'ALTER TABLE admins ADD COLUMN address TEXT NULL');
	if ($result) mysqli_free_result($result);
}

function accountPayload($data)
{
	$payload = [
		'first_name' => trim($data['firstName'] ?? ''),
		'last_name' => trim($data['lastName'] ?? ''),
		'username' => trim($data['username'] ?? ''),
		'email' => trim($data['email'] ?? ''),
		'address' => trim($data['address'] ?? ''),
		'role' => $data['role'] ?? 'Admin',
		'status' => $data['status'] ?? 'Active'
	];
	if ($payload['first_name'] === '' || $payload['last_name'] === '' || $payload['username'] === '' || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
		respond(['success' => false, 'message' => 'Please provide valid account details.'], 422);
	}
	if (!in_array($payload['role'], ['Admin', 'Super Admin'], true) || !in_array($payload['status'], ['Active', 'Inactive'], true)) {
		respond(['success' => false, 'message' => 'Please select a valid role and status.'], 422);
	}
	return $payload;
}

function listAccounts()
{
	ensureAdminAddressColumn();
	$rows = getAllRecords('admins', 'ORDER BY admin_id DESC');
	$accounts = [];
	foreach ($rows as $row) {
		$accountId = (int) ($row['admin_id'] ?? $row['id'] ?? 0);
		$accounts[] = [
			'id' => $accountId,
			'admin_id' => $accountId,
			'firstName' => $row['first_name'] ?? '',
			'lastName' => $row['last_name'] ?? '',
			'username' => $row['username'] ?? '',
			'email' => $row['email'] ?? '',
			'address' => $row['address'] ?? '',
			'role' => $row['role'],
			'status' => $row['status'],
			'lastLogin' => $row['last_login'] ? str_replace(' ', 'T', $row['last_login']) : ''
		];
	}
	respond(['success' => true, 'accounts' => $accounts]);
}

try {
	requireSuperAdmin();
	ensureAdminAddressColumn();
	if ($_SERVER['REQUEST_METHOD'] === 'GET') listAccounts();

	$data = requestData();
	$action = $data['action'] ?? '';
	$id = resolveAccountId($data);

	if ($action === 'create') {
		$payload = accountPayload($data);
		$usernameExists = getRecord('admins', 'username = ? LIMIT 1', [$payload['username']]);
		$emailExists = getRecord('admins', 'email = ? LIMIT 1', [$payload['email']]);
		if ($usernameExists || $emailExists) {
			respond(['success' => false, 'message' => 'An account with that username or email already exists.'], 409);
		}
		$password = $data['password'] ?? '';
		if (strlen($password) < 8) respond(['success' => false, 'message' => 'Password must be at least 8 characters.'], 422);
		$payload['password'] = password_hash($password, PASSWORD_DEFAULT);
		$newId = insertSomething('admins', $payload);
		respond(['success' => true, 'id' => $newId, 'admin_id' => $newId], 201);
	}

	if (!$id || !getRecord('admins', 'admin_id = ?', [$id])) respond(['success' => false, 'message' => 'Account not found.'], 404);

	if ($action === 'update') {
		$payload = accountPayload($data);
		$duplicateUsername = getRecord('admins', 'admin_id != ? AND username = ? LIMIT 1', [$id, $payload['username']]);
		$duplicateEmail = getRecord('admins', 'admin_id != ? AND email = ? LIMIT 1', [$id, $payload['email']]);
		if ($duplicateUsername || $duplicateEmail) {
			respond(['success' => false, 'message' => 'Another account is already using that username or email.'], 409);
		}
		$password = $data['password'] ?? '';
		if ($password !== '') {
			if (strlen($password) < 8) respond(['success' => false, 'message' => 'Password must be at least 8 characters.'], 422);
			$payload['password'] = password_hash($password, PASSWORD_DEFAULT);
		}
		updateRecord('admins', $payload, 'admin_id = ?', [$id]);
		respond(['success' => true, 'admin_id' => $id]);
	}

	if ($action === 'status') {
		$status = $data['status'] ?? '';
		if (!in_array($status, ['Active', 'Inactive'], true)) respond(['success' => false, 'message' => 'Invalid account status.'], 422);
		updateRecord('admins', ['status' => $status], 'admin_id = ?', [$id]);
		respond(['success' => true, 'admin_id' => $id]);
	}

	if ($action === 'delete') {
		if ($id === (int) $_SESSION['admin_id']) respond(['success' => false, 'message' => 'You cannot remove your own account.'], 422);
		deleteRecord('admins', 'admin_id = ?', [$id]);
		respond(['success' => true, 'admin_id' => $id]);
	}

	respond(['success' => false, 'message' => 'Invalid request.'], 400);
} catch (Throwable $error) {
	respond(['success' => false, 'message' => 'Unable to process account request.'], 500);
}
