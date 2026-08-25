<?php
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
	$data = json_decode($raw, true);
	return is_array($data) ? $data : $_POST;
}

function idValue($value)
{
	$id = filter_var($value, FILTER_VALIDATE_INT);
	return $id === false ? null : $id;
}

function tricyclePayload($data)
{
	$brand = trim($data['brand'] ?? '');
	$engine = trim($data['engine'] ?? $data['engine_number'] ?? '');
	$chassis = trim($data['chassis'] ?? $data['chassis_number'] ?? '');
	$plate = trim($data['plate'] ?? $data['plate_number'] ?? '');
	if ($brand === '' || $engine === '' || $chassis === '' || $plate === '') {
		respond(['success' => false, 'message' => 'Brand, plate, engine, and chassis numbers are required.'], 422);
	}

	return [
		'brand' => $brand,
		'engine_number' => $engine,
		'chassis_number' => $chassis,
		'plate_number' => $plate,
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

function listTricycles()
{
	$tricycles = getAllRecords('tricycles', 'ORDER BY tricycle_id DESC');
	$drivers = getAllRecords('drivers');
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
		$driverId = $assignedDrivers[$id] ?? null;
		$franchiseId = $assignedFranchises[$id] ?? null;
		$tricycle = [
			'id' => $id,
			'brand' => $tricycle['brand'],
			'plate' => $tricycle['plate_number'],
			'engine' => $tricycle['engine_number'],
			'chassis' => $tricycle['chassis_number'],
			'color' => $tricycle['color'] ?? '',
			'driverId' => $driverId,
			'driver' => $driverId ? ($driverNames[$driverId] ?? '') : '',
			'franchiseId' => $franchiseId,
			'franchise' => $franchiseId ? ($franchiseNames[$franchiseId] ?? '') : ''
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
		$tricycleId = insertSomething('tricycles', tricyclePayload($data));
		replaceAssignment('driver_tricycle', 'driver_id', $tricycleId, idValue($data['driver_id'] ?? null));
		replaceAssignment('franchise_tricycle', 'franchise_id', $tricycleId, idValue($data['franchise_id'] ?? null));
		respond(['success' => true, 'id' => $tricycleId], 201);
	}

	if ($action === 'update' && $id) {
		if (!getRecord('tricycles', 'tricycle_id = ?', [$id])) respond(['success' => false, 'message' => 'Tricycle not found.'], 404);
		updateRecord('tricycles', tricyclePayload($data), 'tricycle_id = ?', [$id]);
		replaceAssignment('driver_tricycle', 'driver_id', $id, idValue($data['driver_id'] ?? null));
		replaceAssignment('franchise_tricycle', 'franchise_id', $id, idValue($data['franchise_id'] ?? null));
		respond(['success' => true]);
	}

	if ($action === 'delete' && $id) {
		if (!getRecord('tricycles', 'tricycle_id = ?', [$id])) respond(['success' => false, 'message' => 'Tricycle not found.'], 404);
		deleteRecord('tricycles', 'tricycle_id = ?', [$id]);
		respond(['success' => true]);
	}

	respond(['success' => false, 'message' => 'Invalid request.'], 400);
} catch (Throwable $error) {
	respond(['success' => false, 'message' => 'Unable to process tricycle request.'], 500);
}
