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

function ensureTricycleRiderColumn()
{
	global $conn;
	$check = mysqli_query($conn, "SHOW COLUMNS FROM tricycles LIKE 'rider_id'");
	if (!$check) throw new RuntimeException('Unable to inspect tricycle rider assignment storage: ' . mysqli_error($conn));
	$columnExists = mysqli_num_rows($check) > 0;
	mysqli_free_result($check);
	if (!$columnExists && !mysqli_query($conn, "ALTER TABLE tricycles ADD COLUMN rider_id INT NULL DEFAULT NULL AFTER status")) {
		throw new RuntimeException('Unable to prepare tricycle rider assignment storage: ' . mysqli_error($conn));
	}

	$indexCheck = mysqli_query($conn, "SHOW INDEX FROM tricycles WHERE Column_name = 'rider_id'");
	if (!$indexCheck) throw new RuntimeException('Unable to inspect tricycle rider assignment index: ' . mysqli_error($conn));
	$indexExists = mysqli_num_rows($indexCheck) > 0;
	mysqli_free_result($indexCheck);
	if (!$indexExists && !mysqli_query($conn, "ALTER TABLE tricycles ADD INDEX idx_tricycles_rider_id (rider_id)")) {
		throw new RuntimeException('Unable to prepare tricycle rider assignment index: ' . mysqli_error($conn));
	}
}

function ensureTricycleDocumentColumns()
{
	global $conn;
	foreach (['or_document', 'cr_document', 'or_number'] as $column) {
		$check = mysqli_query($conn, "SHOW COLUMNS FROM tricycles LIKE '$column'");
		if (!$check) throw new Exception('Unable to inspect tricycle document fields: ' . mysqli_error($conn));
		$columnExists = mysqli_num_rows($check) > 0;
		mysqli_free_result($check);
		if (!$columnExists && !mysqli_query($conn, "ALTER TABLE tricycles ADD COLUMN $column VARCHAR(255) NULL")) {
			throw new Exception('Unable to prepare tricycle document storage: ' . mysqli_error($conn));
		}
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
		'or_number' => trim($data['orNumber'] ?? $data['or_number'] ?? ''),
		'color' => trim($data['color'] ?? ''),
		'status' => $status
	];
}

function ownerTricyclePayload($data)
{
	$brand = trim($data['brand'] ?? '');
	$sticker = trim($data['sticker'] ?? $data['sticker_number'] ?? $data['body_number'] ?? '');
	$engine = trim($data['engine'] ?? $data['engine_number'] ?? '');
	$chassis = trim($data['chassis'] ?? $data['chassis_number'] ?? '');
	$plate = trim($data['plate'] ?? $data['plate_number'] ?? '');
	if ($brand === '' || $sticker === '' || $engine === '' || $chassis === '' || $plate === '') {
		respond(['success' => false, 'message' => 'Brand, body number/sticker, plate, engine, and chassis numbers are required.'], 422);
	}

	return [
		'brand' => $brand,
		'sticker_number' => $sticker,
		'engine_number' => $engine,
		'chassis_number' => $chassis,
		'plate_number' => $plate,
		'or_number' => trim($data['orNumber'] ?? $data['or_number'] ?? ''),
		'color' => trim($data['color'] ?? '')
	];
}

function replaceAssignment($table, $column, $tricycleId, $assignedId)
{
	deleteRecord($table, 'tricycle_id = ?', [$tricycleId]);
	if ($assignedId) {
		insertSomething($table, [$column => $assignedId, 'tricycle_id' => $tricycleId]);
	}
}

