<?php
// Load notification configuration if it exists
$configFile = __DIR__ . '/../.notification_config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

$password = getenv('DB_PASSWORD');
$database = getenv('DB_NAME');
if ($password === false || $password === '' || $database === false || $database === '') {
    error_log('Database configuration is missing DB_PASSWORD or DB_NAME.');
    http_response_code(503);
    exit('Database unavailable.');
}

$host = getenv('DB_HOST') ?: 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com';
$user = getenv('DB_USER') ?: '2BsfQMyrVHEEVBQ.root';
$port = (int) (getenv('DB_PORT') ?: 4000);
$caFile = getenv('DB_SSL_CA') ?: null;

try {
    $conn = mysqli_init();
    if (!$conn || !mysqli_ssl_set($conn, null, null, $caFile, null, null)
        || !mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true)
        || !mysqli_real_connect($conn, $host, $user, $password, $database, $port, null, MYSQLI_CLIENT_SSL)
        || !mysqli_set_charset($conn, 'utf8')) {
        throw new RuntimeException('Database setup failed.');
    }
} catch (Throwable $error) {
    error_log('Database setup failed (' . get_class($error) . ', code ' . $error->getCode() . ').');
    http_response_code(503);
    exit('Database unavailable.');
}

unset($password);
date_default_timezone_set('Asia/Manila');
