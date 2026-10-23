<?php

if (!defined('ITOPS')) {
    http_response_code(403);
    exit('Forbidden');
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function safe_next(string $next): string
{
    if (preg_match('/^(index|case|report|accounts|password|bookings|booking_sync)\.php(\?[A-Za-z0-9_\-=&%\.]*)?$/', $next)) {
        return $next;
    }
    return 'index.php';
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_pull(): ?array
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || !is_string($known) || $known === '' || !hash_equals($known, $sent)) {
        flash_set('error', '頁面已過期，請再試一次');
        $self = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        redirect($id > 0 ? $self . '?id=' . $id : $self);
    }
}

function role_label(string $role): string
{
    return $role === 'admin' ? '管理員' : '技術員';
}

function format_when(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '—';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    if (!$dt) {
        return $value;
    }
    return $dt->format('Y-m-d H:i');
}

function status_pill(int $id, string $label): string
{
    return '<span class="pill" data-status="' . $id . '"><span class="dot"></span>' . e($label) . ' (' . $id . ')</span>';
}

function person_html(?string $name, ?string $username = null): string
{
    $name = trim((string) $name);
    if ($name === '') {
        return '<span class="person person-none"><span class="avatar">?</span><span>未指派</span></span>';
    }
    $key = strtolower(trim((string) $username));
    $attr = $key !== '' ? ' data-person="' . e($key) . '"' : '';
    $initial = function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
    return '<span class="person"' . $attr . '><span class="avatar">' . e($initial) . '</span><span>' . e($name) . '</span></span>';
}

function text_or_dash(?string $value, bool $flatten = false): string
{
    $value = trim((string) $value);
    if ($flatten) {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
    }
    if ($value === '') {
        return '<span class="muted">—</span>';
    }
    return e($value);
}

function paragraphs(?string $value, string $empty = '沒有備註'): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '<p class="muted">' . e($empty) . '</p>';
    }
    $html = '';
    foreach (preg_split("/\r\n|\n|\r/", $value) as $line) {
        $html .= '<p>' . e($line === '' ? ' ' : $line) . '</p>';
    }
    return $html;
}

function url_with(array $current, array $overrides = []): string
{
    $params = array_merge($current, $overrides);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            unset($params[$key]);
        }
    }
    if (($params['sort'] ?? null) === 'date') {
        unset($params['sort']);
    }
    if (($params['dir'] ?? null) === 'desc') {
        unset($params['dir']);
    }
    if ((int) ($params['per_page'] ?? 25) === 25) {
        unset($params['per_page']);
    }
    if ((int) ($params['page'] ?? 1) <= 1) {
        unset($params['page']);
    }
    $query = http_build_query($params);
    return 'index.php' . ($query !== '' ? '?' . $query : '');
}

function back_to_list(): string
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $parts = parse_url($ref);
    if (!is_array($parts) || empty($parts['path']) || basename($parts['path']) !== 'index.php') {
        return 'index.php';
    }
    if (!empty($parts['host']) && $parts['host'] !== ($_SERVER['HTTP_HOST'] ?? '')) {
        return 'index.php';
    }

    $allowed = ['status', 'handler', 'type', 'q', 'sort', 'dir', 'per_page', 'page'];
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $raw);
        foreach ($allowed as $key) {
            if (isset($raw[$key]) && (is_string($raw[$key]) || is_int($raw[$key])) && (string) $raw[$key] !== '') {
                $query[$key] = (string) $raw[$key];
            }
        }
    }
    return 'index.php' . ($query ? '?' . http_build_query($query) : '');
}

function form_options(): array
{
    return [
        'statuses' => db()->query('SELECT id, label_en, label_zh FROM statuses ORDER BY sort_order, id')->fetchAll(),
        'types' => db()->query('SELECT id, name, label_zh FROM work_types ORDER BY sort_order, id')->fetchAll(),
        'technicians' => db()->query("SELECT id, username, display_name FROM users WHERE role = 'technician' AND active = 1 ORDER BY sort_order, display_name")->fetchAll(),
    ];
}

function case_from_sql(): string
{
    return 'FROM work_cases c
        JOIN statuses s ON s.id = c.status_id
        JOIN work_types t ON t.id = c.type_id
        LEFT JOIN users h ON h.id = c.handler_id';
}

