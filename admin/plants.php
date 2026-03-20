<?php
require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../config/config.php';
require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add_seedling') {
        $name = trim($_POST['seedling_name'] ?? '');
        if ($name) {
            $pdo->prepare("INSERT INTO seedlings (seedling_name) VALUES (?)")->execute([$name]);
        }
    }

    if ($_POST['action'] === 'edit_seedling') {
        $id   = (int)$_POST['seedling_id'];
        $name = trim($_POST['seedling_name'] ?? '');
        if ($id && $name) {
            $pdo->prepare("UPDATE seedlings SET seedling_name = ? WHERE seedling_id = ?")->execute([$name, $id]);
        }
    }

    if ($_POST['action'] === 'add_variety') {
        $sid  = (int)$_POST['seedling_id'];
        $name = trim($_POST['variety_name'] ?? '');
        if ($sid && $name) {
            $pdo->prepare("INSERT INTO varieties (seedling_id, variety_name) VALUES (?,?)")->execute([$sid, $name]);
        }
    }

    if ($_POST['action'] === 'edit_variety') {
        $id   = (int)$_POST['variety_id'];
        $sid  = (int)$_POST['seedling_id'];
        $name = trim($_POST['variety_name'] ?? '');
        if ($id && $sid && $name) {
            $pdo->prepare("UPDATE varieties SET seedling_id = ?, variety_name = ? WHERE variety_id = ?")->execute([$sid, $name, $id]);
        }
    }

    if ($_POST['action'] === 'delete_seedling') {
        $id = (int)$_POST['seedling_id'];
        $pdo->prepare("DELETE FROM varieties WHERE seedling_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM seedlings WHERE seedling_id = ?")->execute([$id]);
    }

    if ($_POST['action'] === 'delete_variety') {
        $id = (int)$_POST['variety_id'];
        $pdo->prepare("DELETE FROM varieties WHERE variety_id = ?")->execute([$id]);
    }

    header('Location: /plant/admin/plants.php');
    exit();
}

$seedlings = $pdo->query("SELECT * FROM seedlings ORDER BY seedling_name ASC")->fetchAll();
$varieties = $pdo->query("SELECT v.*, s.seedling_name FROM varieties v JOIN seedlings s ON v.seedling_id = s.seedling_id ORDER BY s.seedling_name, v.variety_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Plants</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
</head>
<body>

<?php require_once __DIR__ . '/../includes/admin/sidebar.php'; ?>

<div id="content">
  <div id="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light d-md-none" id="toggler"><i class="fas fa-bars"></i></button>
      <span class="fw-semibold text-dark">Plants</span>
    </div>
    <small class="text-muted"><?= date('D, M d Y') ?></small>
  </div>

  <div class="p-4">

    <div class="d-flex justify-content-end gap-2 mb-3">
      <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#addSeedlingModal">
        <i class="fas fa-plus me-1"></i> Add Seedling
      </button>
      <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addVarietyModal">
        <i class="fas fa-plus me-1"></i> Add Variety
      </button>
    </div>

    <div class="row g-3">
      <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom py-3 px-4">
            <span class="fw-semibold">Seedlings</span>
            <span class="text-muted small ms-2">(<?= count($seedlings) ?>)</span>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Name</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($seedlings)): ?>
                <tr><td colspan="2" class="text-center text-muted py-4">No seedlings found.</td></tr>
                <?php else: ?>
                <?php foreach ($seedlings as $s): ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($s['seedling_name']) ?></td>
                  <td class="text-end pe-3">
                    <div class="d-flex gap-1 justify-content-end">
                      <button class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#editSeedlingModal"
                        data-id="<?= $s['seedling_id'] ?>"
                        data-name="<?= htmlspecialchars($s['seedling_name']) ?>">
                        <i class="fas fa-pen"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-danger"
                        data-bs-toggle="modal" data-bs-target="#deleteSeedlingModal"
                        data-id="<?= $s['seedling_id'] ?>"
                        data-name="<?= htmlspecialchars($s['seedling_name']) ?>">
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

      <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom py-3 px-4">
            <span class="fw-semibold">Varieties</span>
            <span class="text-muted small ms-2">(<?= count($varieties) ?>)</span>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Seedling</th>
                  <th>Variety</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($varieties)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">No varieties found.</td></tr>
                <?php else: ?>
                <?php foreach ($varieties as $v): ?>
                <tr>
                  <td class="text-muted small"><?= htmlspecialchars($v['seedling_name']) ?></td>
                  <td class="fw-semibold"><?= htmlspecialchars($v['variety_name']) ?></td>
                  <td class="text-end pe-3">
                    <div class="d-flex gap-1 justify-content-end">
                      <button class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#editVarietyModal"
                        data-id="<?= $v['variety_id'] ?>"
                        data-name="<?= htmlspecialchars($v['variety_name']) ?>"
                        data-seedling-id="<?= $v['seedling_id'] ?>">
                        <i class="fas fa-pen"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-danger"
                        data-bs-toggle="modal" data-bs-target="#deleteVarietyModal"
                        data-id="<?= $v['variety_id'] ?>"
                        data-name="<?= htmlspecialchars($v['variety_name']) ?>">
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
  </div>
