<?php
/**
 * Notification Triggers for various events
 * Include this file in controllers that need to trigger notifications
 */

require_once __DIR__ . '/notifications.php';

/**
 * Get default admin email (for system-wide events not tied to a specific admin account)
 */
function getAdminEmail()
{
	$sessionEmail = trim($_SESSION['admin_email'] ?? '');
	if (filter_var($sessionEmail, FILTER_VALIDATE_EMAIL)) {
		return $sessionEmail;
	}

	return defined('TRICYCLE_ADMIN_EMAIL') ? TRICYCLE_ADMIN_EMAIL : (getenv('TRICYCLE_ADMIN_EMAIL') ?: 'admin@tricyclefranchise.local');
}

/**
 * Get all active Super Admin email addresses for system-wide submissions.
 */
function getSuperAdminEmails()
{
	$admins = getAllRecords('admins', 'WHERE role = ? AND status = ? AND email IS NOT NULL AND email <> ? ORDER BY admin_id', ['Super Admin', 'Active', '']);
	$emails = array_map(fn($admin) => trim($admin['email'] ?? ''), $admins);
	return array_values(array_unique(array_filter($emails, fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))));
}

/**
 * Notify every active Super Admin about a new rider registration submission.
 */
function notifySuperAdminsOfSubmission($type, $title, $message, $relatedId = null)
{
	foreach (getSuperAdminEmails() as $email) {
		createNotification($title, $message, $type, 'warning', $email, $relatedId, strtolower($type) . '_submission');
	}
}

/**
 * Trigger franchise status change notification
 */
function triggerFranchiseStatusNotification($franchise_id, $franchise_name, $owner_email, $new_status, $admin_email = null)
{
	// If admin_email not provided, try to find the admin who owns this franchise
	if (!$admin_email && $franchise_id) {
		$admin_email = getAdminEmailByFranchiseId($franchise_id);
	}
	
	$statusMessages = [
		'Active' => 'Your franchise is now active.',
		'Expired' => 'Your franchise has expired. Please renew immediately.',
		'Pending Renewal' => 'Your franchise is pending renewal.'
	];

	$message = $statusMessages[$new_status] ?? 'Your franchise status has been updated.';
	
	createNotification(
		"Franchise Status: $new_status",
		$message,
		'Franchise',
		$new_status === 'Expired' ? 'urgent' : 'warning',
		$owner_email,
		$franchise_id,
		'franchise_status_change'
	);

	// Only notify the admin who owns this franchise
	if ($admin_email) {
		createNotification(
			"$franchise_name - Status: $new_status",
			"$franchise_name (ID: $franchise_id) status changed to $new_status",
			'Franchise',
			'info',
			$admin_email,
			$franchise_id,
			'franchise_status_change'
		);
	}
}

/**
 * Trigger driver status change notification
 */
function triggerDriverStatusNotification($driver_id, $driver_name, $driver_email, $old_status, $new_status, $admin_email = null)
{
	// If admin_email not provided, get the admin who owns this driver
	if (!$admin_email && $driver_id) {
		$admin_email = getAdminEmailByDriverId($driver_id);
	}
	$admin_email = $admin_email ?: getAdminEmail();
	
    $rider_email = getRiderEmailByDriverId($driver_id) ?: $driver_email;
    notifyDriverStatusChange($driver_name, $rider_email, $old_status, $new_status, $admin_email, $driver_id);
}

/**
 * Trigger tricycle status change notification
 */
function triggerTricycleStatusNotification($tricycle_id, $tricycle_plate, $old_status, $new_status, $admin_email = null)
{
	if (!$admin_email && $tricycle_id) {
		$admin_email = getAdminEmailByTricycleId($tricycle_id);
	}
	$admin_email = $admin_email ?: getAdminEmail();

	notifyTricycleStatusChange(
		$tricycle_id,
		$tricycle_plate,
		$old_status,
		$new_status,
		getRiderEmailByTricycleId($tricycle_id),
		$admin_email
	);
}

/**
 * Trigger tricycle assignment notification
 */
function triggerTricycleAssignmentNotification($tricycle_id, $driver_id, $driver_name, $driver_email, $tricycle_plate, $franchise_name, $admin_email = null)
{
	// If admin_email not provided, get the admin who owns this tricycle
	if (!$admin_email && $tricycle_id) {
		$admin_email = getAdminEmailByTricycleId($tricycle_id);
	}
	
	notifyTricycleAssignment($driver_name, $driver_email, $tricycle_plate, $franchise_name, $admin_email);
	
	if ($admin_email) {
		createNotification(
			"Tricycle Assignment",
			"Tricycle $tricycle_plate assigned to $driver_name under $franchise_name",
			'Tricycle',
			'info',
			$admin_email,
			$tricycle_id,
			'tricycle_assignment'
		);
	}
}

/**
 * Check and trigger renewal notifications for all franchises
 * Now scoped to send only to the admin who owns each franchise
 */
