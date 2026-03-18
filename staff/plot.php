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
                $_SESSION['plot_error'] = "Cannot exceed 100 seedlings per plot. Current: {$current_total}, Available: " . (100 - $current_total);
            } else {
                $existing = $pdo->prepare("SELECT plot_seedling_id, quantity FROM plot_seedlings WHERE plot_id = ? AND variety_id = ?");
                $existing->execute([$plot_id, $variety_id]);
                $row = $existing->fetch();
                $staff_id = $_SESSION['staff_id'];
                if ($row) {
                    $pdo->prepare("UPDATE plot_seedlings SET quantity = quantity + ? WHERE plot_seedling_id = ?")->execute([$quantity, $row['plot_seedling_id']]);
                } else {
                    $pdo->prepare("INSERT INTO plot_seedlings (plot_id, variety_id, staff_id, quantity) VALUES (?,?,?,?)")->execute([$plot_id, $variety_id, $staff_id, $quantity]);
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
                $pdo->prepare("INSERT INTO inventory_logs (staff_id, variety_id, quantity) VALUES (?,?,?)")->execute([$_SESSION['staff_id'], $ps['variety_id'], $quantity]);
                $pdo->prepare("DELETE FROM plot_seedlings WHERE plot_seedling_id = ? AND quantity = 0")->execute([$plot_seedling_id]);
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
                        $ext   = pathinfo($_FILES['photos']['name'][$k], PATHINFO_EXTENSION);
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
<link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/staff/plot.css">
</head>
<body>

<?php require_once __DIR__ . '/../includes/staff/navbar.php'; ?>

<div class="wrap">

    <div class="top-bar">
        <h1>
            <i class="fas fa-seedling" style="color:var(--green);margin-right:10px;font-size:22px"></i>Nursery Plots
        </h1>
        <span class="plot-count-chip"><?= count($plots) ?> plot<?= count($plots) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if ($plot_error): ?>
    <div class="err-bar">
        <i class="fas fa-circle-exclamation"></i>
        <?= htmlspecialchars($plot_error) ?>
    </div>
    <?php endif; ?>

    <div class="ground-wrap">
        <div class="ground-title">Nursery Ground</div>

        <div class="plot-grid">
            <?php foreach ($plots as $plot):
                $total     = (int)$plot['total_seedlings'];
                $available = 100 - $total;
                $pct       = $total;
                $state     = $pct >= 90 ? 'red' : ($pct >= 55 ? 'amber' : '');
            ?>
            <div class="plot-card <?= $state ?>">
                <div class="plot-body">
                    <div class="plot-head">
                        <span class="plot-label"><?= htmlspecialchars($plot['plot_name']) ?></span>
                        <span class="plot-badge"><?= $total ?>/100</span>
                    </div>

                    <div class="cap-bar">
                        <div class="cap-fill" style="width:<?= $pct ?>%"></div>
                    </div>

                    <?php if (!empty($seedlings_per_plot[$plot['plot_id']])): ?>
                    <div class="seedling-list">
                        <?php foreach ($seedlings_per_plot[$plot['plot_id']] as $ps): ?>
                        <div class="s-row">
                            <span class="s-text" title="<?= htmlspecialchars($ps['seedling_name'] . ' — ' . $ps['variety_name']) ?>">
                                <?= htmlspecialchars($ps['seedling_name'] . ' — ' . $ps['variety_name']) ?>
                            </span>
                            <span class="s-qty">×<?= $ps['quantity'] ?></span>
                            <div class="s-btns">
                                <button class="s-btn s-btn-inv"
                                    title="Move to Inventory"
                                    data-bs-toggle="modal" data-bs-target="#saleModal"
                                    data-id="<?= $ps['plot_seedling_id'] ?>"
                                    data-name="<?= htmlspecialchars($ps['seedling_name'] . ' — ' . $ps['variety_name']) ?>"
                                    data-max="<?= $ps['quantity'] ?>">
                                    <i class="fas fa-arrow-right"></i>
                                </button>
                                <button class="s-btn s-btn-dmg"
                                    title="Report Damage"
                                    data-bs-toggle="modal" data-bs-target="#damageModal"
                                    data-id="<?= $ps['plot_seedling_id'] ?>"
                                    data-plot="<?= $plot['plot_id'] ?>"
                                    data-name="<?= htmlspecialchars($ps['seedling_name'] . ' — ' . $ps['variety_name']) ?>"
                                    data-max="<?= $ps['quantity'] ?>">
                                    <i class="fas fa-triangle-exclamation"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-msg">
                        <i class="fas fa-leaf" style="color:#c8d8c0"></i> Empty — ready to plant
                    </div>
                    <?php endif; ?>
                </div>

                <button class="btn-add"
                    data-bs-toggle="modal" data-bs-target="#addSeedlingModal"
                    data-plot-id="<?= $plot['plot_id'] ?>"
                    data-plot-name="<?= htmlspecialchars($plot['plot_name']) ?>"
                    data-available="<?= $available ?>">
                    <i class="fas fa-plus" style="margin-right:5px;font-size:10px"></i>
                    Add Seedling<?= $available > 0 ? " · $available left" : ' · Full' ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ADD SEEDLING -->
<div class="modal fade" id="addSeedlingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title"><i class="fas fa-seedling" style="color:var(--green);margin-right:8px"></i>Add Seedling</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_seedling">
                <input type="hidden" name="plot_id" id="addPlotId">
                <div class="modal-body">
                    <div class="modal-info">
                        Plot: <strong id="addPlotName"></strong> — <strong id="addAvailable"></strong> slots available
                    </div>
                    <div class="mb-f">
                        <label class="field-label">Variety</label>
                        <select name="variety_id" class="field-select" required>
                            <option value="">— Select variety —</option>
                            <?php foreach ($varieties as $v): ?>
                            <option value="<?= $v['variety_id'] ?>"><?= htmlspecialchars($v['seedling_name'] . ' — ' . $v['variety_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Quantity</label>
                        <input type="number" name="quantity" class="field-input" min="1" max="100" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cx" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-ok"><i class="fas fa-plus" style="margin-right:5px"></i>Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MOVE TO INVENTORY -->
<div class="modal fade" id="saleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title"><i class="fas fa-arrow-right" style="color:var(--green);margin-right:8px"></i>Move to Inventory</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="ready_for_sale">
                <input type="hidden" name="plot_seedling_id" id="saleId">
                <div class="modal-body">
                    <div class="modal-info">Seedling: <strong id="saleName"></strong></div>
                    <label class="field-label">Quantity to move</label>
                    <input type="number" name="quantity" id="saleQty" class="field-input" min="1" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cx" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-ok">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- REPORT DAMAGE -->
<div class="modal fade" id="damageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title"><i class="fas fa-triangle-exclamation" style="color:var(--red);margin-right:8px"></i>Report Damage</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="report_damage">
                <input type="hidden" name="plot_seedling_id" id="damageId">
                <input type="hidden" name="plot_id" id="damagePlotId">
                <div class="modal-body">
                    <div class="modal-info danger">Seedling: <strong id="damageName"></strong></div>
                    <div class="mb-f">
                        <label class="field-label">Quantity Damaged</label>
                        <input type="number" name="quantity_damaged" id="damageQty" class="field-input" min="1" required>
                    </div>
                    <div class="mb-f">
                        <label class="field-label">Description</label>
                        <textarea name="description" class="field-textarea" rows="3" placeholder="Describe the damage..."></textarea>
                    </div>
                    <div>
                        <label class="field-label">Photos</label>
                        <input type="file" name="photos[]" class="field-input" accept="image/*" multiple>
                        <div class="form-hint">You can select multiple photos.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cx" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-del"><i class="fas fa-triangle-exclamation" style="margin-right:5px"></i>Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addSeedlingModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('addPlotId').value          = b.dataset.plotId;
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
    document.getElementById('damageId').value         = b.dataset.id;
    document.getElementById('damagePlotId').value     = b.dataset.plot;
    document.getElementById('damageName').textContent = b.dataset.name;
    document.getElementById('damageQty').max          = b.dataset.max;
});
</script>
</body>
</html>