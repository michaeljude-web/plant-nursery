<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config/config.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: /plant/admin/dashboard.php');
    exit();
}
if (!empty($_SESSION['staff_logged_in'])) {
    header('Location: /plant/staff/dashboard.php');
    exit();
}

$MAX_ATTEMPTS = 5;
$BAN_SECONDS  = 20;
$type         = 'plant';

$ip          = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua          = $_SERVER['HTTP_USER_AGENT']      ?? '';
$lang        = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$encoding    = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
$device_hash = hash('sha256', $ua . $lang . $encoding);

function get_browser_name($ua) {
    if (!$ua) return 'Unknown';
    if (str_contains($ua, 'Edg'))     return 'Microsoft Edge';
    if (str_contains($ua, 'OPR'))     return 'Opera';
    if (str_contains($ua, 'Chrome'))  return 'Chrome';
    if (str_contains($ua, 'Firefox')) return 'Firefox';
    if (str_contains($ua, 'Safari'))  return 'Safari';
    return 'Unknown Browser';
}

$browser = get_browser_name($ua);

$stmt = $pdo->prepare("SELECT attempts, ban_until FROM login_attempts WHERE ip_address = ? AND device_hash = ? AND type = ?");
$stmt->execute([$ip, $device_hash, $type]);
$row = $stmt->fetch();

$attempts  = $row ? (int)$row['attempts']  : 0;
$ban_until = $row ? (int)$row['ban_until'] : 0;

$isBanned       = $ban_until > time();
$banSecondsLeft = max(0, $ban_until - time());

if ($row && !$isBanned && $ban_until > 0) {
    $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND device_hash = ? AND type = ?")
        ->execute([$ip, $device_hash, $type]);
    $attempts = 0;
}

function is_safe_input($val) {
    $blocked = ["'", '"', ';', '--', '#', '/*', '*/', 'SELECT', 'INSERT', 'UPDATE',
                'DELETE', 'DROP', 'UNION', 'OR ', 'AND ', '<script', '</script',
                '<', '>', '\\', '/', '=', '%', '&', '|', '`', 'EXEC', 'CAST',
                'CHAR(', 'alert(', 'onerror', 'onload'];
    $upper = strtoupper($val);
    foreach ($blocked as $b) {
        if (str_contains($upper, strtoupper($b))) return false;
    }
    return true;
}

function is_trusted($pdo, $user_id, $user_type, $device_hash) {
    $s = $pdo->prepare("SELECT id FROM trusted_devices WHERE user_id = ? AND user_type = ? AND device_hash = ? AND is_approved = 1");
    $s->execute([$user_id, $user_type, $device_hash]);
    return $s->fetch() !== false;
}

function get_request_status($pdo, $user_id, $user_type, $device_hash) {
    $s = $pdo->prepare("SELECT status FROM device_requests WHERE user_id = ? AND user_type = ? AND device_hash = ?");
    $s->execute([$user_id, $user_type, $device_hash]);
    $r = $s->fetch();
    return $r ? $r['status'] : null;
}

