<?php
header('Content-Type: application/json; charset=utf-8');

$modelsPath = __DIR__ . '/../models';
set_include_path($modelsPath . PATH_SEPARATOR . get_include_path());
require_once $modelsPath . '/functions.php';
require_once $modelsPath . '/upload.php';

function ensureFranchiseDocumentsTable()
{
	global $conn;
	$query = 'CREATE TABLE IF NOT EXISTS franchise_documents (document_id INT NOT NULL AUTO_INCREMENT, franchise_id INT NOT NULL, receipt_photo VARCHAR(255) DEFAULT NULL, PRIMARY KEY (document_id), UNIQUE KEY franchise_id (franchise_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
	if (!mysqli_query($conn, $query)) throw new Exception('Unable to prepare franchise document storage.');
}

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

function franchisePayload($data)
{
	$name = trim($data['name'] ?? $data['franchise_name'] ?? '');
	$owner = trim($data['owner'] ?? $data['owner_name'] ?? '');
	$issue = $data['issue'] ?? $data['issue_date'] ?? '';
	$expiry = $data['expiry'] ?? $data['expiry_date'] ?? '';
	$status = $data['status'] ?? $data['renewal_status'] ?? 'Active';
	if ($name === '' || $owner === '' || $issue === '' || $expiry === '') {
		respond(['success' => false, 'message' => 'Franchise name, owner, issue date, and expiry date are required.'], 422);
	}
	if (!in_array($status, ['Active', 'Expired', 'Pending Renewal'], true)) {
		respond(['success' => false, 'message' => 'Please select a valid renewal status.'], 422);
	}

	return [
		'franchise_name' => $name,
		'owner_name' => $owner,
		'address' => trim($data['address'] ?? ''),
		'issue_date' => $issue,
		'expiry_date' => $expiry,
		'renewal_status' => $status
	];
}

function listFranchises()
{
	ensureFranchiseDocumentsTable();
	$franchises = getAllRecords('franchises', 'ORDER BY franchise_id DESC');
	$documents = getAllRecords('franchise_documents');
	$receipts = [];
	foreach ($documents as $document) $receipts[(int) $document['franchise_id']] = $document['receipt_photo'];
	$assignments = getAllRecords('franchise_tricycle');
	$tricycles = getAllRecords('tricycles');
	$tricycleNames = [];
	foreach ($tricycles as $tricycle) $tricycleNames[(int) $tricycle['tricycle_id']] = $tricycle['plate_number'];
	$assigned = [];
	foreach ($assignments as $assignment) {
		$assigned[(int) $assignment['franchise_id']][] = $tricycleNames[(int) $assignment['tricycle_id']] ?? '';
	}

	foreach ($franchises as &$franchise) {
		$id = (int) $franchise['franchise_id'];
		$franchise = [
			'id' => $id,
			'name' => $franchise['franchise_name'],
			'owner' => $franchise['owner_name'],
			'address' => $franchise['address'] ?? '',
			'issue' => $franchise['issue_date'],
			'expiry' => $franchise['expiry_date'],
			'status' => $franchise['renewal_status'],
			'tricycles' => array_values(array_filter($assigned[$id] ?? [])),
			'receipt' => $receipts[$id] ? basename($receipts[$id]) : '',
			'receiptDataUrl' => uploadUrl($receipts[$id] ?? '')
		];
	}
	unset($franchise);
	respond(['success' => true, 'franchises' => $franchises]);
}

try {
	if ($_SERVER['REQUEST_METHOD'] === 'GET') listFranchises();
	$data = requestData();
	$action = $data['action'] ?? '';
	$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

	if ($action === 'create') {
		ensureFranchiseDocumentsTable();
		$id = insertSomething('franchises', franchisePayload($data));
		$receipt = saveDataUrlUpload($data['receiptDataUrl'] ?? '', 'franchise_receipt');
		if ($receipt) insertSomething('franchise_documents', ['franchise_id' => $id, 'receipt_photo' => $receipt]);
		respond(['success' => true, 'id' => $id], 201);
	}
	if ($action === 'update' && $id) {
		ensureFranchiseDocumentsTable();
		if (!getRecord('franchises', 'franchise_id = ?', [$id])) respond(['success' => false, 'message' => 'Franchise not found.'], 404);
		updateRecord('franchises', franchisePayload($data), 'franchise_id = ?', [$id]);
		$receipt = saveDataUrlUpload($data['receiptDataUrl'] ?? '', 'franchise_receipt');
		if ($receipt) {
			$document = getRecord('franchise_documents', 'franchise_id = ?', [$id]);
			if ($document) updateRecord('franchise_documents', ['receipt_photo' => $receipt], 'franchise_id = ?', [$id]);
			else insertSomething('franchise_documents', ['franchise_id' => $id, 'receipt_photo' => $receipt]);
		}
		respond(['success' => true]);
	}
	if ($action === 'delete' && $id) {
		ensureFranchiseDocumentsTable();
		if (!getRecord('franchises', 'franchise_id = ?', [$id])) respond(['success' => false, 'message' => 'Franchise not found.'], 404);
		deleteRecord('franchises', 'franchise_id = ?', [$id]);
		respond(['success' => true]);
	}
	respond(['success' => false, 'message' => 'Invalid request.'], 400);
} catch (Throwable $error) {
	respond(['success' => false, 'message' => 'Unable to process franchise request.'], 500);
}
