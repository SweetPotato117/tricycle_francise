<?php
header('Content-Type: application/json; charset=utf-8');

$modelsPath = __DIR__ . '/../models';
set_include_path($modelsPath . PATH_SEPARATOR . get_include_path());
require_once $modelsPath . '/functions.php';
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
	$drivers = getAllRecords('drivers', 'ORDER BY driver_id DESC');
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
		$payload = driverPayload($data);
		$payload['driver_license'] = saveDataUrlUpload($data['driverLicenseData'] ?? '', 'driver_license') ?: 'Not provided';
		$payload['or_cr'] = saveDataUrlUpload($data['orCrData'] ?? '', 'or_cr');
		$payload['president_certificate'] = saveDataUrlUpload($data['presidentCertificateData'] ?? '', 'president_certificate');
		$newId = insertSomething('drivers', $payload);
		respond(['success' => true, 'id' => $newId], 201);
	}

	if ($action === 'update' && $id) {
		$existing = getRecord('drivers', 'driver_id = ?', [$id]);
		if (!$existing) {
			respond(['success' => false, 'message' => 'Driver not found.'], 404);
		}
		$payload = driverPayload($data, $existing);
		$payload['driver_license'] = saveDataUrlUpload($data['driverLicenseData'] ?? '', 'driver_license', $existing['driver_license']) ?: 'Not provided';
		$payload['or_cr'] = saveDataUrlUpload($data['orCrData'] ?? '', 'or_cr', $existing['or_cr']);
		$payload['president_certificate'] = saveDataUrlUpload($data['presidentCertificateData'] ?? '', 'president_certificate', $existing['president_certificate']);
		updateRecord('drivers', $payload, 'driver_id = ?', [$id]);
		respond(['success' => true]);
	}

	if ($action === 'approve' && $id) {
		if (!getRecord('drivers', 'driver_id = ?', [$id])) {
			respond(['success' => false, 'message' => 'Driver not found.'], 404);
		}
		updateRecord('drivers', ['status' => 'Approved'], 'driver_id = ?', [$id]);
		respond(['success' => true]);
	}

	respond(['success' => false, 'message' => 'Invalid request.'], 400);
} catch (Throwable $error) {
	respond(['success' => false, 'message' => 'Unable to process driver request.'], 500);
}
