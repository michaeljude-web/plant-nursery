<?php
require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../config/config.php';
require_admin_auth();

$admin_id = $_SESSION['admin_id'];

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

$pw_success = $pw_error = '';
$sq_success = $sq_error = '';
$sq_confirm_mode = false;
$open_section = '';

$stmt_sq = $pdo->prepare("SELECT sa.*, sq.question FROM security_answer sa JOIN security_questions sq ON sa.question_id = sq.id WHERE sa.user_id = ? AND sa.user_type = 'admin'");
$stmt_sq->execute([$admin_id]);
$existing_sq = $stmt_sq->fetch();

$all_questions = $pdo->query("SELECT * FROM security_questions ORDER BY id ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'change_password') {
        $open_section = 'password';
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM admin WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch();

        if (!is_safe_input($current) || !is_safe_input($new) || !is_safe_input($confirm)) {
            $pw_error = 'Invalid characters detected in input.';
        } elseif (!password_verify($current, $admin['password'])) {
            $pw_error = 'Current password is incorrect.';
        } elseif (empty($new)) {
            $pw_error = 'New password cannot be empty.';
        } elseif ($new !== $confirm) {
            $pw_error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE admin SET password = ? WHERE admin_id = ?")
                ->execute([$hash, $admin_id]);
            $pw_success = 'Password updated successfully.';
        }
    }

    if ($_POST['action'] === 'set_security_question') {
        $open_section = 'security_question';
        $question_id  = (int)($_POST['question_id'] ?? 0);
        $answer       = trim($_POST['sq_answer'] ?? '');

        if ($question_id <= 0) {
            $sq_error = 'Please select a security question.';
        } elseif (empty($answer)) {
            $sq_error = 'Answer cannot be empty.';
        } elseif (strlen($answer) < 2) {
            $sq_error = 'Answer is too short.';
        } else {
            $answer_hash = password_hash(strtolower($answer), PASSWORD_DEFAULT);
            if ($existing_sq) {
                $pdo->prepare("UPDATE security_answer SET question_id = ?, answer_hash = ? WHERE user_id = ? AND user_type = 'admin'")
                    ->execute([$question_id, $answer_hash, $admin_id]);
            } else {
                $pdo->prepare("INSERT INTO security_answer (user_id, user_type, question_id, answer_hash) VALUES (?, 'admin', ?, ?)")
                    ->execute([$admin_id, $question_id, $answer_hash]);
            }
            $sq_success = 'Security question saved successfully.';
            $stmt_sq->execute([$admin_id]);
            $existing_sq = $stmt_sq->fetch();
        }
    }

    if ($_POST['action'] === 'confirm_reset_sq') {
        $open_section = 'security_question';
        $current_pw   = $_POST['confirm_password_reset'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM admin WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $admin_row = $stmt->fetch();

        if (!password_verify($current_pw, $admin_row['password'])) {
            $sq_error        = 'Incorrect password. Reset cancelled.';
            $sq_confirm_mode = false;
        } else {
            $pdo->prepare("DELETE FROM security_answer WHERE user_id = ? AND user_type = 'admin'")
                ->execute([$admin_id]);
            $existing_sq     = null;
            $sq_success      = 'Security question has been reset. Please set a new one.';
            $sq_confirm_mode = false;
        }
    }

    if ($_POST['action'] === 'request_reset_sq') {
        $open_section    = 'security_question';
        $sq_confirm_mode = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin/style.css">
<style>
.settings-item { border:1px solid #e9ecef; border-radius:12px; overflow:hidden; margin-bottom:12px; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.04); }
.settings-trigger { display:flex; align-items:center; justify-content:space-between; padding:18px 22px; cursor:pointer; user-select:none; transition:background .15s; }
.settings-trigger:hover { background:#f8f9fa; }
.settings-trigger-left { display:flex; align-items:center; gap:14px; }
.settings-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.settings-title { font-weight:600; font-size:15px; color:#1a1a2e; margin:0; }
.settings-subtitle { font-size:12px; color:#6c757d; margin:0; }
.settings-chevron { color:#adb5bd; transition:transform .25s; font-size:13px; }
.settings-chevron.open { transform:rotate(180deg); }
.settings-body { padding:0 22px; max-height:0; overflow:hidden; transition:max-height .35s ease, padding .35s ease; }
.settings-body.open { max-height:900px; padding:4px 22px 22px; }
.divider { height:1px; background:#f0f0f0; margin:0 22px; }
.form-label { font-size:12px; font-weight:600; color:#6c757d; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
.form-control { border-radius:8px; border:1.5px solid #e2e8f0; font-size:14px; padding:9px 12px; }
.form-control:focus { border-color:#0d6efd; box-shadow:0 0 0 3px rgba(13,110,253,.08); }
.btn-save { padding:9px 20px; border-radius:8px; font-size:14px; font-weight:500; }
.sq-set-badge { font-size:11px; padding:2px 8px; border-radius:20px; background:#d1fae5; color:#065f46; font-weight:500; margin-left:6px; }
.confirm-box { background:#fff8f0; border:1.5px solid #ffc107; border-radius:10px; padding:16px 18px; margin-bottom:16px; }
</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin/sidebar.php'; ?>

<div id="content">
  <div id="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light d-md-none" id="toggler"><i class="fas fa-bars"></i></button>
      <span class="fw-semibold text-dark">Settings</span>
    </div>
    <small class="text-muted"><?= date('D, M d Y') ?></small>
  </div>

  <div class="p-4">
    <div class="mb-4">
        <h5 class="fw-semibold mb-0">Account Settings</h5>
    </div>

    <div style="max-width:640px;">

        <div class="settings-item">
            <div class="settings-trigger" onclick="toggleSection('password')">
                <div class="settings-trigger-left">
                    <div class="settings-icon bg-primary bg-opacity-10 text-white">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div>
                        <p class="settings-title">Change Password</p>
                        <p class="settings-subtitle">Update your account password</p>
                    </div>
                </div>
                <i class="fas fa-chevron-down settings-chevron" id="chevron-password"></i>
            </div>
            <div class="divider"></div>
            <div class="settings-body" id="body-password">
                <div class="pt-4">
                    <?php if ($pw_success): ?>
                    <div class="alert alert-success py-2 small border-0 rounded-3"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($pw_success) ?></div>
                    <?php elseif ($pw_error): ?>
                    <div class="alert alert-danger py-2 small border-0 rounded-3"><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($pw_error) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" name="current_password" class="form-control border-end-0" required>
                                <button type="button" class="btn btn-outline-secondary border-start-0" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="togglePw('current_password',this)"><i class="fas fa-eye fa-sm"></i></button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" class="form-control border-end-0" required>
                                <button type="button" class="btn btn-outline-secondary border-start-0" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="togglePw('new_password',this)"><i class="fas fa-eye fa-sm"></i></button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" class="form-control border-end-0" required>
                                <button type="button" class="btn btn-outline-secondary border-start-0" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="togglePw('confirm_password',this)"><i class="fas fa-eye fa-sm"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-save"><i class="fas fa-save me-1"></i> Update Password</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="settings-item">
            <div class="settings-trigger" onclick="toggleSection('security_question')">
                <div class="settings-trigger-left">
                    <div class="settings-icon bg-success bg-opacity-10 text-white">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div>
                        <p class="settings-title">
                            Security Question
                            <?php if ($existing_sq): ?>
                            <span class="sq-set-badge"><i class="fas fa-check me-1"></i>Set</span>
                            <?php endif; ?>
                        </p>
                        <p class="settings-subtitle">
                            <?= $existing_sq
                                ? 'Your security question is configured'
                                : 'No security question set yet' ?>
                        </p>
                    </div>
                </div>
                <i class="fas fa-chevron-down settings-chevron" id="chevron-security_question"></i>
            </div>
            <div class="divider"></div>
            <div class="settings-body" id="body-security_question">
                <div class="pt-4">

                    <?php if ($sq_success): ?>
                    <div class="alert alert-success py-2 small border-0 rounded-3"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($sq_success) ?></div>
                    <?php elseif ($sq_error): ?>
                    <div class="alert alert-danger py-2 small border-0 rounded-3"><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($sq_error) ?></div>
                    <?php endif; ?>

                    <?php if ($existing_sq && !$sq_confirm_mode && !$sq_success): ?>
                    <div class="confirm-box mb-3">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fas fa-circle-info text-warning mt-1"></i>
                            <div>
                                <div class="fw-semibold" style="font-size:13px;color:#92400e;">Current security question</div>
                                <div style="font-size:13px;color:#1a1a2e;margin-top:4px;"><?= htmlspecialchars($existing_sq['question']) ?></div>
                                <div style="font-size:12px;color:#6c757d;margin-top:2px;">Answer is hidden.</div>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-3">To change your security question, you must first confirm your password.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="request_reset_sq">
                        <button type="submit" class="btn btn-warning btn-save text-dark">
                            <i class="fas fa-rotate-right me-1"></i> Reset Security Question
                        </button>
                    </form>

                    <?php elseif ($sq_confirm_mode): ?>
                    <div class="confirm-box mb-3">
                        <div class="fw-semibold mb-1" style="font-size:13px;color:#92400e;"><i class="fas fa-triangle-exclamation me-1"></i> Confirm your identity</div>
                        <div style="font-size:12px;color:#6c757d;">Enter your current password to reset your security question.</div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="confirm_reset_sq">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password_reset" class="form-control border-end-0" required autofocus>
                                <button type="button" class="btn btn-outline-secondary border-start-0" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="togglePwById('confirm_password_reset',this)"><i class="fas fa-eye fa-sm"></i></button>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger btn-save">
                                <i class="fas fa-trash me-1"></i> Confirm Reset
                            </button>
                            <a href="settings.php" class="btn btn-outline-secondary btn-save">Cancel</a>
                        </div>
                    </form>

                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="set_security_question">
                        <div class="mb-3">
                            <label class="form-label">Security Question</label>
                            <select name="question_id" class="form-control" required>
                                <option value="">— Select a question —</option>
                                <?php foreach ($all_questions as $q): ?>
                                <option value="<?= $q['id'] ?>"><?= htmlspecialchars($q['question']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Your Answer</label>
                            <input type="text" name="sq_answer" class="form-control" placeholder="Enter your answer" required autocomplete="off">
                            <div style="font-size:11px;color:#6c757d;margin-top:5px;"><i class="fas fa-info-circle me-1"></i>Answer is case-insensitive and stored securely.</div>
                        </div>
                        <button type="submit" class="btn btn-success btn-save">
                            <i class="fas fa-save me-1"></i> Save
                        </button>
                    </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>

    </div>
  </div>
</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('toggler')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('show');
});

function toggleSection(id) {
    const body    = document.getElementById('body-' + id);
    const chevron = document.getElementById('chevron-' + id);
    const isOpen  = body.classList.contains('open');
    document.querySelectorAll('.settings-body').forEach(b => b.classList.remove('open'));
    document.querySelectorAll('.settings-chevron').forEach(c => c.classList.remove('open'));
    if (!isOpen) {
        body.classList.add('open');
        chevron.classList.add('open');
    }
}

function togglePw(fieldName, btn) {
    const input = document.querySelector('[name="' + fieldName + '"]');
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function togglePwById(fieldName, btn) {
    togglePw(fieldName, btn);
}

<?php if ($open_section): ?>
document.addEventListener('DOMContentLoaded', () => { toggleSection('<?= $open_section ?>'); });
<?php endif; ?>

const blocked = ["'", '"', ';', '--', '<', '>', '\\', '=', '`', '|', '&', '%', '#', '/'];
function stripBlocked(val) {
    let out = val; blocked.forEach(c => { out = out.split(c).join(''); }); return out;
}
document.querySelectorAll('input[type=text], input[type=password]').forEach(input => {
    input.addEventListener('input', function() {
        const c = stripBlocked(this.value);
        if (c !== this.value) this.value = c;
    });
    input.addEventListener('paste', function(e) {
        e.preventDefault();
        this.value += stripBlocked((e.clipboardData || window.clipboardData).getData('text'));
    });
});
</script>
</body>
</html>