function ensureOwnerTricycleApprovalStatus()
{
	global $conn;
	$check = mysqli_query($conn, "SHOW COLUMNS FROM franchise_owner_tricycle LIKE 'status'");
	if (!$check) throw new RuntimeException('Unable to inspect owner tricycle status: ' . mysqli_error($conn));
	$column = mysqli_fetch_assoc($check);
	mysqli_free_result($check);
	if (!$column) throw new RuntimeException('Owner tricycle status column is missing.');
	$needsApprovalStatuses = stripos($column['Type'], "'Pending'") === false
		|| stripos($column['Type'], "'Active'") === false
		|| stripos($column['Type'], "'Inactive'") === false
		|| stripos($column['Type'], "'Archived'") === false;
	if ($needsApprovalStatuses
		&& !mysqli_query($conn, "ALTER TABLE franchise_owner_tricycle MODIFY status ENUM('Pending','Active','Inactive','Archived') NOT NULL DEFAULT 'Pending'")) {
		throw new RuntimeException('Unable to enable owner tricycle approval status: ' . mysqli_error($conn));
	}
	$documentCheck = mysqli_query($conn, "SHOW COLUMNS FROM franchise_owner_tricycle LIKE 'or_cr_document'");
	if (!$documentCheck) throw new RuntimeException('Unable to inspect owner tricycle OR/CR proof storage: ' . mysqli_error($conn));
	$hasDocumentColumn = mysqli_num_rows($documentCheck) > 0;
	mysqli_free_result($documentCheck);
	if (!$hasDocumentColumn
		&& !mysqli_query($conn, "ALTER TABLE franchise_owner_tricycle ADD COLUMN or_cr_document VARCHAR(255) DEFAULT NULL AFTER or_number")) {
		throw new RuntimeException('Unable to prepare owner tricycle OR/CR proof storage: ' . mysqli_error($conn));
	}
}

function validateActiveDriver($driverId)
{
	if (!$driverId) return null;
	$driver = getRecord('drivers', 'driver_id = ? AND status = ?', [(int) $driverId, 'Approved']);
	if (!$driver) respond(['success' => false, 'message' => 'Only active drivers can be assigned to a tricycle.'], 422);
	return (int) $driverId;
}

function validateRider($riderId)
{
	if (!$riderId) return null;
	if (!getRecord('riders', 'rider_id = ?', [(int) $riderId])) {
		respond(['success' => false, 'message' => 'The selected rider was not found.'], 422);
	}
	return (int) $riderId;
}

function duplicateTricycleMessage($duplicate, $payload)
{
	if (($duplicate['plate_number'] ?? null) === $payload['plate_number']) return 'This plate number is already registered to another tricycle.';
	if (($duplicate['engine_number'] ?? null) === $payload['engine_number']) return 'This engine number is already registered to another tricycle.';
	if (($duplicate['chassis_number'] ?? null) === $payload['chassis_number']) return 'This chassis number is already registered to another tricycle.';
	if (($duplicate['sticker_number'] ?? null) === $payload['sticker_number']) return 'This body number/sticker is already registered to another tricycle.';
	return 'This tricycle already exists in the system.';
}

