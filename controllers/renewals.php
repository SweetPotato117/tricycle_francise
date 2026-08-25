<?php
session_start();
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
	$data = json_decode(file_get_contents('php://input'), true);
	return is_array($data) ? $data : $_POST;
}

function renewalPayload($data)
{
	$franchiseId = filter_var($data['franchise_id'] ?? null, FILTER_VALIDATE_INT);
	$year = filter_var($data['year'] ?? $data['renewal_year'] ?? null, FILTER_VALIDATE_INT);
	$status = $data['status'] ?? $data['receipt_status'] ?? 'Not Submitted';
	$renewalDate = $data['renewalDate'] ?? $data['renewal_date'] ?? '';
	$dueDate = $data['dueDate'] ?? $data['due_date'] ?? '';
	if (!$franchiseId || !$year || $year < 2000 || !$renewalDate || !$dueDate) {
		respond(['success' => false, 'message' => 'Franchise, year, renewal date, and due date are required.'], 422);
	}
	if (!in_array($status, ['Not Submitted', 'Submitted', 'Confirmed'], true)) {
		respond(['success' => false, 'message' => 'Please select a valid receipt status.'], 422);
	}
	if (!getRecord('franchises', 'franchise_id = ?', [$franchiseId])) {
		respond(['success' => false, 'message' => 'Selected franchise not found.'], 422);
	}

	return [
		'franchise_id' => $franchiseId,
		'renewal_year' => $year,
		'renewal_date' => $renewalDate,
		'due_date' => $dueDate,
		'penalty' => max(0, (float) ($data['penalty'] ?? 0)),
		'remarks' => trim($data['remarks'] ?? ''),
		'receipt_status' => $status
	];
}

function listRenewals()
{
	$renewals = getAllRecords('renewals', 'ORDER BY renewal_id DESC');
	$franchises = getAllRecords('franchises', 'ORDER BY franchise_name');
	$admins = getAllRecords('admins');
	$franchiseNames = [];
	$adminNames = [];
	foreach ($franchises as $franchise) $franchiseNames[(int) $franchise['franchise_id']] = $franchise['franchise_name'];
	foreach ($admins as $admin) $adminNames[(int) $admin['admin_id']] = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')) ?: $admin['username'];

	foreach ($renewals as &$renewal) {
		$renewal = [
			'id' => (int) $renewal['renewal_id'],
			'franchiseId' => (int) $renewal['franchise_id'],
			'franchise' => $franchiseNames[(int) $renewal['franchise_id']] ?? 'Unknown franchise',
			'year' => (int) $renewal['renewal_year'],
			'renewalDate' => $renewal['renewal_date'],
			'dueDate' => $renewal['due_date'],
			'penalty' => (float) $renewal['penalty'],
			'remarks' => $renewal['remarks'] ?? '',
			'status' => $renewal['receipt_status'],
			'receipt' => $renewal['receipt_photo'] ? basename($renewal['receipt_photo']) : '',
			'receiptDataUrl' => uploadUrl($renewal['receipt_photo']),
			'receiptSubmittedAt' => $renewal['receipt_submitted_at'] ?? '',
			'uploadedBy' => $renewal['receipt_photo'] ? 'Admin' : '',
			'confirmedBy' => $adminNames[(int) ($renewal['receipt_confirmed_by'] ?? 0)] ?? '',
			'confirmedAt' => $renewal['receipt_confirmed_at'] ?? ''
		];
	}
	unset($renewal);
	respond(['success' => true, 'renewals' => $renewals, 'franchises' => $franchises]);
}

try {
	if ($_SERVER['REQUEST_METHOD'] === 'GET') listRenewals();
	$data = requestData();
	$action = $data['action'] ?? '';
	$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

	if ($action === 'create') {
		$payload = renewalPayload($data);
		$payload['receipt_photo'] = saveDataUrlUpload($data['receiptDataUrl'] ?? '', 'receipt');
		if ($payload['receipt_photo']) $payload['receipt_submitted_at'] = date('Y-m-d H:i:s');
		respond(['success' => true, 'id' => insertSomething('renewals', $payload)], 201);
	}
	if ($action === 'update' && $id) {
		$existing = getRecord('renewals', 'renewal_id = ?', [$id]);
		if (!$existing) respond(['success' => false, 'message' => 'Renewal not found.'], 404);
		$payload = renewalPayload($data);
		$receipt = saveDataUrlUpload($data['receiptDataUrl'] ?? '', 'receipt', $existing['receipt_photo']);
		if ($receipt) {
			$payload['receipt_photo'] = $receipt;
			$payload['receipt_submitted_at'] = $existing['receipt_submitted_at'] ?: date('Y-m-d H:i:s');
		}
		updateRecord('renewals', $payload, 'renewal_id = ?', [$id]);
		respond(['success' => true]);
	}
	if ($action === 'confirm' && $id) {
		if (!getRecord('renewals', 'renewal_id = ?', [$id])) respond(['success' => false, 'message' => 'Renewal not found.'], 404);
		$confirmedBy = $_SESSION['admin_id'] ?? null;
		updateRecord('renewals', ['receipt_status' => 'Confirmed', 'receipt_confirmed_at' => date('Y-m-d H:i:s'), 'receipt_confirmed_by' => $confirmedBy], 'renewal_id = ?', [$id]);
		respond(['success' => true]);
	}
	if ($action === 'delete' && $id) {
		if (!getRecord('renewals', 'renewal_id = ?', [$id])) respond(['success' => false, 'message' => 'Renewal not found.'], 404);
		deleteRecord('renewals', 'renewal_id = ?', [$id]);
		respond(['success' => true]);
	}
	respond(['success' => false, 'message' => 'Invalid request.'], 400);
} catch (Throwable $error) {
	respond(['success' => false, 'message' => 'Unable to process renewal request.'], 500);
}
