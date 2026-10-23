<?php

require __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$options = form_options();
$errors = [];
$form = case_form_defaults(null, $user);

if (is_post()) {
    csrf_verify();
    $parsed = validate_case(request_case_input(), $options);
    $errors = $parsed['errors'];
    $form = $parsed['form'];
    if (!$errors) {
        $newId = create_case($parsed['data'], (int) $user['id']);
        flash_set('success', '已新增個案 #' . $newId);
        redirect('case.php?id=' . $newId);
    }
}

$pageTitle = '新增工作';
$activeNav = 'report';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div>
    <p class="kicker">NEW ENTRY</p>
    <h1>新增工作</h1>
    <p class="lede">記錄會即時出現在公開清單，未登入的人也可以查看。</p>
  </div>
</section>
<?php
  $statuses = $options['statuses'];
  $types = $options['types'];
  $technicians = $options['technicians'];
  $submitLabel = '建立記錄';
  $formId = 0;
  require __DIR__ . '/includes/case_form.php';
?>
<?php require __DIR__ . '/includes/footer.php'; ?>
