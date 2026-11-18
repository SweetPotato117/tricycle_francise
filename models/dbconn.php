<?php
// Load notification configuration if it exists
$configFile = __DIR__ . '/../.notification_config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

$conn = mysqli_connect('gateway01.ap-northeast-1.prod.aws.tidbcloud.com', '2BsfQMyrVHEEVBQ.root', 'VfTEs9RYt4aOwBTW', 'sys', 4000) or die('Connection Failed');
mysqli_set_charset($conn, "utf8");
date_default_timezone_set('Asia/Manila');
