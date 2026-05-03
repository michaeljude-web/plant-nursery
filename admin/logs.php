<?php
require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../config/config.php';
require_admin_auth();

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

$date_from     = $_GET['date_from'] ?? '';
$date_to       = $_GET['date_to'] ?? '';
$filter_action = $_GET['action'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 15;

$add_logs = $pdo->query("
    SELECT
        si.firstname as staff_firstname,
        si.lastname  as staff_lastname,
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
        si.firstname as staff_firstname,
        si.lastname  as staff_lastname,
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
        si.firstname as staff_firstname,
        si.lastname  as staff_lastname,
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
        o.order_id,
        si.firstname as staff_firstname,
        si.lastname  as staff_lastname,
        c.firstname  as cust_firstname,
        c.lastname   as cust_lastname,
        o.ordered_at as logged_at
    FROM orders o
    JOIN staff_info si ON o.staff_id = si.staff_id
    JOIN customer_info c ON o.customer_id = c.customer_id
")->fetchAll();

$stmt_items = $pdo->prepare("
    SELECT s.seedling_name, v.variety_name, oi.quantity, v.price
    FROM order_items oi
    JOIN varieties v ON oi.variety_id = v.variety_id
    JOIN seedlings s ON v.seedling_id = s.seedling_id
    WHERE oi.order_id = ?
    ORDER BY s.seedling_name, v.variety_name
");

$order_logs_processed = [];
foreach ($order_logs as $ol) {
    $stmt_items->execute([$ol['order_id']]);
    $items = $stmt_items->fetchAll();

    $item_list = [];
    $total_qty = 0;
    $total_price = 0;
    foreach ($items as $it) {
        $line_total = $it['quantity'] * $it['price'];
        $item_list[] = $it['seedling_name'] . ' — ' . $it['variety_name'] . ' (' . $it['quantity'] . ' × ₱' . number_format($it['price'], 2) . ' = ₱' . number_format($line_total, 2) . ')';
        $total_qty += $it['quantity'];
        $total_price += $line_total;
    }

    $cust_name = trim(dec_staff($ol['cust_firstname']) . ' ' . dec_staff($ol['cust_lastname']));
    $desc = 'Customer: ' . $cust_name;
    if (!empty($item_list)) {
        $desc .= ' | Items: ' . implode(', ', $item_list) . ' | Total: ₱' . number_format($total_price, 2) . ' (' . $total_qty . ' pcs)';
    } else {
        $desc .= ' | No items';
    }

    $order_logs_processed[] = [
        'staff_firstname' => $ol['staff_firstname'],
        'staff_lastname'  => $ol['staff_lastname'],
        'action'          => 'Recorded Order',
        'description'     => $desc,
        'logged_at'       => $ol['logged_at']
    ];
}

$all_logs = array_merge($add_logs, $inv_logs, $dmg_logs, $order_logs_processed);

foreach ($all_logs as &$log) {
    $log['staff_name'] = trim(dec_staff($log['staff_firstname']) . ' ' . dec_staff($log['staff_lastname']));
}
unset($log);

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

$total_logs = count($all_logs);
$total_pages = max(1, ceil($total_logs / $per_page));
$offset = ($page - 1) * $per_page;
$paged_logs = array_slice($all_logs, $offset, $per_page);

$action_colors = [
    'Added Seedling to Plot' => 'bg-success',
    'Moved to Inventory'     => 'bg-primary',
    'Reported Damage'        => 'bg-danger',
    'Recorded Order'         => 'bg-warning text-dark',
];

function build_query_string($page = null, $exclude = []) {
    $params = $_GET;
    if ($page !== null) $params['page'] = $page;
    if ($exclude) {
        foreach ($exclude as $k) unset($params[$k]);
    }
    return '?' . http_build_query($params);
}
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
<style>
@media print {
  body * { visibility: hidden; }
  #printable-area, #printable-area * { visibility: visible; }
  #printable-area { position: absolute; top: 0; left: 0; width: 100%; }
  .no-print { display: none !important; }
  .table { font-size: 12px; }
}
</style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/admin/sidebar.php'; ?>

<div id="content">
  <div id="topbar" class="no-print">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light d-md-none" id="toggler"><i class="fas fa-bars"></i></button>
      <span class="fw-semibold text-dark">Logs</span>
    </div>
    <small class="text-muted"><?= date('D, M d Y') ?></small>
  </div>

  <div class="p-4">

    <form method="GET" class="card border-0 shadow-sm mb-3 p-3 no-print">
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
        <div class="col-auto ms-auto">
          <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i> Print</button>
        </div>
      </div>
    </form>

    <div id="printable-area">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 px-4">
          <span class="fw-semibold"><i class="fas fa-clock-rotate-left text-success me-2"></i>Activity Logs</span>
          <span class="text-muted small ms-2">(<?= $total_logs ?>)</span>
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
              <?php if (empty($paged_logs)): ?>
              <tr><td colspan="4" class="text-center text-muted py-4">No logs yet.</td></tr>
              <?php else: ?>
              <?php foreach ($paged_logs as $log): ?>
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
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-top py-2 px-3 no-print">
          <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= build_query_string(1) ?>">First</a>
              </li>
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= build_query_string($page - 1) ?>">Prev</a>
              </li>
              <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                  <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= build_query_string($i) ?>"><?= $i ?></a>
                  </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= build_query_string($page + 1) ?>">Next</a>
              </li>
              <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= build_query_string($total_pages) ?>">Last</a>
              </li>
            </ul>
          </nav>
        </div>
        <?php endif; ?>
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