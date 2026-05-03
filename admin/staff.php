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
  return base64_encode($iv . $enc);  // ← TAMA
}

function dec_staff($data) {
  if ($data === null || $data === '') return '';
  $decoded = base64_decode($data);
  if (strlen($decoded) < 16) return $data;
  $iv         = substr($decoded, 0, 16);
  $ciphertext = substr($decoded, 16);
  $result     = openssl_decrypt($ciphertext, STAFF_ENC_METHOD, STAFF_ENC_KEY, 0, $iv);  // ← TAMA
  return $result !== false ? $result : $data;
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
        if (!preg_match('/^[a-zA-Z\s]+$/', $firstname)) $errors[] = 'First name: only letters and spaces allowed.';
        if (!preg_match('/^[a-zA-Z\s]+$/', $lastname)) $errors[] = 'Last name: only letters and spaces allowed.';
        if (!preg_match('/^[a-zA-Z0-9\s.,()]+$/', $address)) $errors[] = 'Address: only letters, numbers, spaces, . , ( ) allowed.';
        if (!preg_match('/^09\d{9}$/', $contact)) $errors[] = 'Contact: must be 11 digits starting with 09.';
        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) $errors[] = 'Username: only letters and numbers allowed.';
        if (!preg_match('/^[a-zA-Z0-9]+$/', $password)) $errors[] = 'Password: only letters and numbers, no spaces.';

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO staff_info (firstname, lastname, address, contact, username, password) VALUES (?,?,?,?,?,?)");
            $stmt->execute([enc_staff($firstname), enc_staff($lastname), enc_staff($address), enc_staff($contact), $username, $hash]);
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
        if (!preg_match('/^[a-zA-Z\s]+$/', $firstname)) $errors[] = 'First name: only letters and spaces allowed.';
        if (!preg_match('/^[a-zA-Z\s]+$/', $lastname)) $errors[] = 'Last name: only letters and spaces allowed.';
        if (!preg_match('/^[a-zA-Z0-9\s.,()]+$/', $address)) $errors[] = 'Address: only letters, numbers, spaces, . , ( ) allowed.';
        if (!preg_match('/^09\d{9}$/', $contact)) $errors[] = 'Contact: must be 11 digits starting with 09.';
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
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($staff)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No staff found.</td></tr>
            <?php else: ?>
            <?php foreach ($staff as $s): ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($s['username']) ?></td>
              <td><?= htmlspecialchars($s['contact']) ?></td>
              <td class="text-muted small"><?= htmlspecialchars($s['address']) ?></td>
              <td class="text-muted small"><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
              <td class="text-end pe-3">
                <div class="d-flex gap-1 justify-content-end">
                  <button class="btn btn-sm btn-primary"
                    data-bs-toggle="modal" data-bs-target="#editModal"
                    data-id="<?= $s['staff_id'] ?>"
                    data-firstname="<?= htmlspecialchars($s['firstname']) ?>"
                    data-lastname="<?= htmlspecialchars($s['lastname']) ?>"
                    data-address="<?= htmlspecialchars($s['address']) ?>"
                    data-contact="<?= htmlspecialchars($s['contact']) ?>"
                    data-username="<?= htmlspecialchars($s['username']) ?>">
                    <i class="fas fa-pen"></i>
                  </button>
                  <button class="btn btn-sm btn-danger"
                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                    data-id="<?= $s['staff_id'] ?>"
                    data-name="<?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?>">
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
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm">Add Staff</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT STAFF -->
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

<!-- DELETE STAFF -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-body p-4">
        <h6 class="fw-bold mb-1">Delete Staff</h6>
        <p class="text-muted small mb-4">Are you sure you want to delete <strong id="deleteName"></strong>?</p>
        <form method="POST">
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
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editId').value        = b.dataset.id;
    document.getElementById('editFirstname').value = b.dataset.firstname;
    document.getElementById('editLastname').value  = b.dataset.lastname;
    document.getElementById('editAddress').value   = b.dataset.address;
    document.getElementById('editContact').value   = b.dataset.contact;
    document.getElementById('editUsername').value  = b.dataset.username;
    document.getElementById('editPw').value        = '';
    document.querySelectorAll('#editForm .is-invalid').forEach(el => el.classList.remove('is-invalid'));
    document.querySelectorAll('#editForm .invalid-feedback').forEach(el => el.textContent = '');
});
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(e) {
    document.getElementById('deleteId').value         = e.relatedTarget.dataset.id;
    document.getElementById('deleteName').textContent = e.relatedTarget.dataset.name;
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
        name:     { re: /^[a-zA-Z\s]*$/,    msg: '' },
        address:  { re: /^[a-zA-Z0-9\s.,()]*$/, msg: '' },
        contact:  { re: /^09\d{0,9}$/,       msg: 'Must be 11 digits starting with 09.' },
        username: { re: /^[a-zA-Z0-9]*$/,    msg: 'Only letters and numbers allowed.' },
        password: { re: /^[a-zA-Z0-9]*$/,    msg: 'Only letters and numbers, no spaces.' }
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
        if (!fb) {
            fb = document.createElement('div');
            fb.className = 'invalid-feedback';
            input.parentElement.appendChild(fb);
        }
        return fb;
    }

    function validate(input) {
        const rule = getRule(input);
        if (!rule) return true;
        const val = input.value;
        const fb  = getFeedback(input);
        if (val === '') {
            input.classList.remove('is-invalid', 'is-valid');
            fb.textContent = '';
            return false;
        }
        if (!rule.re.test(val)) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            fb.textContent = rule.msg;
            return false;
        } else {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            fb.textContent = '';
            return true;
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
                if (name === 'password' && action === 'edit' && el.value === '') {
                    return; // password optional in edit
                }
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