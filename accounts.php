<?php

require __DIR__ . '/includes/bootstrap.php';
require_admin();

if (is_post()) {
    csrf_verify();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $password = (string) ($_POST['new_password'] ?? '');
    $message = password_is_valid($password);

    $stmt = db()->prepare('SELECT id, display_name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $target = $stmt->fetch();
    if (!$target) {
        $message = '找不到這個帳戶';
    }

    if ($message) {
        flash_set('error', $message);
        redirect('accounts.php');
    }

    $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $update->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    flash_set('success', '已重設 ' . $target['display_name'] . ' 的密碼');
    redirect('accounts.php');
}

$users = db()->query('SELECT id, username, display_name, role, active FROM users ORDER BY sort_order, id')->fetchAll();

$pageTitle = '帳戶';
$activeNav = 'accounts';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div>
    <p class="kicker">ACCOUNTS</p>
    <h1>帳戶</h1>
    <p class="lede">Marco、Dragon、Stanley 是技術員，admin 是管理員。首次使用後請更改預設密碼。</p>
  </div>
</section>

<section class="table-panel">
  <table>
    <caption class="sr-only">系統帳戶</caption>
    <thead>
      <tr>
        <th>名稱</th>
        <th>帳戶</th>
        <th>身份</th>
        <th>狀態</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $account): ?>
        <tr>
          <td><?= person_html($account['display_name'], $account['username']) ?></td>
          <td class="mono"><?= e($account['username']) ?></td>
          <td><span class="role-badge role-<?= e($account['role']) ?>"><?= e(role_label($account['role'])) ?></span></td>
          <td><?= (int) $account['active'] === 1 ? '啟用' : '停用' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<form method="post" class="panel narrow stack reset-form">
  <h2>重設密碼</h2>
  <?= csrf_field() ?>
  <div class="field">
    <label for="user_id">帳戶</label>
    <select id="user_id" name="user_id" required>
      <?php foreach ($users as $account): ?>
        <option value="<?= (int) $account['id'] ?>"><?= e($account['display_name']) ?> · <?= e($account['username']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="new_password">新密碼</label>
    <input id="new_password" name="new_password" type="password" autocomplete="new-password" required minlength="6">
  </div>
  <button class="btn btn-primary" type="submit">重設</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
