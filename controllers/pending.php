<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$modelsPath = __DIR__ . '/../models';
set_include_path($modelsPath . PATH_SEPARATOR . get_include_path());
require_once $modelsPath . '/functions.php';
require_once $modelsPath . '/notifications.php';
require_once $modelsPath . '/notification_triggers.php';
require_once $modelsPath . '/upload.php';

function respond($payload, $status = 200) { http_response_code($status); echo json_encode($payload); exit; }
function requestData() { $data = json_decode(file_get_contents('php://input'), true); return is_array($data) ? $data : $_POST; }
function requireAdminSession() {
	if (empty($_SESSION['admin_id'])) respond(['success' => false, 'message' => 'Admin login required.'], 401);
}
function requireSuperAdmin() {
	$adminId = (int) ($_SESSION['admin_id'] ?? 0);
	$admin = $adminId ? getRecord('admins', 'admin_id = ? AND status = ?', [$adminId, 'Active']) : null;
	if (trim((string) ($_SESSION['admin_role'] ?? $admin['role'] ?? '')) !== 'Super Admin') {
		respond(['success' => false, 'message' => 'Only Super Admins can review franchise-owner tricycles.'], 403);
	}
}
function runNotificationSafely($callback) {
	try {
		$callback();
	} catch (Throwable $error) {
		error_log('Pending request notification error: ' . $error->getMessage());
	}
}
function ensureDriverLicenseBackColumn() {
	global $conn;
	$check = mysqli_query($conn, "SHOW COLUMNS FROM drivers LIKE 'driver_license_back'");
	if (!$check) throw new RuntimeException('Unable to inspect driver license image storage: ' . mysqli_error($conn));
	$columnExists = mysqli_num_rows($check) > 0;
	mysqli_free_result($check);
	if (!$columnExists && !mysqli_query($conn, 'ALTER TABLE drivers ADD COLUMN driver_license_back VARCHAR(255) NULL AFTER driver_license')) {
		throw new RuntimeException('Unable to prepare driver license image storage: ' . mysqli_error($conn));
	}
}
function ensureFranchiseDocumentsTable() {
	global $conn;
	if (!mysqli_query($conn, 'CREATE TABLE IF NOT EXISTS franchise_documents (document_id INT NOT NULL AUTO_INCREMENT, franchise_id INT NOT NULL, receipt_photo VARCHAR(255) DEFAULT NULL, cedula_photo VARCHAR(255) DEFAULT NULL, drivers_license VARCHAR(100) DEFAULT NULL, drivers_license_photo VARCHAR(255) DEFAULT NULL, drivers_license_back_photo VARCHAR(255) DEFAULT NULL, PRIMARY KEY (document_id), UNIQUE KEY franchise_id (franchise_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4')) {
		throw new Exception('Unable to prepare franchise document storage: ' . mysqli_error($conn));
	}
	foreach (['cedula_photo' => 'VARCHAR(255) NULL', 'drivers_license' => 'VARCHAR(100) NULL', 'drivers_license_photo' => 'VARCHAR(255) NULL', 'drivers_license_back_photo' => 'VARCHAR(255) NULL'] as $column => $definition) {
		$result = mysqli_query($conn, "SHOW COLUMNS FROM franchise_documents LIKE '$column'");
		if (!$result) throw new RuntimeException('Unable to inspect franchise document storage: ' . mysqli_error($conn));
		$columnExists = mysqli_num_rows($result) > 0;
		mysqli_free_result($result);
		if (!$columnExists && !mysqli_query($conn, "ALTER TABLE franchise_documents ADD COLUMN $column $definition")) {
			throw new RuntimeException('Unable to prepare franchise document storage: ' . mysqli_error($conn));
		}
	}
}
function ensureFranchiseApplicationsTable() {
	global $conn;
	$query = "CREATE TABLE IF NOT EXISTS franchise_applications (application_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, rider_id INT NULL, rider_name VARCHAR(150) NOT NULL, rider_email VARCHAR(255) NOT NULL, franchise_id INT NULL, franchise_name VARCHAR(150) NULL, application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending', admin_comments TEXT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, address TEXT NULL, issue_date DATE NULL, expiry_date DATE NULL, receipt_photo VARCHAR(255) NULL, cedula_document VARCHAR(255) NULL, drivers_license VARCHAR(100) NULL, drivers_license_photo VARCHAR(255) NULL, drivers_license_back_photo VARCHAR(255) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
	if (!mysqli_query($conn, $query)) throw new Exception('Unable to prepare franchise application storage.');
	foreach (['cedula_document' => 'VARCHAR(255) NULL', 'drivers_license' => 'VARCHAR(100) NULL', 'drivers_license_photo' => 'VARCHAR(255) NULL', 'drivers_license_back_photo' => 'VARCHAR(255) NULL'] as $column => $definition) {
		$result = mysqli_query($conn, "SHOW COLUMNS FROM franchise_applications LIKE '$column'");
		if (!$result) throw new RuntimeException('Unable to inspect franchise application document storage: ' . mysqli_error($conn));
		$columnExists = mysqli_num_rows($result) > 0;
		mysqli_free_result($result);
		if (!$columnExists && !mysqli_query($conn, "ALTER TABLE franchise_applications ADD COLUMN $column $definition")) {
			throw new RuntimeException('Unable to prepare franchise application document storage: ' . mysqli_error($conn));
		}
	}
}
function ensureOwnerTricycleReviewColumns() {
	global $conn;
	$checks = [
		'status' => "ALTER TABLE franchise_owner_tricycle MODIFY status ENUM('Pending','Active','Inactive','Archived') NOT NULL DEFAULT 'Pending'",
		'or_cr_document' => "ALTER TABLE franchise_owner_tricycle ADD COLUMN or_cr_document VARCHAR(255) DEFAULT NULL AFTER or_number"
	];
	foreach ($checks as $column => $alterSql) {
		$check = mysqli_query($conn, "SHOW COLUMNS FROM franchise_owner_tricycle LIKE '$column'");
		if (!$check) throw new RuntimeException('Unable to inspect owner tricycle review fields: ' . mysqli_error($conn));
		$definition = mysqli_fetch_assoc($check);
		mysqli_free_result($check);
		if ($column === 'status') {
			$type = $definition['Type'] ?? '';
			if (stripos($type, "'Pending'") === false || stripos($type, "'Active'") === false || stripos($type, "'Inactive'") === false || stripos($type, "'Archived'") === false) {
				if (!mysqli_query($conn, $alterSql)) throw new RuntimeException('Unable to enable owner tricycle pending review: ' . mysqli_error($conn));
			}
		} elseif (!$definition && !mysqli_query($conn, $alterSql)) {
			throw new RuntimeException('Unable to prepare owner tricycle document review: ' . mysqli_error($conn));
		}
	}
}
function groupRequest(&$groups, $franchiseId, $franchiseName, $owner, $email, $request) {
	$key = $franchiseId ?: 'application-' . $request['id'];
	if (!isset($groups[$key])) $groups[$key] = ['id' => $key, 'franchiseId' => $franchiseId, 'franchiseName' => $franchiseName ?: 'Unassigned Franchise', 'owner' => $owner ?: 'Unknown Owner', 'contact' => $email ?: 'No email', 'requests' => []];
	$groups[$key]['requests'][] = $request;
}
function listPendingRequests() {
	global $conn;
	ensureFranchiseApplicationsTable();
	ensureDriverLicenseBackColumn();
	$groups = [];
	$franchises = getAllRecords('franchises');
	$franchiseMap = [];
	foreach ($franchises as $franchise) $franchiseMap[(int) $franchise['franchise_id']] = $franchise;

	$applications = getAllRecords('franchise_applications', 'WHERE status = ? ORDER BY application_date DESC', ['Pending']);
	foreach ($applications as $application) {
		groupRequest($groups, (int) ($application['franchise_id'] ?? 0), $application['franchise_name'], $application['rider_name'], $application['rider_email'], [
			'id' => (int) $application['application_id'], 'type' => 'Franchise Application', 'actionType' => 'franchise',
			'title' => 'Franchise application - ' . $application['franchise_name'], 'description' => 'New franchise application submitted for review.',
			'submittedAt' => $application['application_date'], 'status' => 'Pending', 'denialReason' => $application['admin_comments'] ?? '',
			'docs' => array_values(array_filter([
				!empty($application['receipt_photo']) ? ['label' => 'Payment Receipt', 'url' => uploadUrl($application['receipt_photo'])] : null,
				!empty($application['cedula_document']) ? ['label' => 'Cedula', 'url' => uploadUrl($application['cedula_document'])] : null,
				!empty($application['drivers_license']) ? ['label' => "Driver's License Number", 'value' => $application['drivers_license']] : null,
				!empty($application['drivers_license_photo']) ? ['label' => "Driver's License (Front)", 'url' => uploadUrl($application['drivers_license_photo'])] : null,
				!empty($application['drivers_license_back_photo']) ? ['label' => "Driver's License (Back)", 'url' => uploadUrl($application['drivers_license_back_photo'])] : null
			]))
		]);
	}

	$drivers = getAllRecords('drivers', 'WHERE status IN (?, ?) ORDER BY created_at DESC', ['Pending', 'For Review']);
	foreach ($drivers as $driver) {
		$assignment = getRecord('franchise_driver', 'driver_id = ? ORDER BY assignment_id DESC LIMIT 1', [(int) $driver['driver_id']]);
		$franchise = $franchiseMap[(int) ($assignment['franchise_id'] ?? 0)] ?? [];
		groupRequest($groups, (int) ($assignment['franchise_id'] ?? 0), $franchise['franchise_name'] ?? '', $franchise['owner_name'] ?? '', $franchise['owner_email'] ?? '', [
			'id' => (int) $driver['driver_id'], 'type' => 'Driver Addition', 'actionType' => 'driver',
			'title' => 'Driver submission - ' . $driver['full_name'], 'description' => 'Driver registration submitted for approval.',
			'submittedAt' => $driver['created_at'], 'status' => 'Pending', 'denialReason' => '',
			'docs' => array_values(array_filter([['label' => "Driver's License Number", 'value' => $driver['driver_license_number'] ?? ''], ['label' => 'OR/CR Number', 'value' => $driver['or_cr_number'] ?? ''], ['label' => "Driver's License (Front)", 'url' => uploadUrl($driver['driver_license'])], ['label' => "Driver's License (Back)", 'url' => uploadUrl($driver['driver_license_back'])], ['label' => 'OR/CR', 'url' => uploadUrl($driver['or_cr'])], ['label' => "President's Certificate", 'url' => uploadUrl($driver['president_certificate'])]], fn($document) => ($document['url'] ?? '') !== '' || ($document['value'] ?? '') !== ''))
		]);
	}

	$tricycles = getAllRecords('tricycles', 'WHERE status = ? ORDER BY created_at DESC', ['Pending']);
	foreach ($tricycles as $tricycle) {
		$assignment = getRecord('franchise_tricycle', 'tricycle_id = ? ORDER BY assignment_id DESC LIMIT 1', [(int) $tricycle['tricycle_id']]);
		$franchise = $franchiseMap[(int) ($assignment['franchise_id'] ?? 0)] ?? [];
		groupRequest($groups, (int) ($assignment['franchise_id'] ?? 0), $franchise['franchise_name'] ?? '', $franchise['owner_name'] ?? '', $franchise['owner_email'] ?? '', [
			'id' => (int) $tricycle['tricycle_id'], 'type' => 'Tricycle Addition', 'actionType' => 'tricycle',
			'title' => 'Tricycle submission - ' . ($tricycle['plate_number'] ?: 'No plate'), 'description' => 'Tricycle registration submitted for approval.',
			'submittedAt' => $tricycle['created_at'], 'status' => 'Pending', 'denialReason' => '',
			'docs' => !empty($tricycle['or_document']) ? [['label' => 'OR Document', 'url' => uploadUrl($tricycle['or_document'])]] : []
		]);
	}

	ensureOwnerTricycleReviewColumns();
	$ownerTricycles = getAllRecords('franchise_owner_tricycle', 'WHERE status = ? ORDER BY created_at DESC', ['Pending']);
	foreach ($ownerTricycles as $tricycle) {
		$franchise = $franchiseMap[(int) $tricycle['franchise_id']] ?? [];
		groupRequest($groups, (int) ($tricycle['franchise_id'] ?? 0), $franchise['franchise_name'] ?? '', $franchise['owner_name'] ?? '', $franchise['owner_email'] ?? '', [
			'id' => (int) $tricycle['owner_tricycle_id'], 'type' => 'Tricycle Addition', 'actionType' => 'owner_tricycle',
			'title' => 'Owner tricycle submission - ' . ($tricycle['plate_number'] ?: 'No plate'), 'description' => 'Franchise-owner tricycle submitted for approval.',
			'submittedAt' => $tricycle['created_at'], 'status' => 'Pending', 'denialReason' => '',
			'docs' => array_values(array_filter([
				!empty($tricycle['or_number']) ? ['label' => 'Official Receipt Number', 'value' => $tricycle['or_number']] : null,
				!empty($tricycle['or_cr_document']) ? ['label' => 'OR/CR Proof', 'url' => uploadUrl($tricycle['or_cr_document'])] : null
			]))
		]);
	}

	$renewals = getAllRecords('renewals', 'WHERE receipt_status = ? ORDER BY renewal_id DESC', ['Submitted']);
	foreach ($renewals as $renewal) {
		$franchise = $franchiseMap[(int) $renewal['franchise_id']] ?? [];
		groupRequest($groups, (int) $renewal['franchise_id'], $franchise['franchise_name'] ?? '', $franchise['owner_name'] ?? '', $franchise['owner_email'] ?? '', [
			'id' => (int) $renewal['renewal_id'], 'type' => 'Renewal Submission', 'actionType' => 'renewal',
			'title' => ($renewal['renewal_year'] ?? date('Y')) . ' renewal payment receipt', 'description' => 'Renewal payment receipt submitted for review.',
			'submittedAt' => $renewal['receipt_submitted_at'] ?: $renewal['renewal_date'], 'status' => 'Pending', 'denialReason' => '',
			'docs' => !empty($renewal['receipt_photo']) ? [['label' => 'Payment Receipt', 'url' => uploadUrl($renewal['receipt_photo'])]] : []
		]);
	}
	respond(['success' => true, 'groups' => array_values($groups)]);
}
function resolveRequest($data, $approve) {
	global $conn;
	$type = $data['type'] ?? '';
	$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
	if (!$id) respond(['success' => false, 'message' => 'Request not found.'], 404);
	$comments = trim($data['reason'] ?? $data['comments'] ?? '');
	if ($type === 'franchise') {
		$application = getRecord('franchise_applications', 'application_id = ? AND status = ?', [$id, 'Pending']);
		if (!$application) respond(['success' => false, 'message' => 'Pending franchise application not found.'], 404);
		if ($approve) {
			ensureFranchiseDocumentsTable();
			$franchiseId = insertSomething('franchises', ['franchise_name' => $application['franchise_name'], 'owner_name' => $application['rider_name'], 'owner_email' => $application['rider_email'], 'address' => $application['address'], 'issue_date' => $application['issue_date'], 'expiry_date' => $application['expiry_date'], 'renewal_status' => 'Active']);
			$documentData = ['franchise_id' => $franchiseId];
			foreach (['receipt_photo', 'cedula_photo', 'drivers_license', 'drivers_license_photo', 'drivers_license_back_photo'] as $field) {
				$applicationField = $field === 'cedula_photo' ? 'cedula_document' : $field;
				if (!empty($application[$applicationField])) $documentData[$field] = $application[$applicationField];
			}
			if (count($documentData) > 1) insertSomething('franchise_documents', $documentData);
			updateRecord('franchise_applications', ['status' => 'Approved', 'franchise_id' => $franchiseId, 'admin_comments' => $comments], 'application_id = ?', [$id]);
			runNotificationSafely(function () use ($application) {
				notifyFranchiseApproval($application['rider_email'], $application['franchise_name']);
			});
		} else {
			updateRecord('franchise_applications', ['status' => 'Rejected', 'admin_comments' => $comments], 'application_id = ?', [$id]);
				runNotificationSafely(function () use ($application, $comments) {
					notifyFranchiseRejection($application['rider_email'], $application['franchise_name'], $comments ?: 'Please contact us for more details.');
				});
		}
	} elseif ($type === 'driver') {
		$driver = getRecord('drivers', 'driver_id = ? AND status IN (?, ?)', [$id, 'Pending', 'For Review']);
		if (!$driver) respond(['success' => false, 'message' => 'Pending driver request not found.'], 404);
		if (!$approve) respond(['success' => false, 'message' => 'Driver rejection is not supported by the current driver status schema.'], 422);
		updateRecord('drivers', ['status' => 'Approved'], 'driver_id = ?', [$id]);
		runNotificationSafely(function () use ($id, $driver) {
			triggerDriverStatusNotification($id, $driver['full_name'], $driver['email'] ?? null, $driver['status'], 'Approved');
		});
	} elseif ($type === 'tricycle') {
		$tricycle = getRecord('tricycles', 'tricycle_id = ? AND status = ?', [$id, 'Pending']);
		if (!$tricycle) respond(['success' => false, 'message' => 'Pending tricycle request not found.'], 404);
		updateRecord('tricycles', ['status' => $approve ? 'Active' : 'Inactive'], 'tricycle_id = ?', [$id]);
		runNotificationSafely(function () use ($id, $tricycle, $approve) {
			triggerTricycleStatusNotification($id, $tricycle['plate_number'] ?: "Unit $id", 'Pending', $approve ? 'Active' : 'Inactive');
		});
	} elseif ($type === 'owner_tricycle') {
		requireSuperAdmin();
		ensureOwnerTricycleReviewColumns();
		$tricycle = getRecord('franchise_owner_tricycle', 'owner_tricycle_id = ? AND status = ?', [$id, 'Pending']);
		if (!$tricycle) respond(['success' => false, 'message' => 'Pending franchise-owner tricycle request not found.'], 404);
		$franchise = getRecord('franchises', 'franchise_id = ?', [(int) $tricycle['franchise_id']]);
		if (!$franchise) respond(['success' => false, 'message' => 'The franchise owner for this tricycle could not be found.'], 404);
		$newStatus = $approve ? 'Active' : 'Inactive';
		$statement = mysqli_prepare($conn, "UPDATE franchise_owner_tricycle SET status = ? WHERE owner_tricycle_id = ? AND status = 'Pending'");
		if (!$statement) throw new RuntimeException('Unable to prepare owner tricycle decision: ' . mysqli_error($conn));
		mysqli_stmt_bind_param($statement, 'si', $newStatus, $id);
		if (!mysqli_stmt_execute($statement)) {
			$error = mysqli_stmt_error($statement);
			mysqli_stmt_close($statement);
			throw new RuntimeException('Unable to save owner tricycle decision: ' . $error);
		}
		$changed = mysqli_stmt_affected_rows($statement);
		mysqli_stmt_close($statement);
		if ($changed !== 1) respond(['success' => false, 'message' => 'This owner tricycle has already been reviewed.'], 409);
		$ownerEmail = trim((string) ($franchise['owner_email'] ?? ''));
		if (filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
			runNotificationSafely(function () use ($ownerEmail, $tricycle, $approve, $comments, $id) {
				$plate = $tricycle['plate_number'] ?: "Unit $id";
				$message = $approve
					? "Your owner tricycle ($plate) has been approved and is available for certification."
					: "Your owner tricycle ($plate) was not approved. Reason: " . ($comments ?: 'Please contact the Super Admin for assistance.');
				createNotification(
					$approve ? 'Owner Tricycle Approved' : 'Owner Tricycle Not Approved',
					$message,
					'Tricycle',
					$approve ? 'info' : 'warning',
					$ownerEmail,
					$id,
					'owner_tricycle_status'
				);
			});
		}
	} elseif ($type === 'renewal') {
		$renewal = getRecord('renewals', 'renewal_id = ? AND receipt_status = ?', [$id, 'Submitted']);
		if (!$renewal) respond(['success' => false, 'message' => 'Pending renewal request not found.'], 404);
		$franchise = getRecord('franchises', 'franchise_id = ?', [(int) $renewal['franchise_id']]);
		$decisionAt = date('Y-m-d H:i:s');
		updateRecord('renewals', [
			'receipt_status' => $approve ? 'Confirmed' : 'Rejected',
			'receipt_confirmed_at' => $decisionAt,
			'receipt_confirmed_by' => $_SESSION['admin_id']
		], 'renewal_id = ?', [$id]);
		if ($approve) {
			updateRecord('franchises', ['issue_date' => $renewal['renewal_date'], 'expiry_date' => $renewal['due_date'], 'renewal_status' => 'Active'], 'franchise_id = ?', [$renewal['franchise_id']]);
		}
		if ($franchise) {
			$reason = trim($data['reason'] ?? 'Please contact the admin team for assistance.');
			runNotificationSafely(function () use ($approve, $franchise, $renewal, $reason, $id) {
				createNotification(
					$approve ? 'Renewal Approved' : 'Renewal Requires Attention',
					$approve
						? "Your renewal for {$franchise['franchise_name']} has been approved. Your franchise is active through {$renewal['due_date']}."
						: "Your renewal for {$franchise['franchise_name']} was not approved. {$reason}",
					'Renewal',
					$approve ? 'info' : 'urgent',
					$franchise['owner_email'] ?? '',
					$id,
					'renewal_decision'
				);
			});
		}
	} else respond(['success' => false, 'message' => 'Unknown request type.'], 422);
	respond(['success' => true]);
}

try {
	requireAdminSession();
	if ($_SERVER['REQUEST_METHOD'] === 'GET') listPendingRequests();
	$data = requestData();
	resolveRequest($data, ($data['action'] ?? '') === 'approve');
} catch (Throwable $error) {
	error_log('Pending request error: ' . $error->getMessage());
	respond(['success' => false, 'message' => 'Unable to process pending request.'], 500);
}
