<?php
require_once __DIR__ . '/../includes/staff/auth.php';
require_once __DIR__ . '/../config/config.php';
require_staff_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add_order') {
        $firstname  = trim($_POST['firstname'] ?? '');
        $lastname   = trim($_POST['lastname'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $staff_id   = $_SESSION['staff_id'];
        $variety_ids = $_POST['variety_id'] ?? [];
        $quantities  = $_POST['quantity'] ?? [];

        if ($firstname && $lastname && !empty($variety_ids)) {
            $cust = $pdo->prepare("INSERT INTO customer_info (firstname, lastname, address) VALUES (?,?,?)");
            $cust->execute([$firstname, $lastname, $address]);
            $customer_id = $pdo->lastInsertId();

            $ord = $pdo->prepare("INSERT INTO orders (customer_id, staff_id) VALUES (?,?)");
            $ord->execute([$customer_id, $staff_id]);
            $order_id = $pdo->lastInsertId();

            foreach ($variety_ids as $k => $vid) {
                $vid = (int)$vid;
                $qty = (int)($quantities[$k] ?? 0);
                if ($vid && $qty > 0) {
                    $inv = $pdo->prepare("SELECT inventory_id, quantity FROM inventory WHERE variety_id = ?");
                    $inv->execute([$vid]);
                    $inv_row = $inv->fetch();
                    if ($inv_row && $inv_row['quantity'] >= $qty) {
                        $pdo->prepare("INSERT INTO order_items (order_id, variety_id, quantity) VALUES (?,?,?)")->execute([$order_id, $vid, $qty]);
                        $pdo->prepare("UPDATE inventory SET quantity = quantity - ?, updated_at = NOW() WHERE inventory_id = ?")->execute([$qty, $inv_row['inventory_id']]);
                    }
                }
            }
        }
    }

    header('Location: /plant/staff/order.php');
    exit();
}

