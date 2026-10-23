<?php

require __DIR__ . '/includes/bootstrap.php';

$options = form_options();
$statusIds = array_map('intval', array_column($options['statuses'], 'id'));
$typeIds = array_map('intval', array_column($options['types'], 'id'));

$status = (int) ($_GET['status'] ?? 0);
if (!in_array($status, $statusIds, true)) {
    $status = 0;
}

$type = (int) ($_GET['type'] ?? 0);
if (!in_array($type, $typeIds, true)) {
    $type = 0;
}

$handlerGet = trim((string) ($_GET['handler'] ?? ''));
if ($handlerGet === 'none') {
    $handler = 'none';
} elseif (ctype_digit($handlerGet) && (int) $handlerGet > 0) {
    $handler = (int) $handlerGet;
} else {
    $handler = '';
}

$q = trim((string) ($_GET['q'] ?? ''));
if (text_length($q) > 100) {
    $q = function_exists('mb_substr') ? mb_substr($q, 0, 100, 'UTF-8') : substr($q, 0, 100);
}

$sortMap = [
    'id' => 'c.id',
    'date' => 'c.case_date',
    'status' => 's.sort_order',
    'type' => 't.name',
    'location' => 'c.location',
    'report_by' => 'c.report_by',
    'handler' => 'h.display_name',
];
$sort = (string) ($_GET['sort'] ?? 'date');
if (!isset($sortMap[$sort])) {
    $sort = 'date';
}
$dir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

$perPage = (int) ($_GET['per_page'] ?? 25);
if (!in_array($perPage, [10, 25, 50, 100], true)) {
    $perPage = 25;
}

$filters = [
    'status' => $status,
    'handler' => $handler,
    'type' => $type,
    'q' => $q,
];

[$countWhere, $countParams] = case_where($filters, false);
$countSql = 'SELECT c.status_id, COUNT(*) AS total ' . case_from_sql() . ' WHERE ' . $countWhere . ' GROUP BY c.status_id';
$countStmt = db()->prepare($countSql);
$countStmt->execute($countParams);
$statusCounts = [];
$allCount = 0;
foreach ($countStmt->fetchAll() as $row) {
    $statusCounts[(int) $row['status_id']] = (int) $row['total'];
    $allCount += (int) $row['total'];
}

[$where, $params] = case_where($filters, true);
$listCount = db()->prepare('SELECT COUNT(*) ' . case_from_sql() . ' WHERE ' . $where);
$listCount->execute($params);
$total = (int) $listCount->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page = max(1, (int) ($_GET['page'] ?? 1));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$state = [
    'status' => $status,
    'handler' => $handler,
    'type' => $type,
    'q' => $q,
    'sort' => $sort,
    'dir' => $dir,
    'per_page' => $perPage,
    'page' => $page,
];

$order = $sortMap[$sort] . ' ' . ($dir === 'asc' ? 'ASC' : 'DESC') . ', c.id DESC';
$listSql = 'SELECT c.id, c.case_date, c.location, c.report_by, c.description, c.remarks, c.handler_id,
        c.status_id, s.label_zh AS status_zh,
        t.name AS type_name, t.label_zh AS type_zh,
        h.display_name AS handler_name, h.username AS handler_username '
    . case_from_sql()
    . ' WHERE ' . $where
    . ' ORDER BY ' . $order
    . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
$listStmt = db()->prepare($listSql);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

$filtered = $status || $handler !== '' || $type || $q !== '';
$from = $total === 0 ? 0 : $offset + 1;
$to = min($offset + $perPage, $total);

$columns = [
    ['id', '個案編號', 'id'],
    ['status', '狀態', 'status'],
    ['date', '日期', 'date'],
    ['type', '類型', 'type'],
    ['location', '地點', 'location'],
    ['report_by', '報告人', 'report'],
    ['desc', '描述', 'desc'],
    ['handler', '負責人', 'handler'],
    ['remarks', '備註', 'remarks'],
];

