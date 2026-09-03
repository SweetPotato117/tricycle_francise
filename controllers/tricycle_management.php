<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$modelsPath = __DIR__ . '/../models';
set_include_path($modelsPath . PATH_SEPARATOR . get_include_path());
require_once $modelsPath . '/functions.php';
require_once $modelsPath . '/notifications.php';
require_once $modelsPath . '/notification_triggers.php';
require_once $modelsPath . '/upload.php';

function respond($payload, $status = 200)
{
	http_response_code($status);
	echo json_encode($payload);
	exit;
}

function requestData()
{
	$raw = file_get_contents('php://input');
	$data = json_decode($raw, true);
	return is_array($data) ? $data : $_POST;
}

function currentAdminId()
{
	return (int) ($_SESSION['admin_id'] ?? 0);
}

function isSuperAdmin()
{
	if (trim((string) ($_SESSION['admin_role'] ?? '')) === 'Super Admin') return true;
	$adminId = currentAdminId();
	if (!$adminId) return false;
	$admin = getRecord('admins', 'admin_id = ? AND status = ?', [$adminId, 'Active']);
	return trim((string) ($admin['role'] ?? '')) === 'Super Admin';
}

function ensureTricycleStatusColumn()
{
	global $conn;
	$check = mysqli_query($conn, "SHOW COLUMNS FROM tricycles LIKE 'status'");
	if ($check && mysqli_num_rows($check) === 0) {
		mysqli_query($conn, "ALTER TABLE tricycles ADD COLUMN status ENUM('Active','Inactive','Pending') NOT NULL DEFAULT 'Pending' AFTER plate_number");
	}
}

function ensureTricycleOwnershipColumn()
{
	global $conn;
	$check = mysqli_query($conn, "SHOW COLUMNS FROM tricycles LIKE 'admin_id'");
	if ($check && mysqli_num_rows($check) === 0) {
		mysqli_query($conn, "ALTER TABLE tricycles ADD COLUMN admin_id INT NULL DEFAULT NULL AFTER status");
		mysqli_query($conn, "ALTER TABLE tricycles ADD INDEX idx_tricycles_admin_id (admin_id)");
	}
}

function ensureTricycleDocumentColumns()
{
	global $conn;
	foreach (['or_document', 'cr_document'] as $column) {
		$check = mysqli_query($conn, "SHOW COLUMNS FROM tricycles LIKE '$column'");
		if ($check && mysqli_num_rows($check) === 0) mysqli_query($conn, "ALTER TABLE tricycles ADD COLUMN $column VARCHAR(255) NULL");
	}
}

function idValue($value)
{
	$id = filter_var($value, FILTER_VALIDATE_INT);
	return $id === false ? null : $id;
}

function tricyclePayload($data)
{
	$brand = trim($data['brand'] ?? '');
	$sticker = trim($data['sticker'] ?? $data['sticker_number'] ?? $data['body_number'] ?? '');
	$engine = trim($data['engine'] ?? $data['engine_number'] ?? '');
	$chassis = trim($data['chassis'] ?? $data['chassis_number'] ?? '');
	$plate = trim($data['plate'] ?? $data['plate_number'] ?? '');
	$status = trim($data['status'] ?? $data['approval_status'] ?? 'Pending');
	if ($brand === '' || $sticker === '' || $engine === '' || $chassis === '' || $plate === '') {
		respond(['success' => false, 'message' => 'Brand, body number/sticker, plate, engine, and chassis numbers are required.'], 422);
	}
	if (!in_array($status, ['Pending', 'Active', 'Inactive'], true)) {
		respond(['success' => false, 'message' => 'Please select a valid tricycle status.'], 422);
	}

	return [
		'brand' => $brand,
		'sticker_number' => $sticker,
		'engine_number' => $engine,
		'chassis_number' => $chassis,
		'plate_number' => $plate,
		'color' => trim($data['color'] ?? ''),
		'status' => $status
	];
}

function replaceAssignment($table, $column, $tricycleId, $assignedId)
{
	deleteRecord($table, 'tricycle_id = ?', [$tricycleId]);
	if ($assignedId) {
		insertSomething($table, [$column => $assignedId, 'tricycle_id' => $tricycleId]);
	}
}

function validateActiveDriver($driverId)
{
	if (!$driverId) return null;
	$driver = getRecord('drivers', 'driver_id = ? AND status = ?', [(int) $driverId, 'Approved']);
	if (!$driver) respond(['success' => false, 'message' => 'Only active drivers can be assigned to a tricycle.'], 422);
	return (int) $driverId;
}

