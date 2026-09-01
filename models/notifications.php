<?php
/**
 * Notification Helper Functions
 * Handles creating, sending, and managing notifications with email integration
 */

include 'dbconn.php';

/**
 * Get admin email by admin_id
 */
function getAdminEmailById($admin_id)
{
    global $conn;
    if (!$admin_id || !filter_var($admin_id, FILTER_VALIDATE_INT)) {
        return null;
    }
    $admin = getRecord('admins', 'admin_id = ? AND status = ?', [(int) $admin_id, 'Active']);
    return $admin ? ($admin['email'] ?? null) : null;
}

/**
 * Get admin email by driver_id (find the admin who owns this driver)
 */
function getAdminEmailByDriverId($driver_id)
{
    global $conn;
    if (!$driver_id) return null;
    $driver = getRecord('drivers', 'driver_id = ?', [(int) $driver_id]);
    if (!$driver) return null;
    $adminId = (int) ($driver['admin_id'] ?? 0);
    return $adminId ? getAdminEmailById($adminId) : null;
}

/**
 * Get admin email by tricycle_id (find the admin who owns this tricycle)
 */
function getAdminEmailByTricycleId($tricycle_id)
{
    global $conn;
    if (!$tricycle_id) return null;
    $tricycle = getRecord('tricycles', 'tricycle_id = ?', [(int) $tricycle_id]);
    if (!$tricycle) return null;
    $adminId = (int) ($tricycle['admin_id'] ?? 0);
    return $adminId ? getAdminEmailById($adminId) : null;
}

/**
 * Get admin email by franchise_id (find the admin who owns this franchise)
 */
function getAdminEmailByFranchiseId($franchise_id)
{
    global $conn;
    if (!$franchise_id) return null;
    $franchise = getRecord('franchises', 'franchise_id = ?', [(int) $franchise_id]);
    if (!$franchise) return null;
    $ownerEmail = trim($franchise['owner_email'] ?? '');
    if (!$ownerEmail) return null;
    // Find the admin account with this email
    $admin = getRecord('admins', 'email = ? AND status = ?', [$ownerEmail, 'Active']);
    return $admin ? ($admin['email'] ?? null) : null;
}

/**
 * Get the rider email for a driver attached to a franchise
 */
function getRiderEmailByDriverId($driver_id)
{
    if (!$driver_id) return null;
    $assignment = getRecord('franchise_driver', 'driver_id = ? ORDER BY assignment_id DESC LIMIT 1', [(int) $driver_id]);
    if (!$assignment) return null;
    $franchise = getRecord('franchises', 'franchise_id = ?', [(int) $assignment['franchise_id']]);
    $email = trim($franchise['owner_email'] ?? '');
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

/**
 * Get the rider email for a tricycle attached to a franchise
 */
function getRiderEmailByTricycleId($tricycle_id)
{
    if (!$tricycle_id) return null;
    $assignment = getRecord('franchise_tricycle', 'tricycle_id = ? ORDER BY assignment_id DESC LIMIT 1', [(int) $tricycle_id]);
    if (!$assignment) return null;
    $franchise = getRecord('franchises', 'franchise_id = ?', [(int) $assignment['franchise_id']]);
    $email = trim($franchise['owner_email'] ?? '');
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

/**
 * Send email using PHPMailer
 */
function sendEmail($recipient, $subject, $message, $htmlBody = null)
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid recipient email: $recipient");
        return false;
    }

    // Get credentials from config
    $from = defined('TRICYCLE_MAIL_FROM') ? TRICYCLE_MAIL_FROM : getenv('TRICYCLE_MAIL_FROM');
    $password = defined('TRICYCLE_MAIL_PASSWORD') ? TRICYCLE_MAIL_PASSWORD : getenv('TRICYCLE_MAIL_PASSWORD');
    
    if (!$from || !$password) {
        error_log("Mail credentials not configured. Set TRICYCLE_MAIL_FROM and TRICYCLE_MAIL_PASSWORD");
        return false;
    }

    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid sender email: $from");
        return false;
    }

    $mailName = defined('TRICYCLE_MAIL_NAME') ? TRICYCLE_MAIL_NAME : 'Reina Mercedes Tricycle Franchise';
    $subjectPrefix = defined('TRICYCLE_MAIL_SUBJECT_PREFIX') ? trim(TRICYCLE_MAIL_SUBJECT_PREFIX) : 'Reina Mercedes Tricycle Franchise';
    $cleanSubject = trim(preg_replace('/[\r\n]+/', ' ', (string) $subject));
    $formattedSubject = $subjectPrefix !== '' && stripos($cleanSubject, $subjectPrefix) !== 0
        ? $subjectPrefix . ' - ' . $cleanSubject
        : $cleanSubject;

    $sourcePath = __DIR__ . '/../controllers/PHPMailer-master/src';
    
    // Check if PHPMailer files exist
    if (!file_exists($sourcePath . '/PHPMailer.php')) {
        error_log("PHPMailer not found at $sourcePath");
        return false;
    }

    try {
        foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $file) {
            require_once $sourcePath . '/' . $file;
        }

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $from;
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setFrom($from, $mailName);
        $mail->addReplyTo($from, $mailName);
        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $mail->MessageID = sprintf('<notification-%s@%s>', bin2hex(random_bytes(12)), substr(strrchr($from, '@'), 1));
        $mail->Subject = $formattedSubject;
        $mail->Body = $htmlBody ?: '<h3>' . htmlspecialchars($cleanSubject, ENT_QUOTES, 'UTF-8') . '</h3><p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>';
        $mail->AltBody = $formattedSubject . "\n\n" . trim($message) . "\n\n" . $mailName;
        $mail->send();
        
        error_log("Email sent successfully to $recipient");
        return true;
    } catch (Exception $e) {
        error_log("Email send failed to $recipient: " . $e->getMessage());
        return false;
    }
}