$pageTitle = '工作記錄';
$activeNav = 'records';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div>
    <p class="kicker">WORK LOG</p>
    <h1>工作記錄</h1>
    <p class="lede">未登入也可以查看全部記錄。技術員登入後可新增工作，並更新狀態與負責人。</p>
  </div>
  <?php if ($user): ?>
    <a class="btn btn-primary" href="report.php">新增工作</a>
  <?php else: ?>
    <a class="btn btn-ghost" href="login.php?next=<?= rawurlencode('report.php') ?>">登入以記錄工作</a>
  <?php endif; ?>
</section>

<section class="stats" aria-label="依狀態篩選">
  <a class="stat<?= $status === 0 ? ' is-on' : '' ?>" href="<?= e(url_with($state, ['status' => null, 'page' => null])) ?>"<?= $status === 0 ? ' aria-current="true"' : '' ?>>
    <b><?= (int) $allCount ?></b>
    <span>全部</span>
  </a>
  <?php foreach ($options['statuses'] as $item): ?>
    <?php $sid = (int) $item['id']; ?>
    <a class="stat<?= $status === $sid ? ' is-on' : '' ?>" data-status="<?= $sid ?>" href="<?= e(url_with($state, ['status' => $sid, 'page' => null])) ?>"<?= $status === $sid ? ' aria-current="true"' : '' ?>>
      <b><?= (int) ($statusCounts[$sid] ?? 0) ?></b>
      <span><?= e($item['label_zh']) ?></span>
    </a>
  <?php endforeach; ?>
</section>

