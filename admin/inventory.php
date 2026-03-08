<?php
require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../config/config.php';
require_admin_auth();

$inventory = $pdo->query("
    SELECT i.*, v.variety_name, s.seedling_name
    FROM inventory i
    JOIN varieties v ON i.variety_id = v.variety_id
    JOIN seedlings s ON v.seedling_id = s.seedling_id
    ORDER BY s.seedling_name, v.variety_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Inventory</title>
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
               <span class="fw-semibold text-dark">Inventory</span>
            </div>
            <small class="text-muted"><?= date('D, M d Y') ?></small>
         </div>
         <div class="p-4">
            <?php if (empty($inventory)): ?>
            <p class="text-muted">No inventory yet.</p>
            <?php else: ?>
            <div class="d-flex justify-content-end gap-4 mb-3">
               <span class="text-muted small">Total Varieties: <strong><?= count($inventory) ?></strong></span>
               <span class="text-muted small">Total Seedlings: <strong><?= array_sum(array_column($inventory, 'quantity')) ?></strong></span>
            </div>
            <hr>
            <div class="row g-3">
               <?php foreach ($inventory as $item): ?>
               <div class="col-6 col-sm-4 col-lg-3 col-xl-2">
                  <div class="card border-0 shadow-sm text-center py-3 px-2">
                     <div class="small text-muted mb-1"><?= htmlspecialchars($item['seedling_name']) ?></div>
                     <div class="fw-semibold mb-1"><?= htmlspecialchars($item['variety_name']) ?></div>
                     <div class="fw-bold fs-4"><?= $item['quantity'] ?></div>
                     <?php if ($item['quantity'] <= 10 && $item['quantity'] > 0): ?>
                     <div class="small text-warning fw-semibold mt-1">Low Stock</div>
                     <?php elseif ($item['quantity'] == 0): ?>
                     <div class="small text-danger fw-semibold mt-1">Out of Stock</div>
                     <?php endif; ?>
                  </div>
               </div>
               <?php endforeach; ?>
            </div>
            <?php endif; ?>
         </div>
      </div>
      <script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
      <script src="/plant/assets/js/admin/inventory.js"></script>
   </body>
</html>
