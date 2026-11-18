<?php
// Load notification configuration if it exists
$configFile = __DIR__ . '/../.notification_config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

// TiDB Cloud Connection Parameters
$host = 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com'; //
$port = 4000; //[cite: 4]
$username = '2BsfQMyrVHEEVBQ.root'; //[cite: 4]
$password = 'VfTEs9RYt4aOwBTW'; //[cite: 4]
$database = 'sys';

// Path to your downloaded TiDB CA certificate (place the .pem file in your project folder)
$caCertPath = __DIR__ . '/isrgrootx1.pem'; 

// Initialize MySQLi and configure SSL required by TiDB Cloud[cite: 4]
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, $caCertPath, NULL, NULL);

// Connect securely while keeping $conn as your active connection object
if (!mysqli_real_connect($conn, $host, $username, $password, $database, $port, NULL, MYSQLI_CLIENT_SSL)) {
    die('Connection Failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");
date_default_timezone_set('Asia/Manila');
