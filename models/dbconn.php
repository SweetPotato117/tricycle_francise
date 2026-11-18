<?php
$configFile = __DIR__ . '/../.notification_config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $password = getenv('DB_PASSWORD');
    if ($password === false || $password === '') {
        throw new RuntimeException('Missing DB_PASSWORD');
    }

    $caFile = getenv('DB_SSL_CA') ?: __DIR__ . '/isrg-root-x1.pem';
    if (!is_readable($caFile)) {
        throw new RuntimeException('Missing database CA bundle');
    }

    $conn = mysqli_init();
    mysqli_ssl_set($conn, null, null, $caFile, null, null);
    mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    mysqli_real_connect($conn, 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com',
        getenv('DB_USER') ?: '2BsfQMyrVHEEVBQ.root', $password,
        getenv('DB_NAME') ?: 'tricycle_franchise_system', 4000, null, MYSQLI_CLIENT_SSL);
    mysqli_set_charset($conn, 'utf8');
} catch (Throwable $error) {
    $reason = $error instanceof mysqli_sql_exception ? 'MySQL error ' . $error->getCode()
        : ($error instanceof RuntimeException ? $error->getMessage() : get_class($error));
    error_log('Database setup failed: ' . $reason);
    http_response_code(503);
    exit('Database unavailable.');
}
unset($password);
date_default_timezone_set('Asia/Manila');
