<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$modelsPath = __DIR__ . '/../models';
require_once $modelsPath . '/functions.php';
require_once $modelsPath . '/notifications.php';
require_once $modelsPath . '/notification_triggers.php';
require_once $modelsPath . '/upload.php';

function ensureFranchiseDocumentsTable()
{
	global $conn;
	$query = 'CREATE TABLE IF NOT EXISTS franchise_documents (document_id INT NOT NULL AUTO_INCREMENT, franchise_id INT NOT NULL, receipt_photo VARCHAR(255) DEFAULT NULL, PRIMARY KEY (document_id), UNIQUE KEY franchise_id (franchise_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
	if (!mysqli_query($conn, $query)) throw new Exception('Unable to prepare franchise document storage.');
}

function ensureAdminAddressColumn()
{
	global $conn;
	$result = mysqli_query($conn, "SHOW COLUMNS FROM admins LIKE 'address'");
	if (!$result || mysqli_num_rows($result) === 0) mysqli_query($conn, 'ALTER TABLE admins ADD COLUMN address TEXT NULL');
	if ($result) mysqli_free_result($result);
}

function ensureFranchiseApplicationsTable()
{
	global $conn;
	$query = "CREATE TABLE IF NOT EXISTS franchise_applications (
		application_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		rider_id INT NULL, rider_name VARCHAR(150) NOT NULL, rider_email VARCHAR(255) NOT NULL,
		franchise_id INT NULL, franchise_name VARCHAR(150) NULL, application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending', admin_comments TEXT NULL,
		updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		address TEXT NULL, issue_date DATE NULL, expiry_date DATE NULL, receipt_photo VARCHAR(255) NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
	if (!mysqli_query($conn, $query)) throw new Exception('Unable to prepare franchise application storage.');
	foreach (['address' => 'TEXT NULL', 'issue_date' => 'DATE NULL', 'expiry_date' => 'DATE NULL', 'receipt_photo' => 'VARCHAR(255) NULL'] as $column => $definition) {
		$result = mysqli_query($conn, "SHOW COLUMNS FROM franchise_applications LIKE '$column'");
		if (!$result || mysqli_num_rows($result) === 0) mysqli_query($conn, "ALTER TABLE franchise_applications ADD COLUMN $column $definition");
		if ($result) mysqli_free_result($result);
	}
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
	$ownerEmail = trim($data['ownerEmail'] ?? $data['owner_email'] ?? '');
	$address = trim($data['address'] ?? '');
	$issue = $data['issue'] ?? $data['issue_date'] ?? '';
	$expiry = $data['expiry'] ?? $data['expiry_date'] ?? '';
	$status = $data['status'] ?? $data['renewal_status'] ?? 'Active';
	if ($name === '' || $owner === '' || $ownerEmail === '' || $address === '' || $issue === '' || $expiry === '') {
		respond(['success' => false, 'message' => 'Franchise name, owner name, owner email, franchise address, issue date, and expiry date are required.'], 422);
	}
	if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
		respond(['success' => false, 'message' => 'Please provide a valid owner email address.'], 422);
	}
	$ownerAccount = getRecord('admins', 'email = ? AND role = ? AND status = ?', [$ownerEmail, 'Admin', 'Active']);
	if (!$ownerAccount) {
		respond(['success' => false, 'message' => 'Please select an active Admin account as the franchise owner.'], 422);
	}
	$owner = trim(($ownerAccount['first_name'] ?? '') . ' ' . ($ownerAccount['last_name'] ?? '')) ?: $ownerAccount['username'];
	if (!in_array($status, ['Active', 'Expired', 'Pending Renewal'], true)) {
		respond(['success' => false, 'message' => 'Please select a valid renewal status.'], 422);
	}

	return [
		'franchise_name' => $name,
		'owner_name' => $owner,
		'owner_email' => $ownerEmail,
		'address' => $address,
		'issue_date' => $issue,
		'expiry_date' => $expiry,
		'renewal_status' => $status
	];
}

