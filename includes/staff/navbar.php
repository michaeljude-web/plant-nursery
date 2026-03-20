<?php
require_once __DIR__ . '/../../config/config.php';

$staff_id = $_SESSION['staff_id'];
$ua       = $_SERVER['HTTP_USER_AGENT']      ?? '';
$lang     = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
$cur_hash = hash('sha256', $ua . $lang . $encoding);

function get_browser_name_staff($ua) {
    if (!$ua) return 'Unknown';
    if (str_contains($ua, 'Edg'))     return 'Microsoft Edge';
    if (str_contains($ua, 'OPR'))     return 'Opera';
    if (str_contains($ua, 'Chrome'))  return 'Chrome';
    if (str_contains($ua, 'Firefox')) return 'Firefox';
    if (str_contains($ua, 'Safari'))  return 'Safari';
    return 'Unknown Browser';
}

function get_os_name_staff($ua) {
    if (!$ua) return 'Unknown OS';
    if (str_contains($ua, 'Windows NT 10')) return 'Windows 10/11';
    if (str_contains($ua, 'Windows NT 6.3')) return 'Windows 8.1';
    if (str_contains($ua, 'Windows NT 6.1')) return 'Windows 7';
    if (str_contains($ua, 'Windows'))        return 'Windows';
    if (str_contains($ua, 'Mac OS X'))       return 'macOS';
    if (str_contains($ua, 'iPhone'))         return 'iPhone';
    if (str_contains($ua, 'iPad'))           return 'iPad';
    if (str_contains($ua, 'Android'))        return 'Android';
    if (str_contains($ua, 'Linux'))          return 'Linux';
    return 'Unknown OS';
}

