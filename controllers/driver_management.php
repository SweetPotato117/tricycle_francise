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
	if ($raw !== '') {
		$data = json_decode($raw, true);
		if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
			return $data;
		}
	}

	return $_POST;
}

function currentAdminId()
{
	return (int) ($_SESSION['admin_id'] ?? 0);
}

function isSuperAdmin()
{
	return ($_SESSION['admin_role'] ?? '') === 'Super Admin';
}

function ensureDriverOwnershipColumn()
{
	global $conn;
	$check = mysqli_query($conn, "SHOW COLUMNS FROM drivers LIKE 'admin_id'");
	if ($check && mysqli_num_rows($check) === 0) {
		mysqli_query($conn, "ALTER TABLE drivers ADD COLUMN admin_id INT NULL DEFAULT NULL AFTER status");
		mysqli_query($conn, "ALTER TABLE drivers ADD INDEX idx_drivers_admin_id (admin_id)");
	}
}

function driverPayload($data, $existing = [])
{
	$name = trim($data['name'] ?? $data['full_name'] ?? '');
	$contact = trim($data['contact'] ?? $data['contact_number'] ?? '');
	$age = filter_var($data['age'] ?? null, FILTER_VALIDATE_INT);
	$gender = $data['gender'] ?? '';
	$status = $data['status'] ?? 'Pending';

	if ($name === '' || $age === false || $age < 18 || $age > 80) {
		respond(['success' => false, 'message' => 'A valid name and age between 18 and 80 are required.'], 422);
	}
	if (!in_array($gender, ['Male', 'Female'], true)) {
		respond(['success' => false, 'message' => 'Please select a valid gender.'], 422);
	}
	if (!in_array($status, ['Pending', 'For Review', 'Approved'], true)) {
		respond(['success' => false, 'message' => 'Please select a valid status.'], 422);
	}

	return [
		'full_name' => $name,
		'contact_number' => $contact,
		'age' => $age,
		'gender' => $gender,
		'address' => trim($data['address'] ?? ''),
		'driver_license' => $existing['driver_license'] ?? null,
		'or_cr' => $existing['or_cr'] ?? null,
		'president_certificate' => $existing['president_certificate'] ?? null,
		'status' => $status
	];
}

function listDrivers()
{
	ensureDriverOwnershipColumn();
	$adminId = currentAdminId();
	$driverQuery = isSuperAdmin() ? 'ORDER BY driver_id DESC' : 'WHERE admin_id = ? ORDER BY driver_id DESC';
	$driverParams = isSuperAdmin() ? [] : [$adminId];
	$drivers = getAllRecords('drivers', $driverQuery, $driverParams);
	$assignments = getAllRecords(
		'driver_tricycle',
		'WHERE driver_id IN (SELECT driver_id FROM drivers)',
		[]
	);
	$tricycles = getAllRecords('tricycles');
	$plates = [];
	foreach ($tricycles as $tricycle) {
		$plates[(int) $tricycle['tricycle_id']] = $tricycle['plate_number'];
	}

	$assigned = [];
	foreach ($assignments as $assignment) {
		$assigned[(int) $assignment['driver_id']] = $plates[(int) $assignment['tricycle_id']] ?? 'Unassigned';
	}

	foreach ($drivers as &$driver) {
		$driver = [
			'id' => (int) $driver['driver_id'],
			'name' => $driver['full_name'],
			'contact' => $driver['contact_number'] ?? '',
			'age' => (int) $driver['age'],
			'gender' => $driver['gender'],
			'address' => $driver['address'] ?? '',
			'tricycle' => $assigned[(int) $driver['driver_id']] ?? 'Unassigned',
			'status' => $driver['status'],
			'driverLicense' => uploadUrl($driver['driver_license']),
			'orCr' => uploadUrl($driver['or_cr']),
			'presidentCertificate' => uploadUrl($driver['president_certificate'])
		];
	}
	unset($driver);

	respond(['success' => true, 'drivers' => $drivers]);
}

