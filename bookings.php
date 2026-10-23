<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/bookings.php';

$range = (string) ($_GET['range'] ?? 'upcoming');
if (!in_array($range, ['upcoming', 'today', 'week', 'all'], true)) {
    $range = 'upcoming';
}
$room = trim((string) ($_GET['room'] ?? ''));
if (text_length($room) > 120) {
    $room = function_exists('mb_substr') ? mb_substr($room, 0, 120, 'UTF-8') : substr($room, 0, 120);
}
$status = trim((string) ($_GET['status'] ?? ''));
if (text_length($status) > 40) {
    $status = function_exists('mb_substr') ? mb_substr($status, 0, 40, 'UTF-8') : substr($status, 0, 40);
}
$q = trim((string) ($_GET['q'] ?? ''));
if (text_length($q) > 100) {
    $q = function_exists('mb_substr') ? mb_substr($q, 0, 100, 'UTF-8') : substr($q, 0, 100);
}

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');
$where = [];
$params = [];
if ($range === 'today') {
    $where[] = 'DATE(starts_at) = ?';
    $params[] = $today;
} elseif ($range === 'week') {
    $where[] = 'starts_at >= ? AND starts_at < DATE_ADD(?, INTERVAL 7 DAY)';
    $params[] = $today . ' 00:00:00';
    $params[] = $today . ' 00:00:00';
} elseif ($range === 'upcoming') {
    $where[] = '(starts_at >= ? OR (ends_at IS NOT NULL AND ends_at >= ?))';
    $params[] = $today . ' 00:00:00';
    $params[] = $now;
}
if ($room !== '') {
    $where[] = 'room = ?';
    $params[] = $room;
}
if ($status !== '') {
    $where[] = 'status_label = ?';
    $params[] = $status;
}
if ($q !== '') {
    $like = '%' . like_escape($q) . '%';
    $where[] = "(room LIKE ? ESCAPE '\\\\' OR IFNULL(booker,'') LIKE ? ESCAPE '\\\\' OR IFNULL(purpose,'') LIKE ? ESCAPE '\\\\' OR IFNULL(remarks,'') LIKE ? ESCAPE '\\\\')";
    array_push($params, $like, $like, $like, $like);
}
$sqlWhere = $where ? implode(' AND ', $where) : '1=1';

$count = db()->prepare('SELECT COUNT(*) FROM room_bookings WHERE ' . $sqlWhere);
$count->execute($params);
$total = (int) $count->fetchColumn();

