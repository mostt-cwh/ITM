<?php

if (!defined('ITOPS')) {
    http_response_code(403);
    exit('Forbidden');
}

function booking_sync_token(): string
{
    $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
    $stmt->execute(['booking_sync_token']);
    $token = $stmt->fetchColumn();
    if (is_string($token) && preg_match('/^[a-f0-9]{32}$/', $token)) {
        return $token;
    }

    $token = bin2hex(random_bytes(16));
    $save = db()->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $save->execute(['booking_sync_token', $token]);
    return $token;
}

function booking_rotate_token(): string
{
    $token = bin2hex(random_bytes(16));
    $save = db()->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $save->execute(['booking_sync_token', $token]);
    return $token;
}

function booking_norm_header(string $header): string
{
    $header = function_exists('mb_strtolower') ? mb_strtolower(trim($header), 'UTF-8') : strtolower(trim($header));
    $header = preg_replace('/[\s_\-：:()（）\[\]\/\\\\]/u', '', $header);
    return (string) $header;
}

function booking_header_map(array $headers): array
{
    $aliases = [
        'room' => ['場地', '房間', '設施', '地點', '資源', 'room', 'facility', 'venue', 'location', '預訂設施', '項目'],
        'date' => ['日期', '預訂日期', '借用日期', 'date', 'bookingdate'],
        'start' => ['開始', '開始時間', '由', 'start', 'from', '開始時段'],
        'end' => ['結束', '結束時間', '至', 'end', 'to', '結束時段'],
        'slot' => ['時段', '時間', 'time', 'period', '使用時間'],
        'booker' => ['申請人', '預訂人', '借用人', '用戶', '使用者', '老師', 'booker', 'requestedby', 'user', '姓名'],
        'purpose' => ['用途', '活動', '原因', 'purpose', 'event', '活動名稱', '事由'],
        'status' => ['狀態', 'status', '批核', '審批'],
        'remarks' => ['備註', 'remarks', 'note', 'notes', '說明'],
        'ref' => ['編號', '預訂編號', 'bookingno', 'ref', '紀錄編號', 'bookingid'],
    ];
    $normalized = [];
    foreach ($aliases as $field => $names) {
        foreach ($names as $name) {
            $normalized[booking_norm_header($name)] = $field;
        }
    }

    $map = [];
    foreach ($headers as $index => $header) {
        $key = booking_norm_header((string) $header);
        if ($key !== '' && isset($normalized[$key]) && !isset($map[$normalized[$key]])) {
            $map[$normalized[$key]] = (int) $index;
        }
    }
    return $map;
}

function booking_cell(array $row, array $map, string $field): string
{
    if (!isset($map[$field])) {
        return '';
    }
    return trim((string) ($row[$map[$field]] ?? ''));
}

function booking_parse_time(string $value): ?string
{
    $value = trim($value);
    if (preg_match('/^(上午|下午|am|pm)\s*(\d{1,2}):(\d{2})/iu', $value, $match)) {
        $hour = (int) $match[2];
        $marker = function_exists('mb_strtolower') ? mb_strtolower($match[1], 'UTF-8') : strtolower($match[1]);
        $pm = $marker === '下午' || $marker === 'pm';
        if ($pm && $hour < 12) {
            $hour += 12;
        }
        if (!$pm && $hour === 12) {
            $hour = 0;
        }
        if ($hour > 23) {
            return null;
        }
        return sprintf('%02d:%02d:00', $hour, (int) $match[3]);
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $match)) {
        $hour = (int) $match[1];
        $minute = (int) $match[2];
        $second = isset($match[3]) ? (int) $match[3] : 0;
        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }
        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }
    return null;
}

