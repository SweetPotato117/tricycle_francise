<?php
/**
 * Notification System Diagnostic Script
 * Run this to check what's wrong with notifications
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../models/dbconn.php';

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check 1: Environment variables
$checks = [];
$mailFrom = getenv('TRICYCLE_MAIL_FROM');
$mailPassword = getenv('TRICYCLE_MAIL_PASSWORD');
$adminEmail = getenv('TRICYCLE_ADMIN_EMAIL');

$checks['TRICYCLE_MAIL_FROM'] = [
    'set' => !empty($mailFrom),
    'value' => empty($mailFrom) ? 'NOT SET' : substr($mailFrom, 0, 10) . '***'
];

$checks['TRICYCLE_MAIL_PASSWORD'] = [
    'set' => !empty($mailPassword),
    'value' => empty($mailPassword) ? 'NOT SET' : '***' . substr($mailPassword, -4)
];

$checks['TRICYCLE_ADMIN_EMAIL'] = [
    'set' => !empty($adminEmail),
    'value' => empty($adminEmail) ? 'NOT SET' : $adminEmail
];

$diagnostics['environment_variables'] = $checks;

// Check 2: Database schema - check if notification columns exist
$schemaChecks = [];
$columnsQuery = "DESCRIBE notifications";
$result = mysqli_query($conn, $columnsQuery);
$columns = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[$row['Field']] = $row['Type'];
    }
}

$requiredColumns = ['type', 'severity', 'recipient_email', 'related_id', 'related_type'];
$schemaChecks['required_columns_present'] = true;
$schemaChecks['missing_columns'] = [];

foreach ($requiredColumns as $col) {
    if (!isset($columns[$col])) {
        $schemaChecks['required_columns_present'] = false;
        $schemaChecks['missing_columns'][] = $col;
    }
}

$schemaChecks['all_columns'] = array_keys($columns);
$diagnostics['database_schema'] = $schemaChecks;

// Check 3: PHPMailer files
$phpMailerPath = __DIR__ . '/controllers/PHPMailer-master/src';
$phpMailerChecks = [
    'Exception.php' => file_exists($phpMailerPath . '/Exception.php'),
    'PHPMailer.php' => file_exists($phpMailerPath . '/PHPMailer.php'),
    'SMTP.php' => file_exists($phpMailerPath . '/SMTP.php')
];

$diagnostics['phpmailer_files'] = $phpMailerChecks;
$diagnostics['phpmailer_found'] = array_reduce($phpMailerChecks, fn($a, $b) => $a && $b, true);

// Check 4: Notification records in database
$notifCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications"));
$diagnostics['notification_records'] = [
    'total' => $notifCount['count'] ?? 0,
    'last_5' => []
];

$lastNotifs = mysqli_query($conn, "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
if ($lastNotifs) {
    while ($row = mysqli_fetch_assoc($lastNotifs)) {
        $diagnostics['notification_records']['last_5'][] = [
            'id' => $row['notification_id'],
            'title' => $row['title'],
            'recipient_email' => $row['recipient_email'] ?? 'N/A',
            'created_at' => $row['created_at'],
            'is_read' => $row['is_read']
        ];
    }
}

// Check 5: Try to test email sending
$emailTestCheck = [
    'attempted' => false,
    'success' => false,
    'error' => null
];

if (!empty($mailFrom) && !empty($mailPassword)) {
    $emailTestCheck['attempted'] = true;
    
    try {
        require_once __DIR__ . '/models/notifications.php';
        $testResult = sendEmail(
            'reinamercedes2026@gmail.com',
            'Test Notification - System Check',
            'This is a test email to verify your notification system is working correctly.'
        );
        $emailTestCheck['success'] = $testResult;
        $emailTestCheck['error'] = $testResult ? null : 'Email function returned false';
    } catch (Exception $e) {
        $emailTestCheck['success'] = false;
        $emailTestCheck['error'] = $e->getMessage();
    }
}

$diagnostics['email_test'] = $emailTestCheck;

// Summary
$allGood = 
    $checks['TRICYCLE_MAIL_FROM']['set'] &&
    $checks['TRICYCLE_MAIL_PASSWORD']['set'] &&
    $checks['TRICYCLE_ADMIN_EMAIL']['set'] &&
    $schemaChecks['required_columns_present'] &&
    $diagnostics['phpmailer_found'];

$diagnostics['summary'] = [
    'all_systems_go' => $allGood,
    'status' => $allGood ? 'READY' : 'NEEDS CONFIGURATION',
    'issues' => []
];

if (!$checks['TRICYCLE_MAIL_FROM']['set']) {
    $diagnostics['summary']['issues'][] = 'TRICYCLE_MAIL_FROM environment variable not set';
}
if (!$checks['TRICYCLE_MAIL_PASSWORD']['set']) {
    $diagnostics['summary']['issues'][] = 'TRICYCLE_MAIL_PASSWORD environment variable not set';
}
if (!$checks['TRICYCLE_ADMIN_EMAIL']['set']) {
    $diagnostics['summary']['issues'][] = 'TRICYCLE_ADMIN_EMAIL environment variable not set';
}
if (!$schemaChecks['required_columns_present']) {
    $diagnostics['summary']['issues'][] = 'Missing database columns: ' . implode(', ', $schemaChecks['missing_columns']);
}
if (!$diagnostics['phpmailer_found']) {
    $diagnostics['summary']['issues'][] = 'PHPMailer files not found';
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>
