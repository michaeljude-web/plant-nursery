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
define('STAFF_SESSION_TIMEOUT', 30 * 60);
function staff_regenerate_session(): void {
session_regenerate_id(true);
}
function staff_generate_csrf_token(): string {
if (empty($_SESSION['staff_csrf_token'])) {
$_SESSION['staff_csrf_token'] = bin2hex(random_bytes(32));
    }
return $_SESSION['staff_csrf_token'];
}
function staff_verify_csrf_token(string $token): bool {
return isset($_SESSION['staff_csrf_token'])
&& hash_equals($_SESSION['staff_csrf_token'], $token);
}
function staff_record_failed_attempt(string $ip): void {
$_SESSION['staff_login_attempts'][$ip] = ($_SESSION['staff_login_attempts'][$ip] ?? 0) + 1;
}
function staff_reset_attempts(string $ip): void {
unset($_SESSION['staff_login_attempts'][$ip]);
}
function staff_check_session_timeout(): void {
if (isset($_SESSION['staff_last_activity'])
&& (time() - $_SESSION['staff_last_activity']) > STAFF_SESSION_TIMEOUT) {
session_unset();
session_destroy();
header('Location: /plant/login.php?timeout=1');
exit();
    }
$_SESSION['staff_last_activity'] = time();
}
function require_staff_auth(): void {
if (empty($_SESSION['staff_logged_in'])) {
header('Location: /plant/login.php');
exit();
    }
staff_check_session_timeout();
global $pdo;
if ($pdo && !empty($_SESSION['staff_id'])) {
$stmt = $pdo->prepare("SELECT session_id FROM login_sessions WHERE user_id = ? AND user_type = 'staff'");
$stmt->execute([$_SESSION['staff_id']]);
$row = $stmt->fetch();
if (!$row || $row['session_id'] !== session_id()) {
session_unset();
session_destroy();
header('Location: /plant/login.php?timeout=1');
exit();
        }
    }
}