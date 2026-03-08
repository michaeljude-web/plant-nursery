<?php
require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../config/config.php';
require_admin_auth();

$date_from    = $_GET['date_from'] ?? '';
$date_to      = $_GET['date_to'] ?? '';
$filter_action = $_GET['action'] ?? '';

$add_logs = $pdo->query("
    SELECT 
        CONCAT(si.firstname, ' ', si.lastname) as staff_name,
        'Added Seedling to Plot' as action,
        CONCAT(s.seedling_name, ' — ', v.variety_name, ' (', ps.quantity, ' pcs) in ', p.plot_name) as description,
        ps.added_at as logged_at
    FROM plot_seedlings ps
    JOIN staff_info si ON ps.staff_id = si.staff_id
    JOIN varieties v ON ps.variety_id = v.variety_id
    JOIN seedlings s ON v.seedling_id = s.seedling_id
    JOIN plots p ON ps.plot_id = p.plot_id
")->fetchAll();

$inv_logs = $pdo->query("
    SELECT
        CONCAT(si.firstname, ' ', si.lastname) as staff_name,
        'Moved to Inventory' as action,
        CONCAT(s.seedling_name, ' — ', v.variety_name, ' (', il.quantity, ' pcs)') as description,
        il.logged_at
    FROM inventory_logs il
    JOIN staff_info si ON il.staff_id = si.staff_id
    JOIN varieties v ON il.variety_id = v.variety_id
    JOIN seedlings s ON v.seedling_id = s.seedling_id
")->fetchAll();

$dmg_logs = $pdo->query("
    SELECT
        CONCAT(si.firstname, ' ', si.lastname) as staff_name,
        'Reported Damage' as action,
        CONCAT(s.seedling_name, ' — ', v.variety_name, ' (', dr.quantity_damaged, ' pcs) in ', p.plot_name) as description,
        dr.reported_at as logged_at
    FROM damage_reports dr
    JOIN staff_info si ON dr.staff_id = si.staff_id
    JOIN plot_seedlings ps ON dr.plot_seedling_id = ps.plot_seedling_id
    JOIN varieties v ON ps.variety_id = v.variety_id
    JOIN seedlings s ON v.seedling_id = s.seedling_id
    JOIN plots p ON dr.plot_id = p.plot_id
")->fetchAll();

$order_logs = $pdo->query("
    SELECT
        CONCAT(si.firstname, ' ', si.lastname) as staff_name,
        'Recorded Order' as action,
        CONCAT('Customer: ', c.firstname, ' ', c.lastname) as description,
        o.ordered_at as logged_at
    FROM orders o
    JOIN staff_info si ON o.staff_id = si.staff_id
    JOIN customer_info c ON o.customer_id = c.customer_id
")->fetchAll();

$all_logs = array_merge($add_logs, $inv_logs, $dmg_logs, $order_logs);

if ($filter_action) {
    $all_logs = array_filter($all_logs, fn($l) => $l['action'] === $filter_action);
}

if ($date_from) {
    $all_logs = array_filter($all_logs, fn($l) => strtotime($l['logged_at']) >= strtotime($date_from . ' 00:00:00'));
}
if ($date_to) {
    $all_logs = array_filter($all_logs, fn($l) => strtotime($l['logged_at']) <= strtotime($date_to . ' 23:59:59'));
}

usort($all_logs, fn($a, $b) => strtotime($b['logged_at']) - strtotime($a['logged_at']));

$action_colors = [
    'Added Seedling to Plot' => 'bg-success',
    'Moved to Inventory'     => 'bg-primary',
    'Reported Damage'        => 'bg-danger',
    'Recorded Order'         => 'bg-warning text-dark',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logs</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin/style.css">
</head>
<body>

<?php require_once __DIR__ . '/../includes/admin/sidebar.php'; ?>

<div id="content">
  <div id="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light d-md-none" id="toggler"><i class="fas fa-bars"></i></button>
      <span class="fw-semibold text-dark">Logs</span>
    </div>
    <small class="text-muted"><?= date('D, M d Y') ?></small>
  </div>

  <div class="p-4">

    <form method="GET" class="card border-0 shadow-sm mb-3 p-3">
      <div class="row g-2 align-items-end">
        <div class="col-auto">
          <label class="form-label small fw-semibold text-secondary mb-1">From</label>
          <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label small fw-semibold text-secondary mb-1">To</label>
          <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label small fw-semibold text-secondary mb-1">Action</label>
          <select name="action" class="form-select form-select-sm">
            <option value="">— All —</option>
            <option value="Added Seedling to Plot" <?= $filter_action === 'Added Seedling to Plot' ? 'selected' : '' ?>>Added Seedling to Plot</option>
            <option value="Moved to Inventory" <?= $filter_action === 'Moved to Inventory' ? 'selected' : '' ?>>Moved to Inventory</option>
            <option value="Reported Damage" <?= $filter_action === 'Reported Damage' ? 'selected' : '' ?>>Reported Damage</option>
            <option value="Recorded Order" <?= $filter_action === 'Recorded Order' ? 'selected' : '' ?>>Recorded Order</option>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-filter me-1"></i> Filter</button>
          <a href="logs.php" class="btn btn-light btn-sm ms-1">Clear</a>
        </div>
      </div>
    </form>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-3 px-4">
        <span class="fw-semibold"><i class="fas fa-clock-rotate-left text-success me-2"></i>Activity Logs</span>
        <span class="text-muted small ms-2">(<?= count($all_logs) ?>)</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-4">Staff</th>
              <th>Action</th>
              <th>Description</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($all_logs)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No logs yet.</td></tr>
            <?php else: ?>
            <?php foreach ($all_logs as $log): ?>
            <tr>
              <td class="ps-4 fw-semibold small"><?= htmlspecialchars($log['staff_name']) ?></td>
              <td>
                <span class="badge <?= $action_colors[$log['action']] ?? 'bg-secondary' ?> small">
                  <?= htmlspecialchars($log['action']) ?>
                </span>
              </td>
              <td class="small text-muted"><?= htmlspecialchars($log['description']) ?></td>
              <td class="small text-muted text-nowrap"><?= date('M d, Y h:i A', strtotime($log['logged_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('toggler')?.addEventListener('click', function() {
  document.getElementById('sidebar').classList.toggle('show');
});
</script>
</body>
</html>