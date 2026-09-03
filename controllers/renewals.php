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
	$data = json_decode(file_get_contents('php://input'), true);
	return is_array($data) ? $data : $_POST;
}

function ensureRenewalStatusSchema()
{
	global $conn;
	$check = mysqli_query($conn, "SHOW COLUMNS FROM renewals LIKE 'receipt_status'");
	$column = $check ? mysqli_fetch_assoc($check) : null;
	if ($column && strpos((string) ($column['Type'] ?? ''), "'Rejected'") === false) {
		mysqli_query($conn, "ALTER TABLE renewals MODIFY receipt_status ENUM('Not Submitted','Submitted','Confirmed','Rejected') DEFAULT 'Not Submitted'");
	}
}

function renewalPayload($data)
{
	$franchiseId = filter_var($data['franchise_id'] ?? null, FILTER_VALIDATE_INT);
	$franchise = $franchiseId ? getRecord('franchises', 'franchise_id = ?', [$franchiseId]) : null;
	if (!$franchise) {
		respond(['success' => false, 'message' => 'Selected franchise not found.'], 422);
	}
	$expiryYear = !empty($franchise['expiry_date']) ? (int) date('Y', strtotime($franchise['expiry_date'])) : 0;
	$year = $expiryYear > 0 ? $expiryYear - 1 : (int) date('Y');
	$renewalDate = $year . '-01-01';
	$dueDate = ($year + 1) . '-01-01';

	return [
		'franchise_id' => $franchiseId,
		'renewal_year' => $year,
		'renewal_date' => $renewalDate,
		'due_date' => $dueDate,
		'receipt_status' => 'Submitted',
		'receipt_confirmed_at' => null,
		'receipt_confirmed_by' => null,
		'franchise' => $franchise
	];
}

function listRenewals()
{
	markExpiredFranchises();
	ensureRenewalStatusSchema();
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
			'status' => $renewal['receipt_status'],
			'receipt' => $renewal['receipt_photo'] ? basename($renewal['receipt_photo']) : '',
			'receiptDataUrl' => uploadUrl($renewal['receipt_photo']),
			'receiptSubmittedAt' => $renewal['receipt_submitted_at'] ?? '',
			'uploadedBy' => $renewal['receipt_photo'] ? ($renewal['receipt_submitted_at'] ? 'Rider' : 'Admin') : '',
			'confirmedBy' => $adminNames[(int) ($renewal['receipt_confirmed_by'] ?? 0)] ?? '',
			'confirmedAt' => $renewal['receipt_confirmed_at'] ?? ''
		];
	}
	unset($renewal);
	respond(['success' => true, 'renewals' => $renewals, 'franchises' => $franchises]);
}

try {
	ensureRenewalStatusSchema();
	if ($_SERVER['REQUEST_METHOD'] === 'GET') listRenewals();
	$data = requestData();
	$action = $data['action'] ?? '';
	$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

	if ($action === 'create') {
		$payload = renewalPayload($data);
		unset($payload['franchise']);
		$payload['receipt_status'] = 'Submitted';
		$payload['receipt_confirmed_at'] = null;
		$payload['receipt_confirmed_by'] = null;
		$payload['receipt_photo'] = saveDataUrlUpload($data['receiptDataUrl'] ?? '', 'receipt');
		if ($payload['receipt_photo']) $payload['receipt_submitted_at'] = date('Y-m-d H:i:s');
		$id = insertSomething('renewals', $payload);
		respond(['success' => true, 'id' => $id], 201);
	}
	if ($action === 'update' && $id) {
		$existing = getRecord('renewals', 'renewal_id = ?', [$id]);
		if (!$existing) respond(['success' => false, 'message' => 'Renewal not found.'], 404);
		$payload = renewalPayload($data);
		unset($payload['franchise']);
		$receipt = saveDataUrlUpload($data['receiptDataUrl'] ?? '', 'receipt', $existing['receipt_photo']);
		if ($receipt) {
			$payload['receipt_photo'] = $receipt;
			$payload['receipt_submitted_at'] = $existing['receipt_submitted_at'] ?: date('Y-m-d H:i:s');
		}
		updateRecord('renewals', $payload, 'renewal_id = ?', [$id]);
		respond(['success' => true]);
	}
	if ($action === 'confirm' && $id) {
		$renewal = getRecord('renewals', 'renewal_id = ?', [$id]);
		if (!$renewal) respond(['success' => false, 'message' => 'Renewal not found.'], 404);
		$confirmedBy = $_SESSION['admin_id'] ?? null;
		updateRecord('renewals', ['receipt_status' => 'Confirmed', 'receipt_confirmed_at' => date('Y-m-d H:i:s'), 'receipt_confirmed_by' => $confirmedBy], 'renewal_id = ?', [$id]);
		updateRecord('franchises', [
			'issue_date' => $renewal['renewal_date'],
			'expiry_date' => $renewal['due_date'],
			'renewal_status' => 'Active'
		], 'franchise_id = ?', [$renewal['franchise_id']]);
		$franchise = getRecord('franchises', 'franchise_id = ?', [(int) $renewal['franchise_id']]);
		if ($franchise) {
			createNotification(
				'Renewal Approved',
				"Your renewal for {$franchise['franchise_name']} has been approved. Your franchise is active through {$renewal['due_date']}.",
				'Renewal',
				'info',
				$franchise['owner_email'] ?? '',
				$id,
				'renewal_decision'
			);
		}
		respond(['success' => true]);
	}
	if ($action === 'reject' && $id) {
		$renewal = getRecord('renewals', 'renewal_id = ? AND receipt_status = ?', [$id, 'Submitted']);
		if (!$renewal) respond(['success' => false, 'message' => 'Submitted renewal not found.'], 404);
		$confirmedBy = $_SESSION['admin_id'] ?? null;
		updateRecord('renewals', ['receipt_status' => 'Rejected', 'receipt_confirmed_at' => date('Y-m-d H:i:s'), 'receipt_confirmed_by' => $confirmedBy], 'renewal_id = ?', [$id]);
		$franchise = getRecord('franchises', 'franchise_id = ?', [(int) $renewal['franchise_id']]);
		$reason = trim($data['reason'] ?? 'Please contact the admin team for assistance.');
		if ($franchise) {
			createNotification(
				'Renewal Requires Attention',
				"Your renewal for {$franchise['franchise_name']} was not approved. {$reason}",
				'Renewal',
				'urgent',
				$franchise['owner_email'] ?? '',
				$id,
				'renewal_decision'
			);
		}
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
