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

$step  = (int)($_SESSION['fp_step'] ?? 1);
$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['fp_step1'])) {
        $username = trim($_POST['username'] ?? '');

        if (!is_safe_input($username) || empty($username)) {
            $error = 'Invalid username.';
        } else {
            $found_id   = null;
            $found_type = null;

            $s = $pdo->prepare("SELECT admin_id FROM admin WHERE username = ? LIMIT 1");
            $s->execute([$username]);
            $a = $s->fetch();
            if ($a) {
                $found_id   = $a['admin_id'];
                $found_type = 'admin';
            }

            if (!$found_id) {
                $s = $pdo->prepare("SELECT staff_id FROM staff_info WHERE username = ? LIMIT 1");
                $s->execute([$username]);
                $sf = $s->fetch();
                if ($sf) {
                    $found_id   = $sf['staff_id'];
                    $found_type = 'staff';
                }
            }

            if (!$found_id) {
                $error = 'Username not found.';
            } else {
                $s = $pdo->prepare("SELECT sa.question_id, sq.question FROM security_answer sa JOIN security_questions sq ON sa.question_id = sq.id WHERE sa.user_id = ? AND sa.user_type = ?");
                $s->execute([$found_id, $found_type]);
                $sq = $s->fetch();

                if (!$sq) {
                    $error = 'No security question set for this account. Please contact the administrator.';
                } else {
                    $_SESSION['fp_step']      = 2;
                    $_SESSION['fp_user_id']   = $found_id;
                    $_SESSION['fp_user_type'] = $found_type;
                    $_SESSION['fp_username']  = $username;
                    $_SESSION['fp_question']  = $sq['question'];
                    $step = 2;
                }
            }
        }
    }

    elseif (isset($_POST['fp_step2'])) {
        if (empty($_SESSION['fp_user_id'])) {
            session_unset();
            header('Location: /plant/forgot_password.php');
            exit();
        }

        $answer = trim($_POST['sq_answer'] ?? '');

        if (empty($answer)) {
            $error = 'Please enter your answer.';
            $step  = 2;
        } else {
            $s = $pdo->prepare("SELECT answer_hash FROM security_answer WHERE user_id = ? AND user_type = ?");
            $s->execute([$_SESSION['fp_user_id'], $_SESSION['fp_user_type']]);
            $row = $s->fetch();

            if (!$row || !password_verify(strtolower($answer), $row['answer_hash'])) {
                $error = 'Incorrect answer. Please try again.';
                $step  = 2;
            } else {
                $_SESSION['fp_step']     = 3;
                $_SESSION['fp_verified'] = true;
                $step = 3;
            }
        }
    }

    elseif (isset($_POST['fp_step3'])) {
        if (empty($_SESSION['fp_verified']) || empty($_SESSION['fp_user_id'])) {
            session_unset();
            header('Location: /plant/forgot_password.php');
            exit();
        }

        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!is_safe_input($new) || !is_safe_input($confirm)) {
            $error = 'Invalid characters detected in input.';
            $step  = 3;
        } elseif (empty($new)) {
            $error = 'New password cannot be empty.';
            $step  = 3;
        } elseif (strlen($new) < 6) {
            $error = 'Password must be at least 6 characters.';
            $step  = 3;
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
            $step  = 3;
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);

            if ($_SESSION['fp_user_type'] === 'admin') {
                $pdo->prepare("UPDATE admin SET password = ? WHERE admin_id = ?")
                    ->execute([$hash, $_SESSION['fp_user_id']]);
            } else {
                $pdo->prepare("UPDATE staff_info SET password = ? WHERE staff_id = ?")
                    ->execute([$hash, $_SESSION['fp_user_id']]);
            }

            session_unset();
            header('Location: /plant/login.php?reset=1');
            exit();
        }
    }

    elseif (isset($_POST['fp_back'])) {
        $back_to = (int)($_POST['fp_back']);
        if ($back_to === 1) {
            session_unset();
            $_SESSION['fp_step'] = 1;
            $step = 1;
        } elseif ($back_to === 2) {
            $_SESSION['fp_step'] = 2;
            unset($_SESSION['fp_verified']);
            $step = 2;
        }
    }
}

