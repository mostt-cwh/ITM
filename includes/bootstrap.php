<?php

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

define('ITOPS', true);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Hong_Kong');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    $cookieDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $cookiePath = rtrim($cookieDir, '/') . '/';
    session_name('ITOPSSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath === '/' ? '/' : $cookiePath,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    session_start();
}

$config = dirname(__DIR__) . '/config.php';
if (!is_file($config)) {
    http_response_code(500);
    exit('找不到 config.php');
}

require $config;

foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $required) {
    if (!defined($required)) {
        http_response_code(500);
        exit('config.php 設定不完整');
    }
}

require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/auth.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=UTF-8');

set_exception_handler(function (Throwable $e): void {
    error_log('[itops] ' . $e->getMessage());
    if ($e instanceof PDOException) {
        render_setup_page($e);
        return;
    }
    http_response_code(500);
    echo '系統發生錯誤，請稍後再試。';
});
