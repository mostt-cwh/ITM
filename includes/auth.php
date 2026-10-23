<?php

if (!defined('ITOPS')) {
    http_response_code(403);
    exit('Forbidden');
}

const ITOPS_DUMMY_HASH = '$2y$10$hiMkPE.QJxibfo4OPSFpGelBgGG/IC9An0aBAxuWEV.0mP1Nyh.KG';

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, username, display_name, role, active FROM users WHERE id = ?');
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();

    if (!$row || (int) $row['active'] !== 1) {
        unset($_SESSION['user_id']);
        return null;
    }

    $user = $row;
    return $user;
}

function require_login(): void
{
    if (current_user()) {
        return;
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? 'index.php', PHP_URL_PATH);
    $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    $next = basename((string) $path) . ($query ? '?' . $query : '');
    redirect('login.php?next=' . rawurlencode(safe_next($next)));
}

function require_admin(): void
{
    require_login();
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        flash_set('error', '需要管理員權限');
        redirect('index.php');
    }
}

function attempt_login(string $username, string $password): string
{
    $lockedUntil = (int) ($_SESSION['login_lock_until'] ?? 0);
    if ($lockedUntil > time()) {
        return '嘗試次數過多，請稍後再登入';
    }

    $username = trim($username);
    $tooLong = text_length($username) > 40 || strlen($password) > 72 || $username === '' || $password === '';

    $row = null;
    if (!$tooLong) {
        $stmt = db()->prepare('SELECT id, username, password_hash, active FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch() ?: null;
    }

    $hash = $row['password_hash'] ?? ITOPS_DUMMY_HASH;
    $valid = password_verify($password, $hash);
    $active = $row && (int) $row['active'] === 1;

    if ($tooLong || !$row || !$active || !$valid) {
        $_SESSION['login_fails'] = (int) ($_SESSION['login_fails'] ?? 0) + 1;
        if ((int) $_SESSION['login_fails'] >= 5) {
            $_SESSION['login_lock_until'] = time() + 60;
            $_SESSION['login_fails'] = 0;
        }
        return '帳戶或密碼不正確';
    }

    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int) $row['id']];
    return '';
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
