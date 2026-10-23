<?php

require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
if (is_post()) {
    require_login();
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
}

if ($id <= 0) {
    render_not_found();
}

$case = fetch_case($id);
if (!$case) {
    render_not_found();
}

$user = current_user();
$options = form_options();
$errors = [];
$form = case_form_defaults($case, $user);

if (is_post()) {
    if (($_POST['action'] ?? '') === 'delete') {
        require_admin();
        db()->prepare('DELETE FROM work_cases WHERE id = ?')->execute([$id]);
        flash_set('success', '已刪除個案 #' . $id);
        redirect('index.php');
    }

    $parsed = validate_case(request_case_input(), $options);
    $errors = $parsed['errors'];
    $form = $parsed['form'];
    if (!$errors) {
        update_case($id, $parsed['data'], (int) $user['id']);
        flash_set('success', '已更新個案 #' . $id);
        redirect('case.php?id=' . $id);
    }
}

$pageTitle = '個案 #' . $id;
$activeNav = 'records';
require __DIR__ . '/includes/header.php';
?>
<p class="back"><a href="<?= e(back_to_list()) ?>">← 返回記錄</a></p>

<?php if ($user): ?>
  <section class="page-head">
    <div>
      <p class="kicker">CASE <?= (int) $case['id'] ?></p>
      <h1>更新個案 #<?= (int) $case['id'] ?></h1>
      <p class="lede">儲存後會即時顯示在公開記錄。</p>
    </div>
  </section>
  <?php
    $statuses = $options['statuses'];
    $types = $options['types'];
    $technicians = $options['technicians'];
    $submitLabel = '儲存更新';
    $formId = (int) $case['id'];
    require __DIR__ . '/includes/case_form.php';
  ?>
  <?php if ($user['role'] === 'admin'): ?>
    <form method="post" class="danger-zone" data-confirm="確定刪除個案 #<?= (int) $case['id'] ?>？此操作無法還原。">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $case['id'] ?>">
      <input type="hidden" name="action" value="delete">
      <div>
        <strong>刪除個案</strong>
        <p>只有管理員可以刪除。刪除後無法還原。</p>
      </div>
      <button class="btn btn-danger" type="submit">刪除</button>
    </form>
  <?php endif; ?>
<?php else: ?>
  <section class="case-hero">
    <div>
      <p class="kicker">CASE <?= (int) $case['id'] ?></p>
      <h1>個案 #<?= (int) $case['id'] ?></h1>
      <p class="lede"><?= e($case['type_name']) ?> · <?= e($case['type_zh']) ?> · <?= e(format_when($case['case_date'])) ?></p>
    </div>
    <div class="case-side">
      <?= status_pill((int) $case['status_id'], $case['status_zh']) ?>
      <?= person_html($case['handler_name'], $case['handler_username']) ?>
    </div>
  </section>
  <section class="split">
    <article class="panel prose">
      <h2>描述</h2>
      <?= paragraphs($case['description'], '沒有描述') ?>
      <h2>備註</h2>
      <?= paragraphs($case['remarks']) ?>
    </article>
    <dl class="panel specs">
      <div><dt>狀態</dt><dd><?= status_pill((int) $case['status_id'], $case['status_zh']) ?></dd></div>
      <div><dt>負責人</dt><dd><?= person_html($case['handler_name'], $case['handler_username']) ?></dd></div>
      <div><dt>報告人</dt><dd><?= e($case['report_by']) ?></dd></div>
      <div><dt>類型</dt><dd><?= e($case['type_name']) ?> · <?= e($case['type_zh']) ?></dd></div>
      <div><dt>地點</dt><dd><?= text_or_dash($case['location']) ?></dd></div>
      <div><dt>日期</dt><dd><?= e(format_when($case['case_date'])) ?></dd></div>
      <div><dt>建立</dt><dd><?= e(format_when($case['created_at'])) ?><?= $case['created_name'] ? ' · ' . e($case['created_name']) : '' ?></dd></div>
      <div><dt>更新</dt><dd><?= e(format_when($case['updated_at'])) ?><?= $case['updated_name'] ? ' · ' . e($case['updated_name']) : '' ?></dd></div>
    </dl>
  </section>
  <p class="note">這是公開記錄。登入後可以更新狀態、負責人與備註。<a href="login.php?next=<?= rawurlencode('case.php?id=' . (int) $case['id']) ?>">登入</a></p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
