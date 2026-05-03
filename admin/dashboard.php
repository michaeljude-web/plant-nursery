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

$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';

$order_where = '';
$order_params = [];
if ($date_from) { $order_where .= " AND o.ordered_at >= ?"; $order_params[] = $date_from . ' 00:00:00'; }
if ($date_to)   { $order_where .= " AND o.ordered_at <= ?"; $order_params[] = $date_to . ' 23:59:59'; }

$total_staff     = $pdo->query("SELECT COUNT(*) FROM staff_info")->fetchColumn();
$total_in_plot   = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM plot_seedlings")->fetchColumn();
$total_inventory = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM inventory")->fetchColumn();
$total_damage    = $pdo->query("SELECT COALESCE(SUM(quantity_damaged),0) FROM damage_reports")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE 1=1 $order_where");
$stmt->execute($order_params);
$total_orders = $stmt->fetchColumn();

$monthly_orders = $pdo->query("
    SELECT DATE_FORMAT(ordered_at, '%b') as month,
           MONTH(ordered_at) as month_num,
           COUNT(*) as total
    FROM orders
    WHERE YEAR(ordered_at) = YEAR(NOW())
    GROUP BY month_num, month
    ORDER BY month_num ASC
")->fetchAll();

$monthly_labels = [];
$monthly_data   = [];
$month_map = [];
foreach ($monthly_orders as $m) {
    $month_map[$m['month_num']] = ['label' => $m['month'], 'total' => $m['total']];
}
for ($i = 1; $i <= 12; $i++) {
    $monthly_labels[] = date('M', mktime(0,0,0,$i,1));
    $monthly_data[]   = $month_map[$i]['total'] ?? 0;
}

$top_stmt = $pdo->prepare("
    SELECT s.seedling_name, v.variety_name, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN varieties v ON oi.variety_id = v.variety_id
    JOIN seedlings s ON v.seedling_id = s.seedling_id
    WHERE 1=1 $order_where
    GROUP BY oi.variety_id
    ORDER BY total_sold DESC
    LIMIT 5
");
$top_stmt->execute($order_params);
$top_varieties = $top_stmt->fetchAll();

$recent_stmt = $pdo->prepare("
    SELECT o.order_id, o.ordered_at,
           c.firstname  as cust_firstname,
           c.lastname   as cust_lastname,
           si.firstname as staff_firstname,
           si.lastname  as staff_lastname,
           COUNT(oi.item_id) as item_count
    FROM orders o
    JOIN customer_info c ON o.customer_id = c.customer_id
    JOIN staff_info si ON o.staff_id = si.staff_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE 1=1 $order_where
    GROUP BY o.order_id, c.firstname, c.lastname, si.firstname, si.lastname
    ORDER BY o.ordered_at DESC
    LIMIT 5
");
$recent_stmt->execute($order_params);
$recent_orders = $recent_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin/style.css?v=1">
</head>
<body>

<?php require_once __DIR__ . '/../includes/admin/sidebar.php'; ?>

<div id="content">
  <div id="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light d-md-none" id="toggler"><i class="fas fa-bars"></i></button>
      <span class="fw-semibold text-dark">Dashboard</span>
    </div>
    <small class="text-muted"><?= date('D, M d Y') ?></small>
  </div>

  <div class="p-4">

    <form method="GET" class="card border-0 shadow-sm mb-4 p-3">
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
          <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-filter me-1"></i> Filter</button>
          <a href="dashboard.php" class="btn btn-light btn-sm ms-1">Clear</a>
        </div>
      </div>
    </form>

    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-4 col-xl-2">
        <div class="card border-0 shadow-sm dashboard-stat-card green h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="dashboard-stat-icon bg-success bg-opacity-10">
              <i class="fas fa-users text-white"></i>
            </div>
            <div>
              <div class="text-muted small">Staff</div>
              <div class="fw-bold fs-5"><?= $total_staff ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-4 col-xl-2">
        <div class="card border-0 shadow-sm dashboard-stat-card teal h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="dashboard-stat-icon bg-success bg-opacity-10">
              <i class="fas fa-seedling text-white"></i>
            </div>
            <div>
              <div class="text-muted small">In Plot</div>
              <div class="fw-bold fs-5"><?= $total_in_plot ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-4 col-xl-2">
        <div class="card border-0 shadow-sm dashboard-stat-card blue h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="dashboard-stat-icon bg-primary bg-opacity-10">
              <i class="fas fa-boxes-stacked text-white"></i>
            </div>
            <div>
              <div class="text-muted small">Inventory</div>
              <div class="fw-bold fs-5"><?= $total_inventory ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-4 col-xl-2">
        <div class="card border-0 shadow-sm dashboard-stat-card orange h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="dashboard-stat-icon bg-warning bg-opacity-10">
              <i class="fas fa-cart-shopping text-white"></i>
            </div>
            <div>
              <div class="text-muted small">Orders</div>
              <div class="fw-bold fs-5"><?= $total_orders ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-4 col-xl-2">
        <div class="card border-0 shadow-sm dashboard-stat-card red h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="dashboard-stat-icon bg-danger bg-opacity-10">
              <i class="fas fa-triangle-exclamation text-white"></i>
            </div>
            <div>
              <div class="text-muted small">Damaged</div>
              <div class="fw-bold fs-5"><?= $total_damage ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white border-bottom py-3 px-4">
            <span class="fw-semibold">Monthly Orders <span class="text-muted small">(<?= date('Y') ?>)</span></span>
          </div>
          <div class="card-body">
            <canvas id="ordersChart" height="100"></canvas>
          </div>
        </div>
      </div>
      <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <span class="fw-semibold" id="calMonthLabel"></span>
            <div class="d-flex gap-1">
              <button class="btn btn-sm btn-light py-0 px-2" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
              <button class="btn btn-sm btn-light py-0 px-2" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
            </div>
          </div>
          <div class="card-body pb-2">
            <div class="dashboard-calendar-grid mb-1">
              <div class="dashboard-cal-header sun">Sun</div>
              <div class="dashboard-cal-header weekday">Mon</div>
              <div class="dashboard-cal-header weekday">Tue</div>
              <div class="dashboard-cal-header weekday">Wed</div>
              <div class="dashboard-cal-header weekday">Thu</div>
              <div class="dashboard-cal-header weekday">Fri</div>
              <div class="dashboard-cal-header sat">Sat</div>
            </div>
            <div class="dashboard-calendar-grid" id="calGrid"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom py-3 px-4">
            <span class="fw-semibold">Top Varieties Sold</span>
          </div>
          <div class="card-body">
            <?php if (empty($top_varieties)): ?>
            <p class="text-muted small">No sales yet.</p>
            <?php else: ?>
            <?php $max = max(array_column($top_varieties, 'total_sold')); ?>
            <div class="row g-3">
            <?php foreach ($top_varieties as $tv): ?>
            <div class="col-12 col-md-6 col-xl-4">
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted"><?= htmlspecialchars($tv['seedling_name']) ?> — <?= htmlspecialchars($tv['variety_name']) ?></span>
                <span class="fw-semibold"><?= $tv['total_sold'] ?></span>
              </div>
              <div class="progress" style="height:5px;">
                <div class="progress-bar bg-success" style="width:<?= $max > 0 ? round($tv['total_sold']/$max*100) : 0 ?>%"></div>
              </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-3 px-4">
        <span class="fw-semibold">Recent Orders</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-4">Customer</th>
              <th>Items</th>
              <th>Recorded By</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent_orders)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No orders yet.</td></tr>
            <?php else: ?>
            <?php foreach ($recent_orders as $o): ?>
            <tr>
              <td class="ps-4 fw-semibold small">
                <?= htmlspecialchars(dec_staff($o['cust_firstname']) . ' ' . dec_staff($o['cust_lastname'])) ?>
              </td>
              <td class="small text-muted"><?= $o['item_count'] ?> item<?= $o['item_count'] != 1 ? 's' : '' ?></td>
              <td class="small">
                <?= htmlspecialchars(dec_staff($o['staff_firstname']) . ' ' . dec_staff($o['staff_lastname'])) ?>
              </td>
              <td class="small text-muted text-nowrap"><?= date('M d, Y h:i A', strtotime($o['ordered_at'])) ?></td>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.getElementById('toggler')?.addEventListener('click', function() {
  document.getElementById('sidebar').classList.toggle('show');
});

const ctx = document.getElementById('ordersChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($monthly_labels) ?>,
    datasets: [{
      label: 'Orders',
      data: <?= json_encode($monthly_data) ?>,
      backgroundColor: 'rgba(25,135,84,0.15)',
      borderColor: '#198754',
      borderWidth: 2,
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed.y + ' orders' } }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { stepSize: 1, font: { size: 11 } },
        grid: { color: '#f0f0f0' }
      },
      x: {
        ticks: { font: { size: 11 } },
        grid: { display: false }
      }
    }
  }
});

