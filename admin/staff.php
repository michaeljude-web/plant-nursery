<?php
require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../config/config.php';
require_admin_auth();

define('STAFF_ENC_KEY',    'xK#9mP$2vL@nQ8zR!dW6sY&4bT*1jF0e');
define('STAFF_ENC_METHOD', 'AES-256-CBC');

function enc_staff($data) {
  if ($data === null || $data === '') return '';
  $iv  = random_bytes(16);
  $enc = openssl_encrypt($data, STAFF_ENC_METHOD, STAFF_ENC_KEY, 0, $iv);
  return base64_encode($iv . $enc);
}

function dec_staff($data) {
  if ($data === null || $data === '') return '';
  $decoded = base64_decode($data);
  if (strlen($decoded) < 16) return $data;
  $iv         = substr($decoded, 0, 16);
  $ciphertext = substr($decoded, 16);
  $result     = openssl_decrypt($ciphertext, STAFF_ENC_METHOD, STAFF_ENC_KEY, 0, $iv);
  return $result !== false ? $result : $data;
}

if (isset($_GET['verify_pw']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $password = $_POST['password'] ?? '';
    if (empty($password)) { echo json_encode(['success' => false]); exit(); }
    $stmt = $pdo->prepare("SELECT password FROM admin WHERE admin_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['admin_id'] ?? 0]);
    $admin = $stmt->fetch();
    echo json_encode(['success' => ($admin && password_verify($password, $admin['password']))]);
    exit();
}

$staff_error = $_SESSION['staff_error'] ?? '';
unset($_SESSION['staff_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname  = trim($_POST['lastname']  ?? '');
        $address   = trim($_POST['address']   ?? '');
        $contact   = trim($_POST['contact']   ?? '');
        $username  = trim($_POST['username']  ?? '');
        $password  = $_POST['password']       ?? '';

        $errors = [];
        if (!preg_match('/^[a-zA-Z]+(?: [a-zA-Z]+)*$/', $firstname)) $errors[] = 'First name: only letters and single spaces between words.';
        if (!preg_match('/^[a-zA-Z]+(?: [a-zA-Z]+)*$/', $lastname))  $errors[] = 'Last name: only letters and single spaces between words.';
        if (!preg_match('/^(?!.*[ ]{2})[a-zA-Z0-9 .,()]+$/', $address)) $errors[] = 'Address: only letters, numbers, spaces, . , ( ) allowed; no double spaces.';
        if (!preg_match('/^09\d{9}$/', $contact))  $errors[] = 'Contact: must be 11 digits starting with 09.';
        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) $errors[] = 'Username: only letters and numbers allowed.';
        if (!preg_match('/^[a-zA-Z0-9]+$/', $password)) $errors[] = 'Password: only letters and numbers, no spaces.';

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO staff_info (firstname, lastname, address, contact, username, password) VALUES (?,?,?,?,?,?)")
                ->execute([enc_staff($firstname), enc_staff($lastname), enc_staff($address), enc_staff($contact), $username, $hash]);
        } else {
            $_SESSION['staff_error'] = implode('<br>', $errors);
        }
    }

    if ($_POST['action'] === 'edit') {
        $id        = (int)$_POST['staff_id'];
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname  = trim($_POST['lastname']  ?? '');
        $address   = trim($_POST['address']   ?? '');
        $contact   = trim($_POST['contact']   ?? '');
        $username  = trim($_POST['username']  ?? '');
        $password  = $_POST['password']       ?? '';

        $errors = [];
        if (!preg_match('/^[a-zA-Z]+(?: [a-zA-Z]+)*$/', $firstname)) $errors[] = 'First name: only letters and single spaces between words.';
        if (!preg_match('/^[a-zA-Z]+(?: [a-zA-Z]+)*$/', $lastname))  $errors[] = 'Last name: only letters and single spaces between words.';
        if (!preg_match('/^(?!.*[ ]{2})[a-zA-Z0-9 .,()]+$/', $address)) $errors[] = 'Address: only letters, numbers, spaces, . , ( ) allowed; no double spaces.';
        if (!preg_match('/^09\d{9}$/', $contact))  $errors[] = 'Contact: must be 11 digits starting with 09.';
        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) $errors[] = 'Username: only letters and numbers allowed.';
        if ($password !== '' && !preg_match('/^[a-zA-Z0-9]+$/', $password)) $errors[] = 'Password: only letters and numbers, no spaces.';

        if (empty($errors)) {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE staff_info SET firstname=?,lastname=?,address=?,contact=?,username=?,password=? WHERE staff_id=?")
                    ->execute([enc_staff($firstname), enc_staff($lastname), enc_staff($address), enc_staff($contact), $username, $hash, $id]);
            } else {
                $pdo->prepare("UPDATE staff_info SET firstname=?,lastname=?,address=?,contact=?,username=? WHERE staff_id=?")
                    ->execute([enc_staff($firstname), enc_staff($lastname), enc_staff($address), enc_staff($contact), $username, $id]);
            }
        } else {
            $_SESSION['staff_error'] = implode('<br>', $errors);
        }
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['staff_id'];
        $pdo->prepare("DELETE FROM staff_info WHERE staff_id = ?")->execute([$id]);
    }

    header('Location: /plant/admin/staff.php');
    exit();
}

