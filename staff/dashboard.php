<?php
require_once __DIR__ . '/../includes/staff/auth.php';
require_once __DIR__ . '/../config/config.php';
require_staff_auth();

if (!defined('STAFF_ENC_KEY')) {
  define('STAFF_ENC_KEY',    'xK#9mP$2vL@nQ8zR!dW6sY&4bT*1jF0e');
  define('STAFF_ENC_METHOD', 'AES-256-CBC');
}
if (!function_exists('dec_staff')) {
  function dec_staff($data) {
      if ($data === null || $data === '') return '';
      $decoded = base64_decode($data);
      if (strlen($decoded) < 16) return $data;
      $iv     = substr($decoded, 0, 16);
      $result = openssl_decrypt(base64_encode(substr($decoded, 16)), STAFF_ENC_METHOD, STAFF_ENC_KEY, 0, $iv);
      return $result !== false ? $result : $data;
  }
}
$staff_firstname = dec_staff($_SESSION['staff_firstname'] ?? '');

$staff_id = $_SESSION['staff_id'];

$total_in_plot   = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM plot_seedlings")->fetchColumn();
$total_inventory = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM inventory")->fetchColumn();
$total_orders    = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE staff_id = ?");
$total_orders->execute([$staff_id]);
$total_orders    = $total_orders->fetchColumn();
$total_damage    = $pdo->prepare("SELECT COUNT(*) FROM damage_reports WHERE staff_id = ?");
$total_damage->execute([$staff_id]);
$total_damage    = $total_damage->fetchColumn();

$recent_orders = $pdo->prepare("
    SELECT o.ordered_at,
           CONCAT(c.firstname,' ',c.lastname) as customer,
           COUNT(oi.item_id) as item_count
    FROM orders o
    JOIN customer_info c ON o.customer_id = c.customer_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.staff_id = ?
    GROUP BY o.order_id
    ORDER BY o.ordered_at DESC
    LIMIT 5
");
$recent_orders->execute([$staff_id]);
$recent_orders = $recent_orders->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/../includes/staff/navbar.php'; ?>

<div class="container-fluid px-4 py-4">

  <div class="mb-3">
    <span class="fw-semibold text-dark">Welcome, <?= htmlspecialchars($staff_firstname) ?>!</span>
    <span class="text-muted small ms-2"><?= date('D, M d Y') ?></span>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-2 bg-success bg-opacity-10 p-2">
            <i class="fas fa-seedling text-white"></i>
          </div>
          <div>
            <div class="text-muted small">In Plot</div>
            <div class="fw-bold fs-5"><?= $total_in_plot ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-2 bg-primary bg-opacity-10 p-2">
            <i class="fas fa-boxes-stacked text-white"></i>
          </div>
          <div>
            <div class="text-muted small">Inventory</div>
            <div class="fw-bold fs-5"><?= $total_inventory ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-2 bg-warning bg-opacity-10 p-2">
            <i class="fas fa-cart-shopping text-white"></i>
          </div>
          <div>
            <div class="text-muted small">My Orders</div>
            <div class="fw-bold fs-5"><?= $total_orders ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-2 bg-danger bg-opacity-10 p-2">
            <i class="fas fa-triangle-exclamation text-white"></i>
          </div>
          <div>
            <div class="text-muted small">My Reports</div>
            <div class="fw-bold fs-5"><?= $total_damage ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 px-4">
      <span class="fw-semibold"><i class="fas fa-cart-shopping text-success me-2"></i>My Recent Orders</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-4">Customer</th>
            <th>Items</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recent_orders)): ?>
          <tr><td colspan="3" class="text-center text-muted py-4">No orders yet.</td></tr>
          <?php else: ?>
          <?php foreach ($recent_orders as $o): ?>
          <tr>
            <td class="ps-4 fw-semibold small"><?= htmlspecialchars($o['customer']) ?></td>
            <td class="small text-muted"><?= $o['item_count'] ?> item<?= $o['item_count'] != 1 ? 's' : '' ?></td>
            <td class="small text-muted text-nowrap"><?= date('M d, Y h:i A', strtotime($o['ordered_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
</body>
</html>