$error         = '';
$device_status = null;
$timeout       = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBanned) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']     ?? '';

    if (!is_safe_input($username) || !is_safe_input($password)) {
        $error = 'Invalid characters detected in input.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT admin_id, username, password FROM admin WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND device_hash = ? AND type = ?")
                ->execute([$ip, $device_hash, $type]);

            if (is_trusted($pdo, $admin['admin_id'], 'admin', $device_hash)) {
                require_once 'includes/admin/auth.php';
                regenerate_session();
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $admin['admin_id'];
                $_SESSION['admin_username']  = $admin['username'];
                $_SESSION['last_activity']   = time();

                $pdo->prepare("INSERT INTO login_sessions (user_id, user_type, session_id)
                    VALUES (?, 'admin', ?)
                    ON DUPLICATE KEY UPDATE session_id = ?"
                )->execute([$admin['admin_id'], session_id(), session_id()]);

                header('Location: /plant/admin/dashboard.php');
                exit();
            } else {
                $status = get_request_status($pdo, $admin['admin_id'], 'admin', $device_hash);
                if ($status === 'rejected') {
                    $device_status = 'rejected';
                } elseif ($status === 'pending') {
                    $device_status = 'pending';
                } else {
                    $pdo->prepare("
                        INSERT INTO device_requests (user_id, user_type, device_hash, ip_address, user_agent, browser, status)
                        VALUES (?, 'admin', ?, ?, ?, ?, 'pending')
                        ON DUPLICATE KEY UPDATE status = 'pending', created_at = NOW()
                    ")->execute([$admin['admin_id'], $device_hash, $ip, $ua, $browser]);
                    $_SESSION['pending_user_id']   = $admin['admin_id'];
                    $_SESSION['pending_user_type'] = 'admin';
                    $device_status = 'pending';
                }
            }
        } else {
            $stmt = $pdo->prepare("SELECT staff_id, firstname, lastname, username, password FROM staff_info WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $staff = $stmt->fetch();

            if ($staff && password_verify($password, $staff['password'])) {
                $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND device_hash = ? AND type = ?")
                    ->execute([$ip, $device_hash, $type]);

                if (is_trusted($pdo, $staff['staff_id'], 'staff', $device_hash)) {
                    require_once 'includes/staff/auth.php';
                    staff_regenerate_session();
                    $_SESSION['staff_logged_in']     = true;
                    $_SESSION['staff_id']            = $staff['staff_id'];
                    $_SESSION['staff_username']      = $staff['username'];
                    $_SESSION['staff_firstname']     = $staff['firstname'];
                    $_SESSION['staff_lastname']      = $staff['lastname'];
                    $_SESSION['staff_last_activity'] = time();

                    $pdo->prepare("INSERT INTO login_sessions (user_id, user_type, session_id)
                        VALUES (?, 'staff', ?)
                        ON DUPLICATE KEY UPDATE session_id = ?"
                    )->execute([$staff['staff_id'], session_id(), session_id()]);

                    header('Location: /plant/staff/dashboard.php');
                    exit();
                } else {
                    $status = get_request_status($pdo, $staff['staff_id'], 'staff', $device_hash);
                    if ($status === 'rejected') {
                        $device_status = 'rejected';
                    } elseif ($status === 'pending') {
                        $device_status = 'pending';
                    } else {
                        $pdo->prepare("
                            INSERT INTO device_requests (user_id, user_type, device_hash, ip_address, user_agent, browser, status)
                            VALUES (?, 'staff', ?, ?, ?, ?, 'pending')
                            ON DUPLICATE KEY UPDATE status = 'pending', created_at = NOW()
                        ")->execute([$staff['staff_id'], $device_hash, $ip, $ua, $browser]);
                        $_SESSION['pending_user_id']   = $staff['staff_id'];
                        $_SESSION['pending_user_type'] = 'staff';
                        $device_status = 'pending';
                    }
                }
            } else {
                $attempts++;

                if ($attempts >= $MAX_ATTEMPTS) {
                    $ban_until      = time() + $BAN_SECONDS;
                    $isBanned       = true;
                    $banSecondsLeft = $BAN_SECONDS;

                    $pdo->prepare("
                        INSERT INTO login_attempts (ip_address, device_hash, type, attempts, ban_until)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE attempts = ?, ban_until = ?
                    ")->execute([$ip, $device_hash, $type, $attempts, $ban_until, $attempts, $ban_until]);

                    header('Location: /plant/login.php?banned=1');
                    exit();
                } else {
                    $error = 'Invalid username or password.';

                    $pdo->prepare("
                        INSERT INTO login_attempts (ip_address, device_hash, type, attempts, ban_until)
                        VALUES (?, ?, ?, ?, 0)
                        ON DUPLICATE KEY UPDATE attempts = ?
                    ")->execute([$ip, $device_hash, $type, $attempts, $attempts]);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
<style>
.blocked-hint { font-size:11px; color:#dc3545; margin-top:4px; display:none; }
.blocked-hint.show { display:block; }
</style>
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">

<div class="card border-0 shadow-sm" style="width:100%;max-width:380px;">
  <div class="card-body p-5">

    <h5 class="fw-bold text-dark mb-1">Sign in</h5>
    <p class="text-muted small mb-4">Enter your credentials to continue.</p>

    <?php if ($isBanned): ?>
    <div class="alert alert-danger py-2 small text-center">
        <i class="fas fa-ban me-1"></i>
        <strong>Try again in <span id="countdown"><?= $banSecondsLeft ?></span> second<?= $banSecondsLeft !== 1 ? 's' : '' ?>.</strong>
    </div>

    <?php elseif ($device_status === 'pending'): ?>
    <div class="alert alert-warning border-0 rounded-3 mb-3">
        <div class="d-flex align-items-center gap-2 mb-1">
            <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
            <strong>Waiting for Approval</strong>
        </div>
        <p class="mb-0 small">This device is not yet trusted. Your request has been sent to the admin. Please wait for approval before trying again.</p>
    </div>
    <input type="hidden" id="poll_user_id" value="<?= htmlspecialchars($_SESSION['pending_user_id'] ?? '') ?>">
    <input type="hidden" id="poll_user_type" value="<?= htmlspecialchars($_SESSION['pending_user_type'] ?? '') ?>">
    <input type="hidden" id="poll_device_hash" value="<?= htmlspecialchars($device_hash) ?>">

    <?php elseif ($device_status === 'rejected'): ?>
    <div class="alert alert-danger border-0 rounded-3 mb-3">
        <div class="d-flex align-items-center gap-2 mb-1">
            <i class="fas fa-ban"></i>
            <strong>Device Rejected</strong>
        </div>
        <p class="mb-0 small">This device has been rejected by the admin. Please contact your administrator.</p>
    </div>

    <?php elseif ($timeout): ?>
    <div class="alert alert-warning py-2 small"><i class="fas fa-clock me-1"></i> Session expired.</div>

    <?php elseif (isset($_GET['reset'])): ?>
    <div class="alert alert-success py-2 small"><i class="fas fa-check-circle me-1"></i> Password reset successfully.</div>

    <?php elseif ($error): ?>
    <div class="alert alert-danger py-2 small"><i class="fas fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$device_status): ?>
    <form method="POST" action="" autocomplete="off" novalidate>
      <div class="mb-3">
        <label for="username" class="form-label small fw-semibold text-secondary">Username</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="fas fa-user text-secondary small"></i></span>
          <input type="text" class="form-control border-start-0 ps-0"
            id="username" name="username"
            autocomplete="off" maxlength="50" required autofocus
            <?= $isBanned ? 'disabled' : '' ?>
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="blocked-hint" id="hint-username"><i class="fas fa-triangle-exclamation me-1"></i> Special characters are not allowed.</div>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="fas fa-lock text-secondary small"></i></span>
          <input type="password" class="form-control border-start-0 ps-0 border-end-0"
            id="password" name="password"
            autocomplete="off" maxlength="128" required
            <?= $isBanned ? 'disabled' : '' ?>>
          <button type="button" class="input-group-text bg-white border-start-0" onclick="togglePw()" tabindex="-1" <?= $isBanned ? 'disabled' : '' ?>>
            <i class="fas fa-eye text-secondary small" id="eye-icon"></i>
          </button>
        </div>
        <div class="blocked-hint" id="hint-password"><i class="fas fa-triangle-exclamation me-1"></i> Special characters are not allowed.</div>
      </div>

      <div class="text-center mb-4">
        <a href="/plant/forgot_password.php" class="text-muted small text-decoration-none">
            <i class="fas fa-key me-1"></i>Forgot password?
        </a>
      </div>

      <button type="submit" class="btn btn-success w-100 fw-semibold" <?= $isBanned ? 'disabled' : '' ?>>
        <i class="fas fa-right-to-bracket me-2"></i><?= $isBanned ? 'Account Locked' : 'Login' ?>
      </button>
    </form>
    <?php elseif ($device_status === 'pending'): ?>
    <div class="text-center mt-2">
        <a href="/plant/login.php" class="text-muted small text-decoration-none">
            <i class="fas fa-arrow-left me-1"></i>Try a different account
        </a>
    </div>
    <?php endif; ?>

  </div>
</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
<script>
function togglePw() {
    const i = document.getElementById('password');
    const e = document.getElementById('eye-icon');
    const s = i.type === 'password';
    i.type = s ? 'text' : 'password';
    e.className = s ? 'fas fa-eye-slash text-secondary small' : 'fas fa-eye text-secondary small';
}

<?php if ($isBanned): ?>
(function() {
    let secs = <?= $banSecondsLeft ?>;
    const c1 = document.getElementById('countdown');
    const c2 = document.getElementById('countdown2');
    const timer = setInterval(function() {
        secs--;
        if (c1) c1.textContent = secs;
        if (c2) c2.textContent = secs;
        if (secs <= 0) { clearInterval(timer); location.href = '/plant/login.php'; }
    }, 1000);
})();
<?php endif; ?>

const blocked = ["'", '"', ';', '--', '<', '>', '\\', '=', '`', '|', '&', '%', '#', '/'];
function stripBlocked(val) {
    let out = val;
    blocked.forEach(c => { out = out.split(c).join(''); });
    return out;
}
function attachGuard(inputId, hintId) {
    const input = document.getElementById(inputId);
    const hint  = document.getElementById(hintId);
    if (!input) return;
    input.addEventListener('input', function() {
        const orig    = this.value;
        const cleaned = stripBlocked(orig);
        if (cleaned !== orig) {
            this.value = cleaned;
            hint.classList.add('show');
            setTimeout(() => hint.classList.remove('show'), 2000);
        }
    });
    input.addEventListener('paste', function(e) {
        e.preventDefault();
        let text    = (e.clipboardData || window.clipboardData).getData('text');
        const clean = stripBlocked(text);
        this.value += clean;
        if (clean !== text) {
            hint.classList.add('show');
            setTimeout(() => hint.classList.remove('show'), 2000);
        }
    });
}
attachGuard('username', 'hint-username');
attachGuard('password', 'hint-password');
</script>
<?php if ($device_status === 'pending'): ?>
<script>
(function() {
    const userId     = document.getElementById('poll_user_id')?.value;
    const userType   = document.getElementById('poll_user_type')?.value;
    const deviceHash = document.getElementById('poll_device_hash')?.value;
    if (!userId || !userType || !deviceHash) return;

    const interval = setInterval(function() {
        const fd = new FormData();
        fd.append('user_id',     userId);
        fd.append('user_type',   userType);
        fd.append('device_hash', deviceHash);

        fetch('/plant/check_approval.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'approved' && data.redirect) {
                    clearInterval(interval);
                    window.location.href = data.redirect;
                } else if (data.status === 'rejected') {
                    clearInterval(interval);
                    window.location.reload();
                }
            })
            .catch(() => {});
    }, 3000);
})();
</script>
<?php endif; ?>
</body>
</html>