function booking_parse_date(string $value): ?string
{
    $value = trim($value);
    $value = str_replace(['年', '月', '/', '.'], ['-', '-', '-', '-'], $value);
    $value = str_replace('日', '', $value);
    $value = trim((string) preg_replace('/\s+/', ' ', $value));
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $match)) {
        return booking_valid_date((int) $match[1], (int) $match[2], (int) $match[3]);
    }
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $value, $match)) {
        $first = (int) $match[1];
        $second = (int) $match[2];
        $year = (int) $match[3];
        if ($first > 12) {
            return booking_valid_date($year, $second, $first);
        }
        if ($second > 12) {
            return booking_valid_date($year, $first, $second);
        }
        return booking_valid_date($year, $second, $first);
    }
    return null;
}

function booking_valid_date(int $year, int $month, int $day): ?string
{
    if ($year < 2000 || $year > 2100 || !checkdate($month, $day, $year)) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function booking_parse_slot(string $value): array
{
    $value = trim(str_replace(['～', '至', '–', '—', '~'], '-', $value));
    $meridian = null;
    if (preg_match('/^(上午|下午|am|pm)/iu', $value, $mark)) {
        $marker = function_exists('mb_strtolower') ? mb_strtolower($mark[1], 'UTF-8') : strtolower($mark[1]);
        $meridian = ($marker === '下午' || $marker === 'pm') ? 'pm' : 'am';
    }
    $parts = preg_split('/\s*-\s*/', $value, 2);
    $start = booking_parse_time((string) ($parts[0] ?? ''));
    $endRaw = isset($parts[1]) ? trim($parts[1]) : '';
    $end = $endRaw !== '' ? booking_parse_time($endRaw) : null;
    if ($meridian === 'pm' && $start !== null && $end !== null && !preg_match('/^(上午|下午|am|pm)/iu', $endRaw)) {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $endRaw, $hm)) {
            $hour = (int) $hm[1];
            if ($hour < 12) {
                $promoted = sprintf('%02d:%02d:00', $hour + 12, (int) $hm[2]);
                if (strcmp($promoted, $start) > 0) {
                    $end = $promoted;
                }
            }
        }
    }
    return [$start, $end];
}

function booking_combine(string $date, string $time): string
{
    return $date . ' ' . $time;
}

function booking_rows_from_table(array $headers, array $rows): array
{
    $map = booking_header_map($headers);
    if (!isset($map['room']) || (!isset($map['date']) && !isset($map['start']) && !isset($map['slot']))) {
        return ['items' => [], 'skipped' => count($rows), 'error' => '認不出欄位。請保留標題列，例如日期、場地、時間、申請人、用途。'];
    }

    $items = [];
    $skipped = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            $skipped++;
            continue;
        }
        $room = booking_clip(booking_cell($row, $map, 'room'), 120);
        $booker = booking_clip(booking_cell($row, $map, 'booker'), 80);
        $purpose = booking_clip(booking_cell($row, $map, 'purpose'), 255);
        $status = booking_clip(booking_cell($row, $map, 'status'), 40);
        $remarks = booking_clip(booking_cell($row, $map, 'remarks'), 2000);
        $ref = booking_clip(booking_cell($row, $map, 'ref'), 160);
        $dateText = booking_cell($row, $map, 'date');
        $startText = booking_cell($row, $map, 'start');
        $endText = booking_cell($row, $map, 'end');
        $slotText = booking_cell($row, $map, 'slot');

        if ($room === '' && $dateText === '' && $startText === '' && $slotText === '' && $purpose === '') {
            continue;
        }

        $starts = null;
        $ends = null;
        $date = $dateText !== '' ? booking_parse_date($dateText) : null;
        $startTime = $startText !== '' ? booking_parse_time($startText) : null;
        $endTime = $endText !== '' ? booking_parse_time($endText) : null;

        if ($startTime === null && $startText !== '') {
            $starts = booking_parse_datetime_text($startText);
        }
        if ($endTime === null && $endText !== '') {
            $ends = booking_parse_datetime_text($endText);
        }
        if ($slotText !== '') {
            [$slotStart, $slotEnd] = booking_parse_slot($slotText);
            if ($startTime === null) {
                $startTime = $slotStart;
            }
            if ($endTime === null && $ends === null) {
                $endTime = $slotEnd;
            }
        }
        if ($starts === null && $date !== null && $startTime !== null) {
            $starts = booking_combine($date, $startTime);
        }
        if ($starts === null && $date !== null && $startTime === null && $startText === '' && $slotText === '') {
            $starts = $date . ' 00:00:00';
        }
        if ($ends === null && $endTime !== null) {
            $endDate = $date;
            if ($endDate === null && $starts !== null) {
                $endDate = substr($starts, 0, 10);
            }
            if ($endDate !== null) {
                $ends = booking_combine($endDate, $endTime);
            }
        }
        if ($starts !== null && $ends !== null && strcmp($ends, $starts) <= 0) {
            $endAt = DateTime::createFromFormat('Y-m-d H:i:s', $ends);
            if ($endAt) {
                $endAt->modify('+1 day');
                $ends = $endAt->format('Y-m-d H:i:s');
            }
        }

        if ($room === '' || $starts === null) {
            $skipped++;
            continue;
        }

        $source = $ref !== ''
            ? 'eclass:' . $ref
            : 'row:' . sha1($room . '|' . $starts . '|' . (string) $ends . '|' . $booker . '|' . $purpose);

        $items[] = [
            'source_key' => $source,
            'room' => $room,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'booker' => $booker !== '' ? $booker : null,
            'purpose' => $purpose !== '' ? $purpose : null,
            'status_label' => $status !== '' ? $status : null,
            'remarks' => $remarks !== '' ? $remarks : null,
        ];
    }

    return ['items' => $items, 'skipped' => $skipped, 'error' => null];
}

