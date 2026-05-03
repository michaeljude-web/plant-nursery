<?php
require_once __DIR__ . '/../includes/staff/auth.php';
require_once __DIR__ . '/../config/config.php';
require_staff_auth();

if (!defined('STAFF_ENC_KEY')) {
    define('STAFF_ENC_KEY',    'xK#9mP$2vL@nQ8zR!dW6sY&4bT*1jF0e');
    define('STAFF_ENC_METHOD', 'AES-256-CBC');
}

if (!function_exists('enc_staff')) {
    function enc_staff($data) {
        if ($data === null || $data === '') return '';
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($data, STAFF_ENC_METHOD, STAFF_ENC_KEY, 0, $iv);
        return base64_encode($iv . $enc);
    }
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

$order_error = $_SESSION['order_error'] ?? '';
unset($_SESSION['order_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_order') {
        $firstname      = trim($_POST['firstname'] ?? '');
        $lastname       = trim($_POST['lastname'] ?? '');
        $address        = trim($_POST['address'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $staff_id       = $_SESSION['staff_id'];
        $variety_ids    = $_POST['variety_id'] ?? [];
        $quantities     = $_POST['quantity'] ?? [];

        $errors = [];
        if ($firstname === '' || !preg_match('/^[a-zA-Z ]+$/', $firstname)) {
            $errors[] = 'First name must contain only letters and spaces.';
        }
        if ($lastname === '' || !preg_match('/^[a-zA-Z ]+$/', $lastname)) {
            $errors[] = 'Last name must contain only letters and spaces.';
        }
        if ($address !== '' && !preg_match('/^[a-zA-Z0-9 ,\.\(\)]+$/', $address)) {
            $errors[] = 'Address contains invalid characters. Allowed: letters, numbers, space, comma, period, parentheses.';
        }
        if ($contact_number !== '' && !preg_match('/^09[0-9]{9}$/', $contact_number)) {
            $errors[] = 'Contact number must be 11 digits starting with 09.';
        }
        if (empty($firstname) || empty($lastname) || empty($variety_ids)) {
            $errors[] = 'First name, last name, and at least one item are required.';
        }

        if (!empty($errors)) {
            $_SESSION['order_error'] = implode(' ', $errors);
        } else {
            $enc_firstname      = enc_staff($firstname);
            $enc_lastname       = enc_staff($lastname);
            $enc_address        = enc_staff($address);
            $enc_contact_number = enc_staff($contact_number);

            $cust = $pdo->prepare("INSERT INTO customer_info (firstname, lastname, address, contact_number) VALUES (?,?,?,?)");
            $cust->execute([$enc_firstname, $enc_lastname, $enc_address, $enc_contact_number]);
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
           c.firstname, c.lastname, c.address, c.contact_number
    FROM orders o
    JOIN customer_info c ON o.customer_id = c.customer_id
    ORDER BY o.ordered_at DESC
")->fetchAll();

$items_by_order = [];
$items = $pdo->query("
    SELECT oi.*, v.variety_name, s.seedling_name, v.price
    FROM order_items oi
    JOIN varieties v ON oi.variety_id = v.variety_id
    JOIN seedlings s ON v.seedling_id = s.seedling_id
")->fetchAll();
foreach ($items as $it) {
    $items_by_order[$it['order_id']][] = $it;
}

$inventory = $pdo->query("
    SELECT i.inventory_id, i.variety_id, i.quantity, v.variety_name, s.seedling_name, v.price
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
<style>
.invalid-feedback-custom {
    font-size: 11px;
    color: #dc3545;
    margin-top: 4px;
    display: none;
}
.invalid-feedback-custom.show {
    display: block;
}
input.is-invalid-custom,
select.is-invalid-custom {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.2rem rgba(220,53,69,.15) !important;
}
button:disabled {
    cursor: not-allowed !important;
    opacity: 0.65;
}
.modal-total {
    font-weight: bold;
    font-size: 1.1rem;
}
</style>
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/../includes/staff/navbar.php'; ?>

<div class="container-fluid px-4 py-4">

  <?php if ($order_error): ?>
  <div class="alert alert-danger py-2 small border-0 rounded-3 mb-3">
    <i class="fas fa-circle-exclamation me-1"></i><?= htmlspecialchars($order_error) ?>
  </div>
  <?php endif; ?>

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
            <th>Contact Number</th>
            <th>Items</th>
            <th>Total</th>
            <th>Date & Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No orders yet.</td></tr>
          <?php else: ?>
          <?php foreach ($orders as $o):
              $order_total = 0;
              if (!empty($items_by_order[$o['order_id']])) {
                  foreach ($items_by_order[$o['order_id']] as $it) {
                      $order_total += $it['quantity'] * $it['price'];
                  }
              }
          ?>
          <tr>
            <td class="ps-4 fw-semibold">
                <?= htmlspecialchars(dec_staff($o['firstname']) . ' ' . dec_staff($o['lastname'])) ?>
            </td>
            <td class="small text-muted"><?= htmlspecialchars(dec_staff($o['address']) ?: '—') ?></td>
            <td class="small text-muted"><?= htmlspecialchars(dec_staff($o['contact_number']) ?: '—') ?></td>
            <td class="small">
              <?php if (!empty($items_by_order[$o['order_id']])): ?>
                <?php foreach ($items_by_order[$o['order_id']] as $it):
                    $line_total = $it['quantity'] * $it['price'];
                ?>
                  <div>
                    <?= htmlspecialchars($it['seedling_name'] . ' — ' . $it['variety_name']) ?>
                    <span class="text-muted"> ×<?= $it['quantity'] ?></span>
                    <br><small class="text-muted">&#8369;<?= number_format($it['price'], 2) ?> each = &#8369;<?= number_format($line_total, 2) ?></small>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="fw-semibold">&#8369;<?= number_format($order_total, 2) ?></td>
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
      <form method="POST" id="orderForm">
        <input type="hidden" name="action" value="add_order">
        <div class="modal-body">

          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary">First Name</label>
              <input type="text" name="firstname" class="form-control form-control-sm" id="firstname" required>
              <div class="invalid-feedback-custom" id="hint-firstname"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary">Last Name</label>
              <input type="text" name="lastname" class="form-control form-control-sm" id="lastname" required>
              <div class="invalid-feedback-custom" id="hint-lastname"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary">Address</label>
              <input type="text" name="address" class="form-control form-control-sm" id="address">
              <div class="invalid-feedback-custom" id="hint-address"></div>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary">Contact Number</label>
              <input type="text" name="contact_number" class="form-control form-control-sm" id="contact_number" maxlength="11" inputmode="numeric">
              <div class="invalid-feedback-custom" id="hint-contact_number"></div>
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
                <select name="variety_id[]" class="form-select form-select-sm variety-select" required>
                  <option value="">— Select Variety —</option>
                  <?php foreach ($inventory as $inv): ?>
                  <option value="<?= $inv['variety_id'] ?>" data-max="<?= $inv['quantity'] ?>" data-price="<?= $inv['price'] ?>">
                    <?= htmlspecialchars($inv['seedling_name'] . ' — ' . $inv['variety_name']) ?> (<?= $inv['quantity'] ?> available) - &#8369;<?= number_format($inv['price'], 2) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-3">
                <input type="number" name="quantity[]" class="form-control form-control-sm quantity-input" placeholder="Qty" min="1" required>
              </div>
              <div class="col-2">
                <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-item" style="display:none;">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="mt-3 text-end modal-total">
            Total: &#8369;<span id="liveTotal">0.00</span>
          </div>

        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="submit" class="btn btn-success btn-sm" id="saveOrderBtn" disabled>Save Order</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    const container = document.getElementById('itemsContainer');
    const addBtn = document.getElementById('addItemBtn');
    const saveBtn = document.getElementById('saveOrderBtn');

    const firstnameInput = document.getElementById('firstname');
    const lastnameInput  = document.getElementById('lastname');
    const addressInput   = document.getElementById('address');
    const contactInput   = document.getElementById('contact_number');
    const hintFirst      = document.getElementById('hint-firstname');
    const hintLast       = document.getElementById('hint-lastname');
    const hintAddr       = document.getElementById('hint-address');
    const hintContact    = document.getElementById('hint-contact_number');

    const nameRegex   = /^[a-zA-Z ]*$/;
    const addrRegex   = /^[a-zA-Z0-9 ,\.\(\)]*$/;
    const phoneRegex  = /^[0-9]*$/;
    const fullPhoneRegex = /^09[0-9]{9}$/;

    const touched = { firstname: false, lastname: false, address: false, contact_number: false };

    function validateField(input, hint, required, customValid) {
        const val = input.value.trim();
        let isValid;
        if (input === contactInput) {
            isValid = val === '' ? true : fullPhoneRegex.test(val);
        } else if (customValid) {
            isValid = customValid(val);
        } else {
            isValid = required ? (val !== '' && nameRegex.test(val)) : (val === '' || addrRegex.test(val));
        }
        if (touched[input.id]) {
            if (!isValid) {
                if (input === firstnameInput || input === lastnameInput) hint.textContent = 'Only letters and spaces allowed.';
                else if (input === addressInput) hint.textContent = 'Allowed: letters, numbers, space, comma, period, parentheses.';
                else if (input === contactInput) hint.textContent = 'Must be 11 digits starting with 09.';
                hint.classList.add('show');
                input.classList.add('is-invalid-custom');
            } else {
                hint.classList.remove('show');
                input.classList.remove('is-invalid-custom');
            }
        }
        return isValid;
    }

    function checkValid() {
        const firstOk = validateField(firstnameInput, hintFirst, true, v => nameRegex.test(v) && v.length > 0);
        const lastOk  = validateField(lastnameInput,  hintLast,  true, v => nameRegex.test(v) && v.length > 0);
        const addrOk  = validateField(addressInput,   hintAddr,  false, v => v === '' || addrRegex.test(v));
        const phoneOk = validateField(contactInput,   hintContact, false, v => v === '' || fullPhoneRegex.test(v));
        const hasItems = document.querySelectorAll('#itemsContainer .item-row').length > 0;
        const itemsValid = calculateTotal() > 0;
        saveBtn.disabled = !(firstOk && lastOk && addrOk && phoneOk && hasItems && itemsValid);
    }

    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('#itemsContainer .item-row').forEach(row => {
            const select = row.querySelector('select');
            const qtyInput = row.querySelector('input[type="number"]');
            if (!select || !qtyInput) return;
            const price = parseFloat(select.selectedOptions[0]?.dataset.price || 0);
            const qty = parseInt(qtyInput.value) || 0;
            total += price * qty;
        });
        document.getElementById('liveTotal').textContent = total.toFixed(2);
        return total;
    }

    function attachRowEvents(row) {
        const select = row.querySelector('select');
        const qtyInput = row.querySelector('input[type="number"]');
        select?.addEventListener('change', function() {
            const max = this.selectedOptions[0]?.dataset.max || '';
            if (qtyInput) {
                qtyInput.max = max;
                qtyInput.placeholder = max ? 'Max: ' + max : 'Qty';
                if (parseInt(qtyInput.value) > parseInt(max)) qtyInput.value = max;
            }
            calculateTotal();
            checkValid();
        });
        qtyInput?.addEventListener('input', function() {
            const max = parseInt(this.max);
            if (max && parseInt(this.value) > max) this.value = max;
            calculateTotal();
            checkValid();
        });
    }

    document.querySelectorAll('#itemsContainer .item-row').forEach(attachRowEvents);

    addBtn.addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 item-row';
        row.innerHTML = `
            <div class="col-7">
                <select name="variety_id[]" class="form-select form-select-sm variety-select" required>
                    <option value="">— Select Variety —</option>
                    <?php foreach ($inventory as $inv): ?>
                    <option value="<?= $inv['variety_id'] ?>" data-max="<?= $inv['quantity'] ?>" data-price="<?= $inv['price'] ?>">
                        <?= htmlspecialchars($inv['seedling_name'] . ' — ' . $inv['variety_name']) ?> (<?= $inv['quantity'] ?> available) - &#8369;<?= number_format($inv['price'], 2) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-3">
                <input type="number" name="quantity[]" class="form-control form-control-sm quantity-input" placeholder="Qty" min="1" required>
            </div>
            <div class="col-2">
                <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-item">
                    <i class="fas fa-trash"></i>
                </button>
            </div>`;
        container.appendChild(row);
        attachRowEvents(row);
        updateRemoveButtons();
        calculateTotal();
        checkValid();
    });

    container.addEventListener('click', function(e) {
        if (e.target.closest('.remove-item')) {
            e.target.closest('.item-row').remove();
            updateRemoveButtons();
            calculateTotal();
            checkValid();
        }
    });

    function updateRemoveButtons() {
        const rows = document.querySelectorAll('#itemsContainer .item-row');
        rows.forEach((row, i) => {
            const btn = row.querySelector('.remove-item');
            if (btn) btn.style.display = rows.length > 1 ? '' : 'none';
        });
    }

    function setupInput(input, hint, required, customValid) {
        input.addEventListener('input', function() {
            touched[input.id] = true;
            if (input === contactInput) input.value = input.value.replace(/[^0-9]/g, '');
            validateField(input, hint, required, customValid);
            checkValid();
        });
        input.addEventListener('blur', function() {
            touched[input.id] = true;
            validateField(input, hint, required, customValid);
            checkValid();
        });
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            let text = (e.clipboardData || window.clipboardData).getData('text');
            if (input === contactInput) {
                text = text.replace(/[^0-9]/g, '');
            } else if (input === addressInput) {
                text = text.split('').filter(ch => addrRegex.test(ch)).join('');
            } else {
                text = text.split('').filter(ch => nameRegex.test(ch)).join('');
            }
            input.value += text;
            touched[input.id] = true;
            validateField(input, hint, required, customValid);
            checkValid();
        });
    }

    setupInput(firstnameInput, hintFirst, true, v => nameRegex.test(v) && v.length > 0);
    setupInput(lastnameInput,  hintLast,  true, v => nameRegex.test(v) && v.length > 0);
    setupInput(addressInput,   hintAddr,  false, v => v === '' || addrRegex.test(v));
    setupInput(contactInput,   hintContact, false, v => v === '' || fullPhoneRegex.test(v));

    saveBtn.disabled = true;
    calculateTotal();
})();
</script>
</body>
</html>