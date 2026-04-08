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

function get_browser_name($ua) {
    if (!$ua) return 'Unknown';
    if (str_contains($ua, 'Edg'))     return 'Microsoft Edge';
    if (str_contains($ua, 'OPR'))     return 'Opera';
    if (str_contains($ua, 'Chrome'))  return 'Chrome';
    if (str_contains($ua, 'Firefox')) return 'Firefox';
    if (str_contains($ua, 'Safari'))  return 'Safari';
    return 'Unknown Browser';
}

function get_os_name($ua) {
    if (!$ua) return 'Unknown OS';
    if (str_contains($ua, 'Windows NT 10')) return 'Windows 10/11';
    if (str_contains($ua, 'Windows NT 6.3')) return 'Windows 8.1';
    if (str_contains($ua, 'Windows NT 6.1')) return 'Windows 7';
    if (str_contains($ua, 'Windows'))       return 'Windows';
    if (str_contains($ua, 'Mac OS X'))      return 'macOS';
    if (str_contains($ua, 'iPhone'))        return 'iPhone';
    if (str_contains($ua, 'iPad'))          return 'iPad';
    if (str_contains($ua, 'Android'))       return 'Android';
    if (str_contains($ua, 'Linux'))         return 'Linux';
    return 'Unknown OS';
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

    if ($_POST['action'] === 'remove_device') {
        $device_id = (int)($_POST['device_id'] ?? 0);
        if ($device_id) {
            $row = $pdo->prepare("SELECT user_id, user_type FROM trusted_devices WHERE id = ?");
            $row->execute([$device_id]);
            $dev = $row->fetch();

            $pdo->prepare("DELETE FROM trusted_devices WHERE id = ?")
                ->execute([$device_id]);

            if ($dev) {
                $pdo->prepare("DELETE FROM login_sessions WHERE user_id = ? AND user_type = ?")
                    ->execute([$dev['user_id'], $dev['user_type']]);
            }
        }
        header('Location: settings.php?removed=1');
        exit();
    }

    if ($_POST['action'] === 'approve_device') {
        $request_id = (int)($_POST['request_id'] ?? 0);
        if ($request_id) {
            $req = $pdo->prepare("SELECT * FROM device_requests WHERE id = ?");
            $req->execute([$request_id]);
            $r = $req->fetch();
            if ($r) {
                $pdo->prepare("
                    INSERT INTO trusted_devices (user_id, user_type, device_hash, ip_address, user_agent, browser, is_approved)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE is_approved = 1, ip_address = ?, browser = ?
                ")->execute([$r['user_id'], $r['user_type'], $r['device_hash'], $r['ip_address'], $r['user_agent'], $r['browser'], $r['ip_address'], $r['browser']]);
                $pdo->prepare("UPDATE device_requests SET status = 'approved' WHERE id = ?")
                    ->execute([$request_id]);
            }
        }
        header('Location: settings.php?approved=1#devices');
        exit();
    }

    if ($_POST['action'] === 'reject_device') {
        $request_id = (int)($_POST['request_id'] ?? 0);
        if ($request_id) {
            $pdo->prepare("UPDATE device_requests SET status = 'rejected' WHERE id = ?")
                ->execute([$request_id]);
        }
        header('Location: settings.php?rejected=1#devices');
        exit();
    }

    if ($_POST['action'] === 'unblock_device') {
        $request_id = (int)($_POST['request_id'] ?? 0);
        if ($request_id) {
            $pdo->prepare("DELETE FROM device_requests WHERE id = ?")
                ->execute([$request_id]);
        }
        header('Location: settings.php?unblocked=1');
        exit();
    }
}

$ua           = $_SERVER['HTTP_USER_AGENT']      ?? '';
$lang         = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$encoding     = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
$current_hash = hash('sha256', $ua . $lang . $encoding);

$stmt = $pdo->prepare("SELECT * FROM trusted_devices WHERE user_id = ? AND user_type = 'admin' ORDER BY created_at DESC");
$stmt->execute([$admin_id]);
$devices = $stmt->fetchAll();

if (empty($devices)) {
    $pdo->prepare("
        INSERT INTO trusted_devices (user_id, user_type, device_hash, ip_address, user_agent, browser, is_approved)
        VALUES (?, 'admin', ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE is_approved = 1
    ")->execute([$admin_id, $current_hash, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $ua, get_browser_name($ua)]);

    $stmt = $pdo->prepare("SELECT * FROM trusted_devices WHERE user_id = ? AND user_type = 'admin' ORDER BY created_at DESC");
    $stmt->execute([$admin_id]);
    $devices = $stmt->fetchAll();
}

$pending_requests = $pdo->query("
    SELECT dr.*,
        CASE dr.user_type
            WHEN 'admin' THEN (SELECT username FROM admin WHERE admin_id = dr.user_id)
            WHEN 'staff' THEN (SELECT username FROM staff_info WHERE staff_id = dr.user_id)
        END as username
    FROM device_requests dr
    WHERE dr.status = 'pending'
    ORDER BY dr.created_at DESC
")->fetchAll();

$blocked_devices = $pdo->query("
    SELECT dr.*,
        CASE dr.user_type
            WHEN 'admin' THEN (SELECT username FROM admin WHERE admin_id = dr.user_id)
            WHEN 'staff' THEN (SELECT username FROM staff_info WHERE staff_id = dr.user_id)
        END as username
    FROM device_requests dr
    WHERE dr.status = 'rejected'
    ORDER BY dr.created_at DESC
")->fetchAll();
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
.device-card { border:1px solid #e9ecef; border-radius:10px; padding:14px 16px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
.device-card.current { border-color:#0d6efd; background:#f0f5ff; }
.device-browser { font-weight:600; font-size:14px; color:#1a1a2e; }
.device-meta { font-size:12px; color:#6c757d; margin-top:3px; }
.this-badge { font-size:11px; padding:2px 8px; border-radius:20px; background:#e8f4fd; color:#0d6efd; font-weight:500; margin-left:6px; }
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

    <?php if (isset($_GET['removed'])): ?>
    <div class="alert alert-success py-2 small mb-3 rounded-3 border-0"><i class="fas fa-check-circle me-1"></i> Device removed successfully.</div>
    <?php elseif (isset($_GET['approved'])): ?>
    <div class="alert alert-success py-2 small mb-3 rounded-3 border-0"><i class="fas fa-check-circle me-1"></i> Device approved successfully.</div>
    <?php elseif (isset($_GET['rejected'])): ?>
    <div class="alert alert-secondary py-2 small mb-3 rounded-3 border-0"><i class="fas fa-times-circle me-1"></i> Device rejected.</div>
    <?php elseif (isset($_GET['unblocked'])): ?>
    <div class="alert alert-info py-2 small mb-3 rounded-3 border-0"><i class="fas fa-unlock me-1"></i> Device unblocked — user can request again.</div>
    <?php endif; ?>

    <?php if (!empty($pending_requests)): ?>
    <div class="settings-item mb-3" id="devices">
        <div class="settings-trigger" onclick="toggleSection('pending')">
            <div class="settings-trigger-left">
                <div class="settings-icon bg-warning bg-opacity-10 text-white">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <p class="settings-title">Pending Device Requests
                        <span class="badge bg-danger ms-1" style="font-size:11px;"><?= count($pending_requests) ?></span>
                    </p>
                    <p class="settings-subtitle">Devices waiting for approval</p>
                </div>
            </div>
            <i class="fas fa-chevron-down settings-chevron open" id="chevron-pending"></i>
        </div>
        <div class="divider"></div>
        <div class="settings-body open" id="body-pending">
            <div class="pt-4">
                <?php foreach ($pending_requests as $r): ?>
                <div class="device-card" style="border-left:3px solid #ffc107;">
                    <div style="flex:1;min-width:0;">
                        <div class="device-browser">
                            <i class="fas fa-globe me-1 text-muted"></i>
                            <?= htmlspecialchars($r['browser'] ?: 'Unknown Browser') ?>
                            &nbsp;·&nbsp;
                            <i class="fas fa-desktop me-1 text-muted"></i>
                            <?= htmlspecialchars(get_os_name($r['user_agent'] ?? '')) ?>
                            <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:#fff3cd;color:#856404;font-weight:500;margin-left:6px;"><?= strtoupper($r['user_type']) ?></span>
                        </div>
                        <div class="device-meta">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($r['username'] ?? 'Unknown') ?>
                            &nbsp;·&nbsp;
                            <i class="fas fa-network-wired me-1"></i><?= htmlspecialchars($r['ip_address']) ?>
                            &nbsp;·&nbsp;
                            <i class="fas fa-clock me-1"></i><?= date('M d, Y h:i A', strtotime($r['created_at'])) ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-shrink-0">
                        <form method="POST">
                            <input type="hidden" name="action" value="approve_device">
                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="fas fa-check me-1"></i>Approve
                            </button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="action" value="reject_device">
                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-times me-1"></i>Reject
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

        <div class="settings-item">
            <div class="settings-trigger" onclick="toggleSection('devices')">
                <div class="settings-trigger-left">
                    <div class="settings-icon bg-info bg-opacity-10 text-white">
                        <i class="fas fa-laptop"></i>
                    </div>
                    <div>
                        <p class="settings-title">Trusted Devices</p>
                        <p class="settings-subtitle"><?= count($devices) ?> device<?= count($devices) !== 1 ? 's' : '' ?> approved</p>
                    </div>
                </div>
                <i class="fas fa-chevron-down settings-chevron" id="chevron-devices"></i>
            </div>
            <div class="divider"></div>
            <div class="settings-body" id="body-devices">
                <div class="pt-4">
                    <?php if (empty($devices)): ?>
                    <p class="text-muted small">No trusted devices yet.</p>
                    <?php else: ?>
                    <?php foreach ($devices as $d):
                        $is_current = $d['device_hash'] === $current_hash;
                    ?>
                    <div class="device-card <?= $is_current ? 'current' : '' ?>">
                        <div style="flex:1;min-width:0;">
                            <div class="device-browser">
                                <i class="fas fa-globe me-1 text-muted"></i>
                                <?= htmlspecialchars(get_browser_name($d['user_agent'])) ?>
                                &nbsp;·&nbsp;
                                <i class="fas fa-desktop me-1 text-muted"></i>
                                <?= htmlspecialchars(get_os_name($d['user_agent'])) ?>
                                <?php if ($is_current): ?>
                                <span class="this-badge">This device</span>
                                <?php endif; ?>
                            </div>
                            <div class="device-meta">
                                <i class="fas fa-network-wired me-1"></i><?= htmlspecialchars($d['ip_address']) ?>
                                &nbsp;·&nbsp;
                                <i class="fas fa-clock me-1"></i><?= date('M d, Y h:i A', strtotime($d['created_at'])) ?>
                            </div>
                        </div>
                        <?php if (!$is_current): ?>
                        <form method="POST" onsubmit="return confirm('Remove this device?')">
                            <input type="hidden" name="action" value="remove_device">
                            <input type="hidden" name="device_id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <i class="fas fa-shield-halved text-primary"></i>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($blocked_devices)): ?>
        <div class="settings-item">
            <div class="settings-trigger" onclick="toggleSection('blocked')">
                <div class="settings-trigger-left">
                    <div class="settings-icon bg-danger bg-opacity-10 text-dark">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div>
                        <p class="settings-title">Blocked Devices
                            <span class="badge bg-danger ms-1" style="font-size:11px;"><?= count($blocked_devices) ?></span>
                        </p>
                        <p class="settings-subtitle">Rejected device requests</p>
                    </div>
                </div>
                <i class="fas fa-chevron-down settings-chevron" id="chevron-blocked"></i>
            </div>
            <div class="divider"></div>
            <div class="settings-body" id="body-blocked">
                <div class="pt-4">
                    <?php foreach ($blocked_devices as $b): ?>
                    <div class="device-card" style="border-left:3px solid #dc3545;">
                        <div style="flex:1;min-width:0;">
                            <div class="device-browser">
                                <i class="fas fa-globe me-1 text-muted"></i>
                                <?= htmlspecialchars($b['browser'] ?: 'Unknown Browser') ?>
                                &nbsp;·&nbsp;
                                <i class="fas fa-desktop me-1 text-muted"></i>
                                <?= htmlspecialchars(get_os_name($b['user_agent'] ?? '')) ?>
                                <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:#fbeaea;color:#dc3545;font-weight:500;margin-left:6px;"><?= strtoupper($b['user_type']) ?></span>
                            </div>
                            <div class="device-meta">
                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($b['username'] ?? 'Unknown') ?>
                                &nbsp;·&nbsp;
                                <i class="fas fa-network-wired me-1"></i><?= htmlspecialchars($b['ip_address']) ?>
                                &nbsp;·&nbsp;
                                <i class="fas fa-clock me-1"></i><?= date('M d, Y h:i A', strtotime($b['created_at'])) ?>
                            </div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="unblock_device">
                            <input type="hidden" name="request_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Unblock — allow to request again">
                                <i class="fas fa-unlock me-1"></i>Unblock
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

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