function booking_parse_datetime_text(string $value): ?string
{
    $value = trim(str_replace('T', ' ', $value));
    if (preg_match('/^(\d{4}[\-\/\.年]\d{1,2}[\-\/\.月]\d{1,2}日?)\s+(.+)$/u', $value, $match)) {
        $date = booking_parse_date($match[1]);
        $time = booking_parse_time($match[2]);
        if ($date && $time) {
            return booking_combine($date, $time);
        }
    }
    $date = booking_parse_date($value);
    return $date ? $date . ' 00:00:00' : null;
}

function booking_clip(string $value, int $max): string
{
    $value = trim((string) preg_replace('/\s+/u', ' ', $value));
    if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $max) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    if (strlen($value) > $max) {
        return substr($value, 0, $max);
    }
    return $value;
}

function booking_text_to_utf8(string $text): string
{
    if (strncmp($text, "\xFF\xFE", 2) === 0) {
        $converted = function_exists('mb_convert_encoding') ? mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16LE') : $text;
        return is_string($converted) ? $converted : $text;
    }
    if (strncmp($text, "\xFE\xFF", 2) === 0) {
        $converted = function_exists('mb_convert_encoding') ? mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16BE') : $text;
        return is_string($converted) ? $converted : $text;
    }
    if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
        $text = substr($text, 3);
    }
    if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8') && function_exists('mb_convert_encoding')) {
        $converted = mb_convert_encoding($text, 'UTF-8', 'BIG-5');
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
    }
    return $text;
}

function booking_table_from_text(string $text): array
{
    $text = booking_text_to_utf8($text);
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $lines = preg_split('/\r\n|\n|\r/', trim((string) $text));
    $rows = [];
    if (!$lines || trim((string) $lines[0]) === '') {
        return [[], []];
    }
    $delimiter = substr_count((string) $lines[0], "\t") > 0 ? "\t" : ',';
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $rows[] = $delimiter === "\t" ? explode("\t", $line) : str_getcsv($line);
    }
    if (!$rows) {
        return [[], []];
    }
    $headers = array_shift($rows);
    return [$headers, $rows];
}