let calDate = new Date();
function renderCalendar(d) {
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  document.getElementById('calMonthLabel').textContent = months[d.getMonth()] + ' ' + d.getFullYear();
  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';
  const today = new Date();
  const first = new Date(d.getFullYear(), d.getMonth(), 1);
  const last  = new Date(d.getFullYear(), d.getMonth()+1, 0);
  for (let i = 0; i < first.getDay(); i++) {
    const prev = new Date(d.getFullYear(), d.getMonth(), -first.getDay()+i+1);
    const el = document.createElement('div');
    el.className = 'dashboard-cal-day other-month';
    el.textContent = prev.getDate();
    grid.appendChild(el);
  }
  for (let i = 1; i <= last.getDate(); i++) {
    const el = document.createElement('div');
    const dayOfWeek = new Date(d.getFullYear(), d.getMonth(), i).getDay();
    let cls = 'dashboard-cal-day';
    if (dayOfWeek === 0) cls += ' sun-col';
    if (dayOfWeek === 6) cls += ' sat-col';
    if (i === today.getDate() && d.getMonth() === today.getMonth() && d.getFullYear() === today.getFullYear()) {
      cls += ' today';
    }
    el.className = cls;
    el.textContent = i;
    grid.appendChild(el);
  }
  const remaining = 42 - grid.children.length;
  for (let i = 1; i <= remaining; i++) {
    const el = document.createElement('div');
    el.className = 'dashboard-cal-day other-month';
    el.textContent = i;
    grid.appendChild(el);
  }
}
function changeMonth(dir) {
  calDate.setMonth(calDate.getMonth() + dir);
  renderCalendar(calDate);
}
renderCalendar(calDate);
</script>
</body>
</html>