$list = db()->prepare('SELECT id, room, starts_at, ends_at, booker, purpose, status_label, remarks, source_key
    FROM room_bookings WHERE ' . $sqlWhere . ' ORDER BY starts_at ASC, room ASC LIMIT 300');
$list->execute($params);
$rows = $list->fetchAll();

$rooms = db()->query('SELECT DISTINCT room FROM room_bookings ORDER BY room')->fetchAll(PDO::FETCH_COLUMN);
$statuses = db()->query("SELECT DISTINCT status_label FROM room_bookings WHERE status_label IS NOT NULL AND status_label <> '' ORDER BY status_label")->fetchAll(PDO::FETCH_COLUMN);
$latest = booking_latest_sync();

$state = ['range' => $range, 'room' => $room, 'status' => $status, 'q' => $q];
$rangeChips = [
    'upcoming' => '今日及之後',
    'today' => '今日',
    'week' => '7 日內',
    'all' => '全部',
];

$pageTitle = '訂房';
$activeNav = 'bookings';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div>
    <p class="kicker">ECLASS BOOKING</p>
    <h1>訂房記錄</h1>
    <p class="lede">場地預訂仍在 eClass。這裡顯示已同步過來的記錄，未登入也可以查看。</p>
  </div>
  <?php if ($user): ?>
    <a class="btn btn-primary" href="booking_sync.php">同步 eClass</a>
  <?php else: ?>
    <a class="btn btn-ghost" href="login.php?next=<?= rawurlencode('booking_sync.php') ?>">登入以同步</a>
  <?php endif; ?>
</section>

<?php if ($latest): ?>
  <p class="note">最後同步 <?= e(format_when($latest['created_at'])) ?><?= $latest['display_name'] ? ' · ' . e($latest['display_name']) : '' ?> · <?= (int) $latest['row_count'] ?> 筆<?= $latest['note'] ? ' · ' . e($latest['note']) : '' ?></p>
<?php else: ?>
  <p class="note">尚未從 eClass 同步。下面是示範訂房，登入後可以貼上 eClass 表格或用書籤送過來。</p>
<?php endif; ?>

<section class="filters">
  <div class="filter-row">
    <span class="filter-label">日期</span>
    <div class="seg">
      <?php foreach ($rangeChips as $key => $label): ?>
        <?php $on = $range === $key; ?>
        <a href="<?= e(url_with_path('bookings.php', $state, ['range' => $key === 'upcoming' ? null : $key])) ?>" class="<?= $on ? 'is-on' : '' ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if ($statuses): ?>
    <div class="filter-row">
      <span class="filter-label">狀態</span>
      <div class="seg">
        <?php $on = $status === ''; ?>
        <a href="<?= e(url_with_path('bookings.php', $state, ['status' => null])) ?>" class="<?= $on ? 'is-on' : '' ?>"<?= $on ? ' aria-current="true"' : '' ?>>全部</a>
        <?php foreach ($statuses as $item): ?>
          <?php $on = $status === $item; ?>
          <a href="<?= e(url_with_path('bookings.php', $state, ['status' => $item])) ?>" class="<?= $on ? 'is-on' : '' ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= e($item) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  <form class="filter-form booking-form" method="get" action="bookings.php">
    <?php if ($range !== 'upcoming'): ?><input type="hidden" name="range" value="<?= e($range) ?>"><?php endif; ?>
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <label class="field">
      <span>場地</span>
      <select name="room" data-auto-submit>
        <option value="">全部</option>
        <?php foreach ($rooms as $item): ?>
          <option value="<?= e($item) ?>"<?= $room === $item ? ' selected' : '' ?>><?= e($item) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">
      <span>搜尋</span>
      <input type="search" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="場地、申請人、用途">
    </label>
    <div class="filter-actions">
      <button class="btn btn-primary" type="submit">搜尋</button>
      <?php if ($room !== '' || $status !== '' || $q !== '' || $range !== 'upcoming'): ?>
        <a class="btn btn-ghost" href="bookings.php">清除</a>
      <?php endif; ?>
    </div>
  </form>
</section>

<section class="table-panel">
  <table class="records bookings">
    <caption class="sr-only">eClass 訂房記錄</caption>
    <thead>
      <tr>
        <th>時間</th>
        <th>場地</th>
        <th>申請人</th>
        <th>用途</th>
        <th>狀態</th>
        <th>備註</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr>
          <td colspan="6">
            <div class="empty">
              <strong>這個範圍沒有訂房</strong>
              <p>可以改看全部日期，或登入後再同步一次 eClass。</p>
            </div>
          </td>
        </tr>
      <?php endif; ?>
      <?php foreach ($rows as $row): ?>
        <?php $code = booking_status_code((string) $row['status_label']); ?>
        <tr data-status="<?= e($code) ?>">
          <td data-col="date" data-label="時間" class="nowrap"><?= e(booking_when($row)) ?></td>
          <td data-col="location" data-label="場地"><?= e($row['room']) ?></td>
          <td data-col="report" data-label="申請人"><?= $row['booker'] ? person_html($row['booker']) : '<span class="muted">—</span>' ?></td>
          <td data-col="desc" data-label="用途"><?= text_or_dash($row['purpose']) ?></td>
          <td data-col="status" data-label="狀態"><?= $row['status_label'] ? '<span class="pill" data-status="' . e($code) . '"><span class="dot"></span>' . e($row['status_label']) . '</span>' : '<span class="muted">—</span>' ?></td>
          <td data-col="remarks" data-label="備註"><?= text_or_dash($row['remarks'], true) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<p class="pager"><span>顯示 <?= (int) count($rows) ?> 筆<?= $total > count($rows) ? '，共 ' . (int) $total . ' 筆' : '' ?></span></p>
<?php
require __DIR__ . '/includes/footer.php';
