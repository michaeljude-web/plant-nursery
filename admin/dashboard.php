<?php
require_once __DIR__ . '/../includes/admin/auth.php';
require_admin_auth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
<style>
  body { overflow-x:hidden; background:#f5f6fa; }
  .stat-card { background:#fff; border:1px solid #e9ecef; border-radius:10px; padding:20px 22px; }
  .stat-card .label { font-size:.82rem; color:#999; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
  .stat-card .value { font-size:1.75rem; font-weight:700; color:#111; }
  .stat-card .icon { font-size:1.1rem; color:#bbb; }
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/admin/sidebar.php'; ?>

<div id="content">
  <div id="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light d-md-none" id="toggler"><i class="fas fa-bars"></i></button>
      <span class="fw-semibold text-dark">Dashboard</span>
    </div>
    <small class="text-muted"><?= date('D, M d Y') ?></small>
  </div>
  </div>
</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
</body>
</html>