function sendNotificationEmail($recipient, $title, $message, $type)
{
    $mailName = defined('TRICYCLE_MAIL_NAME') ? TRICYCLE_MAIL_NAME : 'Reina Mercedes Tricycle Franchise';
    $cleanType = trim(preg_replace('/[\r\n]+/', ' ', (string) $type));
    $cleanTitle = trim(preg_replace('/[\r\n]+/', ' ', (string) $title));
    $subject = $cleanType !== '' ? $cleanType . ' notification: ' . $cleanTitle : $cleanTitle;
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $safeType = htmlspecialchars($cleanType ?: 'System', ENT_QUOTES, 'UTF-8');
    $safeMailName = htmlspecialchars($mailName, ENT_QUOTES, 'UTF-8');

    $htmlBody = '<div style="margin:0; padding:24px; background:#f4f6f9; font-family:Arial,sans-serif; color:#333;">';
    $htmlBody .= '<div style="max-width:560px; margin:0 auto; padding:24px; background:#fff; border:1px solid #e3e8ef; border-radius:8px;">';
    $htmlBody .= '<p style="margin:0 0 8px; font-size:12px; color:#667085;">' . $safeType . ' notification</p>';
    $htmlBody .= '<h2 style="margin:0 0 16px; color:#16213e; font-size:20px;">' . $safeSubject . '</h2>';
    $htmlBody .= '<p style="margin:0; line-height:1.6;">' . $safeMessage . '</p>';
    $htmlBody .= '<hr style="margin:24px 0; border:0; border-top:1px solid #e3e8ef;">';
    $htmlBody .= '<p style="margin:0; font-size:12px; color:#667085;">' . $safeMailName . '</p>';
    $htmlBody .= '</div></div>';

    return sendEmail($recipient, $subject, $message, $htmlBody);
}

/**
 * Create a notification and optionally send email
 * 
 * @param string $title - Notification title
 * @param string $message - Notification message
 * @param string $type - Type: 'Driver', 'Tricycle', 'Franchise', 'Application', 'Renewal'
 * @param string $severity - Severity: 'info', 'warning', 'urgent'
 * @param string $recipient_email - Email to send notification to (optional)
 * @param int $related_id - ID of related entity (franchise_id, driver_id, etc.)
 * @param string $related_type - Type of related entity
 * 
 * @return int - Notification ID or 0 on failure
 */