function like_escape(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

function case_where(array $filters, bool $includeStatus): array
{
    $where = [];
    $params = [];

    if ($includeStatus && !empty($filters['status'])) {
        $where[] = 'c.status_id = ?';
        $params[] = (int) $filters['status'];
    }

    $handler = $filters['handler'] ?? '';
    if ($handler === 'none') {
        $where[] = 'c.handler_id IS NULL';
    } elseif ($handler !== '' && $handler !== null) {
        $where[] = 'c.handler_id = ?';
        $params[] = (int) $handler;
    }

    if (!empty($filters['type'])) {
        $where[] = 'c.type_id = ?';
        $params[] = (int) $filters['type'];
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . like_escape($q) . '%';
        $where[] = "(c.description LIKE ? ESCAPE '\\\\'
            OR c.report_by LIKE ? ESCAPE '\\\\'
            OR IFNULL(c.location, '') LIKE ? ESCAPE '\\\\'
            OR IFNULL(c.remarks, '') LIKE ? ESCAPE '\\\\'
            OR IFNULL(h.display_name, '') LIKE ? ESCAPE '\\\\'
            OR CAST(c.id AS CHAR) LIKE ? ESCAPE '\\\\'
            OR t.name LIKE ? ESCAPE '\\\\'
            OR t.label_zh LIKE ? ESCAPE '\\\\')";
        for ($i = 0; $i < 8; $i++) {
            $params[] = $like;
        }
    }

    return [$where ? implode(' AND ', $where) : '1=1', $params];
}

function fetch_case(int $id): ?array
{
    $sql = 'SELECT c.id, c.status_id, c.case_date, c.type_id, c.location, c.report_by,
                   c.description, c.handler_id, c.remarks, c.created_at, c.updated_at,
                   s.label_zh AS status_zh, s.label_en AS status_en,
                   t.name AS type_name, t.label_zh AS type_zh,
                   h.display_name AS handler_name, h.username AS handler_username,
                   cu.display_name AS created_name, uu.display_name AS updated_name
            FROM work_cases c
            JOIN statuses s ON s.id = c.status_id
            JOIN work_types t ON t.id = c.type_id
            LEFT JOIN users h ON h.id = c.handler_id
            LEFT JOIN users cu ON cu.id = c.created_by
            LEFT JOIN users uu ON uu.id = c.updated_by
            WHERE c.id = ?';
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function to_datetime_local(string $value): string
{
    $value = trim(str_replace(' ', 'T', $value));
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value)) {
        return substr($value, 0, 16);
    }
    return '';
}

function parse_case_date(string $value): ?string
{
    $value = trim(str_replace('T', ' ', $value));
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
        $dt = DateTime::createFromFormat('!' . $format, $value);
        if (!$dt || $dt->format($format) !== $value) {
            continue;
        }
        $errors = DateTime::getLastErrors();
        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            continue;
        }
        $year = (int) $dt->format('Y');
        if ($year < 2000 || $year > 2100) {
            continue;
        }
        return $dt->format('Y-m-d H:i:s');
    }
    return null;
}

