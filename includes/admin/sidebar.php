<style>
  #sidebar {
    width:220px;
    min-height:100vh;
    position:fixed;
    top:0; left:0;
    background:#fff;
    border-right:1px solid #e9ecef;
    z-index:1000;
    display:flex;
    flex-direction:column;
    transition:left .25s;
  }
  #sidebar .brand {
    padding:22px 20px 18px;
    border-bottom:1px solid #e9ecef;
    font-weight:700;
    font-size:1rem;
    color:#111;
  }
  #sidebar .nav-link {
    color:#555;
    padding:10px 20px;
    font-size:.93rem;
    border-radius:0;
    display:flex;
    align-items:center;
    gap:10px;
    transition:background .15s, color .15s;
  }
  #sidebar .nav-link:hover { background:#f5f6fa; color:#111; }
  #sidebar .nav-link.active { background:#f0f0f0; color:#111; font-weight:600; }
  #sidebar .nav-link i { width:16px; font-size:.85rem; }
  #sidebar .sidebar-footer {
    margin-top:auto;
    padding:14px 16px;
    border-top:1px solid #e9ecef;
  }
  .user-wrap {
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 10px;
    border-radius:8px;
  }
  .user-avatar {
    width:34px; height:34px;
    border-radius:50%;
    background:#111;
    color:#fff;
    display:flex; align-items:center; justify-content:center;
    font-size:.85rem; font-weight:700;
    flex-shrink:0;
  }
  .user-name { font-size:.9rem; font-weight:600; color:#111; line-height:1.2; }
  .user-role { font-size:.75rem; color:#999; }
  .btn-dots {
    background:none; border:none;
    color:#aaa; cursor:pointer;
    padding:4px 7px; border-radius:6px;
    margin-left:auto;
    transition:background .15s, color .15s;
  }
  .btn-dots:hover { background:#f0f0f0; color:#111; }
  #content { margin-left:220px; min-height:100vh; }
  #topbar {
    background:#fff;
    border-bottom:1px solid #e9ecef;
    padding:14px 28px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    position:sticky;
    top:0;
    z-index:999;
  }
  @media(max-width:768px){
    #sidebar { left:-220px; }
    #sidebar.show { left:0; }
    #content { margin-left:0; }
  }
</style>

<div id="sidebar">
  <div class="brand">
    <i class="fas fa-leaf me-2 text-success"></i>Ej's Plant Nursery
  </div>
  <ul class="nav flex-column mt-1">
    <li><a href="/plant/admin/dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>"><i class="fas fa-gauge-high"></i> Dashboard</a></li>
    <li><a href="/plant/admin/staff.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='staff.php'?'active':'' ?>"><i class="fas fa-users"></i> Staff</a></li>
    <li><a href="/plant/admin/plants.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='plants.php'?'active':'' ?>"><i class="fas fa-seedling"></i> Plants</a></li>
    <li><a href="/plant/admin/inventory.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='inventory.php'?'active':'' ?>"><i class="fas fa-boxes-stacked"></i> Inventory</a></li>
    <li><a href="/plant/admin/logs.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='logs.php'?'active':'' ?>"><i class="fas fa-clock-rotate-left"></i> Logs</a></li>
    <li><a href="/plant/admin/reports.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='reports.php'?'active':'' ?>"><i class="fas fa-chart-bar"></i> Reports</a></li>
    <li><a href="/plant/admin/settings.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='settings.php'?'active':'' ?>"><i class="fa-solid fa-gear me-1"></i> Settings</a></li>
    <li><a href="/plant/admin/backup.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='backup.php'?'active':'' ?>"><i class="fas fa-database"></i> Database Backup</a></li>
  </ul>
  <div class="sidebar-footer">
    <div class="user-wrap">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['admin_username'],0,1)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($_SESSION['admin_username']) ?></div>
        <div class="user-role">Administrator</div>
      </div>
      <button class="btn-dots" data-bs-toggle="modal" data-bs-target="#logoutModal">
        <i class="fas fa-ellipsis-vertical"></i>
      </button>
    </div>
  </div>
</div>

<div class="modal fade" id="logoutModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-body p-4">
        <h6 class="fw-bold mb-1">Logout</h6>
        <p class="text-muted small mb-4">Are you sure you want to logout?</p>
        <div class="d-flex gap-2">
          <button class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancel</button>
          <a href="/plant/logout.php" class="btn btn-danger flex-fill">Logout</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('toggler')?.addEventListener('click',function(){
  document.getElementById('sidebar').classList.toggle('show');
});
</script>