<?php
$configFile = __DIR__ . '/../.notification_config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

if (!mysqli_real_connect($conn, 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com', '2BsfQMyrVHEEVBQ.root', 'VfTEs9RYt4aOwBTW', 'sys', 4000, NULL, MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT)) {
    die('Connection Error: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");
date_default_timezone_set('Asia/Manila');