function request_case_input(): array
{
    $handler = trim((string) ($_POST['handler_id'] ?? ''));
    if ($handler === '') {
        $handlerId = null;
    } elseif (ctype_digit($handler)) {
        $handlerId = (int) $handler;
    } else {
        $handlerId = -1;
    }

    return [
        'status_id' => (int) ($_POST['status_id'] ?? 0),
        'case_date' => trim((string) ($_POST['case_date'] ?? '')),
        'type_id' => (int) ($_POST['type_id'] ?? 0),
        'location' => trim((string) ($_POST['location'] ?? '')),
        'report_by' => trim((string) ($_POST['report_by'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'handler_id' => $handlerId,
        'remarks' => trim((string) ($_POST['remarks'] ?? '')),
    ];
}

function case_form_defaults(?array $record, ?array $user): array
{
    if ($record) {
        return [
            'status_id' => (int) $record['status_id'],
            'case_date' => to_datetime_local((string) $record['case_date']),
            'type_id' => (int) $record['type_id'],
            'location' => (string) ($record['location'] ?? ''),
            'report_by' => (string) $record['report_by'],
            'description' => (string) $record['description'],
            'handler_id' => $record['handler_id'] !== null ? (int) $record['handler_id'] : null,
            'remarks' => (string) ($record['remarks'] ?? ''),
        ];
    }

    return [
        'status_id' => 1,
        'case_date' => date('Y-m-d\TH:i'),
        'type_id' => 0,
        'location' => '',
        'report_by' => '',
        'description' => '',
        'handler_id' => ($user && $user['role'] === 'technician') ? (int) $user['id'] : null,
        'remarks' => '',
    ];
}

function validate_case(array $input, array $options): array
{
    $errors = [];
    $statusIds = array_map('intval', array_column($options['statuses'], 'id'));
    $typeIds = array_map('intval', array_column($options['types'], 'id'));
    $techIds = array_map('intval', array_column($options['technicians'], 'id'));

    $statusId = (int) ($input['status_id'] ?? 0);
    $typeId = (int) ($input['type_id'] ?? 0);
    $location = trim((string) ($input['location'] ?? ''));
    $reportBy = trim((string) ($input['report_by'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $remarks = trim((string) ($input['remarks'] ?? ''));
    $rawDate = trim((string) ($input['case_date'] ?? ''));
    $handlerId = $input['handler_id'] ?? null;
    if ($handlerId !== null) {
        $handlerId = (int) $handlerId;
    }

    if (!in_array($statusId, $statusIds, true)) {
        $errors['status_id'] = '請選擇狀態';
    }
    $sqlDate = parse_case_date($rawDate);
    if ($sqlDate === null) {
        $errors['case_date'] = '請輸入有效日期時間';
    }
    if (!in_array($typeId, $typeIds, true)) {
        $errors['type_id'] = '請選擇類型';
    }
    if ($location !== '' && text_length($location) > 80) {
        $errors['location'] = '地點最多 80 字';
    }
    if ($reportBy === '') {
        $errors['report_by'] = '請填寫報告人';
    } elseif (text_length($reportBy) > 80) {
        $errors['report_by'] = '報告人最多 80 字';
    }
    if ($description === '') {
        $errors['description'] = '請填寫描述';
    } elseif (text_length($description) > 4000) {
        $errors['description'] = '描述最多 4000 字';
    }
    if ($handlerId !== null && !in_array($handlerId, $techIds, true)) {
        $errors['handler_id'] = '請選擇有效負責人';
    }
    if (text_length($remarks) > 4000) {
        $errors['remarks'] = '備註最多 4000 字';
    }

    return [
        'errors' => $errors,
        'form' => [
            'status_id' => $statusId,
            'case_date' => to_datetime_local($rawDate) !== '' ? to_datetime_local($rawDate) : $rawDate,
            'type_id' => $typeId,
            'location' => $location,
            'report_by' => $reportBy,
            'description' => $description,
            'handler_id' => $handlerId,
            'remarks' => $remarks,
        ],
        'data' => [
            'status_id' => $statusId,
            'case_date' => $sqlDate,
            'type_id' => $typeId,
            'location' => $location === '' ? null : $location,
            'report_by' => $reportBy,
            'description' => $description,
            'handler_id' => $handlerId,
            'remarks' => $remarks === '' ? null : $remarks,
        ],
    ];
}

function create_case(array $data, int $userId): int
{
    $sql = 'INSERT INTO work_cases
        (status_id, case_date, type_id, location, report_by, description, handler_id, remarks, created_by, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = db()->prepare($sql);
    $stmt->execute([
        $data['status_id'],
        $data['case_date'],
        $data['type_id'],
        $data['location'],
        $data['report_by'],
        $data['description'],
        $data['handler_id'],
        $data['remarks'],
        $userId,
        $userId,
    ]);
    return (int) db()->lastInsertId();
}

function update_case(int $id, array $data, int $userId): void
{
    $sql = 'UPDATE work_cases
        SET status_id = ?, case_date = ?, type_id = ?, location = ?, report_by = ?,
            description = ?, handler_id = ?, remarks = ?, updated_by = ?
        WHERE id = ?';
    $stmt = db()->prepare($sql);
    $stmt->execute([
        $data['status_id'],
        $data['case_date'],
        $data['type_id'],
        $data['location'],
        $data['report_by'],
        $data['description'],
        $data['handler_id'],
        $data['remarks'],
        $userId,
        $id,
    ]);
}

function field_error(array $errors, string $key): string
{
    if (empty($errors[$key])) {
        return '';
    }
    return '<p class="field-error" id="' . e($key) . '-error">' . e($errors[$key]) . '</p>';
}

function described_by(array $errors, string $key): string
{
    if (empty($errors[$key])) {
        return '';
    }
    return ' aria-invalid="true" aria-describedby="' . e($key) . '-error"';
}

function render_not_found(): void
{
    http_response_code(404);
    $pageTitle = '找不到個案';
    $activeNav = 'records';
    require __DIR__ . '/header.php';
    echo '<section class="empty-page">';
    echo '<p class="kicker">404</p>';
    echo '<h1>找不到這個個案</h1>';
    echo '<p class="lede">它可能已刪除，或編號不正確。</p>';
    echo '<a class="btn btn-primary" href="index.php">返回記錄</a>';
    echo '</section>';
    require __DIR__ . '/footer.php';
    exit;
}

function password_is_valid(string $password): ?string
{
    if (trim($password) === '') {
        return '請輸入密碼';
    }
    if (text_length($password) < 6) {
        return '密碼至少 6 個字';
    }
    if (strlen($password) > 72) {
        return '密碼太長';
    }
    return null;
}
