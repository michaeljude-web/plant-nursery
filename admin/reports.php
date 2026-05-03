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
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 15;

$where  = [];
$params = [];

if ($date_from) {
    $where[]  = "dr.reported_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}
if ($date_to) {
    $where[]  = "dr.reported_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT dr.*,
           p.plot_name,
           s.seedling_name,
           v.variety_name,
           si.firstname as staff_firstname,
           si.lastname  as staff_lastname
    FROM damage_reports dr
    JOIN plots p ON dr.plot_id = p.plot_id
    JOIN plot_seedlings ps ON dr.plot_seedling_id = ps.plot_seedling_id
    JOIN varieties v ON ps.variety_id = v.variety_id
    JOIN seedlings s ON v.seedling_id = s.seedling_id
    JOIN staff_info si ON dr.staff_id = si.staff_id
    $where_sql
    ORDER BY dr.reported_at DESC
");
$stmt->execute($params);
$reports = $stmt->fetchAll();

foreach ($reports as &$r) {
    $r['staff_name'] = trim(dec_staff($r['staff_firstname']) . ' ' . dec_staff($r['staff_lastname']));
}
unset($r);

$photos_by_report = [];
$photos = $pdo->query("SELECT * FROM damage_photos")->fetchAll();
foreach ($photos as $ph) {
    $photos_by_report[$ph['report_id']][] = $ph;
}

$total_reports = count($reports);
$total_pages   = max(1, ceil($total_reports / $per_page));
$offset        = ($page - 1) * $per_page;
$paged_reports = array_slice($reports, $offset, $per_page);

function build_query_string_reports($page = null) {
    $params = $_GET;
    if ($page !== null) $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Damage Reports</title>
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
      <span class="fw-semibold text-dark">Damage Reports</span>
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
          <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-filter me-1"></i> Filter</button>
          <a href="reports.php" class="btn btn-light btn-sm ms-1">Clear</a>
        </div>
        <div class="col-auto ms-auto">
          <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i> Print</button>
        </div>
      </div>
    </form>

    <div id="printable-area">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 px-4">
          <span class="fw-semibold"><i class="fas fa-triangle-exclamation text-danger me-2"></i>Damage Reports</span>
          <span class="text-muted small ms-2">(<?= $total_reports ?>)</span>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Plot</th>
                <th>Seedling / Variety</th>
                <th>Qty Damaged</th>
                <th>Reported By</th>
                <th>Description</th>
                <th>Photos</th>
                <th>Date & Time</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($paged_reports)): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">No damage reports found.</td></tr>
              <?php else: ?>
              <?php foreach ($paged_reports as $r): ?>
              <tr>
                <td class="ps-4 small"><?= htmlspecialchars($r['plot_name']) ?></td>
                <td>
                  <span class="fw-semibold"><?= htmlspecialchars($r['seedling_name']) ?></span>
                  <span class="text-muted small"> — <?= htmlspecialchars($r['variety_name']) ?></span>
                </td>
                <td><span class="badge bg-danger"><?= $r['quantity_damaged'] ?></span></td>
                <td class="small"><?= htmlspecialchars($r['staff_name']) ?></td>
                <td class="small text-muted" style="max-width:200px;">
                  <?= $r['description'] ? htmlspecialchars($r['description']) : '<em>—</em>' ?>
                </td>
                <td>
                  <?php if (!empty($photos_by_report[$r['report_id']])): ?>
                  <div class="d-flex gap-1 flex-wrap">
                    <?php foreach ($photos_by_report[$r['report_id']] as $ph): ?>
                    <a href="/plant/<?= htmlspecialchars($ph['photo_path']) ?>" target="_blank">
                      <img src="/plant/<?= htmlspecialchars($ph['photo_path']) ?>"
                           style="width:40px;height:40px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;">
                    </a>
                    <?php endforeach; ?>
                  </div>
                  <?php else: ?>
                  <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted text-nowrap"><?= date('M d, Y h:i A', strtotime($r['reported_at'])) ?></td>
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
                <a class="page-link" href="<?= build_query_string_reports(1) ?>">First</a>
              </li>
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= build_query_string_reports($page - 1) ?>">Prev</a>
              </li>
              <?php
                $start = max(1, $page - 2);
                $end   = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                  <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= build_query_string_reports($i) ?>"><?= $i ?></a>
                  </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= build_query_string_reports($page + 1) ?>">Next</a>
              </li>
              <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= build_query_string_reports($total_pages) ?>">Last</a>
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