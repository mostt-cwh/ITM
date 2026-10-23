<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/bookings.php';
require_login();

$user = current_user();
$tableText = '';
$replace = true;

if (is_post()) {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? 'import');
    if ($action === 'rotate') {
        require_admin();
        booking_rotate_token();
        flash_set('success', '已更新同步碼。請重新拖曳書籤，舊書籤已失效。');
        redirect('booking_sync.php');
    }

    $replace = isset($_POST['replace']);
    $posted = booking_posted_text();
    if (!empty($posted['error'])) {
        flash_set('error', $posted['error']);
    } else {
        $tableText = $posted['text'];
        [$headers, $rows] = booking_table_from_text($tableText);
        if (!$headers) {
            flash_set('error', '沒有讀到表格。請連標題列一併貼上。');
        } else {
            $result = booking_import_table($headers, $rows, $replace, (int) $user['id'], $posted['note']);
            flash_set($result['ok'] ? 'success' : 'error', $result['message']);
            if ($result['ok']) {
                redirect('bookings.php');
            }
        }
    }
}

$token = booking_sync_token();
$bookmarklet = booking_bookmarklet(booking_push_url(), $token);
$history = db()->query('SELECT s.created_at, s.row_count, s.removed_count, s.note, u.display_name
    FROM booking_syncs s
    LEFT JOIN users u ON u.id = s.synced_by
    ORDER BY s.id DESC
    LIMIT 8')->fetchAll();

$pageTitle = '同步訂房';
$activeNav = 'bookings';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div>
    <p class="kicker">ECLASS SYNC</p>
    <h1>同步 eClass 訂房</h1>
    <p class="lede">eClass 沒有可讀取訂房的 API。請在已登入 eClass 的頁面把看見的表格送過來，或貼上、上傳 CSV。這裡不會儲存 eClass 密碼。</p>
  </div>
  <a class="btn btn-ghost" href="bookings.php">返回訂房</a>
</section>

<div class="sync-grid">
  <section class="panel">
    <h2>書籤</h2>
    <ol class="sync-steps">
      <li>把下面的按鈕拖到瀏覽器書籤列。</li>
      <li>用學校帳戶登入 eClass，打開場地預訂列表，確認畫面有表格。</li>
      <li>按書籤。成功後會回到訂房頁。</li>
    </ol>
    <div class="bookmark-row">
      <a class="btn btn-primary" href="<?= e($bookmarklet) ?>">同步這頁到 I.T. OPS</a>
    </div>
    <label class="field">
      <span>複製書籤網址</span>
      <textarea class="codebox" readonly rows="4"><?= e($bookmarklet) ?></textarea>
    </label>
    <p class="note">書籤內含同步碼。管理員更新同步碼後，要重新拖一次書籤。</p>
  </section>

  <section class="panel">
    <h2>貼上或 CSV</h2>
    <ol class="sync-steps">
      <li>在 eClass 選取表格，連標題列一起複製。</li>
      <li>貼到下面。Excel 請另存 CSV，不支援 xlsx。</li>
      <li>勾選取代時，今次匯入涵蓋的日期裡，沒有出現的舊記錄會移除。</li>
    </ol>
    <form class="stack" method="post" action="booking_sync.php" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="import">
      <label class="field">
        <span>表格</span>
        <textarea name="table" rows="8" maxlength="200000" placeholder="日期	場地	時間	申請人	用途	狀態&#10;2026-10-23	Hall	09:00-10:30	LPK	畢業典禮綵排	已批核"><?= e($tableText) ?></textarea>
      </label>
      <label class="field">
        <span>或上傳 CSV</span>
        <input type="file" name="csv" accept=".csv,.txt,.tsv,text/csv,text/plain">
      </label>
      <label class="checkline">
        <input type="checkbox" name="replace" value="1"<?= $replace ? ' checked' : '' ?>>
        <span>以今次匯入取代相同日期的記錄</span>
      </label>
      <button class="btn btn-primary" type="submit">匯入訂房</button>
    </form>
  </section>
</div>

<?php if ($user['role'] === 'admin'): ?>
  <section class="danger-zone">
    <div>
      <strong>更新同步碼</strong>
      <p>舊書籤會立即失效。已經同步的訂房不會被刪除。</p>
    </div>
    <form method="post" action="booking_sync.php" data-confirm="更新後，同事要重新拖曳書籤。確定更新同步碼？">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="rotate">
      <button class="btn btn-danger" type="submit">更新同步碼</button>
    </form>
  </section>
<?php endif; ?>

<?php if ($history): ?>
  <section class="table-panel sync-log-wrap">
    <table class="sync-log">
      <caption class="sr-only">最近同步</caption>
      <thead>
        <tr>
          <th>時間</th>
          <th>人員</th>
          <th>筆數</th>
          <th>移除</th>
          <th>來源</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $item): ?>
          <tr>
            <td><?= e(format_when($item['created_at'])) ?></td>
            <td><?= e($item['display_name'] ?: '書籤') ?></td>
            <td><?= (int) $item['row_count'] ?></td>
            <td><?= (int) $item['removed_count'] ?></td>
            <td><?= e($item['note'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
<?php endif; ?>
<?php
require __DIR__ . '/includes/footer.php';

function booking_posted_text(): array
{
    $file = $_FILES['csv'] ?? null;
    if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $error = (int) $file['error'];
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return ['error' => '檔案太大'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['error' => '檔案上傳失敗'];
        }
        $name = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls', 'xlsm'], true)) {
            return ['error' => '不支援 Excel 檔。請另存為 CSV 再上傳。'];
        }
        if ($ext !== '' && !in_array($ext, ['csv', 'txt', 'tsv'], true)) {
            return ['error' => '請上傳 CSV 或純文字檔'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 1048576) {
            return ['error' => '檔案太大或是空的'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $text = is_uploaded_file($tmp) ? file_get_contents($tmp) : false;
        if (!is_string($text)) {
            return ['error' => '讀不到檔案'];
        }
        if (strncmp($text, "PK\x03\x04", 4) === 0) {
            return ['error' => '這是 Excel 檔。請另存為 CSV 再上傳。'];
        }
        return ['text' => $text, 'note' => 'csv-upload'];
    }

    $text = (string) ($_POST['table'] ?? '');
    if (trim($text) === '') {
        return ['error' => '請貼上表格或上傳 CSV'];
    }
    if (strlen($text) > 1048576) {
        return ['error' => '貼上的內容太大'];
    }
    return ['text' => $text, 'note' => 'paste'];
}
