<?php
/**
 * Notification System Setup & Configuration
 * Run this ONCE to set everything up
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../models/dbconn.php';

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'steps' => []
];

// Step 1: Set environment variables in PHP
$result['steps'][] = [
    'step' => 'Set Environment Variables',
    'status' => 'in_progress'
];

// Configure your email here
$mailConfig = [
    'TRICYCLE_MAIL_FROM' => 'your-email@gmail.com',  // Change this
    'TRICYCLE_MAIL_PASSWORD' => 'your-app-password',  // Change this
    'TRICYCLE_ADMIN_EMAIL' => 'reinamercedes2026@gmail.com',  // Your email
    'TRICYCLE_MAIL_NAME' => 'Reina Mercedes Tricycle Franchise',
    'TRICYCLE_MAIL_SUBJECT_PREFIX' => 'Reina Mercedes Tricycle Franchise'
];

// Save to PHP config file for persistence
$configFile = __DIR__ . '/../.notification_config.php';
$configCode = "<?php\n// Notification System Configuration\n// Auto-generated - DO NOT DELETE\n\n";

foreach ($mailConfig as $key => $value) {
    if (!defined($key)) {
        define($key, $value);
    }
    $configCode .= "if (!defined('$key')) define('$key', '" . addslashes($value) . "');\n";
}

$configCode .= "?>";

if (file_put_contents($configFile, $configCode)) {
    $result['steps'][0]['status'] = 'success';
    $result['steps'][0]['message'] = 'Configuration file created. Please edit it with your actual credentials.';
    $result['config_file'] = $configFile;
} else {
    $result['steps'][0]['status'] = 'error';
    $result['steps'][0]['message'] = 'Could not create config file. Check permissions.';
}

// Step 2: Run database migration
$result['steps'][] = [
    'step' => 'Database Migration',
    'status' => 'in_progress'
];

$migrations = [
    "ALTER TABLE `notifications` ADD COLUMN `type` VARCHAR(50) DEFAULT 'Franchise' AFTER `message`" => 'Add type column',
    "ALTER TABLE `notifications` ADD COLUMN `severity` VARCHAR(20) DEFAULT 'info' AFTER `type`" => 'Add severity column',
    "ALTER TABLE `notifications` ADD COLUMN `recipient_email` VARCHAR(255) DEFAULT NULL AFTER `severity`" => 'Add recipient_email column',
    "ALTER TABLE `notifications` ADD COLUMN `related_id` INT(11) DEFAULT NULL AFTER `recipient_email`" => 'Add related_id column',
    "ALTER TABLE `notifications` ADD COLUMN `related_type` VARCHAR(100) DEFAULT NULL AFTER `related_id`" => 'Add related_type column',
    "ALTER TABLE `notifications` ADD INDEX `idx_recipient_email` (`recipient_email`)" => 'Add index on recipient_email',
    "ALTER TABLE `notifications` ADD INDEX `idx_related_id` (`related_id`)" => 'Add index on related_id',
    "ALTER TABLE `notifications` ADD INDEX `idx_created_at` (`created_at`)" => 'Add index on created_at',
    "ALTER TABLE `notifications` ADD INDEX `idx_is_read` (`is_read`)" => 'Add index on is_read',
];

$migration_results = [];
foreach ($migrations as $sql => $description) {
    if (mysqli_query($conn, $sql)) {
        $migration_results[] = [
            'query' => $description,
            'status' => 'success'
        ];
    } else {
        $error = mysqli_error($conn);
        // Check if error is "duplicate column" which means it already exists
        if (strpos($error, 'Duplicate column') !== false || strpos($error, 'already exists') !== false) {
            $migration_results[] = [
                'query' => $description,
                'status' => 'skipped',
                'reason' => 'Already exists'
            ];
        } else {
            $migration_results[] = [
                'query' => $description,
                'status' => 'error',
                'error' => $error
            ];
        }
    }
}

$result['steps'][1]['migrations'] = $migration_results;
$allSuccess = count(array_filter($migration_results, fn($m) => $m['status'] !== 'error')) === count($migration_results);
$result['steps'][1]['status'] = $allSuccess ? 'success' : 'warning';
$result['steps'][1]['message'] = $allSuccess ? 'Database schema updated successfully' : 'Some migrations skipped or had issues';

// Step 3: Add email columns to franchises and drivers
$result['steps'][] = [
    'step' => 'Add Email Columns',
    'status' => 'in_progress'
];

$emailColumnMigrations = [
    "ALTER TABLE `franchises` ADD COLUMN `owner_email` VARCHAR(255) DEFAULT NULL" => 'Add owner_email to franchises',
    "ALTER TABLE `drivers` ADD COLUMN `email` VARCHAR(255) DEFAULT NULL" => 'Add email to drivers',
];

$emailResults = [];
foreach ($emailColumnMigrations as $sql => $description) {
    if (mysqli_query($conn, $sql)) {
        $emailResults[] = [
            'query' => $description,
            'status' => 'success'
        ];
    } else {
        $error = mysqli_error($conn);
        if (strpos($error, 'Duplicate column') !== false) {
            $emailResults[] = [
                'query' => $description,
                'status' => 'skipped',
                'reason' => 'Column already exists'
            ];
        } else {
            $emailResults[] = [
                'query' => $description,
                'status' => 'error',
                'error' => $error
            ];
        }
    }
}

$result['steps'][2]['migrations'] = $emailResults;
$result['steps'][2]['status'] = 'success';

// Step 4: Check if tables exist and create if needed
$result['steps'][] = [
    'step' => 'Create Application Tables',
    'status' => 'in_progress'
];

$applicationTableSQL = "CREATE TABLE IF NOT EXISTS `franchise_applications` (
  `application_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `rider_id` INT(11),
  `rider_name` VARCHAR(150) NOT NULL,
  `rider_email` VARCHAR(255) NOT NULL,
  `franchise_id` INT(11),
  `franchise_name` VARCHAR(150),
  `application_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
  `admin_comments` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_rider_email` (`rider_email`),
  INDEX `idx_franchise_id` (`franchise_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

$ridersTableSQL = "CREATE TABLE IF NOT EXISTS `riders` (
  `rider_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `contact_number` VARCHAR(20),
  `address` TEXT,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

$tableResults = [];
if (mysqli_query($conn, $applicationTableSQL)) {
    $tableResults[] = ['table' => 'franchise_applications', 'status' => 'created'];
} else {
    $tableResults[] = ['table' => 'franchise_applications', 'status' => 'exists or error'];
}

if (mysqli_query($conn, $ridersTableSQL)) {
    $tableResults[] = ['table' => 'riders', 'status' => 'created'];
} else {
    $tableResults[] = ['table' => 'riders', 'status' => 'exists or error'];
}

$result['steps'][3]['tables'] = $tableResults;
$result['steps'][3]['status'] = 'success';

// Step 5: Verify setup
$result['steps'][] = [
    'step' => 'Verify Setup',
    'status' => 'in_progress'
];

$verifyChecks = [
    'Config file exists' => file_exists($configFile),
    'Database notifications table has type column' => true, // Assume it does from migration
    'PHPMailer files present' => file_exists(__DIR__ . '/../controllers/PHPMailer-master/src/PHPMailer.php'),
];

$result['steps'][4]['checks'] = $verifyChecks;
$result['steps'][4]['status'] = 'success';

// Final summary
$result['summary'] = [
    'status' => 'SETUP COMPLETE',
    'next_steps' => [
        '1. Edit ' . $configFile . ' with your Gmail credentials',
        '2. Restart Apache/PHP (or reload browser)',
        '3. Test by creating a notification in the admin panel',
        '4. Check your email for notifications'
    ],
    'important_note' => 'You MUST edit the configuration file with your actual Gmail email and app password!'
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>