function booking_import_items(array $items, bool $replace, ?int $userId, string $note): array
{
    if (!$items) {
        return ['ok' => false, 'message' => '沒有可同步的訂房記錄'];
    }
    if (count($items) > 500) {
        return ['ok' => false, 'message' => '一次最多同步 500 筆'];
    }

    $keys = [];
    $dates = [];
    foreach ($items as $item) {
        $keys[] = $item['source_key'];
        $dates[substr($item['starts_at'], 0, 10)] = true;
    }
    $keys = array_values(array_unique($keys));

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $existing = [];
        $check = $pdo->prepare('SELECT source_key FROM room_bookings WHERE source_key = ?');
        foreach ($keys as $key) {
            $check->execute([$key]);
            if ($check->fetchColumn()) {
                $existing[$key] = true;
            }
        }

        $save = $pdo->prepare('INSERT INTO room_bookings
            (source_key, room, starts_at, ends_at, booker, purpose, status_label, remarks, synced_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
              room = VALUES(room),
              starts_at = VALUES(starts_at),
              ends_at = VALUES(ends_at),
              booker = VALUES(booker),
              purpose = VALUES(purpose),
              status_label = VALUES(status_label),
              remarks = VALUES(remarks),
              synced_at = NOW()');

        $inserted = 0;
        $updated = 0;
        foreach ($items as $item) {
            $save->execute([
                $item['source_key'],
                $item['room'],
                $item['starts_at'],
                $item['ends_at'],
                $item['booker'],
                $item['purpose'],
                $item['status_label'],
                $item['remarks'],
            ]);
            if (isset($existing[$item['source_key']])) {
                $updated++;
            } else {
                $inserted++;
                $existing[$item['source_key']] = true;
            }
        }

        $removed = 0;
        if ($replace && $dates) {
            $dateList = array_keys($dates);
            $dateMarks = implode(',', array_fill(0, count($dateList), '?'));
            $keyMarks = implode(',', array_fill(0, count($keys), '?'));
            $delete = $pdo->prepare('DELETE FROM room_bookings WHERE DATE(starts_at) IN (' . $dateMarks . ') AND source_key NOT IN (' . $keyMarks . ')');
            $delete->execute(array_merge($dateList, $keys));
            $removed = $delete->rowCount();
        }

        $log = $pdo->prepare('INSERT INTO booking_syncs (synced_by, row_count, removed_count, note) VALUES (?, ?, ?, ?)');
        $log->execute([$userId, count($items), $removed, booking_clip($note, 200)]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'ok' => true,
        'inserted' => $inserted,
        'updated' => $updated,
        'removed' => $removed,
        'message' => '已同步 ' . count($items) . ' 筆（新增 ' . $inserted . '，更新 ' . $updated . '，移除 ' . $removed . '）',
    ];
}

function booking_grid_from_push(array $data): array
{
    if (!isset($data['headers'], $data['rows']) || !is_array($data['headers']) || !is_array($data['rows'])) {
        return ['error' => '缺少表格'];
    }
    if (count($data['headers']) > 40 || count($data['rows']) > 500) {
        return ['error' => '表格太大'];
    }
    $headers = [];
    foreach ($data['headers'] as $header) {
        if (is_array($header) || is_object($header)) {
            return ['error' => '標題格式不正確'];
        }
        $headers[] = (string) $header;
    }
    $rows = [];
    foreach ($data['rows'] as $row) {
        if (!is_array($row) || count($row) > 40) {
            return ['error' => '列格式不正確'];
        }
        $cells = [];
        foreach ($row as $cell) {
            if (is_array($cell) || is_object($cell)) {
                return ['error' => '儲存格格式不正確'];
            }
            $cells[] = (string) $cell;
        }
        $rows[] = $cells;
    }
    return ['headers' => $headers, 'rows' => $rows, 'error' => null];
}

function booking_import_table(array $headers, array $rows, bool $replace, ?int $userId, string $note): array
{
    $parsed = booking_rows_from_table($headers, $rows);
    if ($parsed['error']) {
        return ['ok' => false, 'message' => $parsed['error']];
    }
    $result = booking_import_items($parsed['items'], $replace, $userId, $note);
    if ($result['ok'] && $parsed['skipped'] > 0) {
        $result['message'] .= '，略過 ' . $parsed['skipped'] . ' 行';
    }
    return $result;
}

function booking_latest_sync(): ?array
{
    $sql = 'SELECT s.created_at, s.row_count, s.removed_count, s.note, u.display_name
        FROM booking_syncs s
        LEFT JOIN users u ON u.id = s.synced_by
        ORDER BY s.id DESC
        LIMIT 1';
    $row = db()->query($sql)->fetch();
    return $row ?: null;
}

function booking_when(array $row): string
{
    $start = format_when($row['starts_at']);
    if (empty($row['ends_at'])) {
        return $start;
    }
    $end = format_when($row['ends_at']);
    $startDay = substr((string) $row['starts_at'], 0, 10);
    $endDay = substr((string) $row['ends_at'], 0, 10);
    if ($startDay === $endDay) {
        return $start . '–' . substr($end, 11);
    }
    return $start . ' – ' . $end;
}

function booking_status_code(string $status): string
{
    if (preg_match('/批核|批准|approve|confirm/i', $status)) {
        return '4';
    }
    if (preg_match('/待|pending|wait/iu', $status)) {
        return '1';
    }
    if (preg_match('/拒|reject|取消|cancel/iu', $status)) {
        return '3';
    }
    return '2';
}

function url_with_path(string $path, array $current, array $overrides = []): string
{
    $params = array_merge($current, $overrides);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
            continue;
        }
        if ($key === 'range' && $value === 'upcoming') {
            unset($params[$key]);
        }
    }
    $query = http_build_query($params);
    return $path . ($query !== '' ? '?' . $query : '');
}