$orders = $pdo->query("
    SELECT o.order_id, o.ordered_at,
           c.firstname, c.lastname, c.address,
           CONCAT(si.firstname,' ',si.lastname) as staff_name
    FROM orders o
    JOIN customer_info c ON o.customer_id = c.customer_id
    JOIN staff_info si ON o.staff_id = si.staff_id
    ORDER BY o.ordered_at DESC
")->fetchAll();

$items_by_order = [];
$items = $pdo->query("
    SELECT oi.*, v.variety_name, s.seedling_name
    FROM order_items oi
    JOIN varieties v ON oi.variety_id = v.variety_id
    JOIN seedlings s ON v.seedling_id = s.seedling_id
")->fetchAll();
foreach ($items as $it) {
    $items_by_order[$it['order_id']][] = $it;
}

$inventory = $pdo->query("
    SELECT i.inventory_id, i.variety_id, i.quantity, v.variety_name, s.seedling_name
    FROM inventory i
    JOIN varieties v ON i.variety_id = v.variety_id
    JOIN seedlings s ON v.seedling_id = s.seedling_id
    WHERE i.quantity > 0
    ORDER BY s.seedling_name, v.variety_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/../includes/staff/navbar.php'; ?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex justify-content-end mb-3">
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addOrderModal">
      <i class="fas fa-plus me-1"></i> New Order
    </button>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 px-4">
      <span class="fw-semibold"><i class="fas fa-cart-shopping text-success me-2"></i>Orders</span>
      <span class="text-muted small ms-2">(<?= count($orders) ?>)</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-4">Customer</th>
            <th>Address</th>
            <th>Items</th>
            <th>Recorded By</th>
            <th>Date & Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">No orders yet.</td></tr>
          <?php else: ?>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td class="ps-4 fw-semibold"><?= htmlspecialchars($o['firstname'] . ' ' . $o['lastname']) ?></td>
            <td class="small text-muted"><?= htmlspecialchars($o['address'] ?: '—') ?></td>
            <td class="small">
              <?php if (!empty($items_by_order[$o['order_id']])): ?>
                <?php foreach ($items_by_order[$o['order_id']] as $it): ?>
                  <div><?= htmlspecialchars($it['seedling_name'] . ' — ' . $it['variety_name']) ?> <span class="text-muted">(<?= $it['quantity'] ?>)</span></div>
                <?php endforeach; ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="small"><?= htmlspecialchars($o['staff_name']) ?></td>
            <td class="small text-muted text-nowrap"><?= date('M d, Y h:i A', strtotime($o['ordered_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="addOrderModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold">New Order</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add_order">
        <div class="modal-body">

          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary">First Name</label>
              <input type="text" name="firstname" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary">Last Name</label>
              <input type="text" name="lastname" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary">Address</label>
              <input type="text" name="address" class="form-control form-control-sm">
            </div>
          </div>

          <hr class="my-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small fw-semibold text-secondary">Items</span>
            <button type="button" class="btn btn-outline-success btn-sm" id="addItemBtn">
              <i class="fas fa-plus me-1"></i> Add Item
            </button>
          </div>

          <div id="itemsContainer">
            <div class="row g-2 mb-2 item-row">
              <div class="col-7">
                <select name="variety_id[]" class="form-select form-select-sm" required>
                  <option value="">— Select Variety —</option>
                  <?php foreach ($inventory as $inv): ?>
                  <option value="<?= $inv['variety_id'] ?>" data-max="<?= $inv['quantity'] ?>">
                    <?= htmlspecialchars($inv['seedling_name'] . ' — ' . $inv['variety_name']) ?> (<?= $inv['quantity'] ?> available)
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-3">
                <input type="number" name="quantity[]" class="form-control form-control-sm" placeholder="Qty" min="1" required>
              </div>
              <div class="col-2">
                <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-item" style="display:none;">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          </div>

        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm">Save Order</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
<script>
const inventoryOptions = `<?php foreach ($inventory as $inv): ?><option value="<?= $inv['variety_id'] ?>" data-max="<?= $inv['quantity'] ?>"><?= htmlspecialchars($inv['seedling_name'] . ' — ' . $inv['variety_name']) ?> (<?= $inv['quantity'] ?> available)</option><?php endforeach; ?>`;

document.getElementById('itemsContainer').addEventListener('change', function(e) {
  if (e.target.tagName === 'SELECT' && e.target.name === 'variety_id[]') {
    const selected = e.target.options[e.target.selectedIndex];
    const max = selected.dataset.max || '';
    const qtyInput = e.target.closest('.item-row').querySelector('input[name="quantity[]"]');
    qtyInput.max = max;
    qtyInput.placeholder = max ? 'Max: ' + max : 'Qty';
    if (qtyInput.value && parseInt(qtyInput.value) > parseInt(max)) {
      qtyInput.value = max;
    }
  }
});

document.getElementById('itemsContainer').addEventListener('input', function(e) {
  if (e.target.name === 'quantity[]') {
    const max = parseInt(e.target.max);
    if (max && parseInt(e.target.value) > max) {
      e.target.value = max;
    }
  }
});

document.getElementById('addItemBtn').addEventListener('click', function() {
  const container = document.getElementById('itemsContainer');
  const row = document.createElement('div');
  row.className = 'row g-2 mb-2 item-row';
  row.innerHTML = `
    <div class="col-7">
      <select name="variety_id[]" class="form-select form-select-sm" required>
        <option value="">— Select Variety —</option>
        ${inventoryOptions}
      </select>
    </div>
    <div class="col-3">
      <input type="number" name="quantity[]" class="form-control form-control-sm" placeholder="Qty" min="1" required>
    </div>
    <div class="col-2">
      <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-item">
        <i class="fas fa-trash"></i>
      </button>
    </div>`;
  container.appendChild(row);
  updateRemoveButtons();
});

document.getElementById('itemsContainer').addEventListener('click', function(e) {
  if (e.target.closest('.remove-item')) {
    e.target.closest('.item-row').remove();
    updateRemoveButtons();
  }
});

function updateRemoveButtons() {
  const rows = document.querySelectorAll('.item-row');
  rows.forEach((row, i) => {
    const btn = row.querySelector('.remove-item');
    btn.style.display = rows.length > 1 ? '' : 'none';
  });
}
</script>
</body>
</html>