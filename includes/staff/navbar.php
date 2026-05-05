<?php
require_once __DIR__ . '/../../config/config.php';

if (!defined('STAFF_ENC_KEY')) {
    define('STAFF_ENC_KEY',    'xK#9mP$2vL@nQ8zR!dW6sY&4bT*1jF0e');
    define('STAFF_ENC_METHOD', 'AES-256-CBC');
}

if (!function_exists('dec_staff')) {
    function dec_staff($data) {
        if ($data === null || $data === '') return '';
        $decoded = base64_decode($data);
        if (strlen($decoded) < 16) return $data;
        $iv         = substr($decoded, 0, 16);
        $ciphertext = substr($decoded, 16);
        $result     = openssl_decrypt($ciphertext, STAFF_ENC_METHOD, STAFF_ENC_KEY, 0, $iv);
        return $result !== false ? $result : $data;
    }
}

function get_staff_display_name() {
    $first = $_SESSION['staff_firstname'] ?? '';
    $last  = $_SESSION['staff_lastname']  ?? '';
    if ($first && preg_match('/^[a-zA-Z0-9\/+=]{20,}$/', $first)) $first = dec_staff($first);
    if ($last  && preg_match('/^[a-zA-Z0-9\/+=]{20,}$/', $last))  $last  = dec_staff($last);
    return trim($first . ' ' . $last);
}

$staff_display_name = get_staff_display_name();
$staff_id = $_SESSION['staff_id'];

function is_safe_password($val) {
    return preg_match('/^[a-zA-Z0-9]+$/', $val) === 1;
}
function is_safe_answer($val) {
    return preg_match('/^[a-zA-Z0-9 ]+$/', $val) === 1;
}

$settings_success   = $settings_error = '';
$sq_success         = $sq_error = '';
$sq_confirm_mode    = false;
$settings_open_section = '';

$stmt_sq = $pdo->prepare("SELECT sa.*, sq.question FROM security_answer sa JOIN security_questions sq ON sa.question_id = sq.id WHERE sa.user_id = ? AND sa.user_type = 'staff'");
$stmt_sq->execute([$staff_id]);
$existing_sq = $stmt_sq->fetch();