$rows  = $pdo->query("SELECT * FROM staff_info ORDER BY created_at DESC")->fetchAll();
$staff = [];
foreach ($rows as $s) {
    $s['firstname'] = dec_staff($s['firstname']);
    $s['lastname']  = dec_staff($s['lastname']);
    $s['address']   = dec_staff($s['address']);
    $s['contact']   = dec_staff($s['contact']);
    $staff[]        = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
<style>
.invalid-feedback { display: block; }
.masked-value { letter-spacing: 2px; color: #6c757d; font-size: 13px; font-family: monospace; }
.reveal-btn { border: none; background: none; padding: 0 4px; color: #6c757d; cursor: pointer; }
.reveal-btn:hover { color: #198754; }
</style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/admin/sidebar.php'; ?>

<div id="content">
  <div id="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light d-md-none" id="toggler"><i class="fas fa-bars"></i></button>
      <span class="fw-semibold text-dark">Staff</span>
    </div>
    <small class="text-muted"><?= date('D, M d Y') ?></small>
  </div>

  <div class="p-4">
    <?php if ($staff_error): ?>
    <div class="alert alert-danger py-2 small border-0 rounded-3 mb-3">
      <i class="fas fa-circle-exclamation me-1"></i><?= $staff_error ?>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <span class="text-muted small"><?= count($staff) ?> staff found</span>
      <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus me-1"></i> Add Staff
      </button>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th>Username</th>
              <th>Contact</th>
              <th>Address</th>
              <th>Date Added</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($staff)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No staff found.</td></tr>
            <?php else: ?>
            <?php foreach ($staff as $s): ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?></td>
              <td class="text-muted">
                <span class="masked-value" id="username-mask-<?= $s['staff_id'] ?>">••••••••</span>
                <span class="d-none" id="username-val-<?= $s['staff_id'] ?>"><?= htmlspecialchars($s['username']) ?></span>
              </td>
              <td>
                <?php $c = $s['contact']; $cm = (strlen($c) >= 4) ? substr($c,0,2).str_repeat('•',strlen($c)-4).substr($c,-2) : '•••••••••••'; ?>
                <span class="masked-value" id="contact-mask-<?= $s['staff_id'] ?>"><?= htmlspecialchars($cm) ?></span>
                <span class="d-none" id="contact-val-<?= $s['staff_id'] ?>"><?= htmlspecialchars($s['contact']) ?></span>
              </td>
              <td class="text-muted small">
                <span class="masked-value" id="address-mask-<?= $s['staff_id'] ?>">••••••••••••••</span>
                <span class="d-none" id="address-val-<?= $s['staff_id'] ?>"><?= htmlspecialchars($s['address']) ?></span>
              </td>
              <td class="text-muted small"><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
              <td class="text-center pe-3">
                <div class="d-flex gap-1 justify-content-center">
                  <button class="btn btn-sm btn-outline-secondary" onclick="askReveal(<?= $s['staff_id'] ?>)" title="View Info" id="view-btn-<?= $s['staff_id'] ?>">
                    <i class="fas fa-eye fa-xs" id="view-eye-<?= $s['staff_id'] ?>"></i>
                  </button>
                  <button class="btn btn-sm btn-primary" onclick="askEdit(<?= $s['staff_id'] ?>, <?= htmlspecialchars(json_encode([
                    'firstname' => $s['firstname'],
                    'lastname'  => $s['lastname'],
                    'address'   => $s['address'],
                    'contact'   => $s['contact'],
                    'username'  => $s['username'],
                  ]), ENT_QUOTES) ?>)" title="Edit">
                    <i class="fas fa-pen"></i>
                  </button>
                  <button class="btn btn-sm btn-danger" onclick="askDelete(<?= $s['staff_id'] ?>, <?= htmlspecialchars(json_encode($s['firstname'] . ' ' . $s['lastname']), ENT_QUOTES) ?>)" title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Password Modal (shared, multi-purpose) -->