function createNotification($title, $message, $type = 'Franchise', $severity = 'info', $recipient_email = null, $related_id = null, $related_type = null)
{
    global $conn;

    if (empty($title) || empty($message)) {
        error_log("createNotification: title and message are required");
        return 0;
    }

    $query = "INSERT INTO notifications (title, message, type, severity, recipient_email, related_id, related_type, is_read, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return 0;
    }

    $types = "sssssss";
    $title_safe = substr($title, 0, 255);
    
    mysqli_stmt_bind_param($stmt, $types, $title_safe, $message, $type, $severity, $recipient_email, $related_id, $related_type);
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Execute failed: " . mysqli_error($conn));
        mysqli_stmt_close($stmt);
        return 0;
    }

    $notif_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Send email if recipient provided
    if ($recipient_email && filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        // Use the same structured template for rider and admin notifications.
        sendNotificationEmail($recipient_email, $title, $message, $type);
    }

    return $notif_id;
}

/**
 * Create franchise application notification (rider submitted application)
 */
function notifyFranchiseApplication($rider_name, $rider_email, $franchise_name, $admin_email = null)
{
    $title = "New Franchise Application from $rider_name";
    $message = "$rider_name has submitted an application for the $franchise_name franchise.";
    
    // Notify admin
    if ($admin_email) {
        createNotification(
            $title,
            $message,
            'Application',
            'warning',
            $admin_email,
            null,
            'franchise_application'
        );
    }

    // Confirm to rider
    createNotification(
        "Application Submitted",
        "Your application for $franchise_name franchise has been submitted successfully. We will review it shortly.",
        'Application',
        'info',
        $rider_email,
        null,
        'franchise_application'
    );
}

/**
 * Create franchise approval notification
 */
function notifyFranchiseApproval($rider_email, $franchise_name, $details = '')
{
    $title = "Franchise Application Approved!";
    $message = "Your application for $franchise_name franchise has been approved. " . $details;
    
    createNotification(
        $title,
        $message,
        'Franchise',
        'info',
        $rider_email,
        null,
        'franchise_approval'
    );
}

/**
 * Create franchise rejection notification
 */
function notifyFranchiseRejection($rider_email, $franchise_name, $reason = '')
{
    $title = "Franchise Application Update";
    $message = "Your application for $franchise_name franchise has been declined. Reason: " . ($reason ?: "Please contact us for more details.");
    
    createNotification(
        $title,
        $message,
        'Franchise',
        'warning',
        $rider_email,
        null,
        'franchise_rejection'
    );
}

/**
 * Create renewal notification
 */
function notifyRenewalDue($franchise_name, $owner_email, $expiry_date, $admin_email = null, $franchise_id = null, $reminder_days = null, $is_admin_alert = false, $owner_name = '')
{
    $daysUntil = $reminder_days ?? (int) ((strtotime($expiry_date) - strtotime(date('Y-m-d'))) / 86400);
    $ownerFranchise = trim($owner_name) !== '' ? "$owner_name's franchise '$franchise_name'" : "Franchise '$franchise_name'";
    
    if ($daysUntil <= 0) {
        $title = "Franchise Renewal: EXPIRED";
        $severity = 'urgent';
        $message = $is_admin_alert
            ? "Franchise '$franchise_name' expired on $expiry_date. Immediate renewal action is required."
            : "$ownerFranchise expired on $expiry_date. Please renew immediately to avoid penalties.";
    } elseif ($daysUntil === 1) {
        $title = "Franchise Renewal: 1-Day Reminder";
        $severity = 'urgent';
        $message = $is_admin_alert
            ? "Franchise '$franchise_name' is about to expire tomorrow ($expiry_date). Please follow up on its renewal."
            : "$ownerFranchise will expire tomorrow ($expiry_date). Please renew now.";
    } else {
        $title = "Franchise Renewal: {$daysUntil}-Day Reminder";
        $severity = 'warning';
        $message = $is_admin_alert
            ? "Franchise '$franchise_name' is about to expire on $expiry_date (in $daysUntil days). Please follow up on its renewal."
            : "$ownerFranchise will expire on $expiry_date (in $daysUntil days). Please plan your renewal.";
    }

    // Notify franchise owner
    createNotification($title, $message, 'Renewal', $severity, $owner_email, $franchise_id, 'franchise_renewal');

    // Notify admin
    if ($admin_email) {
        createNotification(
            $title,
            "$franchise_name - " . $message,
            'Renewal',
            $severity,
            $admin_email,
            $franchise_id,
            'franchise_renewal'
        );
    }
}

/**
 * Create driver status notification
 */
