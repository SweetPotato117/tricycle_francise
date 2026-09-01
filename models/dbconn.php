<?php
// Load notification configuration if it exists
$configFile = __DIR__ . '/../.notification_config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

$conn = mysqli_connect('localhost', 'root', '', 'tricycle_franchise_system') or die('Connection Failed');
mysqli_set_charset($conn, "utf8");
date_default_timezone_set('Asia/Manila');