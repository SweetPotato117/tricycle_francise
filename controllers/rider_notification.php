<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$modelsPath = __DIR__ . '/../models';
set_include_path($modelsPath . PATH_SEPARATOR . get_include_path());
require_once $modelsPath . '/functions.php';
require_once $modelsPath . '/notifications.php';

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

function formatNotification($row)
{
	return [
		'id' => (int) $row['notification_id'],
		'type' => $row['type'] ?? 'Franchise',
		'severity' => $row['severity'] ?? 'info',
		'title' => $row['title'] ?? '',
		'message' => $row['message'] ?? '',
		'isRead' => (bool) $row['is_read'],
		'createdAt' => str_replace(' ', 'T', $row['created_at'])
	];
}

/**
 * Get admin email from configuration
 */
function getAdminEmail()
{
	return defined('TRICYCLE_ADMIN_EMAIL') ? TRICYCLE_ADMIN_EMAIL : (getenv('TRICYCLE_ADMIN_EMAIL') ?: 'admin@tricyclefranchise.local');
}

try {
	// Handle GET - retrieve notifications for rider email
	if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		$email = $_GET['email'] ?? $_SESSION['rider_email'] ?? null;
		
		if (!$email) {
			respond(['success' => false, 'message' => 'Email required.'], 400);
		}
		
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			respond(['success' => false, 'message' => 'Invalid email format.'], 400);
		}
		
		$notifications = getNotificationsForEmail($email, 50);
		$unreadCount = getUnreadNotificationCount($email);
		
		respond([
			'success' => true,
			'notifications' => array_map('formatNotification', $notifications),
			'unreadCount' => $unreadCount
		]);
	}

	// Handle POST - create franchise application or mark notification as read
	$data = requestData();
	$action = $data['action'] ?? '';

	if ($action === 'apply_franchise') {
		$rider_name = trim($data['rider_name'] ?? '');
		$rider_email = trim($data['rider_email'] ?? '');
		$franchise_name = trim($data['franchise_name'] ?? '');
		$franchise_id = filter_var($data['franchise_id'] ?? null, FILTER_VALIDATE_INT);
		
		if (!$rider_name || !$rider_email || !$franchise_name) {
			respond(['success' => false, 'message' => 'All fields are required.'], 422);
		}
		
		if (!filter_var($rider_email, FILTER_VALIDATE_EMAIL)) {
			respond(['success' => false, 'message' => 'Invalid email format.'], 422);
		}
		
		global $conn;
		
		// Create franchise application record
		$appQuery = "INSERT INTO franchise_applications (rider_name, rider_email, franchise_id, franchise_name, status, application_date) 
		             VALUES (?, ?, ?, ?, 'Pending', NOW())";
		$appStmt = mysqli_prepare($conn, $appQuery);
		mysqli_stmt_bind_param($appStmt, "ssIs", $rider_name, $rider_email, $franchise_id, $franchise_name);
		
		if (!mysqli_stmt_execute($appStmt)) {
			mysqli_stmt_close($appStmt);
			respond(['success' => false, 'message' => 'Failed to submit application.'], 500);
		}
		
		$app_id = mysqli_insert_id($conn);
		mysqli_stmt_close($appStmt);
		
		// Find the admin who owns this franchise and notify them
		$admin_email = $franchise_id ? getAdminEmailByFranchiseId($franchise_id) : null;
		if (!$admin_email) {
			// Fallback to default admin email if franchise is not yet associated with an admin
			$admin_email = getAdminEmail();
		}
		
		notifyFranchiseApplication($rider_name, $rider_email, $franchise_name, $admin_email);
		
		respond([
			'success' => true,
			'message' => 'Application submitted successfully.',
			'applicationId' => $app_id
		], 201);
	}

	if ($action === 'read_notification') {
		$notif_id = filter_var($data['notification_id'] ?? null, FILTER_VALIDATE_INT);
		
		if (!$notif_id) {
			respond(['success' => false, 'message' => 'Notification not found.'], 404);
		}
		
		updateRecord('notifications', ['is_read' => 1], 'notification_id = ?', [$notif_id]);
		respond(['success' => true]);
	}

	if ($action === 'mark_all_read') {
		$email = trim($data['email'] ?? $_SESSION['rider_email'] ?? '');
		
		if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			respond(['success' => false, 'message' => 'Email required.'], 400);
		}
		
		global $conn;
		$query = "UPDATE notifications SET is_read = 1 WHERE recipient_email = ? AND is_read = 0";
		$stmt = mysqli_prepare($conn, $query);
		mysqli_stmt_bind_param($stmt, "s", $email);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		
		respond(['success' => true]);
	}

	respond(['success' => false, 'message' => 'Invalid request.'], 400);
	
} catch (Throwable $error) {
	error_log("Rider notification error: " . $error->getMessage());
	respond(['success' => false, 'message' => 'Unable to process request.'], 500);
}
?>