try {
	if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		listDrivers();
	}

	$data = requestData();
	$action = $data['action'] ?? '';
	$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

	if ($action === 'create') {
		ensureDriverOwnershipColumn();
		$adminId = currentAdminId();
		if (!$adminId) respond(['success' => false, 'message' => 'Admin session required.'], 401);
		$payload = driverPayload($data);
		if (!isSuperAdmin()) {
			$duplicate = getRecord('drivers', 'admin_id = ? AND (LOWER(full_name) = LOWER(?) OR contact_number = ?)', [$adminId, $payload['full_name'], $payload['contact_number']]);
			if ($duplicate) respond(['success' => false, 'message' => 'This driver is already registered to this admin account.'], 409);
		}
		$payload['admin_id'] = $adminId;
		$payload['driver_license'] = saveDataUrlUpload($data['driverLicenseData'] ?? '', 'driver_license') ?: 'Not provided';
		$payload['or_cr'] = saveDataUrlUpload($data['orCrData'] ?? '', 'or_cr');
		$payload['president_certificate'] = saveDataUrlUpload($data['presidentCertificateData'] ?? '', 'president_certificate');
		$newId = insertSomething('drivers', $payload);
		respond(['success' => true, 'id' => $newId], 201);
	}

	if ($action === 'update' && $id) {
		ensureDriverOwnershipColumn();
		$existing = getRecord('drivers', 'driver_id = ?', [$id]);
		if (!$existing) {
			respond(['success' => false, 'message' => 'Driver not found.'], 404);
		}
		if (!isSuperAdmin() && (int) $existing['admin_id'] !== currentAdminId()) respond(['success' => false, 'message' => 'You can only edit your own driver records.'], 403);
		$payload = driverPayload($data, $existing);
		$payload['driver_license'] = saveDataUrlUpload($data['driverLicenseData'] ?? '', 'driver_license', $existing['driver_license']) ?: 'Not provided';
		$payload['or_cr'] = saveDataUrlUpload($data['orCrData'] ?? '', 'or_cr', $existing['or_cr']);
		$payload['president_certificate'] = saveDataUrlUpload($data['presidentCertificateData'] ?? '', 'president_certificate', $existing['president_certificate']);
		updateRecord('drivers', $payload, 'driver_id = ?', [$id]);
		$adminEmail = getAdminEmailByDriverId($id) ?: getAdminEmail();
		if (($existing['status'] ?? '') !== ($payload['status'] ?? '')) {
			triggerDriverStatusNotification($id, $existing['full_name'], $existing['email'] ?? null, $existing['status'] ?? 'Pending', $payload['status'] ?? 'Pending', $adminEmail);
		} else {
			notifyDriverDetailsChange($id, $existing['full_name'], getRiderEmailByDriverId($id), $adminEmail);
		}
		respond(['success' => true]);
	}

	if ($action === 'approve' && $id) {
		$existing = getRecord('drivers', 'driver_id = ?', [$id]);
		if (!$existing) {
			respond(['success' => false, 'message' => 'Driver not found.'], 404);
		}
		if (!isSuperAdmin() && (int) $existing['admin_id'] !== currentAdminId()) respond(['success' => false, 'message' => 'You can only manage your own driver records.'], 403);
		
		$old_status = $existing['status'];
		updateRecord('drivers', ['status' => 'Approved'], 'driver_id = ?', [$id]);
		
		// Trigger notification for status change (admin email will be looked up from driver's admin_id)
		triggerDriverStatusNotification(
			$id,
			$existing['full_name'],
			$existing['email'] ?? null,
			$old_status,
			'Approved'
		);
		
		respond(['success' => true]);
	}

	respond(['success' => false, 'message' => 'Invalid request.'], 400);
} catch (Throwable $error) {
	respond(['success' => false, 'message' => 'Unable to process driver request.'], 500);
}
