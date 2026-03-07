<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-bold text-white" href="/plant/staff/dashboard.php">
      <i class="fas fa-leaf text-success me-2"></i>Ej's Plant Nursery
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav mx-auto">
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active fw-semibold':'' ?>" href="/plant/staff/dashboard.php">
            <i class="fas fa-gauge-high me-1"></i> Dashboard |
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='plot.php'?'active fw-semibold':'' ?>" href="/plant/staff/plot.php">
            <i class="fas fa-map-location-dot me-1"></i> Plot |
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='inventory.php'?'active fw-semibold':'' ?>" href="/plant/staff/inventory.php">
            <i class="fas fa-boxes-stacked me-1"></i> Inventory |
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='order.php'?'active fw-semibold':'' ?>" href="/plant/staff/order.php">
            <i class="fas fa-cart-shopping me-1"></i> Order
          </a>
        </li>
      </ul>
      <div class="dropdown">
        <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
          <i class="fas fa-user me-1"></i><?= htmlspecialchars($_SESSION['staff_firstname'] . ' ' . $_SESSION['staff_lastname']) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item text-danger" href="/plant/staff/logout.php"><i class="fas fa-right-from-bracket me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>