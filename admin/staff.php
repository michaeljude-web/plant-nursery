<?php
require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../config/config.php';
require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname  = trim($_POST['lastname'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $contact   = trim($_POST['contact'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';

        if ($firstname && $lastname && $address && $contact && $username && $password) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO staff_info (firstname, lastname, address, contact, username, password) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$firstname, $lastname, $address, $contact, $username, $hash]);
        }
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['staff_id'];
        $pdo->prepare("DELETE FROM staff_info WHERE staff_id = ?")->execute([$id]);
    }

    header('Location: /plant/admin/staff.php');
    exit();
}

$staff = $pdo->query("SELECT * FROM staff_info ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff's</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
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
            <tr>
              <td colspan="7" class="text-center text-muted py-4">No staff found.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($staff as $i => $s): ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($s['username']) ?></td>
              <td><?= htmlspecialchars($s['contact']) ?></td>
              <td class="text-muted small"><?= htmlspecialchars($s['address']) ?></td>
              <td class="text-muted small"><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
              <td class="text-end pe-3">
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $s['staff_id'] ?>" data-name="<?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?>">
                  <i class="fas fa-trash"></i>
                </button>
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
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label small fw-semibold text-secondary">First Name</label>
              <input type="text" name="firstname" class="form-control" required maxlength="100">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold text-secondary">Last Name</label>
              <input type="text" name="lastname" class="form-control" required maxlength="100">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary">Address</label>
              <textarea name="address" class="form-control" rows="2" required maxlength="300"></textarea>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold text-secondary">Contact</label>
              <input type="text" name="contact" class="form-control" required maxlength="20">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold text-secondary">Username</label>
              <input type="text" name="username" class="form-control" required maxlength="50">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary">Password</label>
              <input type="password" name="password" class="form-control" required maxlength="128">
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Add Staff</button>
        </div>
      </form>
    </div>
  </div>
</div>

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
            <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger flex-fill">Delete</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(e) {
  const btn = e.relatedTarget;
  document.getElementById('deleteId').value = btn.dataset.id;
  document.getElementById('deleteName').textContent = btn.dataset.name;
});
document.getElementById('toggler')?.addEventListener('click', function() {
  document.getElementById('sidebar').classList.toggle('show');
});
</script>
</body>
</html>