<section class="filters">
  <div class="filter-row">
    <span class="filter-label">負責人</span>
    <div class="seg">
      <?php
        $handlerChips = [['', '全部']];
        foreach ($options['technicians'] as $tech) {
            $handlerChips[] = [(string) $tech['id'], $tech['display_name']];
        }
        $handlerChips[] = ['none', '未指派'];
      ?>
      <?php foreach ($handlerChips as [$value, $label]): ?>
        <?php $on = (string) $handler === (string) $value; ?>
        <a href="<?= e(url_with($state, ['handler' => $value === '' ? null : $value, 'page' => null])) ?>" class="<?= $on ? 'is-on' : '' ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <form class="filter-form" method="get" action="index.php">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= (int) $status ?>"><?php endif; ?>
    <?php if ($handler !== ''): ?><input type="hidden" name="handler" value="<?= e((string) $handler) ?>"><?php endif; ?>
    <?php if ($sort !== 'date'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
    <?php if ($dir !== 'desc'): ?><input type="hidden" name="dir" value="<?= e($dir) ?>"><?php endif; ?>
    <label class="field">
      <span>類型</span>
      <select name="type" data-auto-submit>
        <option value="">全部</option>
        <?php foreach ($options['types'] as $item): ?>
          <option value="<?= (int) $item['id'] ?>"<?= $type === (int) $item['id'] ? ' selected' : '' ?>><?= e($item['name']) ?> · <?= e($item['label_zh']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">
      <span>搜尋</span>
      <input type="search" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="編號、地點、內容、報告人">
    </label>
    <label class="field">
      <span>每頁</span>
      <select name="per_page" data-auto-submit>
        <?php foreach ([10, 25, 50, 100] as $size): ?>
          <option value="<?= $size ?>"<?= $perPage === $size ? ' selected' : '' ?>><?= $size ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="filter-actions">
      <button class="btn btn-primary" type="submit">搜尋</button>
      <?php if ($filtered): ?><a class="btn btn-ghost" href="index.php">清除</a><?php endif; ?>
    </div>
  </form>

  <details class="cols">
    <summary>顯示欄位</summary>
    <div class="col-list">
      <?php
        $toggles = [
            'id' => '個案編號',
            'status' => '狀態',
            'date' => '日期',
            'type' => '類型',
            'location' => '地點',
            'report' => '報告人',
            'desc' => '描述',
            'handler' => '負責人',
            'remarks' => '備註',
        ];
      ?>
      <?php foreach ($toggles as $key => $label): ?>
        <label><input type="checkbox" data-col-toggle="<?= e($key) ?>" checked> <?= e($label) ?></label>
      <?php endforeach; ?>
    </div>
  </details>
</section>

<section class="table-panel">
  <table class="records">
    <caption class="sr-only">工作記錄</caption>
    <thead>
      <tr>
        <?php foreach ($columns as [$key, $label, $col]): ?>
          <?php
            $isCurrent = $sort === $key;
            if ($key === 'desc' || $key === 'remarks') {
                echo '<th data-col="' . e($col) . '">' . e($label) . '</th>';
                continue;
            }
            if (!$isCurrent) {
                $nextDir = in_array($key, ['date', 'id'], true) ? 'desc' : 'asc';
            } else {
                $nextDir = $dir === 'asc' ? 'desc' : 'asc';
            }
            $aria = $isCurrent ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';
            $mark = $isCurrent ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
            $href = url_with($state, ['sort' => $key, 'dir' => $nextDir, 'page' => null]);
          ?>
          <th data-col="<?= e($col) ?>" aria-sort="<?= e($aria) ?>"><a href="<?= e($href) ?>"><?= e($label) . $mark ?></a></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr>
          <td colspan="9">
            <div class="empty">
              <strong><?= $filtered ? '沒有符合的記錄' : '尚未有工作記錄' ?></strong>
              <p><?= $filtered ? '試著清除篩選，或用其他關鍵字搜尋。' : '登入後可以新增第一筆工作。' ?></p>
              <?php if ($filtered): ?>
                <a class="btn btn-ghost" href="index.php">清除篩選</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endif; ?>
      <?php foreach ($rows as $row): ?>
        <tr data-status="<?= (int) $row['status_id'] ?>">
          <td data-col="id" data-label="個案編號"><a class="id-link" href="case.php?id=<?= (int) $row['id'] ?>">#<?= (int) $row['id'] ?></a></td>
          <td data-col="status" data-label="狀態"><?= status_pill((int) $row['status_id'], $row['status_zh']) ?></td>
          <td data-col="date" data-label="日期" class="nowrap"><?= e(format_when($row['case_date'])) ?></td>
          <td data-col="type" data-label="類型">
            <div class="type-cell">
              <span><?= e($row['type_name']) ?></span>
              <small><?= e($row['type_zh']) ?></small>
            </div>
          </td>
          <td data-col="location" data-label="地點"><?= text_or_dash($row['location']) ?></td>
          <td data-col="report" data-label="報告人"><?= e($row['report_by']) ?></td>
          <td data-col="desc" data-label="描述"><a class="clamp" href="case.php?id=<?= (int) $row['id'] ?>" title="<?= e($row['description']) ?>"><?= e($row['description']) ?></a></td>
          <td data-col="handler" data-label="負責人">
            <?php if ($row['handler_id']): ?>
              <a class="person-link" href="<?= e(url_with($state, ['handler' => (int) $row['handler_id'], 'page' => null])) ?>" title="只看 <?= e($row['handler_name']) ?> 的記錄"><?= person_html($row['handler_name'], $row['handler_username']) ?></a>
            <?php else: ?>
              <?= person_html(null, null) ?>
            <?php endif; ?>
          </td>
          <td data-col="remarks" data-label="備註"><span class="clamp"><?= text_or_dash($row['remarks'], true) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<div class="pager">
  <p>顯示 <?= (int) $from ?>–<?= (int) $to ?>，共 <?= (int) $total ?> 筆</p>
  <?php if ($totalPages > 1): ?>
    <div class="pager-nav">
      <?php if ($page > 1): ?>
        <a href="<?= e(url_with($state, ['page' => $page - 1])) ?>">上一頁</a>
      <?php else: ?>
        <span class="is-disabled">上一頁</span>
      <?php endif; ?>
      <span><?= (int) $page ?> / <?= (int) $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="<?= e(url_with($state, ['page' => $page + 1])) ?>">下一頁</a>
      <?php else: ?>
        <span class="is-disabled">下一頁</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
