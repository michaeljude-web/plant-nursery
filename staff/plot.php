<?php
require_once __DIR__ . '/../includes/staff/auth.php';
require_once __DIR__ . '/../config/config.php';
require_staff_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add_seedling') {
        $plot_id    = (int)$_POST['plot_id'];
        $variety_id = (int)$_POST['variety_id'];
        $quantity   = (int)$_POST['quantity'];

        if ($plot_id && $variety_id && $quantity > 0) {
            $total_stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) as total FROM plot_seedlings WHERE plot_id = ?");
            $total_stmt->execute([$plot_id]);
            $current_total = (int)$total_stmt->fetch()['total'];

            if (($current_total + $quantity) > 100) {
                $error_msg = "Cannot exceed 100 seedlings per plot. Current: {$current_total}, Available: " . (100 - $current_total);
                $_SESSION['plot_error'] = $error_msg;
            } else {
                $existing = $pdo->prepare("SELECT plot_seedling_id, quantity FROM plot_seedlings WHERE plot_id = ? AND variety_id = ?");
                $existing->execute([$plot_id, $variety_id]);
                $row = $existing->fetch();
                if ($row) {
                    $pdo->prepare("UPDATE plot_seedlings SET quantity = quantity + ? WHERE plot_seedling_id = ?")->execute([$quantity, $row['plot_seedling_id']]);
                } else {
                    $pdo->prepare("INSERT INTO plot_seedlings (plot_id, variety_id, quantity) VALUES (?,?,?)")->execute([$plot_id, $variety_id, $quantity]);
                }
            }
        }
    }

    if ($_POST['action'] === 'ready_for_sale') {
        $plot_seedling_id = (int)$_POST['plot_seedling_id'];
        $quantity         = (int)$_POST['quantity'];

        if ($plot_seedling_id && $quantity > 0) {
            $row = $pdo->prepare("SELECT * FROM plot_seedlings WHERE plot_seedling_id = ?");
            $row->execute([$plot_seedling_id]);
            $ps = $row->fetch();

            if ($ps && $quantity <= $ps['quantity']) {
                $pdo->prepare("UPDATE plot_seedlings SET quantity = quantity - ? WHERE plot_seedling_id = ?")->execute([$quantity, $plot_seedling_id]);

                $inv = $pdo->prepare("SELECT inventory_id FROM inventory WHERE variety_id = ?");
                $inv->execute([$ps['variety_id']]);
                $inv_row = $inv->fetch();
                if ($inv_row) {
                    $pdo->prepare("UPDATE inventory SET quantity = quantity + ?, updated_at = NOW() WHERE inventory_id = ?")->execute([$quantity, $inv_row['inventory_id']]);
                } else {
                    $pdo->prepare("INSERT INTO inventory (variety_id, quantity) VALUES (?,?)")->execute([$ps['variety_id'], $quantity]);
                }

                $zero = $pdo->prepare("DELETE FROM plot_seedlings WHERE plot_seedling_id = ? AND quantity = 0");
                $zero->execute([$plot_seedling_id]);
            }
        }
    }

    if ($_POST['action'] === 'report_damage') {
        $plot_id          = (int)$_POST['plot_id'];
        $plot_seedling_id = (int)$_POST['plot_seedling_id'];
        $quantity_damaged = (int)$_POST['quantity_damaged'];
        $description      = trim($_POST['description'] ?? '');
        $staff_id         = $_SESSION['staff_id'];

        if ($plot_id && $plot_seedling_id && $quantity_damaged > 0) {
            $rpt = $pdo->prepare("INSERT INTO damage_reports (plot_id, plot_seedling_id, staff_id, quantity_damaged, description) VALUES (?,?,?,?,?)");
            $rpt->execute([$plot_id, $plot_seedling_id, $staff_id, $quantity_damaged, $description]);
            $report_id = $pdo->lastInsertId();

            $pdo->prepare("UPDATE plot_seedlings SET quantity = quantity - ? WHERE plot_seedling_id = ? AND quantity >= ?")->execute([$quantity_damaged, $plot_seedling_id, $quantity_damaged]);
            $pdo->prepare("DELETE FROM plot_seedlings WHERE plot_seedling_id = ? AND quantity = 0")->execute([$plot_seedling_id]);

            if (!empty($_FILES['photos']['name'][0])) {
                $upload_dir = __DIR__ . '/../uploads/damage/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                foreach ($_FILES['photos']['tmp_name'] as $k => $tmp) {
                    if ($_FILES['photos']['error'][$k] === 0) {
                        $ext  = pathinfo($_FILES['photos']['name'][$k], PATHINFO_EXTENSION);
                        $fname = uniqid('dmg_') . '.' . $ext;
                        move_uploaded_file($tmp, $upload_dir . $fname);
                        $pdo->prepare("INSERT INTO damage_photos (report_id, photo_path) VALUES (?,?)")->execute([$report_id, 'uploads/damage/' . $fname]);
                    }
                }
            }
        }
    }

    header('Location: /plant/staff/plot.php');
    exit();
}