function notifyDriverStatusChange($driver_name, $driver_email, $old_status, $new_status, $admin_email = null, $driver_id = null)
{
    $statusLabels = [
        'Pending' => 'Under Review',
        'For Review' => 'Pending Admin Review',
        'Approved' => 'Approved'
    ];

    $title = "Driver License Status Updated";
    $newStatusLabel = $statusLabels[$new_status] ?? $new_status;
    $message = "$driver_name's driver license application status has been updated to: $newStatusLabel";

    if ($new_status === 'Approved') {
        $severity = 'info';
        $message .= ". Congratulations! $driver_name is now approved to operate.";
    } else {
        $severity = 'warning';
        $message .= ". Please check the system for more details.";
    }

    // Notify driver
    createNotification($title, $message, 'Driver', $severity, $driver_email, $driver_id, 'driver_status');

    // Notify admin
    if ($admin_email) {
        createNotification(
            "$driver_name - $title",
            "$driver_name has been approved as a driver.",
            'Driver',
            'info',
            $admin_email,
            $driver_id,
            'driver_status'
        );
    }
}

/**
 * Create tricycle status or details notification
 */
function notifyTricycleStatusChange($tricycle_id, $tricycle_plate, $old_status, $new_status, $rider_email = null, $admin_email = null)
{
    $new_status = $new_status ?: 'Updated';
    $title = "Tricycle Status: $new_status";
    $severity = in_array($new_status, ['Inactive', 'Expired'], true) ? 'urgent' : ($new_status === 'Pending' ? 'warning' : 'info');
    $message = "Tricycle $tricycle_plate status changed from $old_status to $new_status.";

    if ($rider_email) {
        createNotification($title, $message, 'Tricycle', $severity, $rider_email, $tricycle_id, 'tricycle_status_change');
    }

    if ($admin_email) {
        createNotification("$tricycle_plate - $title", $message, 'Tricycle', $severity, $admin_email, $tricycle_id, 'tricycle_status_change');
    }
}

/**
 * Create a driver details update notification
 */
function notifyDriverDetailsChange($driver_id, $driver_name, $rider_email = null, $admin_email = null)
{
    $message = "$driver_name's driver record has been updated.";
    if ($rider_email) createNotification('Driver Information Updated', $message, 'Driver', 'info', $rider_email, $driver_id, 'driver_details_change');
    if ($admin_email) createNotification("$driver_name - Driver Information Updated", $message, 'Driver', 'info', $admin_email, $driver_id, 'driver_details_change');
}

/**
 * Create a tricycle details or assignment update notification
 */
function notifyTricycleDetailsChange($tricycle_id, $tricycle_plate, $rider_email = null, $admin_email = null)
{
    $message = "Tricycle $tricycle_plate information or assignment has been updated.";
    if ($rider_email) createNotification('Tricycle Information Updated', $message, 'Tricycle', 'info', $rider_email, $tricycle_id, 'tricycle_details_change');
    if ($admin_email) createNotification("$tricycle_plate - Tricycle Information Updated", $message, 'Tricycle', 'info', $admin_email, $tricycle_id, 'tricycle_details_change');
}

/**
 * Create tricycle assignment notification
 */
function notifyTricycleAssignment($driver_name, $driver_email, $tricycle_plate, $franchise_name, $admin_email = null)
{
    $title = "New Tricycle Assignment";
    $message = "You have been assigned tricycle with plate number $tricycle_plate for franchise '$franchise_name'.";

    createNotification($title, $message, 'Tricycle', 'info', $driver_email, null, 'tricycle_assignment');

    if ($admin_email) {
        createNotification(
            $title,
            "$driver_name assigned to $tricycle_plate ($franchise_name)",
            'Tricycle',
            'info',
            $admin_email,
            null,
            'tricycle_assignment'
        );
    }
}

/**
 * Get unread notification count for email
 */
function getUnreadNotificationCount($email)
{
    global $conn;
    $query = "SELECT COUNT(*) as count FROM notifications WHERE recipient_email = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row['count'] ?? 0;
}

/**
 * Get notifications for email
 */
function getNotificationsForEmail($email, $limit = 50)
{
    global $conn;
    $query = "SELECT * FROM notifications WHERE recipient_email = ? ORDER BY created_at DESC LIMIT ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $email, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $notifications;
}

?>