<div class="modal fade" id="confirmPwModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-body p-4">
        <h6 class="fw-bold mb-1" id="confirmPwTitle">Confirm Password</h6>
        <p class="text-muted small mb-3" id="confirmPwDesc">Enter your admin password to continue.</p>
        <div class="mb-3">
          <div class="input-group">
            <input type="password" id="confirmPwInput" class="form-control border-end-0" placeholder="Password" maxlength="128">
            <button type="button" class="btn btn-outline-secondary border-start-0" onclick="toggleConfirmPw()">
              <i class="fas fa-eye fa-sm" id="confirmPwEye"></i>
            </button>
          </div>
          <div class="text-danger small mt-1 d-none" id="confirmPwError">Incorrect password.</div>
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-light btn-sm flex-fill" data-bs-dismiss="modal" onclick="resetConfirmModal()">Cancel</button>
          <button type="button" class="btn btn-success btn-sm flex-fill" id="confirmPwBtn" onclick="submitConfirmPw()">
            <span id="confirmPwBtnText"><i class="fas fa-check me-1"></i><span id="confirmPwBtnLabel">Confirm</span></span>
            <span id="confirmPwSpinner" class="d-none"><i class="fas fa-spinner fa-spin"></i></span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold">Add Staff</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="addForm">
        <input type="hidden" name="action" value="add">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label small fw-semibold text-secondary">First Name</label>
              <input type="text" name="firstname" class="form-control" required maxlength="100">
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold text-secondary">Last Name</label>
              <input type="text" name="lastname" class="form-control" required maxlength="100">
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary">Address</label>
              <textarea name="address" class="form-control" rows="2" required maxlength="300"></textarea>
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold text-secondary">Contact</label>
              <input type="text" name="contact" class="form-control" required maxlength="11">
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold text-secondary">Username</label>
              <input type="text" name="username" class="form-control" required maxlength="50">
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary">Password</label>
              <div class="input-group">
                <input type="password" name="password" id="addPw" class="form-control border-end-0" required maxlength="128">
                <button type="button" class="btn btn-outline-secondary border-start-0" onclick="togglePw('addPw',this)"><i class="fas fa-eye fa-sm"></i></button>
              </div>
              <div class="invalid-feedback"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="submit" class="btn btn-success btn-sm">Add Staff</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold">Edit Staff</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="editForm">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="staff_id" id="editId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label small fw-semibold text-secondary">First Name</label>
              <input type="text" name="firstname" id="editFirstname" class="form-control" required maxlength="100">
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold text-secondary">Last Name</label>
              <input type="text" name="lastname" id="editLastname" class="form-control" required maxlength="100">
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary">Address</label>
              <textarea name="address" id="editAddress" class="form-control" rows="2" required maxlength="300"></textarea>
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold text-secondary">Contact</label>
              <input type="text" name="contact" id="editContact" class="form-control" required maxlength="11">
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold text-secondary">Username</label>
              <input type="text" name="username" id="editUsername" class="form-control" required maxlength="50">
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary">New Password <span class="text-muted fw-normal">(leave blank to keep)</span></label>
              <div class="input-group">
                <input type="password" name="password" id="editPw" class="form-control border-end-0" maxlength="128">
                <button type="button" class="btn btn-outline-secondary border-start-0" onclick="togglePw('editPw',this)"><i class="fas fa-eye fa-sm"></i></button>
              </div>
              <div class="invalid-feedback"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-body p-4">
        <h6 class="fw-bold mb-1">Delete Staff</h6>
        <p class="text-muted small mb-4">Are you sure you want to delete <strong id="deleteName"></strong>?</p>
        <form method="POST" id="deleteForm">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="staff_id" id="deleteId">
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-light btn-sm flex-fill" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger btn-sm flex-fill">Delete</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
<script>
let _confirmMode   = null;
let _confirmData   = null;
let _revealedRows  = {};

