<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

define('SESSION_TIMEOUT', 30 * 60);

function regenerate_session(): void {
    session_regenerate_id(true);
}

function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function record_failed_attempt(string $ip): void {
    $_SESSION['login_attempts'][$ip] = ($_SESSION['login_attempts'][$ip] ?? 0) + 1;
}

function reset_attempts(string $ip): void {
    unset($_SESSION['login_attempts'][$ip]);
}

function check_session_timeout(): void {
    if (isset($_SESSION['last_activity'])
        && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: /plant/admin/login.php?timeout=1');
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function require_admin_auth(): void {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: /plant/admin/login.php');
        exit();
    }
    check_session_timeout();
}