function listTricycles()
{
	ensureTricycleStatusColumn();
	ensureTricycleOwnershipColumn();
	ensureTricycleDocumentColumns();
	$adminId = currentAdminId();
	$tricycleQuery = isSuperAdmin() ? 'ORDER BY tricycle_id DESC' : 'WHERE admin_id = ? ORDER BY tricycle_id DESC';
	$tricycleParams = isSuperAdmin() ? [] : [$adminId];
	$tricycles = getAllRecords('tricycles', $tricycleQuery, $tricycleParams);
	$drivers = isSuperAdmin() ? getAllRecords('drivers', 'ORDER BY driver_id DESC') : getAllRecords('drivers', 'WHERE admin_id = ? ORDER BY driver_id DESC', [$adminId]);
	$franchises = getAllRecords('franchises');
	$driverNames = [];
	$franchiseNames = [];
	foreach ($drivers as $driver) $driverNames[(int) $driver['driver_id']] = $driver['full_name'];
	foreach ($franchises as $franchise) $franchiseNames[(int) $franchise['franchise_id']] = $franchise['franchise_name'];

	$driverAssignments = getAllRecords('driver_tricycle');
	$franchiseAssignments = getAllRecords('franchise_tricycle');
	$assignedDrivers = [];
	$assignedFranchises = [];
	foreach ($driverAssignments as $assignment) $assignedDrivers[(int) $assignment['tricycle_id']] = (int) $assignment['driver_id'];
	foreach ($franchiseAssignments as $assignment) $assignedFranchises[(int) $assignment['tricycle_id']] = (int) $assignment['franchise_id'];

	foreach ($tricycles as &$tricycle) {
		$id = (int) $tricycle['tricycle_id'];
		$orDocument = !empty($tricycle['or_document']) ? uploadUrl($tricycle['or_document']) : '';
		$driverId = $assignedDrivers[$id] ?? null;
		$franchiseId = $assignedFranchises[$id] ?? null;
		$tricycle = [
			'id' => $id,
			'brand' => $tricycle['brand'],
			'sticker' => $tricycle['sticker_number'] ?? '',
			'plate' => $tricycle['plate_number'],
			'engine' => $tricycle['engine_number'],
			'chassis' => $tricycle['chassis_number'],
			'color' => $tricycle['color'] ?? '',
			'status' => $tricycle['status'] ?? 'Pending',
			'driverId' => $driverId,
			'driver' => $driverId ? ($driverNames[$driverId] ?? '') : '',
			'franchiseId' => $franchiseId,
			'franchise' => $franchiseId ? ($franchiseNames[$franchiseId] ?? '') : '',
			'orDocument' => $orDocument
		];
	}
	unset($tricycle);

	respond(['success' => true, 'tricycles' => $tricycles, 'drivers' => $drivers, 'franchises' => $franchises]);
}