function booking_push_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim($dir, '/');
    return ($https ? 'https' : 'http') . '://' . $host . ($dir !== '' ? $dir : '') . '/booking_push.php';
}

function booking_bookmarklet(string $pushUrl, string $token): string
{
    $url = json_encode($pushUrl, JSON_UNESCAPED_SLASHES);
    $key = json_encode($token);
    $script = '(function(){'
        . 'var url=' . $url . ',token=' . $key . ',best=null,score=-1;'
        . '[].forEach.call(document.querySelectorAll("table"),function(tb){'
        . 'var text=tb.innerText||"",hits=0;'
        . '["日期","場地","房間","時間","申請","用途","狀態","預訂","設施"].forEach(function(w){if(text.indexOf(w)>=0)hits++;});'
        . 'var s=hits*1000+tb.querySelectorAll("tr").length;if(s>score){score=s;best=tb;}});'
        . 'if(!best||score<1000){alert("這頁找不到訂房表格。請打開 eClass 訂房列表，或改為複製表格貼上工作系統。");return;}'
        . 'var clean=[];[].forEach.call(best.querySelectorAll("tr"),function(tr){'
        . 'var cells=[].map.call(tr.querySelectorAll("th,td"),function(td){return (td.innerText||"").replace(/\\s+/g," ").trim();});'
        . 'if(cells.join("")!=="")clean.push(cells);});'
        . 'if(clean.length<2){alert("表格沒有可同步的資料。");return;}'
        . 'var headers=clean.shift();'
        . 'fetch(url,{method:"POST",mode:"cors",headers:{"Content-Type":"application/json"},body:JSON.stringify({token:token,headers:headers,rows:clean,replace:true})})'
        . '.then(function(r){return r.json();})'
        . '.then(function(d){alert(d.message||"已同步");if(d.ok){location.href=url.replace("booking_push.php","bookings.php");}})'
        . '.catch(function(){alert("無法連接工作系統。請確認書籤是在工作系統的同步頁產生。");});'
        . '})();';
    return 'javascript:' . $script;
}