function checkAndTriggerRenewalNotifications($admin_email = null)
{
	global $conn;
	$reminderDays = [20];
	$superAdminEmails = getSuperAdminEmails();
	markExpiredFranchises();
	
	// Get all franchises and their owner admins
	$query = "SELECT f.franchise_id, f.franchise_name, f.owner_name, f.owner_email, f.expiry_date, f.issue_date,
	          a.email as admin_email
	          FROM franchises f
	          LEFT JOIN admins a ON f.owner_email = a.email AND a.status = 'Active'
	          WHERE f.expiry_date IS NOT NULL
	          AND (DATEDIFF(f.expiry_date, CURDATE()) = 20
	               OR f.expiry_date < CURDATE())
	          ORDER BY f.expiry_date ASC";
	
	$result = mysqli_query($conn, $query);
	
	if (!$result) {
		error_log("Renewal notification query failed: " . mysqli_error($conn));
		return;
	}

	while ($row = mysqli_fetch_assoc($result)) {
		$franchise_id = (int)$row['franchise_id'];
		$franchise_name = $row['franchise_name'];
		$owner_name = $row['owner_name'];
		$owner_email = $row['owner_email'];
		$admin_email_for_franchise = trim($row['admin_email'] ?? '');
		$expiry_date = $row['expiry_date'];
		$daysUntilExpiry = (int) ((strtotime($expiry_date) - strtotime(date('Y-m-d'))) / 86400);

		if ($daysUntilExpiry > 0 && !in_array($daysUntilExpiry, $reminderDays, true)) {
			continue;
		}
		$reminderTitle = $daysUntilExpiry < 0
			? 'Franchise Renewal: EXPIRED'
			: "Franchise Renewal: {$daysUntilExpiry}-Day Reminder";

		// Send notification to franchise owner (rider)
		if ($owner_email) {
			$checkQuery = "SELECT COUNT(*) AS count FROM notifications
			               WHERE related_id = ? AND related_type = 'franchise_renewal'
			               AND recipient_email = ? AND title = ?";
			$checkStmt = mysqli_prepare($conn, $checkQuery);
			mysqli_stmt_bind_param($checkStmt, "iss", $franchise_id, $owner_email, $reminderTitle);
			mysqli_stmt_execute($checkStmt);
			$checkResult = mysqli_stmt_get_result($checkStmt);
			$checkRow = mysqli_fetch_assoc($checkResult);
			mysqli_stmt_close($checkStmt);

			if ((int) $checkRow['count'] === 0) {
				notifyRenewalDue($franchise_name, $owner_email, $expiry_date, null, $franchise_id, $daysUntilExpiry, false, $owner_name);
			}
		}

		// Send notification only to the admin who owns this franchise
		if ($admin_email_for_franchise && filter_var($admin_email_for_franchise, FILTER_VALIDATE_EMAIL)) {
			$checkQuery = "SELECT COUNT(*) AS count FROM notifications
			               WHERE related_id = ? AND related_type = 'franchise_renewal'
			               AND recipient_email = ? AND title = ?";
			$checkStmt = mysqli_prepare($conn, $checkQuery);
			mysqli_stmt_bind_param($checkStmt, "iss", $franchise_id, $admin_email_for_franchise, $reminderTitle);
			mysqli_stmt_execute($checkStmt);
			$checkResult = mysqli_stmt_get_result($checkStmt);
			$checkRow = mysqli_fetch_assoc($checkResult);
			mysqli_stmt_close($checkStmt);

			if ((int) $checkRow['count'] === 0) {
				notifyRenewalDue($franchise_name, $admin_email_for_franchise, $expiry_date, null, $franchise_id, $daysUntilExpiry, true, $owner_name);
			}
		}

		foreach ($superAdminEmails as $superAdminEmail) {
			$checkQuery = "SELECT COUNT(*) AS count FROM notifications
			               WHERE related_id = ? AND related_type = 'franchise_renewal'
			               AND recipient_email = ? AND title = ?";
			$checkStmt = mysqli_prepare($conn, $checkQuery);
			mysqli_stmt_bind_param($checkStmt, "iss", $franchise_id, $superAdminEmail, $reminderTitle);
			mysqli_stmt_execute($checkStmt);
			$checkResult = mysqli_stmt_get_result($checkStmt);
			$checkRow = mysqli_fetch_assoc($checkResult);
			mysqli_stmt_close($checkStmt);

			if ((int) $checkRow['count'] === 0) {
				notifyRenewalDue($franchise_name, $superAdminEmail, $expiry_date, null, $franchise_id, $daysUntilExpiry, true, $owner_name);
			}
		}
	}

	mysqli_free_result($result);
}

/**
 * Keep the superadmin franchise status aligned with the expiry date.
 */
function markExpiredFranchises()
{
	global $conn;
	$query = "UPDATE franchises
	          SET renewal_status = 'Expired'
	          WHERE expiry_date IS NOT NULL
	          AND expiry_date < CURDATE()
	          AND renewal_status <> 'Expired'";
	if (!mysqli_query($conn, $query)) {
		error_log('Unable to mark expired franchises: ' . mysqli_error($conn));
	}
}

/**
 * Manually trigger renewal check (can be called from a cron job or admin endpoint)
 */
function runRenewalNotificationCheck($admin_email = null)
{
	checkAndTriggerRenewalNotifications($admin_email);
	return ['success' => true, 'message' => 'Renewal notifications checked'];
}

?>
