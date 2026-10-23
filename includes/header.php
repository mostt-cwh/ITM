<?php

if (!defined('ITOPS')) {
    http_response_code(403);
    exit('Forbidden');
}

$pageTitle = $pageTitle ?? '工作記錄';
$activeNav = $activeNav ?? '';
$bodyClass = $bodyClass ?? '';
$user = current_user();
$documentTitle = $pageTitle . ' · I.T. OPS';
$flash = flash_pull();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#070b12">
  <title><?= e($documentTitle) ?></title>
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Sora:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
  <a class="skip" href="#content">跳至內容</a>
  <header class="topbar">
    <div class="wrap topbar-inner">
      <a class="brand" href="index.php" aria-label="I.T. OPS 工作記錄系統">
        <span class="mark-wrap" aria-hidden="true"><span class="mark"></span></span>
        <span class="brand-text">
          <strong>I.T. OPS</strong>
          <small>工作記錄系統</small>
        </span>
      </a>
      <nav class="nav" aria-label="主要">
        <a href="index.php"<?= $activeNav === 'records' ? ' class="is-on" aria-current="page"' : '' ?>>記錄</a>
        <?php if ($user): ?>
          <a href="report.php"<?= $activeNav === 'report' ? ' class="is-on" aria-current="page"' : '' ?>>新增工作</a>
          <?php if ($user['role'] === 'admin'): ?>
            <a href="accounts.php"<?= $activeNav === 'accounts' ? ' class="is-on" aria-current="page"' : '' ?>>帳戶</a>
          <?php endif; ?>
        <?php endif; ?>
      </nav>
      <div class="session">
        <?php if ($user): ?>
          <a class="who" href="password.php">
            <span><?= e(role_label($user['role'])) ?></span>
            <?= e($user['display_name']) ?>
          </a>
          <a class="btn btn-ghost" href="logout.php">登出</a>
        <?php else: ?>
          <a class="btn btn-primary" href="login.php">登入</a>
        <?php endif; ?>
      </div>
    </div>
  </header>
  <main id="content" class="wrap">
    <?php if ($flash): ?>
      <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endif; ?>
