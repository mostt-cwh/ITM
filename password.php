<?php

require __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();

if (is_post()) {
    csrf_verify();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    $message = password_is_valid($new);

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([(int) $user['id']]);
    $hash = (string) $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $message = '目前密碼不正確';
    } elseif ($message === null && $new !== $confirm) {
        $message = '兩次新密碼不相同';
    }

    if ($message) {
        flash_set('error', $message);
        redirect('password.php');
    }

    $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $update->execute([password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
    flash_set('success', '密碼已更新');
    redirect('password.php');
}

$pageTitle = '更改密碼';
$activeNav = '';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div>
    <p class="kicker">SECURITY</p>
    <h1>更改密碼</h1>
    <p class="lede"><?= e($user['display_name']) ?> · <?= e($user['username']) ?></p>
  </div>
</section>
<form method="post" class="panel narrow stack">
  <?= csrf_field() ?>
  <div class="field">
    <label for="current_password">目前密碼</label>
    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
  </div>
  <div class="field">
    <label for="new_password">新密碼</label>
    <input id="new_password" name="new_password" type="password" autocomplete="new-password" required minlength="6">
  </div>
  <div class="field">
    <label for="confirm_password">再輸入一次</label>
    <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required minlength="6">
  </div>
  <button class="btn btn-primary" type="submit">更新密碼</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