function resetConfirmModal() {
    const input = document.getElementById('confirmPwInput');
    const err   = document.getElementById('confirmPwError');
    if (input) { input.value = ''; input.classList.remove('is-invalid'); }
    if (err)   { err.classList.add('d-none'); }
    document.getElementById('confirmPwBtnText').classList.remove('d-none');
    document.getElementById('confirmPwSpinner').classList.add('d-none');
    document.getElementById('confirmPwBtn').disabled = false;
}

function toggleConfirmPw() {
    const i   = document.getElementById('confirmPwInput');
    const eye = document.getElementById('confirmPwEye');
    i.type = i.type === 'password' ? 'text' : 'password';
    eye.classList.toggle('fa-eye');
    eye.classList.toggle('fa-eye-slash');
}

function openConfirmModal(title, desc, btnLabel) {
    document.getElementById('confirmPwTitle').textContent   = title;
    document.getElementById('confirmPwDesc').textContent    = desc;
    document.getElementById('confirmPwBtnLabel').textContent = btnLabel;
    resetConfirmModal();
    new bootstrap.Modal(document.getElementById('confirmPwModal')).show();
    setTimeout(() => document.getElementById('confirmPwInput').focus(), 400);
}

function askReveal(staffId) {
    if (_revealedRows[staffId]) {
        maskRow(staffId);
        delete _revealedRows[staffId];
        return;
    }
    _confirmMode = 'reveal';
    _confirmData = { staffId };
    openConfirmModal('View Staff Info', 'Enter your admin password to view this information.', 'View');
}

function askEdit(staffId, data) {
    _confirmMode = 'edit';
    _confirmData = { staffId, data };
    openConfirmModal('Confirm to Edit', 'Enter your admin password to edit this staff.', 'Proceed');
}

function askDelete(staffId, name) {
    _confirmMode = 'delete';
    _confirmData = { staffId, name };
    openConfirmModal('Confirm Delete', 'Enter your admin password to delete ' + name + '.', 'Delete');
}

function submitConfirmPw() {
    const password = document.getElementById('confirmPwInput').value;
    if (!password) return;

    document.getElementById('confirmPwBtnText').classList.add('d-none');
    document.getElementById('confirmPwSpinner').classList.remove('d-none');
    document.getElementById('confirmPwBtn').disabled = true;

    fetch('/plant/admin/staff.php?verify_pw=1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'password=' + encodeURIComponent(password)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('confirmPwModal')).hide();
            resetConfirmModal();

            if (_confirmMode === 'reveal') {
                const { staffId } = _confirmData;
                _revealedRows[staffId] = true;
                revealRow(staffId);
            } else if (_confirmMode === 'edit') {
                const { staffId, data } = _confirmData;
                document.getElementById('editId').value        = staffId;
                document.getElementById('editFirstname').value = data.firstname;
                document.getElementById('editLastname').value  = data.lastname;
                document.getElementById('editAddress').value   = data.address;
                document.getElementById('editContact').value   = data.contact;
                document.getElementById('editUsername').value  = data.username;
                document.getElementById('editPw').value        = '';
                document.querySelectorAll('#editForm .is-invalid').forEach(el => el.classList.remove('is-invalid'));
                document.querySelectorAll('#editForm .invalid-feedback').forEach(el => el.textContent = '');
                new bootstrap.Modal(document.getElementById('editModal')).show();
            } else if (_confirmMode === 'delete') {
                const { staffId, name } = _confirmData;
                document.getElementById('deleteId').value         = staffId;
                document.getElementById('deleteName').textContent = name;
                new bootstrap.Modal(document.getElementById('deleteModal')).show();
            }
        } else {
            document.getElementById('confirmPwError').classList.remove('d-none');
            document.getElementById('confirmPwInput').classList.add('is-invalid');
            document.getElementById('confirmPwBtnText').classList.remove('d-none');
            document.getElementById('confirmPwSpinner').classList.add('d-none');
            document.getElementById('confirmPwBtn').disabled = false;
            document.getElementById('confirmPwInput').focus();
        }
    })
    .catch(() => {
        document.getElementById('confirmPwBtnText').classList.remove('d-none');
        document.getElementById('confirmPwSpinner').classList.add('d-none');
        document.getElementById('confirmPwBtn').disabled = false;
    });
}