function listFranchises()
{
	markExpiredFranchises();
	ensureFranchiseDocumentsTable();
	ensureAdminAddressColumn();
	$franchises = getAllRecords('franchises', 'ORDER BY franchise_id DESC');
	$documents = getAllRecords('franchise_documents');
	$receipts = [];
	foreach ($documents as $document) $receipts[(int) $document['franchise_id']] = $document['receipt_photo'];
	$assignments = getAllRecords('franchise_tricycle');
	$tricycles = getAllRecords('tricycles');
	$ownerAccounts = getAllRecords('admins', "WHERE role = 'Admin' AND status = 'Active' ORDER BY first_name, last_name, username");
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
			'ownerEmail' => $franchise['owner_email'] ?? '',
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
	$owners = array_map(function ($account) {
		return [
			'name' => trim(($account['first_name'] ?? '') . ' ' . ($account['last_name'] ?? '')) ?: $account['username'],
			'email' => $account['email'],
			'address' => $account['address'] ?? '',
			'username' => $account['username']
		];
	}, $ownerAccounts);
	respond(['success' => true, 'franchises' => $franchises, 'owners' => $owners]);
}

function listFranchiseApplications()
{
	ensureFranchiseApplicationsTable();
	$applications = getAllRecords('franchise_applications', 'ORDER BY application_date DESC');
	$applications = array_map(function ($application) {
		return [
			'id' => (int) $application['application_id'], 'riderName' => $application['rider_name'],
			'riderEmail' => $application['rider_email'], 'franchiseName' => $application['franchise_name'],
			'franchiseId' => $application['franchise_id'] ? (int) $application['franchise_id'] : null,
			'address' => $application['address'] ?? '', 'issueDate' => $application['issue_date'] ?? '',
			'expiryDate' => $application['expiry_date'] ?? '', 'status' => $application['status'],
			'comments' => $application['admin_comments'] ?? '', 'applicationDate' => $application['application_date'],
			'receiptUrl' => uploadUrl($application['receipt_photo'] ?? '')
		];
	}, $applications);
	respond(['success' => true, 'applications' => $applications]);
}

