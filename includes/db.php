<?php

if (!defined('ITOPS')) {
    http_response_code(403);
    exit('Forbidden');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (PDOException $e) {
        error_log('[itops] ' . $e->getMessage());
        render_setup_page($e);
        exit;
    }

    return $pdo;
}

function render_setup_page(PDOException $e): void
{
    http_response_code(500);
    $detail = $e->getMessage();
    $hint = '請確認 MySQL 已啟動，並依序匯入 sql/schema.sql 與 sql/sample_data.sql。連線帳戶在 config.php。';

    if (stripos($detail, 'Unknown database') !== false || strpos($detail, '1049') !== false) {
        $hint = '找不到資料庫 it_ops。請先匯入 sql/schema.sql。';
    } elseif (stripos($detail, 'Access denied') !== false || strpos($detail, '1045') !== false) {
        $hint = '資料庫帳戶或密碼不正確。請修改 config.php 的 DB_USER 與 DB_PASS。';
    } elseif (stripos($detail, 'Connection refused') !== false || strpos($detail, '2002') !== false) {
        $hint = '無法連線 MySQL。請先啟動資料庫服務。';
    } elseif (stripos($detail, 'Base table or view not found') !== false || strpos($detail, '42S02') !== false) {
        $hint = '資料表尚未建立。請匯入 sql/schema.sql，再匯入 sql/sample_data.sql。';
    }

    $hint = htmlspecialchars($hint, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="zh-Hant"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>需要建立資料庫 · I.T. OPS</title>';
    echo '<link rel="stylesheet" href="assets/css/style.css">';
    echo '</head><body><main class="wrap setup">';
    echo '<p class="kicker">DATABASE</p><h1>需要先建立資料庫</h1>';
    echo '<p class="lede">' . $hint . '</p>';
    echo '<ol class="steps"><li>啟動 MySQL 或 MariaDB。</li>';
    echo '<li>匯入 <code>sql/schema.sql</code>，建立資料庫、資料表與四個帳戶。</li>';
    echo '<li>匯入 <code>sql/sample_data.sql</code>，寫入示範個案。</li>';
    echo '<li>如果資料庫帳戶不是 root、密碼不是空白，請修改 <code>config.php</code>。</li></ol>';
    echo '<p><a class="btn btn-primary" href="index.php">重新整理</a></p>';
    echo '</main></body></html>';
    exit;
}