try {
	if ($_SERVER['REQUEST_METHOD'] === 'GET') listTricycles();
	$data = requestData();
	$action = $data['action'] ?? '';
	$id = idValue($data['id'] ?? null);

	if ($action === 'create') {
		ensureTricycleStatusColumn();
		ensureTricycleOwnershipColumn();
		$adminId = currentAdminId();
		if (!$adminId) respond(['success' => false, 'message' => 'Admin session required.'], 401);
		$payload = tricyclePayload($data);
		$driverId = validateActiveDriver(idValue($data['driver_id'] ?? null));
		if (!isSuperAdmin()) {
			$duplicate = getRecord('tricycles', 'admin_id = ? AND (plate_number = ? OR engine_number = ? OR chassis_number = ? OR sticker_number = ?)', [$adminId, $payload['plate_number'], $payload['engine_number'], $payload['chassis_number'], $payload['sticker_number']]);
			if ($duplicate) respond(['success' => false, 'message' => 'This tricycle already exists for this admin account.'], 409);
		}
		$payload['admin_id'] = $adminId;
		$tricycleId = insertSomething('tricycles', $payload);
		replaceAssignment('driver_tricycle', 'driver_id', $tricycleId, $driverId);
		replaceAssignment('franchise_tricycle', 'franchise_id', $tricycleId, idValue($data['franchise_id'] ?? null));
		respond(['success' => true, 'id' => $tricycleId], 201);
	}

	if ($action === 'update' && $id) {
		ensureTricycleStatusColumn();
		ensureTricycleOwnershipColumn();
		$existing = getRecord('tricycles', 'tricycle_id = ?', [$id]);
		if (!$existing) respond(['success' => false, 'message' => 'Tricycle not found.'], 404);
		if (!isSuperAdmin() && (int) $existing['admin_id'] !== currentAdminId()) respond(['success' => false, 'message' => 'You can only edit your own tricycle records.'], 403);
		$payload = tricyclePayload($data);
		$driverId = validateActiveDriver(idValue($data['driver_id'] ?? null));
		if (!isSuperAdmin()) {
			$duplicate = getRecord('tricycles', 'admin_id = ? AND tricycle_id != ? AND (plate_number = ? OR engine_number = ? OR chassis_number = ? OR sticker_number = ?)', [$adminId = currentAdminId(), $id, $payload['plate_number'], $payload['engine_number'], $payload['chassis_number'], $payload['sticker_number']]);
			if ($duplicate) respond(['success' => false, 'message' => 'This tricycle already exists for this admin account.'], 409);
		}
		$existingDriverAssignment = getRecord('driver_tricycle', 'tricycle_id = ?', [$id]);
		$existingFranchiseAssignment = getRecord('franchise_tricycle', 'tricycle_id = ?', [$id]);
		updateRecord('tricycles', $payload, 'tricycle_id = ?', [$id]);
		replaceAssignment('driver_tricycle', 'driver_id', $id, $driverId);
		replaceAssignment('franchise_tricycle', 'franchise_id', $id, idValue($data['franchise_id'] ?? null));
		$adminEmail = getAdminEmailByTricycleId($id) ?: getAdminEmail();
		$assignmentChanged = (int) ($existingDriverAssignment['driver_id'] ?? 0) !== (int) ($data['driver_id'] ?? 0)
			|| (int) ($existingFranchiseAssignment['franchise_id'] ?? 0) !== (int) ($data['franchise_id'] ?? 0);
		$detailsChanged = $assignmentChanged;
		foreach ($payload as $field => $value) {
			if ((string) ($existing[$field] ?? '') !== (string) $value) {
				$detailsChanged = true;
				break;
			}
		}
		if (($existing['status'] ?? '') !== ($payload['status'] ?? '')) {
			triggerTricycleStatusNotification($id, $existing['plate_number'] ?? "Unit $id", $existing['status'] ?? 'Pending', $payload['status'] ?? 'Pending', $adminEmail);
		} elseif ($detailsChanged) {
			notifyTricycleDetailsChange($id, $existing['plate_number'] ?? "Unit $id", getRiderEmailByTricycleId($id), $adminEmail);
		}
		respond(['success' => true]);
	}

	if ($action === 'approve' && $id) {
		$existing = getRecord('tricycles', 'tricycle_id = ?', [$id]);
		if (!$existing) respond(['success' => false, 'message' => 'Tricycle not found.'], 404);
		if (!isSuperAdmin() && (int) $existing['admin_id'] !== currentAdminId()) respond(['success' => false, 'message' => 'You can only manage your own tricycle records.'], 403);
		
		$old_status = $existing['status'];
		updateRecord('tricycles', ['status' => 'Active'], 'tricycle_id = ?', [$id]);
		
		triggerTricycleStatusNotification($id, $existing['plate_number'] ?? "Unit $id", $old_status, 'Active');
		
		respond(['success' => true]);
	}

	if ($action === 'reject' && $id) {
		$existing = getRecord('tricycles', 'tricycle_id = ?', [$id]);
		if (!$existing) respond(['success' => false, 'message' => 'Tricycle not found.'], 404);
		if (!isSuperAdmin() && (int) $existing['admin_id'] !== currentAdminId()) respond(['success' => false, 'message' => 'You can only manage your own tricycle records.'], 403);
		
		$old_status = $existing['status'];
		updateRecord('tricycles', ['status' => 'Inactive'], 'tricycle_id = ?', [$id]);
		
		triggerTricycleStatusNotification($id, $existing['plate_number'] ?? "Unit $id", $old_status, 'Inactive');
		
		respond(['success' => true]);
	}

	if ($action === 'delete' && $id) {
		$existing = getRecord('tricycles', 'tricycle_id = ?', [$id]);
		if (!$existing) respond(['success' => false, 'message' => 'Tricycle not found.'], 404);
		if (!isSuperAdmin() && (int) $existing['admin_id'] !== currentAdminId()) respond(['success' => false, 'message' => 'You can only delete your own tricycle records.'], 403);
		deleteRecord('tricycles', 'tricycle_id = ?', [$id]);
		respond(['success' => true]);
	}

	respond(['success' => false, 'message' => 'Invalid request.'], 400);
} catch (Throwable $error) {
	respond(['success' => false, 'message' => 'Unable to process tricycle request.'], 500);
}
