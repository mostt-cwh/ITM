<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/bookings.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

set_exception_handler(function (Throwable $e): void {
    error_log('[itops] ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode(['ok' => false, 'message' => '系統發生錯誤，請稍後再試。'], JSON_UNESCAPED_UNICODE);
});

function booking_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    booking_json(405, ['ok' => false, 'message' => '只接受 POST']);
}

$raw = file_get_contents('php://input', false, null, 0, 1048577);
if (!is_string($raw) || strlen($raw) > 1048576) {
    booking_json(413, ['ok' => false, 'message' => '資料太大']);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    booking_json(400, ['ok' => false, 'message' => '不是有效的 JSON']);
}

$token = isset($data['token']) && is_string($data['token']) ? $data['token'] : '';
if (!preg_match('/^[a-f0-9]{32}$/', $token) || !hash_equals(booking_sync_token(), $token)) {
    booking_json(401, ['ok' => false, 'message' => '同步碼不正確']);
}

$grid = booking_grid_from_push($data);
if (!empty($grid['error'])) {
    booking_json(400, ['ok' => false, 'message' => $grid['error']]);
}

$replace = !array_key_exists('replace', $data) || $data['replace'] === true || $data['replace'] === 1 || $data['replace'] === '1';
$result = booking_import_table($grid['headers'], $grid['rows'], $replace, null, 'eclass-bookmarklet');
booking_json($result['ok'] ? 200 : 422, [
    'ok' => $result['ok'],
    'message' => $result['message'],
]);
