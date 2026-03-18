<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
require_once __DIR__ . '/../includes/staff/auth.php';
require_once __DIR__ . '/../config/config.php';

if (!empty($_SESSION['staff_logged_in'])) {
    header('Location: /plant/staff/dashboard.php');
    exit();
}

$MAX_ATTEMPTS = 5;
$BAN_SECONDS  = 20;
$type         = 'plant_staff';

$ip          = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua          = $_SERVER['HTTP_USER_AGENT']      ?? '';
$lang        = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$encoding    = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
$device_hash = hash('sha256', $ua . $lang . $encoding);

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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isBanned) {
        $error = "Too many failed attempts. Try again in {$banSecondsLeft} second(s).";
    } elseif (!staff_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please fill in all fields.';
        } elseif (!is_safe_input($username) || !is_safe_input($password)) {
            $error = 'Invalid characters detected in input.';
        } else {
            $stmt = $pdo->prepare("SELECT staff_id, firstname, lastname, username, password FROM staff_info WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $staff = $stmt->fetch();

            if ($staff && password_verify($password, $staff['password'])) {
                $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND device_hash = ? AND type = ?")
                    ->execute([$ip, $device_hash, $type]);

                staff_regenerate_session();
                $_SESSION['staff_logged_in']     = true;
                $_SESSION['staff_id']            = $staff['staff_id'];
                $_SESSION['staff_username']      = $staff['username'];
                $_SESSION['staff_firstname']     = $staff['firstname'];
                $_SESSION['staff_lastname']      = $staff['lastname'];
                $_SESSION['staff_last_activity'] = time();
                header('Location: /plant/staff/dashboard.php');
                exit();
            } else {
                $attempts++;

                if ($attempts >= $MAX_ATTEMPTS) {
                    $ban_until      = time() + $BAN_SECONDS;
                    $isBanned       = true;
                    $banSecondsLeft = $BAN_SECONDS;
                    $error          = "Too many failed attempts. Try again in {$BAN_SECONDS} second(s).";

                    $pdo->prepare("
                        INSERT INTO login_attempts (ip_address, device_hash, type, attempts, ban_until)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE attempts = ?, ban_until = ?
                    ")->execute([$ip, $device_hash, $type, $attempts, $ban_until, $attempts, $ban_until]);
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

$csrf_token = staff_generate_csrf_token();
$timeout    = isset($_GET['timeout']);
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

    <h5 class="fw-bold text-dark mb-1">Staff Sign in</h5>
    <p class="text-muted small mb-4">Enter your credentials to continue.</p>

    <?php if ($isBanned): ?>
    <div class="alert alert-danger py-2 small text-center">
        <i class="fas fa-ban me-1"></i> Too many failed attempts.<br>
        <strong>Try again in <span id="countdown"><?= $banSecondsLeft ?></span> second<?= $banSecondsLeft !== 1 ? 's' : '' ?>.</strong>
    </div>
    <?php elseif ($timeout): ?>
    <div class="alert alert-warning py-2 small"><i class="fas fa-clock me-1"></i> Session expired.</div>
    <?php elseif ($error): ?>
    <div class="alert alert-danger py-2 small"><i class="fas fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="off" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

      <div class="mb-3">
        <label for="username" class="form-label small fw-semibold text-secondary">Username</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="fas fa-user text-secondary small"></i></span>
          <input type="text" class="form-control border-start-0 ps-0"
            id="username" name="username"
            autocomplete="off" maxlength="50" required
            <?= $isBanned ? 'disabled' : '' ?>
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="blocked-hint" id="hint-username"><i class="fas fa-triangle-exclamation me-1"></i> Special characters are not allowed.</div>
      </div>

      <div class="mb-4">
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

      <button type="submit" class="btn btn-success w-100 fw-semibold" <?= $isBanned ? 'disabled' : '' ?>>
        <i class="fas fa-right-to-bracket me-2"></i><?= $isBanned ? 'Account Locked' : 'Login' ?>
      </button>
    </form>
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
        if (secs <= 0) { clearInterval(timer); location.reload(); }
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
</body>
</html>