if ($step === 2 && empty($_SESSION['fp_user_id'])) {
    $step = 1;
    $_SESSION['fp_step'] = 1;
}
if ($step === 3 && empty($_SESSION['fp_verified'])) {
    $step = 2;
    $_SESSION['fp_step'] = 2;
}

$fp_question = $_SESSION['fp_question'] ?? '';
$fp_username = $_SESSION['fp_username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
<style>
.blocked-hint { font-size:11px; color:#dc3545; margin-top:4px; display:none; }
.blocked-hint.show { display:block; }
.step-indicator { display:flex; align-items:center; gap:0; margin-bottom:28px; }
.step-dot { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; flex-shrink:0; }
.step-dot.done { background:#198754; color:#fff; }
.step-dot.active { background:#0d6efd; color:#fff; }
.step-dot.idle { background:#e9ecef; color:#adb5bd; }
.step-line { flex:1; height:2px; }
.step-line.done { background:#198754; }
.step-line.idle { background:#e9ecef; }
</style>
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">

<div class="card border-0 shadow-sm" style="width:100%;max-width:400px;">
  <div class="card-body p-5">

    <div class="mb-1">
      <a href="/plant/login.php" class="text-muted small text-decoration-none">
        <i class="fas fa-arrow-left me-1"></i>Back to login
      </a>
    </div>

    <h5 class="fw-bold text-dark mb-1 mt-3">Forgot Password</h5>
    <p class="text-muted small mb-4">Reset your password using your security question.</p>

    <div class="step-indicator">
      <div class="step-dot <?= $step > 1 ? 'done' : ($step === 1 ? 'active' : 'idle') ?>">
        <?= $step > 1 ? '<i class="fas fa-check" style="font-size:10px;"></i>' : '1' ?>
      </div>
      <div class="step-line <?= $step > 1 ? 'done' : 'idle' ?>"></div>
      <div class="step-dot <?= $step > 2 ? 'done' : ($step === 2 ? 'active' : 'idle') ?>">
        <?= $step > 2 ? '<i class="fas fa-check" style="font-size:10px;"></i>' : '2' ?>
      </div>
      <div class="step-line <?= $step > 2 ? 'done' : 'idle' ?>"></div>
      <div class="step-dot <?= $step === 3 ? 'active' : 'idle' ?>">3</div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small border-0 rounded-3 mb-3">
      <i class="fas fa-circle-exclamation me-1"></i><?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <p class="small text-muted mb-3">Enter your username to get started.</p>
    <form method="POST" autocomplete="off" novalidate>
      <div class="mb-4">
        <label class="form-label small fw-semibold text-secondary">Username</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="fas fa-user text-secondary small"></i></span>
          <input type="text" name="username" class="form-control border-start-0 ps-0"
            id="fp_username" maxlength="50" required autofocus
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="blocked-hint" id="hint-username"><i class="fas fa-triangle-exclamation me-1"></i> Special characters are not allowed.</div>
      </div>
      <button type="submit" name="fp_step1" class="btn btn-primary w-100 fw-semibold">
        <i class="fas fa-arrow-right me-2"></i>Continue
      </button>
    </form>

    <?php elseif ($step === 2): ?>
    <p class="small text-muted mb-1">Answer your security question for <strong><?= htmlspecialchars($fp_username) ?></strong>.</p>
    <div style="background:#f0f5ff;border:1.5px solid #c7d9f8;border-radius:10px;padding:14px 16px;margin-bottom:20px;margin-top:14px;">
      <div style="font-size:12px;color:#6c757d;margin-bottom:4px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Security Question</div>
      <div style="font-size:14px;color:#1a1a2e;font-weight:500;"><?= htmlspecialchars($fp_question) ?></div>
    </div>
    <form method="POST" autocomplete="off" novalidate>
      <div class="mb-4">
        <label class="form-label small fw-semibold text-secondary">Your Answer</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="fas fa-shield-halved text-secondary small"></i></span>
          <input type="text" name="sq_answer" class="form-control border-start-0 ps-0"
            id="fp_answer" maxlength="100" required autofocus autocomplete="off">
        </div>
        <div class="blocked-hint" id="hint-answer"><i class="fas fa-triangle-exclamation me-1"></i> Special characters are not allowed.</div>
        <div style="font-size:11px;color:#6c757d;margin-top:5px;"><i class="fas fa-info-circle me-1"></i>Answer is case-insensitive.</div>
      </div>
      <button type="submit" name="fp_step2" class="btn btn-primary w-100 fw-semibold mb-2">
        <i class="fas fa-arrow-right me-2"></i>Verify Answer
      </button>
      <button type="submit" name="fp_back" value="1" class="btn btn-outline-secondary w-100 btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back
      </button>
    </form>

    <?php elseif ($step === 3): ?>
    <p class="small text-muted mb-3">Set a new password for <strong><?= htmlspecialchars($fp_username) ?></strong>.</p>
    <form method="POST" autocomplete="off" novalidate>
      <div class="mb-3">
        <label class="form-label small fw-semibold text-secondary">New Password</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="fas fa-lock text-secondary small"></i></span>
          <input type="password" name="new_password" class="form-control border-start-0 ps-0 border-end-0"
            id="fp_new_pw" maxlength="128" required autofocus autocomplete="new-password">
          <button type="button" class="input-group-text bg-white border-start-0" onclick="toggleFpPw('fp_new_pw','eye1')" tabindex="-1">
            <i class="fas fa-eye text-secondary small" id="eye1"></i>
          </button>
        </div>
        <div class="blocked-hint" id="hint-new"><i class="fas fa-triangle-exclamation me-1"></i> Special characters are not allowed.</div>
      </div>
      <div class="mb-4">
        <label class="form-label small fw-semibold text-secondary">Confirm New Password</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="fas fa-lock text-secondary small"></i></span>
          <input type="password" name="confirm_password" class="form-control border-start-0 ps-0 border-end-0"
            id="fp_confirm_pw" maxlength="128" required autocomplete="new-password">
          <button type="button" class="input-group-text bg-white border-start-0" onclick="toggleFpPw('fp_confirm_pw','eye2')" tabindex="-1">
            <i class="fas fa-eye text-secondary small" id="eye2"></i>
          </button>
        </div>
        <div class="blocked-hint" id="hint-confirm"><i class="fas fa-triangle-exclamation me-1"></i> Special characters are not allowed.</div>
      </div>
      <button type="submit" name="fp_step3" class="btn btn-success w-100 fw-semibold mb-2">
        <i class="fas fa-key me-2"></i>Reset Password
      </button>
      <button type="submit" name="fp_back" value="2" class="btn btn-outline-secondary w-100 btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back
      </button>
    </form>
    <?php endif; ?>

  </div>
</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
<script>
function toggleFpPw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    icon.className = show ? 'fas fa-eye-slash text-secondary small' : 'fas fa-eye text-secondary small';
}

const blocked = ["'", '"', ';', '--', '<', '>', '\\', '=', '`', '|', '&', '%', '#', '/'];
function stripBlocked(val) {
    let out = val;
    blocked.forEach(c => { out = out.split(c).join(''); });
    return out;
}
function attachGuard(inputId, hintId) {
    const input = document.getElementById(inputId);
    const hint  = document.getElementById(hintId);
    if (!input || !hint) return;
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
attachGuard('fp_username', 'hint-username');
attachGuard('fp_answer',   'hint-answer');
attachGuard('fp_new_pw',   'hint-new');
attachGuard('fp_confirm_pw', 'hint-confirm');
</script>
</body>
</html>