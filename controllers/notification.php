<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$modelsPath = __DIR__ . '/../models';
set_include_path($modelsPath . PATH_SEPARATOR . get_include_path());
require_once $modelsPath . '/functions.php';
require_once $modelsPath . '/notifications.php';
require_once $modelsPath . '/notification_triggers.php';

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

function requireAdmin()
{
	if (empty($_SESSION['admin_id'])) respond(['success' => false, 'message' => 'Admin login required.'], 401);
}

function formatNotification($row)
{
	return [
		'id' => (int) $row['notification_id'],
		'type' => $row['type'] ?? 'Franchise',
		'severity' => $row['severity'] ?? 'info',
		'title' => $row['title'] ?? '',
		'message' => $row['message'] ?? '',
		'related' => $row['related_type'] ?? ($row['title'] ?? ''),
		'isRead' => (bool) $row['is_read'],
		'createdAt' => str_replace(' ', 'T', $row['created_at']),
		'recipientEmail' => $row['recipient_email'] ?? ''
	];
}

function listNotifications()
{
	try {
		checkAndTriggerRenewalNotifications($_SESSION['admin_email'] ?? null);
	} catch (Throwable $error) {
		error_log('Renewal notification check failed: ' . $error->getMessage());
	}
	
	// Include the configured fallback address so older system notifications remain visible.
	$adminEmail = trim($_SESSION['admin_email'] ?? '');
	if (!$adminEmail || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
		respond(['success' => false, 'message' => 'Admin email not found in session.'], 400);
	}

	$notificationEmails = [$adminEmail];
	$fallbackEmail = defined('TRICYCLE_ADMIN_EMAIL') ? trim(TRICYCLE_ADMIN_EMAIL) : trim(getenv('TRICYCLE_ADMIN_EMAIL') ?: '');
	if (filter_var($fallbackEmail, FILTER_VALIDATE_EMAIL) && strcasecmp($fallbackEmail, $adminEmail) !== 0) {
		$notificationEmails[] = $fallbackEmail;
	}

	$placeholders = implode(', ', array_fill(0, count($notificationEmails), '?'));
	$rows = getAllRecords(
		'notifications',
		"WHERE LOWER(recipient_email) IN ($placeholders) ORDER BY is_read ASC, created_at DESC LIMIT 100",
		$notificationEmails
	);
	respond(['success' => true, 'notifications' => array_map('formatNotification', $rows)]);
}

function listNotificationsForEmail($email)
{
	$notifications = getNotificationsForEmail($email, 50);
	respond(['success' => true, 'notifications' => array_map('formatNotification', $notifications)]);
}

try {
	requireAdmin();
	
	if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		$email = $_GET['email'] ?? null;
		if ($email) {
			listNotificationsForEmail($email);
		} else {
			listNotifications();
		}
	}

	$data = requestData();
	$action = $data['action'] ?? '';
	$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

	if ($action === 'read') {
		if (!$id) respond(['success' => false, 'message' => 'Notification not found.'], 404);
		updateRecord('notifications', ['is_read' => 1], 'notification_id = ?', [$id]);
		respond(['success' => true]);
	}
	
	if ($action === 'mark_all_read') {
		updateRecord('notifications', ['is_read' => 1], 'is_read = ?', [0]);
		respond(['success' => true]);
	}
	
	if ($action === 'delete') {
		if (!$id) respond(['success' => false, 'message' => 'Notification not found.'], 404);
		deleteRecord('notifications', 'notification_id = ?', [$id]);
		respond(['success' => true]);
	}
	
	if ($action === 'create') {
		$title = trim($data['title'] ?? '');
		$message = trim($data['message'] ?? '');
		$type = trim($data['type'] ?? 'Franchise');
		$severity = trim($data['severity'] ?? 'info');
		$recipient_email = trim($data['recipient_email'] ?? '');
		
		if ($title === '' || $message === '') {
			respond(['success' => false, 'message' => 'Title and message are required.'], 422);
		}
		
		$newId = createNotification($title, $message, $type, $severity, $recipient_email ?: null);
		
		if ($newId > 0) {
			respond(['success' => true, 'id' => $newId], 201);
		} else {
			respond(['success' => false, 'message' => 'Failed to create notification.'], 500);
		}
	}
	
	respond(['success' => false, 'message' => 'Invalid request.'], 400);
} catch (Throwable $error) {
	error_log("Notification error: " . $error->getMessage());
	respond(['success' => false, 'message' => 'Unable to process notification request.'], 500);
}
