<?php
// Load notification configuration if it exists
$configFile = __DIR__ . '/../.notification_config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $password = getenv('DB_PASSWORD');
    if ($password === false || $password === '') {
        throw new RuntimeException('Missing database password');
    }

    $conn = mysqli_init();
    mysqli_ssl_set($conn, null, null, getenv('DB_SSL_CA') ?: null, null, null);
    mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    mysqli_real_connect($conn, 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com',
        getenv('DB_USER') ?: '2BsfQMyrVHEEVBQ.root', $password, getenv('DB_NAME') ?: 'sys',
        4000, null, MYSQLI_CLIENT_SSL);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (Throwable $error) {
    error_log('Database connection setup failed (code ' . $error->getCode() . ').');
    http_response_code(503);
    exit('Database unavailable.');
}
unset($password);
date_default_timezone_set('Asia/Manila');