$plot_error = $_SESSION['plot_error'] ?? '';
unset($_SESSION['plot_error']);

$plots = $pdo->query("SELECT p.*, COALESCE(SUM(ps.quantity),0) as total_seedlings FROM plots p LEFT JOIN plot_seedlings ps ON p.plot_id = ps.plot_id GROUP BY p.plot_id ORDER BY p.plot_number")->fetchAll();

$seedlings_per_plot = [];
$rows = $pdo->query("SELECT ps.*, v.variety_name, s.seedling_name, ps.plot_id FROM plot_seedlings ps JOIN varieties v ON ps.variety_id = v.variety_id JOIN seedlings s ON v.seedling_id = s.seedling_id ORDER BY s.seedling_name, v.variety_name")->fetchAll();
foreach ($rows as $r) {
    $seedlings_per_plot[$r['plot_id']][] = $r;
}

$varieties = $pdo->query("SELECT v.variety_id, v.variety_name, s.seedling_name FROM varieties v JOIN seedlings s ON v.seedling_id = s.seedling_id ORDER BY s.seedling_name, v.variety_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Plots</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/../includes/staff/navbar.php'; ?>

<div class="container-fluid px-4 py-4">

  <?php if ($plot_error): ?>
  <div class="alert alert-danger alert-dismissible fade show py-2 small">
    <i class="fas fa-circle-exclamation me-1"></i> <?= htmlspecialchars($plot_error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($plots as $plot):
      $total = (int)$plot['total_seedlings'];
      $available = 100 - $total;
      $pct = $total;
      $bar_color = $pct >= 90 ? 'bg-danger' : ($pct >= 60 ? 'bg-warning' : 'bg-success');
    ?>
    <div class="col-12 col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-2">
              <i class="fas fa-seedling text-success fa-lg"></i>
              <span class="fw-bold"><?= htmlspecialchars($plot['plot_name']) ?></span>
            </div>
            <span class="badge <?= $available > 0 ? 'bg-success' : 'bg-danger' ?> small">
              <?= $total ?>/100 seedlings
            </span>
          </div>

          <?php if (!empty($seedlings_per_plot[$plot['plot_id']])): ?>
          <div class="mb-3">
            <table class="table table-sm table-borderless mb-0">
              <tbody>
                <?php foreach ($seedlings_per_plot[$plot['plot_id']] as $ps): ?>
                <tr>
                  <td class="ps-0 text-muted small"><?= htmlspecialchars($ps['seedling_name']) ?> — <?= htmlspecialchars($ps['variety_name']) ?></td>
                  <td class="text-end pe-0" style="width:75px;">
                    <div class="d-flex gap-1 justify-content-end">
                      <button class="btn btn-sm btn-success py-0 px-2"
                        title="Move to Inventory"
                        data-bs-toggle="modal" data-bs-target="#saleModal"
                        data-id="<?= $ps['plot_seedling_id'] ?>"
                        data-name="<?= htmlspecialchars($ps['seedling_name'] . ' — ' . $ps['variety_name']) ?>"
                        data-max="<?= $ps['quantity'] ?>">
                        <i class="fas fa-arrow-right"></i>
                      </button>
                      <button class="btn btn-sm btn-danger py-0 px-2"
                        title="Report Damage"
                        data-bs-toggle="modal" data-bs-target="#damageModal"
                        data-id="<?= $ps['plot_seedling_id'] ?>"
                        data-plot="<?= $plot['plot_id'] ?>"
                        data-name="<?= htmlspecialchars($ps['seedling_name'] . ' — ' . $ps['variety_name']) ?>"
                        data-max="<?= $ps['quantity'] ?>">
                        <i class="fas fa-triangle-exclamation"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <p class="text-muted small mb-3">No seedlings yet.</p>
          <?php endif; ?>

          <button class="btn btn-sm btn-outline-success w-100"
            data-bs-toggle="modal" data-bs-target="#addSeedlingModal"
            data-plot-id="<?= $plot['plot_id'] ?>"
            data-plot-name="<?= htmlspecialchars($plot['plot_name']) ?>"
            data-available="<?= $available ?>">
            <i class="fas fa-plus me-1"></i> Add Seedling
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="modal fade" id="addSeedlingModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold">Add Seedling</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add_seedling">
        <input type="hidden" name="plot_id" id="addPlotId">
        <div class="modal-body">
          <p class="text-muted small mb-3">Plot: <strong id="addPlotName"></strong> — Available: <strong id="addAvailable"></strong></p>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Variety</label>
            <select name="variety_id" class="form-select form-select-sm" required>
              <option value="">— Select —</option>
              <?php foreach ($varieties as $v): ?>
              <option value="<?= $v['variety_id'] ?>"><?= htmlspecialchars($v['seedling_name'] . ' — ' . $v['variety_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label small fw-semibold text-secondary">Quantity</label>
            <input type="number" name="quantity" class="form-control form-control-sm" min="1" max="100" required>
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

<div class="modal fade" id="saleModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold">Ready for Sale</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="ready_for_sale">
        <input type="hidden" name="plot_seedling_id" id="saleId">
        <div class="modal-body">
          <p class="text-muted small mb-3">Seedling: <strong id="saleName"></strong></p>
          <label class="form-label small fw-semibold text-secondary">Quantity to move to Inventory</label>
          <input type="number" name="quantity" id="saleQty" class="form-control form-control-sm" min="1" required>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm">Confirm</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="damageModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold">Report Damage</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="report_damage">
        <input type="hidden" name="plot_seedling_id" id="damageId">
        <input type="hidden" name="plot_id" id="damagePlotId">
        <div class="modal-body">
          <p class="text-muted small mb-3">Seedling: <strong id="damageName"></strong></p>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Quantity Damaged</label>
            <input type="number" name="quantity_damaged" id="damageQty" class="form-control form-control-sm" min="1" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Description</label>
            <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Describe the damage..."></textarea>
          </div>
          <div>
            <label class="form-label small fw-semibold text-secondary">Photos</label>
            <input type="file" name="photos[]" class="form-control form-control-sm" accept="image/*" multiple>
            <div class="form-text">You can select multiple photos.</div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm">Submit Report</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addSeedlingModal').addEventListener('show.bs.modal', function(e) {
  const b = e.relatedTarget;
  document.getElementById('addPlotId').value    = b.dataset.plotId;
  document.getElementById('addPlotName').textContent  = b.dataset.plotName;
  document.getElementById('addAvailable').textContent = b.dataset.available;
  document.querySelector('#addSeedlingModal input[name="quantity"]').max = b.dataset.available;
});
document.getElementById('saleModal').addEventListener('show.bs.modal', function(e) {
  const b = e.relatedTarget;
  document.getElementById('saleId').value         = b.dataset.id;
  document.getElementById('saleName').textContent = b.dataset.name;
  document.getElementById('saleQty').max          = b.dataset.max;
});
document.getElementById('damageModal').addEventListener('show.bs.modal', function(e) {
  const b = e.relatedTarget;
  document.getElementById('damageId').value          = b.dataset.id;
  document.getElementById('damagePlotId').value      = b.dataset.plot;
  document.getElementById('damageName').textContent  = b.dataset.name;
  document.getElementById('damageQty').max           = b.dataset.max;
});
</script>
</body>
</html>