$all_questions = $pdo->query("SELECT * FROM security_questions ORDER BY id ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings_action'])) {

    if ($_POST['settings_action'] === 'change_password') {
        $settings_open_section = 'pw';
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM staff_info WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        $staff = $stmt->fetch();

        if (!is_safe_password($current) || !is_safe_password($new) || !is_safe_password($confirm)) {
            $settings_error = 'Invalid characters in password. Only letters and numbers allowed.';
        } elseif (!password_verify($current, $staff['password'])) {
            $settings_error = 'Current password is incorrect.';
        } elseif (empty($new)) {
            $settings_error = 'New password cannot be empty.';
        } elseif ($new !== $confirm) {
            $settings_error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE staff_info SET password = ? WHERE staff_id = ?")
                ->execute([$hash, $staff_id]);
            $settings_success = 'Password updated successfully.';
        }
    }

    if ($_POST['settings_action'] === 'set_security_question') {
        $settings_open_section = 'sq';
        $question_id = (int)($_POST['question_id'] ?? 0);
        $answer      = trim($_POST['sq_answer'] ?? '');

        if ($question_id <= 0) {
            $sq_error = 'Please select a security question.';
        } elseif (empty($answer)) {
            $sq_error = 'Answer cannot be empty.';
        } elseif (!is_safe_answer($answer)) {
            $sq_error = 'Invalid answer. Only letters, numbers and spaces allowed.';
        } elseif (strlen($answer) < 2) {
            $sq_error = 'Answer is too short.';
        } else {
            $answer_hash = password_hash(strtolower($answer), PASSWORD_DEFAULT);
            if ($existing_sq) {
                $pdo->prepare("UPDATE security_answer SET question_id = ?, answer_hash = ? WHERE user_id = ? AND user_type = 'staff'")
                    ->execute([$question_id, $answer_hash, $staff_id]);
            } else {
                $pdo->prepare("INSERT INTO security_answer (user_id, user_type, question_id, answer_hash) VALUES (?, 'staff', ?, ?)")
                    ->execute([$staff_id, $question_id, $answer_hash]);
            }
            $sq_success = 'Security question saved successfully.';
            $stmt_sq->execute([$staff_id]);
            $existing_sq = $stmt_sq->fetch();
        }
    }

    if ($_POST['settings_action'] === 'request_reset_sq') {
        $settings_open_section = 'sq';
        $sq_confirm_mode = true;
    }

    if ($_POST['settings_action'] === 'confirm_reset_sq') {
        $settings_open_section = 'sq';
        $current_pw = $_POST['confirm_password_reset'] ?? '';

        if (!is_safe_password($current_pw)) {
            $sq_error = 'Invalid password. Only letters and numbers allowed.';
            $sq_confirm_mode = true;
        } else {
            $stmt = $pdo->prepare("SELECT password FROM staff_info WHERE staff_id = ?");
            $stmt->execute([$staff_id]);
            $staff_row = $stmt->fetch();

            if (!password_verify($current_pw, $staff_row['password'])) {
                $sq_error        = 'Incorrect password. Reset cancelled.';
                $sq_confirm_mode = false;
            } else {
                $pdo->prepare("DELETE FROM security_answer WHERE user_id = ? AND user_type = 'staff'")
                    ->execute([$staff_id]);
                $existing_sq     = null;
                $sq_success      = 'Security question has been reset. Please set a new one.';
                $sq_confirm_mode = false;
            }
        }
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-bold text-white" href="/plant/staff/dashboard.php">
      <i class="fas fa-leaf text-success me-2"></i>Ej's Plant Nursery
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav mx-auto">
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active fw-semibold':'' ?>" href="/plant/staff/dashboard.php">
            <i class="fas fa-gauge-high me-1"></i> Dashboard |
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='plot.php'?'active fw-semibold':'' ?>" href="/plant/staff/plot.php">
            <i class="fas fa-map-location-dot me-1"></i> Plot |
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='inventory.php'?'active fw-semibold':'' ?>" href="/plant/staff/inventory.php">
            <i class="fas fa-boxes-stacked me-1"></i> Inventory |
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='order.php'?'active fw-semibold':'' ?>" href="/plant/staff/order.php">
            <i class="fas fa-cart-shopping me-1"></i> Order
          </a>
        </li>
      </ul>
      <div class="dropdown">
        <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown" title="Account">
          <i class="fas fa-user me-1"></i><?= htmlspecialchars($staff_display_name) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li>
            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#staffSettingsModal">
              <i class="fas fa-gear me-2"></i>Settings
            </a>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="/plant/logout.php"><i class="fas fa-right-from-bracket me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<div class="modal fade" id="staffSettingsModal" tabindex="-1">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header border-0 bg-dark text-white px-4">
        <h5 class="modal-title"><i class="fas fa-gear me-2"></i>Settings</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body bg-light p-4">
        <div style="max-width:620px;margin:0 auto;">

          <?php if ($settings_success): ?>
          <div class="alert alert-success py-2 small border-0 rounded-3 mb-3"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($settings_success) ?></div>
          <?php elseif ($settings_error): ?>
          <div class="alert alert-danger py-2 small border-0 rounded-3 mb-3"><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($settings_error) ?></div>
          <?php endif; ?>

          <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;overflow:hidden;">
            <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between px-4 py-3" style="cursor:pointer;" onclick="toggleStaffSection('pw')">
              <div class="d-flex align-items-center gap-3">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(13,110,253,.1);display:flex;align-items:center;justify-content:center;color:#0d6efd;font-size:16px;">
                  <i class="fas fa-lock"></i>
                </div>
                <div>
                  <div style="font-weight:600;font-size:15px;color:#1a1a2e;">Change Password</div>
                  <div style="font-size:12px;color:#6c757d;">Update your account password</div>
                </div>
              </div>
              <i class="fas fa-chevron-down text-muted small" id="staff-chev-pw" style="transition:transform .25s;"></i>
            </div>
            <div style="height:1px;background:#f0f0f0;margin:0 22px;"></div>
            <div id="staff-body-pw" style="max-height:0;overflow:hidden;transition:max-height .3s ease,padding .3s ease;padding:0 22px;">
              <div class="pt-4 pb-3">
                <?php if ($settings_open_section === 'pw' && $settings_error): ?>
                <div class="alert alert-danger py-2 small border-0 rounded-3 mb-3"><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($settings_error) ?></div>
                <?php elseif ($settings_open_section === 'pw' && $settings_success): ?>
                <div class="alert alert-success py-2 small border-0 rounded-3 mb-3"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($settings_success) ?></div>
                <?php endif; ?>
                <form method="POST" id="passwordForm">
                  <input type="hidden" name="settings_action" value="change_password">
                  <div class="mb-3">
                    <label style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;display:block;">Current Password</label>
                    <div class="input-group">
                      <input type="password" name="current_password" class="form-control" style="border-radius:8px 0 0 8px;border:1.5px solid #e2e8f0;font-size:14px;" required>
                      <button type="button" class="btn btn-outline-secondary" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="toggleStaffPw('current_password',this)"><i class="fas fa-eye fa-sm"></i></button>
                    </div>
                    <div class="invalid-feedback-custom" id="hint-current-pw"></div>
                  </div>
                  <div class="mb-3">
                    <label style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;display:block;">New Password</label>
                    <div class="input-group">
                      <input type="password" name="new_password" class="form-control" style="border-radius:8px 0 0 8px;border:1.5px solid #e2e8f0;font-size:14px;" required>
                      <button type="button" class="btn btn-outline-secondary" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="toggleStaffPw('new_password',this)"><i class="fas fa-eye fa-sm"></i></button>
                    </div>
                    <div class="invalid-feedback-custom" id="hint-new-pw"></div>
                  </div>
                  <div class="mb-4">
                    <label style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;display:block;">Confirm New Password</label>
                    <div class="input-group">
                      <input type="password" name="confirm_password" class="form-control" style="border-radius:8px 0 0 8px;border:1.5px solid #e2e8f0;font-size:14px;" required>
                      <button type="button" class="btn btn-outline-secondary" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="toggleStaffPw('confirm_password',this)"><i class="fas fa-eye fa-sm"></i></button>
                    </div>
                    <div class="invalid-feedback-custom" id="hint-confirm-pw"></div>
                  </div>
                  <button type="submit" class="btn btn-primary" id="updatePasswordBtn" style="border-radius:8px;padding:9px 20px;font-size:14px;"><i class="fas fa-save me-1"></i> Update Password</button>
                </form>
              </div>
            </div>
          </div>

          <div class="card border-0 shadow-sm" style="border-radius:12px;overflow:hidden;">
            <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between px-4 py-3" style="cursor:pointer;" onclick="toggleStaffSection('sq')">
              <div class="d-flex align-items-center gap-3">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(25,135,84,.1);display:flex;align-items:center;justify-content:center;color:#198754;font-size:16px;">
                  <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                  <div style="font-weight:600;font-size:15px;color:#1a1a2e;">
                    Security Question
                    <?php if ($existing_sq): ?>
                    <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:#d1fae5;color:#065f46;font-weight:500;margin-left:6px;"><i class="fas fa-check me-1"></i>Set</span>
                    <?php endif; ?>
                  </div>
                  <div style="font-size:12px;color:#6c757d;">
                    <?= $existing_sq ? 'Your security question is configured' : 'No security question set yet' ?>
                  </div>
                </div>
              </div>
              <i class="fas fa-chevron-down text-muted small" id="staff-chev-sq" style="transition:transform .25s;"></i>
            </div>
            <div style="height:1px;background:#f0f0f0;margin:0 22px;"></div>
            <div id="staff-body-sq" style="max-height:0;overflow:hidden;transition:max-height .3s ease,padding .3s ease;padding:0 22px;">
              <div class="pt-4 pb-3">

                <?php if ($sq_success): ?>
                <div class="alert alert-success py-2 small border-0 rounded-3 mb-3"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($sq_success) ?></div>
                <?php elseif ($sq_error): ?>
                <div class="alert alert-danger py-2 small border-0 rounded-3 mb-3"><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($sq_error) ?></div>
                <?php endif; ?>

                <?php if ($existing_sq && !$sq_confirm_mode && !$sq_success): ?>
                <div style="background:#fff8f0;border:1.5px solid #ffc107;border-radius:10px;padding:16px 18px;margin-bottom:16px;">
                  <div class="d-flex align-items-start gap-2">
                    <i class="fas fa-circle-info text-warning mt-1"></i>
                    <div>
                      <div style="font-weight:600;font-size:13px;color:#92400e;">Current security question</div>
                      <div style="font-size:13px;color:#1a1a2e;margin-top:4px;"><?= htmlspecialchars($existing_sq['question']) ?></div>
                      <div style="font-size:12px;color:#6c757d;margin-top:2px;">Answer is hidden for security.</div>
                    </div>
                  </div>
                </div>
                <p class="text-muted small mb-3">To change your security question, you must first confirm your password.</p>
                <form method="POST">
                  <input type="hidden" name="settings_action" value="request_reset_sq">
                  <button type="submit" class="btn btn-warning text-dark" style="border-radius:8px;padding:9px 20px;font-size:14px;">
                    <i class="fas fa-rotate-right me-1"></i> Reset Security Question
                  </button>
                </form>

                <?php elseif ($sq_confirm_mode): ?>
                <div style="background:#fff8f0;border:1.5px solid #ffc107;border-radius:10px;padding:16px 18px;margin-bottom:16px;">
                  <div style="font-weight:600;font-size:13px;color:#92400e;margin-bottom:4px;"><i class="fas fa-triangle-exclamation me-1"></i> Confirm your identity</div>
                  <div style="font-size:12px;color:#6c757d;">Enter your current password to reset your security question.</div>
                </div>
                <form method="POST" id="resetSqForm">
                  <input type="hidden" name="settings_action" value="confirm_reset_sq">
                  <div class="mb-3">
                    <label style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;display:block;">Current Password</label>
                    <div class="input-group">
                      <input type="password" name="confirm_password_reset" class="form-control" id="confirm_password_reset" style="border-radius:8px 0 0 8px;border:1.5px solid #e2e8f0;font-size:14px;" required autofocus>
                      <button type="button" class="btn btn-outline-secondary" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="toggleStaffPw('confirm_password_reset',this)"><i class="fas fa-eye fa-sm"></i></button>
                    </div>
                    <div class="invalid-feedback-custom" id="hint-reset-pw"></div>
                  </div>
                  <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger" id="confirmResetBtn" style="border-radius:8px;padding:9px 20px;font-size:14px;">
                      <i class="fas fa-trash me-1"></i> Confirm Reset
                    </button>
                    <button type="button" class="btn btn-outline-secondary" style="border-radius:8px;padding:9px 20px;font-size:14px;" data-bs-dismiss="modal">Cancel</button>
                  </div>
                </form>

                <?php else: ?>
                <form method="POST" id="sqForm">
                  <input type="hidden" name="settings_action" value="set_security_question">
                  <div class="mb-3">
                    <label style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;display:block;">Security Question</label>
                    <select name="question_id" class="form-control" style="border-radius:8px;border:1.5px solid #e2e8f0;font-size:14px;" required>
                      <option value="">— Select a question —</option>
                      <?php foreach ($all_questions as $q): ?>
                      <option value="<?= $q['id'] ?>"><?= htmlspecialchars($q['question']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="mb-4">
                    <label style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;display:block;">Your Answer</label>
                    <input type="text" name="sq_answer" class="form-control" id="sq_answer" style="border-radius:8px;border:1.5px solid #e2e8f0;font-size:14px;" placeholder="Enter your answer" required autocomplete="off">
                    <div class="invalid-feedback-custom" id="hint-sq-answer"></div>
                    <div style="font-size:11px;color:#6c757d;margin-top:5px;"><i class="fas fa-info-circle me-1"></i>Answer is case-insensitive and stored securely.</div>
                  </div>
                  <button type="submit" class="btn btn-success" id="saveSqBtn" style="border-radius:8px;padding:9px 20px;font-size:14px;">
                    <i class="fas fa-save me-1"></i> Save Security Question
                  </button>
                </form>
                <?php endif; ?>

              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<style>
#staffSettingsModal .modal-header { padding: 14px 24px; }
.invalid-feedback-custom {
    font-size: 11px;
    color: #dc3545;
    margin-top: 4px;
    display: none;
}
.invalid-feedback-custom.show {
    display: block;
}
input.is-invalid-custom {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.2rem rgba(220,53,69,.15) !important;
}
button:disabled {
    cursor: not-allowed !important;
    opacity: 0.65;
}
</style>

<script>
function toggleStaffSection(id) {
    const body   = document.getElementById('staff-body-' + id);
    const chev   = document.getElementById('staff-chev-' + id);
    const isOpen = body.style.maxHeight && body.style.maxHeight !== '0px';
    document.querySelectorAll('[id^="staff-body-"]').forEach(b => { b.style.maxHeight = '0'; b.style.padding = '0 22px'; });
    document.querySelectorAll('[id^="staff-chev-"]').forEach(c => c.style.transform = '');
    if (!isOpen) {
        body.style.maxHeight = '700px';
        body.style.padding   = '0 22px';
        chev.style.transform = 'rotate(180deg)';
    }
}

function toggleStaffPw(name, btn) {
    const input = btn.closest('.input-group').querySelector('input');
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

<?php
$open_js = '';
if ($settings_open_section === 'sq' || $sq_confirm_mode) {
    $open_js = 'sq';
} elseif ($settings_open_section === 'pw' || $settings_success || $settings_error) {
    $open_js = 'pw';
}
?>
<?php if ($open_js): ?>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('staffSettingsModal'));
    modal.show();
    toggleStaffSection('<?= $open_js ?>');
});
<?php endif; ?>

(function() {
    const alnumRegex = /^[a-zA-Z0-9]*$/;
    const answerRegex = /^[a-zA-Z0-9 ]*$/;

    function setFieldValidation(input, hint, regex, errorMsg) {
        if (!input || !hint) return false;
        function validate() {
            const val = input.value;
            const valid = val === '' || regex.test(val);
            if (!valid) {
                hint.textContent = errorMsg;
                hint.classList.add('show');
                input.classList.add('is-invalid-custom');
            } else {
                hint.classList.remove('show');
                input.classList.remove('is-invalid-custom');
            }
            return valid;
        }
        input.addEventListener('input', validate);
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            let text = (e.clipboardData || window.clipboardData).getData('text');
            text = text.split('').filter(ch => regex.test(ch)).join('');
            input.value += text;
            validate();
        });
        return validate;
    }

    const currentPw = document.querySelector('input[name="current_password"]');
    const newPw     = document.querySelector('input[name="new_password"]');
    const confirmPw = document.querySelector('input[name="confirm_password"]');
    const hintCur   = document.getElementById('hint-current-pw');
    const hintNew   = document.getElementById('hint-new-pw');
    const hintCon   = document.getElementById('hint-confirm-pw');
    const updateBtn = document.getElementById('updatePasswordBtn');

    let validCur = true, validNew = true, validCon = true;
    if (currentPw && hintCur) {
        const v = setFieldValidation(currentPw, hintCur, alnumRegex, 'Only letters and numbers allowed. No spaces.');
        currentPw.addEventListener('input', () => { validCur = v(); toggleUpdateBtn(); });
    }
    if (newPw && hintNew) {
        const v = setFieldValidation(newPw, hintNew, alnumRegex, 'Only letters and numbers allowed. No spaces.');
        newPw.addEventListener('input', () => { validNew = v(); toggleUpdateBtn(); });
    }
    if (confirmPw && hintCon) {
        const v = setFieldValidation(confirmPw, hintCon, alnumRegex, 'Only letters and numbers allowed. No spaces.');
        confirmPw.addEventListener('input', () => { validCon = v(); toggleUpdateBtn(); });
    }

    function toggleUpdateBtn() {
        if (updateBtn) {
            updateBtn.disabled = !(validCur && validNew && validCon && currentPw.value.length > 0 && newPw.value.length > 0 && confirmPw.value.length > 0);
        }
    }

    const resetPwInput = document.getElementById('confirm_password_reset');
    const resetPwHint  = document.getElementById('hint-reset-pw');
    const confirmResetBtn = document.getElementById('confirmResetBtn');
    if (resetPwInput && resetPwHint) {
        const v = setFieldValidation(resetPwInput, resetPwHint, alnumRegex, 'Only letters and numbers allowed. No spaces.');
        resetPwInput.addEventListener('input', () => {
            const valid = v();
            if (confirmResetBtn) confirmResetBtn.disabled = !valid || resetPwInput.value.length === 0;
        });
    }

    const sqAnswer = document.getElementById('sq_answer');
    const sqHint   = document.getElementById('hint-sq-answer');
    const saveSqBtn = document.getElementById('saveSqBtn');
    if (sqAnswer && sqHint) {
        const v = setFieldValidation(sqAnswer, sqHint, answerRegex, 'Only letters, numbers and spaces allowed.');
        sqAnswer.addEventListener('input', () => {
            const valid = v();
            if (saveSqBtn) saveSqBtn.disabled = !valid || sqAnswer.value.trim().length === 0;
        });
    }
})();
</script>