function listTricycles()
{
	ensureTricycleStatusColumn();
	ensureTricycleOwnershipColumn();
	ensureTricycleRiderColumn();
	ensureTricycleDocumentColumns();
	$ownerTricycles = [];
	if (isSuperAdmin()) {
		ensureOwnerTricycleApprovalStatus();
		$ownerQuery = "
			SELECT ot.owner_tricycle_id, ot.brand, ot.sticker_number, ot.plate_number,
			       ot.engine_number, ot.chassis_number, ot.color, ot.or_number, ot.or_cr_document, ot.status,
			       f.franchise_id, f.franchise_name, f.owner_name, f.owner_email
			FROM franchise_owner_tricycle ot
			INNER JOIN franchises f ON f.franchise_id = ot.franchise_id
			ORDER BY ot.owner_tricycle_id DESC
		";
		$ownerResult = mysqli_query($GLOBALS['conn'], $ownerQuery);
		if (!$ownerResult) throw new RuntimeException('Unable to load franchise-owner tricycle submissions: ' . mysqli_error($GLOBALS['conn']));
		while ($row = mysqli_fetch_assoc($ownerResult)) {
			$ownerTricycles[] = [
				'id' => (int) $row['owner_tricycle_id'],
				'brand' => $row['brand'],
				'sticker' => $row['sticker_number'],
				'plate' => $row['plate_number'],
				'engine' => $row['engine_number'],
				'chassis' => $row['chassis_number'],
				'color' => $row['color'],
				'orNumber' => $row['or_number'] ?? '',
				'orCrDocument' => !empty($row['or_cr_document']) ? uploadUrl($row['or_cr_document']) : '',
				'status' => $row['status'],
				'franchiseId' => (int) $row['franchise_id'],
				'franchise' => $row['franchise_name'],
				'owner' => $row['owner_name'],
				'ownerEmail' => $row['owner_email']
			];
		}
		mysqli_free_result($ownerResult);
	}
	$adminId = currentAdminId();
	$tricycleQuery = isSuperAdmin() ? 'ORDER BY tricycle_id DESC' : 'WHERE admin_id = ? ORDER BY tricycle_id DESC';
	$tricycleParams = isSuperAdmin() ? [] : [$adminId];
	$tricycles = getAllRecords('tricycles', $tricycleQuery, $tricycleParams);
	$drivers = isSuperAdmin() ? getAllRecords('drivers', 'ORDER BY driver_id DESC') : getAllRecords('drivers', 'WHERE admin_id = ? ORDER BY driver_id DESC', [$adminId]);
	$franchises = getAllRecords('franchises');
	$riders = getAllRecords('riders', 'ORDER BY full_name');
	$driverNames = [];
	$franchiseNames = [];
	$riderNames = [];
	foreach ($drivers as $driver) $driverNames[(int) $driver['driver_id']] = $driver['full_name'];
	foreach ($franchises as $franchise) $franchiseNames[(int) $franchise['franchise_id']] = $franchise['franchise_name'];
	foreach ($riders as $rider) $riderNames[(int) $rider['rider_id']] = $rider['full_name'];

	$driverAssignments = getAllRecords('driver_tricycle');
	$franchiseAssignments = getAllRecords('franchise_tricycle');
	$assignedDrivers = [];
	$assignedFranchises = [];
	foreach ($driverAssignments as $assignment) $assignedDrivers[(int) $assignment['tricycle_id']] = (int) $assignment['driver_id'];
	foreach ($franchiseAssignments as $assignment) $assignedFranchises[(int) $assignment['tricycle_id']] = (int) $assignment['franchise_id'];

	foreach ($tricycles as &$tricycle) {
		$id = (int) $tricycle['tricycle_id'];
		$orDocument = !empty($tricycle['or_document']) ? uploadUrl($tricycle['or_document']) : '';
		$riderId = (int) ($tricycle['rider_id'] ?? 0);
		$driverId = $assignedDrivers[$id] ?? null;
		$franchiseId = $assignedFranchises[$id] ?? null;
		$tricycle = [
			'id' => $id,
			'brand' => $tricycle['brand'],
			'sticker' => $tricycle['sticker_number'] ?? '',
			'plate' => $tricycle['plate_number'],
			'orNumber' => $tricycle['or_number'] ?? '',
			'engine' => $tricycle['engine_number'],
			'chassis' => $tricycle['chassis_number'],
			'color' => $tricycle['color'] ?? '',
			'status' => $tricycle['status'] ?? 'Pending',
			'riderId' => $riderId ?: null,
			'rider' => $riderNames[$riderId] ?? '',
			'driverId' => $driverId,
			'driver' => $driverId ? ($driverNames[$driverId] ?? '') : '',
			'franchiseId' => $franchiseId,
			'franchise' => $franchiseId ? ($franchiseNames[$franchiseId] ?? '') : '',
			'orDocument' => $orDocument
		];
	}
	unset($tricycle);

	respond([
		'success' => true,
		'isSuperAdmin' => isSuperAdmin(),
		'tricycles' => $tricycles,
		'ownerTricycles' => $ownerTricycles,
		'drivers' => $drivers,
		'franchises' => $franchises,
		'riders' => $riders
	]);
}

