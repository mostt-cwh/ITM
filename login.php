<?php

require __DIR__ . '/includes/bootstrap.php';

$next = safe_next((string) ($_POST['next'] ?? $_GET['next'] ?? 'index.php'));
if (current_user()) {
    redirect($next);
}

$error = '';
$username = '';

if (is_post()) {
    csrf_verify();
    $username = trim((string) ($_POST['username'] ?? ''));
    $error = attempt_login($username, (string) ($_POST['password'] ?? ''));
    if ($error === '') {
        redirect($next);
    }
}

$pageTitle = '登入';
$activeNav = '';
$bodyClass = 'page-auth';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-card">
  <p class="kicker">ACCESS</p>
  <h1>登入</h1>
  <p class="lede">技術員與管理員登入後可以新增及更新工作。未登入仍可查看全部記錄。</p>
  <?php if ($error): ?>
    <div class="flash flash-error" role="alert"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" class="stack" action="login.php">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field">
      <label for="username">帳戶</label>
      <input id="username" name="username" autocomplete="username" required maxlength="40" value="<?= e($username) ?>">
    </div>
    <div class="field">
      <label for="password">密碼</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit">登入</button>
  </form>
  <details class="demo">
    <summary>示範帳戶</summary>
    <ul>
      <li><b>admin</b><span>管理員</span><code>admin123</code></li>
      <li><b>marco</b><span>技術員</span><code>marco123</code></li>
      <li><b>dragon</b><span>技術員</span><code>dragon123</code></li>
      <li><b>stanley</b><span>技術員</span><code>stanley123</code></li>
    </ul>
  </details>
  <p class="note"><a href="index.php">返回記錄</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