function revealRow(staffId) {
    ['username', 'contact', 'address'].forEach(field => {
        document.getElementById(field + '-mask-' + staffId).classList.add('d-none');
        document.getElementById(field + '-val-'  + staffId).classList.remove('d-none');
    });
    const eye = document.getElementById('view-eye-' + staffId);
    if (eye) { eye.classList.replace('fa-eye', 'fa-eye-slash'); }
}

function maskRow(staffId) {
    ['username', 'contact', 'address'].forEach(field => {
        document.getElementById(field + '-mask-' + staffId).classList.remove('d-none');
        document.getElementById(field + '-val-'  + staffId).classList.add('d-none');
    });
    const eye = document.getElementById('view-eye-' + staffId);
    if (eye) { eye.classList.replace('fa-eye-slash', 'fa-eye'); }
}

document.getElementById('confirmPwInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') submitConfirmPw();
});

document.getElementById('toggler')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('show');
});

function togglePw(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

(function () {
    const RULES = {
        name:     { re: /^[a-zA-Z]+(?: [a-zA-Z]+)*$/, msg: 'Only letters and single spaces between words.' },
        address:  { re: /^(?!.*[ ]{2})[a-zA-Z0-9 .,()]+$/, msg: 'Only letters, numbers, spaces, . , ( ) ; no double spaces.' },
        contact:  { re: /^09\d{0,9}$/,    msg: 'Must be 11 digits starting with 09.' },
        username: { re: /^[a-zA-Z0-9]*$/, msg: 'Only letters and numbers allowed.' },
        password: { re: /^[a-zA-Z0-9]*$/, msg: 'Only letters and numbers, no spaces.' }
    };

    function getRule(input) {
        const n = input.name;
        if (n === 'firstname' || n === 'lastname') return RULES.name;
        if (n === 'address')  return RULES.address;
        if (n === 'contact')  return RULES.contact;
        if (n === 'username') return RULES.username;
        if (n === 'password') return RULES.password;
        return null;
    }

    function getFeedback(input) {
        let fb = input.parentElement.querySelector('.invalid-feedback');
        if (!fb) { fb = document.createElement('div'); fb.className = 'invalid-feedback'; input.parentElement.appendChild(fb); }
        return fb;
    }

    function validate(input) {
        const rule = getRule(input);
        if (!rule) return true;
        const val = input.value;
        const fb  = getFeedback(input);
        if (val === '') { input.classList.remove('is-invalid', 'is-valid'); fb.textContent = ''; return false; }
        if (!rule.re.test(val)) {
            input.classList.add('is-invalid'); input.classList.remove('is-valid'); fb.textContent = rule.msg; return false;
        } else {
            input.classList.remove('is-invalid'); input.classList.add('is-valid'); fb.textContent = ''; return true;
        }
    }

    function attachLive(form) {
        ['firstname','lastname','contact','address','username','password'].forEach(name => {
            const el = form.querySelector(`[name="${name}"]`);
            if (!el) return;
            el.addEventListener('input', () => validate(el));
            el.addEventListener('blur',  () => validate(el));
        });
        form.addEventListener('submit', function(e) {
            let ok = true;
            const action = form.querySelector('[name="action"]').value;
            ['firstname','lastname','contact','address','username','password'].forEach(name => {
                const el = form.querySelector(`[name="${name}"]`);
                if (!el) return;
                if (name === 'password' && action === 'edit' && el.value === '') return;
                if (!validate(el)) ok = false;
            });
            if (!ok) e.preventDefault();
        });
    }

    document.querySelectorAll('#addForm, #editForm').forEach(attachLive);
})();
</script>
</body>
</html>