try {
	if ($_SERVER['REQUEST_METHOD'] === 'GET') listTricycles();
	$data = requestData();
	$action = $data['action'] ?? '';
	$id = idValue($data['id'] ?? null);

	if (in_array($action, ['approve-owner-tricycle', 'reject-owner-tricycle'], true) && $id) {
		if (!isSuperAdmin()) respond(['success' => false, 'message' => 'Only Super Admins can review franchise-owner tricycles.'], 403);
		ensureOwnerTricycleApprovalStatus();
		$existing = getRecord('franchise_owner_tricycle', 'owner_tricycle_id = ?', [$id]);
		if (!$existing) respond(['success' => false, 'message' => 'Franchise-owner tricycle submission not found.'], 404);
		$franchise = getRecord('franchises', 'franchise_id = ?', [(int) $existing['franchise_id']]);
		if (!$franchise) respond(['success' => false, 'message' => 'The franchise owner for this submission could not be found.'], 404);

		$newStatus = $action === 'approve-owner-tricycle' ? 'Active' : 'Inactive';
		$oldStatus = $existing['status'] ?? 'Pending';
		if ($oldStatus === $newStatus) respond(['success' => true, 'status' => $newStatus]);
		$statement = mysqli_prepare($GLOBALS['conn'], 'UPDATE franchise_owner_tricycle SET status = ? WHERE owner_tricycle_id = ? AND status = ?');
		if (!$statement) throw new RuntimeException('Unable to prepare owner tricycle decision: ' . mysqli_error($GLOBALS['conn']));
		mysqli_stmt_bind_param($statement, 'sis', $newStatus, $id, $oldStatus);
		if (!mysqli_stmt_execute($statement)) {
			$error = mysqli_stmt_error($statement);
			mysqli_stmt_close($statement);
			throw new RuntimeException('Unable to save owner tricycle decision: ' . $error);
		}
		$changed = mysqli_stmt_affected_rows($statement);
		mysqli_stmt_close($statement);
		if ($changed !== 1) respond(['success' => false, 'message' => 'This owner tricycle changed while you were reviewing it. Refresh and try again.'], 409);

		$ownerEmail = trim((string) ($franchise['owner_email'] ?? ''));
		if (filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
			$plate = $existing['plate_number'] ?? 'your tricycle';
			$approved = $newStatus === 'Active';
			$message = $approved
				? "Your owner tricycle ($plate) has been approved and is now available for certification."
				: "Your owner tricycle ($plate) has been marked inactive. Please contact the Super Admin for assistance.";
			createNotification(
				'Owner Tricycle ' . ($approved ? 'Approved' : 'Not Approved'),
				$message,
				'Tricycle',
				$approved ? 'info' : 'warning',
				$ownerEmail,
				$id,
				'owner_tricycle_status'
			);
		} else {
			error_log('Owner tricycle decision saved without an owner email notification: invalid franchise owner email.');
		}
		respond(['success' => true, 'status' => $newStatus]);
	}

	if ($action === 'update-owner-tricycle' && $id) {
		if (!isSuperAdmin()) respond(['success' => false, 'message' => 'Only Super Admins can edit franchise-owner tricycles.'], 403);
		ensureOwnerTricycleApprovalStatus();
		$existing = getRecord('franchise_owner_tricycle', 'owner_tricycle_id = ?', [$id]);
		if (!$existing) respond(['success' => false, 'message' => 'Franchise-owner tricycle not found.'], 404);
		$payload = ownerTricyclePayload($data);
		$duplicateConditions = '(plate_number = ? OR engine_number = ? OR chassis_number = ? OR sticker_number = ?)';
		$duplicateParams = [$payload['plate_number'], $payload['engine_number'], $payload['chassis_number'], $payload['sticker_number']];
		$duplicate = getRecord('franchise_owner_tricycle', 'owner_tricycle_id != ? AND ' . $duplicateConditions, array_merge([$id], $duplicateParams));
		if (!$duplicate) {
			$duplicate = getRecord('tricycles', $duplicateConditions, $duplicateParams);
		}
		if ($duplicate) respond(['success' => false, 'message' => duplicateTricycleMessage($duplicate, $payload)], 409);
		if (!updateRecord('franchise_owner_tricycle', $payload, 'owner_tricycle_id = ?', [$id])) {
			throw new RuntimeException('Unable to update franchise-owner tricycle.');
		}
		respond(['success' => true]);
	}

	if ($action === 'delete-owner-tricycle' && $id) {
		if (!isSuperAdmin()) respond(['success' => false, 'message' => 'Only Super Admins can remove franchise-owner tricycles.'], 403);
		$existing = getRecord('franchise_owner_tricycle', 'owner_tricycle_id = ?', [$id]);
		if (!$existing) respond(['success' => false, 'message' => 'Franchise-owner tricycle not found.'], 404);
		$franchise = getRecord('franchises', 'franchise_id = ?', [(int) $existing['franchise_id']]);
		if (!$franchise) respond(['success' => false, 'message' => 'The franchise owner for this tricycle could not be found.'], 404);
		if (!deleteRecord('franchise_owner_tricycle', 'owner_tricycle_id = ?', [$id])) {
			throw new RuntimeException('Unable to remove franchise-owner tricycle.');
		}
		$ownerEmail = trim((string) ($franchise['owner_email'] ?? ''));
		if (filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
			createNotification(
				'Owner Tricycle Removed',
				'Your owner tricycle (' . ($existing['plate_number'] ?? 'tricycle') . ') has been removed by the Super Admin.',
				'Tricycle',
				'warning',
				$ownerEmail,
				$id,
				'owner_tricycle_removed'
			);
		} else {
			error_log('Owner tricycle removed without an owner notification: invalid franchise owner email.');
		}
		respond(['success' => true]);
	}

	if ($action === 'create') {
		ensureTricycleStatusColumn();
		ensureTricycleOwnershipColumn();
		ensureTricycleRiderColumn();
		ensureTricycleDocumentColumns();
		$adminId = currentAdminId();
		if (!$adminId) respond(['success' => false, 'message' => 'Admin session required.'], 401);
		$payload = tricyclePayload($data);
		$driverId = validateActiveDriver(idValue($data['driver_id'] ?? null));
		$payload['rider_id'] = validateRider(idValue($data['rider_id'] ?? null));
		$duplicate = getRecord('tricycles', 'plate_number = ? OR engine_number = ? OR chassis_number = ? OR sticker_number = ?', [$payload['plate_number'], $payload['engine_number'], $payload['chassis_number'], $payload['sticker_number']]);
		if ($duplicate) respond(['success' => false, 'message' => duplicateTricycleMessage($duplicate, $payload)], 409);
		$payload['admin_id'] = $adminId;
		$tricycleId = insertSomething('tricycles', $payload);
		replaceAssignment('driver_tricycle', 'driver_id', $tricycleId, $driverId);
		replaceAssignment('franchise_tricycle', 'franchise_id', $tricycleId, idValue($data['franchise_id'] ?? null));
		respond(['success' => true, 'id' => $tricycleId], 201);
	}

	if ($action === 'update' && $id) {
		ensureTricycleStatusColumn();
		ensureTricycleOwnershipColumn();
		ensureTricycleRiderColumn();
		ensureTricycleDocumentColumns();
		$existing = getRecord('tricycles', 'tricycle_id = ?', [$id]);
		if (!$existing) respond(['success' => false, 'message' => 'Tricycle not found.'], 404);
		if (!isSuperAdmin() && (int) $existing['admin_id'] !== currentAdminId()) respond(['success' => false, 'message' => 'You can only edit your own tricycle records.'], 403);
		$payload = tricyclePayload($data);
		$driverId = validateActiveDriver(idValue($data['driver_id'] ?? null));
		$payload['rider_id'] = validateRider(idValue($data['rider_id'] ?? null));
		$duplicate = getRecord('tricycles', 'tricycle_id != ? AND (plate_number = ? OR engine_number = ? OR chassis_number = ? OR sticker_number = ?)', [$id, $payload['plate_number'], $payload['engine_number'], $payload['chassis_number'], $payload['sticker_number']]);
		if ($duplicate) respond(['success' => false, 'message' => duplicateTricycleMessage($duplicate, $payload)], 409);
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
		$riderEmail = getRiderEmailByTricycleId($id);
		$adminEmail = getAdminEmailByTricycleId($id) ?: getAdminEmail();
		deleteRecord('tricycles', 'tricycle_id = ?', [$id]);
		triggerTricycleRemovalNotification($id, $existing['plate_number'] ?? "Unit $id", $riderEmail, $adminEmail);
		respond(['success' => true]);
	}

	respond(['success' => false, 'message' => 'Invalid request.'], 400);
} catch (Throwable $error) {
	if (stripos($error->getMessage(), 'Duplicate entry') !== false) {
		respond(['success' => false, 'message' => 'The plate, engine, chassis, or body number you entered is already in use by another tricycle.'], 409);
	}
	respond(['success' => false, 'message' => 'Unable to process tricycle request.'], 500);
}