</div>

<!-- ADD SEEDLING -->
<div class="modal fade" id="addSeedlingModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold">Add Seedling</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add_seedling">
        <div class="modal-body">
          <label class="form-label small fw-semibold text-secondary">Seedling Name</label>
          <input type="text" name="seedling_name" class="form-control" required maxlength="100">
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm">Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT SEEDLING -->
<div class="modal fade" id="editSeedlingModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold">Edit Seedling</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="edit_seedling">
        <input type="hidden" name="seedling_id" id="editSeedlingId">
        <div class="modal-body">
          <label class="form-label small fw-semibold text-secondary">Seedling Name</label>
          <input type="text" name="seedling_name" id="editSeedlingName" class="form-control" required maxlength="100">
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ADD VARIETY -->
<div class="modal fade" id="addVarietyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold">Add Variety</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add_variety">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Seedling</label>
            <select name="seedling_id" class="form-select" required>
              <option value="">— Select Seedling —</option>
              <?php foreach ($seedlings as $s): ?>
              <option value="<?= $s['seedling_id'] ?>"><?= htmlspecialchars($s['seedling_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label small fw-semibold text-secondary">Variety Name</label>
            <input type="text" name="variety_name" class="form-control" required maxlength="100">
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm">Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT VARIETY -->
<div class="modal fade" id="editVarietyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold">Edit Variety</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="edit_variety">
        <input type="hidden" name="variety_id" id="editVarietyId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Seedling</label>
            <select name="seedling_id" id="editVarietySeedlingId" class="form-select" required>
              <option value="">— Select Seedling —</option>
              <?php foreach ($seedlings as $s): ?>
              <option value="<?= $s['seedling_id'] ?>"><?= htmlspecialchars($s['seedling_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label small fw-semibold text-secondary">Variety Name</label>
            <input type="text" name="variety_name" id="editVarietyName" class="form-control" required maxlength="100">
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE SEEDLING -->
<div class="modal fade" id="deleteSeedlingModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-body p-4">
        <h6 class="fw-bold mb-1">Delete Seedling</h6>
        <p class="text-muted small mb-4">Delete <strong id="deleteSeedlingName"></strong> and all its varieties?</p>
        <form method="POST">
          <input type="hidden" name="action" value="delete_seedling">
          <input type="hidden" name="seedling_id" id="deleteSeedlingId">
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-light btn-sm flex-fill" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger btn-sm flex-fill">Delete</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- DELETE VARIETY -->
<div class="modal fade" id="deleteVarietyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-body p-4">
        <h6 class="fw-bold mb-1">Delete Variety</h6>
        <p class="text-muted small mb-4">Are you sure you want to delete <strong id="deleteVarietyName"></strong>?</p>
        <form method="POST">
          <input type="hidden" name="action" value="delete_variety">
          <input type="hidden" name="variety_id" id="deleteVarietyId">
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
document.getElementById('editSeedlingModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editSeedlingId').value       = b.dataset.id;
    document.getElementById('editSeedlingName').value     = b.dataset.name;
});
document.getElementById('editVarietyModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editVarietyId').value        = b.dataset.id;
    document.getElementById('editVarietyName').value      = b.dataset.name;
    document.getElementById('editVarietySeedlingId').value = b.dataset.seedlingId;
});
document.getElementById('deleteSeedlingModal').addEventListener('show.bs.modal', function(e) {
    document.getElementById('deleteSeedlingId').value             = e.relatedTarget.dataset.id;
    document.getElementById('deleteSeedlingName').textContent     = e.relatedTarget.dataset.name;
});
document.getElementById('deleteVarietyModal').addEventListener('show.bs.modal', function(e) {
    document.getElementById('deleteVarietyId').value              = e.relatedTarget.dataset.id;
    document.getElementById('deleteVarietyName').textContent      = e.relatedTarget.dataset.name;
});
document.getElementById('toggler')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('show');
});
</script>
</body>
</html>