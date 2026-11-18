<?php
header('Content-Type: application/json; charset=utf-8');

function respond($payload, $status = 200)
{
	http_response_code($status);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	header('Allow: GET');
	respond(['success' => false, 'message' => 'Only GET requests are supported.'], 405);
}

require_once __DIR__ . '/../models/dbconn.php';

function ensureAdminMiddleNameColumn()
{
	global $conn;
	$result = mysqli_query($conn, "SHOW COLUMNS FROM admins LIKE 'middle_name'");
	if (!$result) {
		error_log('Certification admin middle-name column check failed: ' . mysqli_error($conn));
		respond(['success' => false, 'message' => 'Unable to load franchise owner names. Please try again.'], 500);
	}
	$columnExists = mysqli_num_rows($result) > 0;
	mysqli_free_result($result);
	if (!$columnExists && !mysqli_query($conn, 'ALTER TABLE admins ADD COLUMN middle_name varchar(100) DEFAULT NULL AFTER first_name')) {
		error_log('Certification admin middle-name column creation failed: ' . mysqli_error($conn));
		respond(['success' => false, 'message' => 'Unable to load franchise owner names. Please try again.'], 500);
	}
}

ensureAdminMiddleNameColumn();

$franchiseQuery = "
	SELECT
		f.franchise_id,
		f.franchise_name,
		f.owner_name,
		f.address,
		f.issue_date,
		f.expiry_date,
		a.first_name AS owner_first_name,
		a.middle_name AS owner_middle_name,
		a.last_name AS owner_last_name
	FROM franchises f
	LEFT JOIN admins a ON a.email = f.owner_email
	ORDER BY f.franchise_id DESC
";
$franchiseResult = mysqli_query($conn, $franchiseQuery);
if (!$franchiseResult) {
	error_log('Certification franchise lookup failed: ' . mysqli_error($conn));
	respond(['success' => false, 'message' => 'Unable to load franchises for certificate generation. Please try again.'], 500);
}

$records = [];
while ($row = mysqli_fetch_assoc($franchiseResult)) {
	$franchiseId = (int) $row['franchise_id'];
	$records[$franchiseId] = [
		'id' => 'franchise-' . $franchiseId,
		'franchiseId' => $franchiseId,
		'franchiseName' => $row['franchise_name'],
		'ownerName' => $row['owner_name'],
		'ownerFirstName' => $row['owner_first_name'] ?? '',
		'ownerMiddleName' => $row['owner_middle_name'] ?? '',
		'ownerLastName' => $row['owner_last_name'] ?? '',
		'address' => $row['address'] ?? '',
		'issueDate' => $row['issue_date'] ?? '',
		'expiryDate' => $row['expiry_date'] ?? '',
		'units' => []
	];
}
mysqli_free_result($franchiseResult);

$unitQuery = "
	SELECT franchise_id, brand, sticker_number, or_number,
	       engine_number, chassis_number, color, plate_number
	FROM franchise_owner_tricycle
	WHERE status = 'Active'
	ORDER BY franchise_id, owner_tricycle_id
";
$unitResult = mysqli_query($conn, $unitQuery);
if (!$unitResult) {
	error_log('Certification franchise-owner unit lookup failed: ' . mysqli_error($conn));
	respond(['success' => false, 'message' => 'Unable to load franchise units for certificate generation. Please try again.'], 500);
}
while ($row = mysqli_fetch_assoc($unitResult)) {
	$franchiseId = (int) $row['franchise_id'];
	if (!isset($records[$franchiseId])) continue;
	$records[$franchiseId]['units'][] = [
		'make' => $row['brand'] ?? '',
		'bodyNumber' => $row['sticker_number'] ?? '',
		'orNumber' => $row['or_number'] ?? '',
		'engine' => $row['engine_number'] ?? '',
		'chassis' => $row['chassis_number'] ?? '',
		'color' => $row['color'] ?? '',
		'plate' => $row['plate_number'] ?? ''
	];
}
mysqli_free_result($unitResult);

respond(['success' => true, 'records' => array_values($records)]);