<?php
// Dispatch only application scripts inside the existing public PHP directories.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!is_string($path) || !preg_match('~^/(admin|controllers|rider)/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\\.php$~D', $path, $match)) {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$file = realpath($root . $path);
if ($file === false || $file !== $root . $path || !is_file($file)) {
    http_response_code(404);
    exit;
}

$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $path;
$_SERVER['SCRIPT_FILENAME'] = $file;
chdir(dirname($file));
require $file;