try {
	if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		if (($_GET['resource'] ?? '') === 'applications') listFranchiseApplications();
		listFranchises();
	}
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
		ensureFranchiseApplicationsTable();
		$existing = getRecord('franchises', 'franchise_id = ?', [$id]);
		if (!$existing) respond(['success' => false, 'message' => 'Franchise not found.'], 404);
		$driverAssignments = getAllRecords('franchise_driver', 'WHERE franchise_id = ?', [$id]);
		$tricycleAssignments = getAllRecords('franchise_tricycle', 'WHERE franchise_id = ?', [$id]);
		if (!mysqli_begin_transaction($conn)) throw new Exception(mysqli_error($conn));
		try {
			$driverIds = array_values(array_unique(array_map(fn($assignment) => (int) $assignment['driver_id'], $driverAssignments)));
			$tricycleIds = array_values(array_unique(array_map(fn($assignment) => (int) $assignment['tricycle_id'], $tricycleAssignments)));
			foreach ($driverIds as $driverId) {
				if (!deleteRecord('driver_tricycle', 'driver_id = ?', [$driverId])) throw new Exception(mysqli_error($conn));
			}
			foreach ($tricycleIds as $tricycleId) {
				if (!deleteRecord('driver_tricycle', 'tricycle_id = ?', [$tricycleId])) throw new Exception(mysqli_error($conn));
			}
			$deletes = [
				['renewals', 'franchise_id = ?', [$id]],
				['franchise_documents', 'franchise_id = ?', [$id]],
				['franchise_driver', 'franchise_id = ?', [$id]],
				['franchise_tricycle', 'franchise_id = ?', [$id]],
				['drivers', 'driver_id IN (' . ($driverIds ? implode(',', array_fill(0, count($driverIds), '?')) : 'NULL') . ')', $driverIds],
				['tricycles', 'tricycle_id IN (' . ($tricycleIds ? implode(',', array_fill(0, count($tricycleIds), '?')) : 'NULL') . ')', $tricycleIds]
			];
			foreach ($deletes as [$table, $condition, $params]) {
				if (!deleteRecord($table, $condition, $params)) throw new Exception(mysqli_error($conn));
			}
			if (!deleteRecord('franchise_applications', 'franchise_id = ?', [$id])) throw new Exception(mysqli_error($conn));
			if (!deleteRecord('franchises', 'franchise_id = ?', [$id])) throw new Exception('The franchise record could not be deleted from the franchises table.');
			if (getRecord('franchises', 'franchise_id = ?', [$id])) throw new Exception('Franchise still exists after delete.');
			if (!mysqli_commit($conn)) throw new Exception(mysqli_error($conn));
		} catch (Throwable $error) {
			mysqli_rollback($conn);
			throw $error;
		}
		$adminEmail = trim($_SESSION['admin_email'] ?? '') ?: getAdminEmail();
		triggerFranchiseRemovalNotification($id, $existing['franchise_name'], $existing['owner_email'] ?? null, $adminEmail);
		respond(['success' => true]);
	}
	if ($action === 'delete-application' && $id) {
		ensureFranchiseApplicationsTable();
		if (!getRecord('franchise_applications', 'application_id = ?', [$id])) respond(['success' => false, 'message' => 'Application not found.'], 404);
		if (!deleteRecord('franchise_applications', 'application_id = ?', [$id])) throw new Exception(mysqli_error($conn));
		respond(['success' => true]);
	}
	if (in_array($action, ['approve-application', 'reject-application'], true) && $id) {
		ensureFranchiseApplicationsTable();
		$application = getRecord('franchise_applications', 'application_id = ?', [$id]);
		if (!$application || $application['status'] !== 'Pending') respond(['success' => false, 'message' => 'Pending application not found.'], 404);
		
		$admin_email = trim($_SESSION['admin_email'] ?? '');
		
		if ($action === 'approve-application') {
			ensureFranchiseDocumentsTable();
			$franchiseId = insertSomething('franchises', ['franchise_name' => $application['franchise_name'], 'owner_name' => $application['rider_name'], 'owner_email' => $application['rider_email'], 'address' => $application['address'], 'issue_date' => $application['issue_date'], 'expiry_date' => $application['expiry_date'], 'renewal_status' => 'Active']);
			if (!empty($application['receipt_photo'])) insertSomething('franchise_documents', ['franchise_id' => $franchiseId, 'receipt_photo' => $application['receipt_photo']]);
			updateRecord('franchise_applications', ['status' => 'Approved', 'franchise_id' => $franchiseId, 'admin_comments' => trim($data['comments'] ?? '')], 'application_id = ?', [$id]);
			notifyFranchiseApproval($application['rider_email'], $application['franchise_name'], 'Your franchise has been approved and is now active.');
			
			// Notify the admin who approved this
			if ($admin_email && filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
				createNotification(
					"Franchise Application Approved",
					$application['rider_name'] . " has been approved for " . $application['franchise_name'],
					'Franchise',
					'info',
					$admin_email,
					$franchiseId,
					'franchise_approval'
				);
			}
		} else {
			updateRecord('franchise_applications', ['status' => 'Rejected', 'admin_comments' => trim($data['comments'] ?? '')], 'application_id = ?', [$id]);
			notifyFranchiseRejection($application['rider_email'], $application['franchise_name'], trim($data['comments'] ?? 'Please contact us for more details.'));
			
			// Notify the admin who rejected this
			if ($admin_email && filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
				createNotification(
					"Franchise Application Rejected",
					$application['rider_name'] . "'s application for " . $application['franchise_name'] . " has been rejected.",
					'Franchise',
					'warning',
					$admin_email,
					null,
					'franchise_rejection'
				);
			}
		}
		respond(['success' => true]);
	}
	respond(['success' => false, 'message' => 'Invalid request.'], 400);
} catch (Throwable $error) {
	error_log('Franchise management error: ' . $error->getMessage());
	error_log('Franchise management error: ' . $error->getMessage());
	respond(['success' => false, 'message' => $error->getMessage()], 500);
}