function is_safe_staff($val) {
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

$settings_success = $settings_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings_action'])) {

    if ($_POST['settings_action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM staff_info WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        $staff = $stmt->fetch();

        if (!is_safe_staff($current) || !is_safe_staff($new) || !is_safe_staff($confirm)) {
            $settings_error = 'Invalid characters detected in input.';
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

    if ($_POST['settings_action'] === 'remove_device') {
        $device_id = (int)($_POST['device_id'] ?? 0);
        if ($device_id) {
            $pdo->prepare("DELETE FROM trusted_devices WHERE id = ? AND user_id = ? AND user_type = 'staff'")
                ->execute([$device_id, $staff_id]);
        }
    }

    if ($_POST['settings_action'] === 'approve_device') {
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
        $settings_success = 'Device approved successfully.';
    }

    if ($_POST['settings_action'] === 'reject_device') {
        $request_id = (int)($_POST['request_id'] ?? 0);
        if ($request_id) {
            $pdo->prepare("UPDATE device_requests SET status = 'rejected' WHERE id = ?")
                ->execute([$request_id]);
        }
        $settings_success = 'Device rejected.';
    }
}

$trusted_devices = $pdo->prepare("SELECT * FROM trusted_devices WHERE user_id = ? AND user_type = 'staff' ORDER BY created_at DESC");
$trusted_devices->execute([$staff_id]);
$staff_devices = $trusted_devices->fetchAll();

if (empty($staff_devices)) {
    $pdo->prepare("
        INSERT INTO trusted_devices (user_id, user_type, device_hash, ip_address, user_agent, browser, is_approved)
        VALUES (?, 'staff', ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE is_approved = 1
    ")->execute([$staff_id, $cur_hash, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $ua, get_browser_name_staff($ua)]);

    $trusted_devices->execute([$staff_id]);
    $staff_devices = $trusted_devices->fetchAll();
}

$staff_pending = $pdo->query("
    SELECT dr.*,
        (SELECT username FROM staff_info WHERE staff_id = dr.user_id) as username
    FROM device_requests dr
    WHERE dr.status = 'pending' AND dr.user_type = 'staff'
    ORDER BY dr.created_at DESC
")->fetchAll();
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
        <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
          <i class="fas fa-user me-1"></i><?= htmlspecialchars($_SESSION['staff_firstname'] . ' ' . $_SESSION['staff_lastname']) ?>
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

          <?php if (!empty($staff_pending)): ?>
          <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;overflow:hidden;">
            <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between px-4 py-3" style="cursor:pointer;" onclick="toggleStaffSection('pending')">
              <div class="d-flex align-items-center gap-3">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,193,7,.1);display:flex;align-items:center;justify-content:center;color:#ffc107;font-size:16px;">
                  <i class="fas fa-clock"></i>
                </div>
                <div>
                  <div style="font-weight:600;font-size:15px;color:#1a1a2e;">
                    Pending Device Requests
                    <span class="badge bg-danger ms-1" style="font-size:11px;"><?= count($staff_pending) ?></span>
                  </div>
                  <div style="font-size:12px;color:#6c757d;">Devices waiting for approval</div>
                </div>
              </div>
              <i class="fas fa-chevron-down text-muted small" id="staff-chev-pending" style="transition:transform .25s;transform:rotate(180deg);"></i>
            </div>
            <div style="height:1px;background:#f0f0f0;margin:0 22px;"></div>
            <div id="staff-body-pending" style="max-height:600px;overflow:hidden;transition:max-height .3s ease,padding .3s ease;padding:0 22px;">
              <div class="pt-4 pb-3">
                <?php foreach ($staff_pending as $r): ?>
                <div style="border:1px solid #e9ecef;border-left:3px solid #ffc107;border-radius:10px;padding:14px 16px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:14px;color:#1a1a2e;">
                      <i class="fas fa-globe me-1 text-muted"></i><?= htmlspecialchars($r['browser'] ?: 'Unknown Browser') ?>
                      &nbsp;·&nbsp;
                      <i class="fas fa-desktop me-1 text-muted"></i><?= htmlspecialchars(get_os_name_staff($r['user_agent'] ?? '')) ?>
                      <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:#fff3cd;color:#856404;font-weight:500;margin-left:6px;"><?= strtoupper($r['user_type']) ?></span>
                    </div>
                    <div style="font-size:12px;color:#6c757d;margin-top:3px;">
                      <i class="fas fa-user me-1"></i><?= htmlspecialchars($r['username'] ?? 'Unknown') ?>
                      &nbsp;·&nbsp;
                      <i class="fas fa-network-wired me-1"></i><?= htmlspecialchars($r['ip_address']) ?>
                      &nbsp;·&nbsp;
                      <i class="fas fa-clock me-1"></i><?= date('M d, Y h:i A', strtotime($r['created_at'])) ?>
                    </div>
                  </div>
                  <div class="d-flex gap-2 flex-shrink-0">
                    <form method="POST">
                      <input type="hidden" name="settings_action" value="approve_device">
                      <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Approve</button>
                    </form>
                    <form method="POST">
                      <input type="hidden" name="settings_action" value="reject_device">
                      <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i>Reject</button>
                    </form>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
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
                <form method="POST">
                  <input type="hidden" name="settings_action" value="change_password">
                  <div class="mb-3">
                    <label style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;display:block;">Current Password</label>
                    <div class="input-group">
                      <input type="password" name="current_password" class="form-control" style="border-radius:8px 0 0 8px;border:1.5px solid #e2e8f0;font-size:14px;" required>
                      <button type="button" class="btn btn-outline-secondary" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="toggleStaffPw('current_password',this)"><i class="fas fa-eye fa-sm"></i></button>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;display:block;">New Password</label>
                    <div class="input-group">
                      <input type="password" name="new_password" class="form-control" style="border-radius:8px 0 0 8px;border:1.5px solid #e2e8f0;font-size:14px;" required>
                      <button type="button" class="btn btn-outline-secondary" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="toggleStaffPw('new_password',this)"><i class="fas fa-eye fa-sm"></i></button>
                    </div>
                  </div>
                  <div class="mb-4">
                    <label style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;display:block;">Confirm New Password</label>
                    <div class="input-group">
                      <input type="password" name="confirm_password" class="form-control" style="border-radius:8px 0 0 8px;border:1.5px solid #e2e8f0;font-size:14px;" required>
                      <button type="button" class="btn btn-outline-secondary" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="toggleStaffPw('confirm_password',this)"><i class="fas fa-eye fa-sm"></i></button>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary" style="border-radius:8px;padding:9px 20px;font-size:14px;"><i class="fas fa-save me-1"></i> Update Password</button>
                </form>
              </div>
            </div>
          </div>

          <div class="card border-0 shadow-sm" style="border-radius:12px;overflow:hidden;">
            <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between px-4 py-3" style="cursor:pointer;" onclick="toggleStaffSection('dev')">
              <div class="d-flex align-items-center gap-3">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(13,202,240,.1);display:flex;align-items:center;justify-content:center;color:#0dcaf0;font-size:16px;">
                  <i class="fas fa-laptop"></i>
                </div>
                <div>
                  <div style="font-weight:600;font-size:15px;color:#1a1a2e;">Trusted Devices</div>
                  <div style="font-size:12px;color:#6c757d;"><?= count($staff_devices) ?> device<?= count($staff_devices) !== 1 ? 's' : '' ?> approved</div>
                </div>
              </div>
              <i class="fas fa-chevron-down text-muted small" id="staff-chev-dev" style="transition:transform .25s;"></i>
            </div>
            <div style="height:1px;background:#f0f0f0;margin:0 22px;"></div>
            <div id="staff-body-dev" style="max-height:0;overflow:hidden;transition:max-height .3s ease,padding .3s ease;padding:0 22px;">
              <div class="pt-4 pb-3">
                <?php if (empty($staff_devices)): ?>
                <p class="text-muted small">No trusted devices yet.</p>
                <?php else: ?>
                <?php foreach ($staff_devices as $d):
                    $is_cur = $d['device_hash'] === $cur_hash;
                ?>
                <div style="border:1px solid <?= $is_cur ? '#0d6efd' : '#e9ecef' ?>;background:<?= $is_cur ? '#f0f5ff' : '#fff' ?>;border-radius:10px;padding:14px 16px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:14px;color:#1a1a2e;">
                      <i class="fas fa-globe me-1 text-muted"></i><?= htmlspecialchars(get_browser_name_staff($d['user_agent'])) ?>
                      &nbsp;·&nbsp;
                      <i class="fas fa-desktop me-1 text-muted"></i><?= htmlspecialchars(get_os_name_staff($d['user_agent'])) ?>
                      <?php if ($is_cur): ?>
                      <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:#e8f4fd;color:#0d6efd;font-weight:500;margin-left:6px;">This device</span>
                      <?php endif; ?>
                    </div>
                    <div style="font-size:12px;color:#6c757d;margin-top:3px;">
                      <i class="fas fa-network-wired me-1"></i><?= htmlspecialchars($d['ip_address']) ?>
                      &nbsp;·&nbsp;
                      <i class="fas fa-clock me-1"></i><?= date('M d, Y h:i A', strtotime($d['created_at'])) ?>
                    </div>
                  </div>
                  <?php if (!$is_cur): ?>
                  <form method="POST" onsubmit="return confirm('Remove this device?')">
                    <input type="hidden" name="settings_action" value="remove_device">
                    <input type="hidden" name="device_id" value="<?= $d['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
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

        </div>
      </div>
    </div>
  </div>
</div>

<style>
#staffSettingsModal .modal-header { padding: 14px 24px; }
</style>

<script>
function toggleStaffSection(id) {
    const body   = document.getElementById('staff-body-' + id);
    const chev   = document.getElementById('staff-chev-' + id);
    const isOpen = body.style.maxHeight && body.style.maxHeight !== '0px';
    document.querySelectorAll('[id^="staff-body-"]').forEach(b => { b.style.maxHeight = '0'; b.style.padding = '0 22px'; });
    document.querySelectorAll('[id^="staff-chev-"]').forEach(c => c.style.transform = '');
    if (!isOpen) {
        body.style.maxHeight = '600px';
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

<?php if ($settings_success || $settings_error || !empty($staff_pending)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('staffSettingsModal'));
    modal.show();
    <?php if ($settings_success || $settings_error): ?>
    toggleStaffSection('pw');
    <?php elseif (!empty($staff_pending)): ?>
    toggleStaffSection('pending');
    <?php endif; ?>
});
<?php endif; ?>

const blockedChars = ["'", '"', ';', '--', '<', '>', '\\', '=', '`', '|', '&', '%', '#', '/'];
document.querySelectorAll('#staffSettingsModal input[type=text], #staffSettingsModal input[type=password]').forEach(input => {
    input.addEventListener('input', function() {
        let v = this.value;
        blockedChars.forEach(c => { v = v.split(c).join(''); });
        if (v !== this.value) this.value = v;
    });
    input.addEventListener('paste', function(e) {
        e.preventDefault();
        let t = (e.clipboardData || window.clipboardData).getData('text');
        blockedChars.forEach(c => { t = t.split(c).join(''); });
        this.value += t;
    });
});
</script>